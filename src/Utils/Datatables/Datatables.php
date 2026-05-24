<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitPHP\Database\Utils\Datatables;

use Closure;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\ORM\Interfaces\ModelInterface;
use InitORM\QueryBuilder\QueryBuilderInterface;
use Throwable;

/**
 * Server-side DataTables.js helper. Builds the response envelope DataTables
 * expects ({@code draw} / {@code recordsTotal} / {@code recordsFiltered} /
 * {@code data}) from a fluent query against an InitORM Database or Model.
 *
 * Usage in three steps:
 *
 *   1. Instantiate against a {@see DatabaseInterface} or {@see ModelInterface}:
 *      {@code $dt = new Datatables($model);}
 *   2. Build the query like you would on the underlying database — every
 *      unknown call is captured and re-played later, so {@code from()},
 *      {@code where()}, {@code join()}, {@code groupBy()} all work as
 *      expected and return {@code $this} for chaining.
 *   3. Declare which columns are exposed and (optionally) per-column
 *      renderers, then call {@see toArray()} or rely on {@see __toString()}
 *      for the JSON envelope.
 *
 * The helper takes care of:
 *   - DataTables protocol parsing (draw, paging, ordering, global search) —
 *     see {@see RequestParser}.
 *   - Running TWO count queries — one without the global-search filter
 *     ({@code recordsTotal}) and one with it ({@code recordsFiltered}) — so
 *     the client-side pagination behaves correctly when the user searches.
 *   - Applying per-column render callbacks via {@see Renderer}.
 *
 * @mixin QueryBuilderInterface
 */
final class Datatables
{
    private RequestParser $request;

    private Renderer $renderer;

    /**
     * Each captured builder call: {@code [name, arguments]}. Replayed once
     * per count / select query so a single fluent chain can drive three
     * separate database round-trips.
     *
     * @var list<array{method: string, arguments: array<int, mixed>}>
     */
    private array $captured = [];

    /**
     * @var list<array{db: string|null, dt: int}>
     */
    private array $columns = [];

    /**
     * Always-present SELECT columns, replayed before every count/select pass.
     *
     * @var list<string>
     */
    private array $permanentSelect = [];

    /**
     * When true (the default), captured {@code orderBy} calls are discarded
     * before applying the client-supplied order. Set false via
     * {@see orderBySave()} to honour both.
     */
    private bool $orderByReset = true;

    /**
     * @var array{
     *     draw: int,
     *     recordsTotal: int,
     *     recordsFiltered: int,
     *     data: array<int, array<string, mixed>>,
     *     post: array<string, mixed>
     * }
     */
    private array $response = [
        'draw'            => 0,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'post'            => [],
    ];

    public function __construct(
        private readonly DatabaseInterface|ModelInterface $db,
        ?RequestParser $request = null,
        ?Renderer $renderer = null
    ) {
        $this->request  = $request ?? RequestParser::fromGlobals();
        $this->renderer = $renderer ?? new Renderer();
    }

    /**
     * Capture an unknown method call (any QueryBuilder method) for later
     * replay against the underlying database. Returns {@code $this} so the
     * fluent chain stays unbroken.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        $this->captured[] = ['method' => $name, 'arguments' => $arguments];

        return $this;
    }

    /**
     * JSON-encode the response envelope. {@see __toString()} cannot raise
     * Throwables in older PHP versions and surfacing one from a magic method
     * is still poor form on 8.0+, so any failure during {@see handle()} is
     * swallowed and an empty JSON object is returned instead.
     */
    public function __toString(): string
    {
        try {
            $json = json_encode($this->toArray(), JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return '{}';
        }

        return $json;
    }

    /**
     * Execute the count + select round-trip and return the DataTables-shaped
     * response envelope.
     *
     * @return array{
     *     draw: int,
     *     recordsTotal: int,
     *     recordsFiltered: int,
     *     data: array<int, array<string, mixed>>,
     *     post: array<string, mixed>
     * }
     *
     * @throws Throwable When any of the underlying queries fail.
     */
    public function toArray(): array
    {
        $this->handle();

        return $this->response;
    }

    /**
     * Register columns exposed to the client. The order of arguments defines
     * the DataTables {@code column[i]} indexing the client will send back in
     * {@code order} directives. Pass {@code null} for a non-orderable /
     * unsearchable slot. Subsequent calls APPEND, preserving the existing
     * indexing.
     */
    public function setColumns(?string ...$columns): self
    {
        $next = count($this->columns);
        foreach ($columns as $column) {
            $this->columns[] = ['db' => $column, 'dt' => $next++];
        }

        return $this;
    }

    /**
     * Register a render callback for {@code $column} — see
     * {@see Renderer::add()} for the callback signature.
     */
    public function addRender(string $column, Closure $render): self
    {
        $this->renderer->add($column, $render);

        return $this;
    }

    /**
     * Columns added here are SELECTed on every pass (count + select), in
     * addition to whatever the captured chain selects.
     */
    public function addPermanentSelect(string ...$select): self
    {
        foreach ($select as $sel) {
            $this->permanentSelect[] = $sel;
        }

        return $this;
    }

    /**
     * Keep captured {@code orderBy} calls instead of overwriting them with the
     * client-supplied order. By default the helper resets them so the client
     * is the sole source of truth on ordering.
     */
    public function orderBySave(): self
    {
        $this->orderByReset = false;

        return $this;
    }

    /**
     * Execute the three queries (total count, filtered count, page) and build
     * the response envelope.
     *
     * @throws Throwable
     */
    public function handle(): self
    {
        $recordsTotal    = $this->runCount(applyFilter: false);
        $recordsFiltered = $this->runCount(applyFilter: true);
        $rows            = $this->runSelect();

        $this->response = [
            'draw'            => $this->request->draw(),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $this->renderer->apply($rows),
            'post'            => $this->request->all(),
        ];

        return $this;
    }

    /**
     * Run a count query against the captured chain. SELECT and ORDER BY
     * fragments are skipped — counting does not need them. When the captured
     * chain contains a {@code groupBy}, the count is built as
     * {@code COUNT(DISTINCT firstGroupByColumn)} so it matches the post-GROUP
     * BY row count.
     */
    private function runCount(bool $applyFilter): int
    {
        $hasGroupBy = false;

        foreach ($this->captured as $call) {
            $method = $call['method'];
            if (str_starts_with($method, 'select') || str_starts_with($method, 'orderBy')) {
                continue;
            }

            if ($method === 'groupBy') {
                if (!$hasGroupBy && isset($call['arguments'][0])) {
                    $first = $call['arguments'][0];
                    if (is_string($first) || $this->isRawQuery($first)) {
                        $this->db->selectCountDistinct($first, 'data_length');
                        $hasGroupBy = true;
                    }
                }
                continue;
            }

            $this->db->{$method}(...$call['arguments']);
        }

        if ($applyFilter) {
            $this->applySearchFilter();
        }

        if (!$hasGroupBy) {
            $this->db->selectCount('*', 'data_length');
        }

        // numRows() is unreliable on SELECT for drivers that don't buffer
        // results (SQLite, unbuffered MySQL) — fetch directly. The count
        // query always returns exactly one row.
        $row   = $this->db->read()->asAssoc()->row();
        $value = is_array($row) ? ($row['data_length'] ?? 0) : 0;

        return (int) $value;
    }

    /**
     * Run the page-of-results SELECT against the captured chain, applying
     * the client-requested filter, order and limit on top.
     *
     * @return array<int, array<string, mixed>>
     */
    private function runSelect(): array
    {
        foreach ($this->permanentSelect as $select) {
            $this->db->select($select);
        }

        foreach ($this->captured as $call) {
            if ($this->orderByReset && str_starts_with($call['method'], 'orderBy')) {
                continue;
            }
            $this->db->{$call['method']}(...$call['arguments']);
        }

        $this->applySearchFilter();
        $this->applyClientOrder();
        $this->applyClientPaging();

        // numRows() is unreliable on SELECT for drivers that don't buffer
        // results — fetch and let the empty array speak for itself.
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->db->read()->asAssoc()->rows();

        return $rows;
    }

    /**
     * Add a {@code (col1 LIKE :s OR col2 LIKE :s OR ...)} group when the
     * client supplied a non-empty search value. No-op otherwise.
     *
     * Implementation note: this used to lean on {@code Database::group()} +
     * {@code orLike()}, which is the idiomatic upstream API — but the
     * QueryBuilder sub-builder spawned inside {@code group()} carries its own
     * parameter bag that never gets merged back into the outer builder's
     * bag, so the resulting SQL contains {@code :foo} placeholders with no
     * bound values and matches zero rows. Until the upstream fix lands we
     * compose the predicate as a single RAW chunk and bind the parameters
     * directly on the outer builder, which keeps the values prepared.
     */
    private function applySearchFilter(): void
    {
        $search = $this->request->searchValue();
        if ($search === null) {
            return;
        }

        $clauses = [];
        $params  = [];
        $index   = 0;
        foreach ($this->columns as $column) {
            if ($column['db'] === null) {
                continue;
            }
            $placeholder           = ':dt_search_' . $index++;
            $clauses[]             = $column['db'] . ' LIKE ' . $placeholder;
            $params[$placeholder]  = '%' . $search . '%';
        }

        if ($clauses === []) {
            return;
        }

        $this->db->where($this->db->raw('(' . implode(' OR ', $clauses) . ')'));
        foreach ($params as $name => $value) {
            $this->db->setParameter($name, $value);
        }
    }

    /**
     * Translate the client's ORDER directives into builder calls.
     */
    private function applyClientOrder(): void
    {
        foreach ($this->request->orders() as [$columnIndex, $direction]) {
            $column = $this->columns[$columnIndex] ?? null;
            if ($column === null || $column['db'] === null) {
                continue;
            }
            $this->db->orderBy($this->db->raw($column['db']), $direction);
        }
    }

    /**
     * Apply the client-requested LIMIT / OFFSET. No-op when the client asked
     * for "all rows" ({@code length = -1}) or did not paginate at all.
     */
    private function applyClientPaging(): void
    {
        if (!$this->request->hasPagination()) {
            return;
        }
        $this->db
            ->offset($this->request->start())
            ->limit($this->request->length());
    }

    /**
     * Best-effort check for InitORM's {@code RawQuery} value object without
     * forcing a hard import here — the package keeps working even if the
     * upstream class is renamed (the check just degrades to "string only").
     */
    private function isRawQuery(mixed $value): bool
    {
        return is_object($value)
            && is_a($value, 'InitORM\\QueryBuilder\\RawQuery');
    }
}
