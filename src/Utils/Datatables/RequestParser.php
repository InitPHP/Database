<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitPHP\Database\Utils\Datatables;

/**
 * Parses a DataTables.js server-side request payload into the typed fields the
 * rest of the package consumes. Decoupled from PHP superglobals so callers can
 * inject a prebuilt request array — that keeps the class unit-testable and
 * lets PSR-7 / framework HTTP layers feed it without touching $_GET / $_POST.
 *
 * The DataTables protocol the parser understands (server-side mode):
 *
 *   - {@code draw}                  : opaque echo value
 *   - {@code start}                 : pagination offset (int)
 *   - {@code length}                : page size (int, -1 means "all")
 *   - {@code search[value]}         : global search string
 *   - {@code order[i][column]}      : column index to sort by
 *   - {@code order[i][dir]}         : 'asc' | 'desc'
 *
 * @see https://datatables.net/manual/server-side
 */
final class RequestParser
{
    /**
     * @param array<string, mixed> $payload Raw request data, already merged
     *        from whichever transport(s) the caller chose (query string, form
     *        body, JSON body).
     */
    public function __construct(private readonly array $payload)
    {
    }

    /**
     * Build a parser from the live request — $_GET + $_POST merged, plus the
     * decoded JSON body when {@code php://input} carries one. Returns an
     * empty-payload parser outside of an HTTP context.
     */
    public static function fromGlobals(): self
    {
        /** @var array<string, mixed> $merged */
        $merged = array_merge($_GET, $_POST);

        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $merged */
                $merged = array_merge($merged, $decoded);
            }
        }

        return new self($merged);
    }

    /**
     * The full payload, as the caller passed it in. Useful for echoing the
     * request back to the client unchanged.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->payload;
    }

    /**
     * The opaque {@code draw} value the client expects to see echoed back.
     * Returns 0 when missing, which is also DataTables' "no draw" sentinel.
     */
    public function draw(): int
    {
        return isset($this->payload['draw']) ? (int) $this->payload['draw'] : 0;
    }

    /**
     * Pagination offset; defaults to 0 when missing or invalid.
     */
    public function start(): int
    {
        return isset($this->payload['start']) ? max(0, (int) $this->payload['start']) : 0;
    }

    /**
     * Page size. The DataTables protocol uses -1 to mean "all rows"; this
     * method returns it untouched so the caller can decide whether to apply
     * a {@code LIMIT}. Defaults to -1 (no limit) when missing.
     */
    public function length(): int
    {
        return isset($this->payload['length']) ? (int) $this->payload['length'] : -1;
    }

    /**
     * Whether the request asked for paginated results — i.e. {@code start} is
     * present and {@code length} is not -1.
     */
    public function hasPagination(): bool
    {
        return isset($this->payload['start']) && $this->length() !== -1;
    }

    /**
     * The global search string, or null when none was supplied / it was empty.
     */
    public function searchValue(): ?string
    {
        $value = $this->payload['search']['value'] ?? null;
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    /**
     * Normalised ORDER BY directives. Each entry is a {@code [columnIndex,
     * direction]} pair where direction is the canonical {@code 'ASC'} or
     * {@code 'DESC'} string. Returns an empty array when no order was
     * requested or when the payload is malformed.
     *
     * @return list<array{0: int, 1: string}>
     */
    public function orders(): array
    {
        $order = $this->payload['order'] ?? null;
        if (!is_array($order)) {
            return [];
        }

        $orders = [];
        foreach ($order as $directive) {
            if (!is_array($directive) || !isset($directive['column'])) {
                continue;
            }
            $columnIndex = (int) $directive['column'];
            $direction   = isset($directive['dir']) && strtolower((string) $directive['dir']) === 'asc'
                ? 'ASC'
                : 'DESC';
            $orders[]    = [$columnIndex, $direction];
        }

        return $orders;
    }
}
