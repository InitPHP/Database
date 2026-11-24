# DataTables — Advanced

Patterns the introductory pages do not cover: group-by counting, permanent selects, injecting your own request parser, working through a `Model`, and understanding how the helper hands off to the underlying builder.

## `groupBy` counts

When the captured chain contains a `groupBy()`, a `SELECT COUNT(*)` would give the **pre-grouping** row count — almost never what you want. The helper detects the group-by and switches both count queries to `SELECT COUNT(DISTINCT firstGroupByColumn) AS data_length`:

```php
$dt = new Datatables(DB::getDatabase());
$dt->from('posts')
   ->groupBy('user_id')
   ->setColumns('user_id', 'COUNT(*) AS post_count')
   ->addPermanentSelect('user_id', DB::raw('COUNT(*) AS post_count'));
```

Three queries are emitted:

```sql
-- recordsTotal
SELECT COUNT(DISTINCT user_id) AS data_length FROM posts;

-- recordsFiltered (with the search filter applied to all columns)
SELECT COUNT(DISTINCT user_id) AS data_length FROM posts
WHERE (user_id LIKE :s0 OR post_count LIKE :s1);

-- page query
SELECT user_id, COUNT(*) AS post_count FROM posts GROUP BY user_id;
```

### Array-form groupBy is skipped

The upstream builder accepts `groupBy(['col_a', 'col_b'])` as well as `groupBy('col_a', 'col_b')`. The helper inspects the **first argument**:

- If it's a string or a `RawQuery`, it becomes the `COUNT(DISTINCT …)` target.
- If it's an array, the group-by is ignored and the count falls back to `SELECT COUNT(*)`.

If you need the count after grouping by multiple columns, run a manual `SELECT COUNT(DISTINCT col_a || col_b) …` outside the helper.

## Permanent SELECTs

`addPermanentSelect()` appends columns to **every** SELECT pass — the count queries skip them anyway (they only need `COUNT(...)`), but the page query benefits.

The use case: columns a renderer needs but the client does not declare as `data: ...`:

```php
$dt = new Datatables(DB::getDatabase());
$dt->from('users')
   ->addPermanentSelect('users.role', 'users.last_login_at')
   ->setColumns('id', 'name', 'email')
   ->addRender('name', static fn (string $name, array $row) =>
       sprintf(
           '%s <small>(%s)</small>',
           htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
           htmlspecialchars((string) $row['role'], ENT_QUOTES, 'UTF-8')
       )
   );
```

The renderer can read `$row['role']` because the column is present in the row even though the client never asked for it.

## Bringing your own request parser

`Datatables::__construct()` takes an optional `RequestParser`. The default (`RequestParser::fromGlobals()`) merges `$_GET`, `$_POST`, and the JSON body of `php://input`. Override it when:

### 1. The request lives in a PSR-7 object

```php
use Psr\Http\Message\ServerRequestInterface;
use InitPHP\Database\Utils\Datatables\RequestParser;

function datatablesEndpoint(ServerRequestInterface $request): string
{
    $payload = array_merge(
        $request->getQueryParams(),
        (array) ($request->getParsedBody() ?? [])
    );

    $dt = new Datatables(DB::getDatabase(), new RequestParser($payload));
    return (string) $dt->from('users')->setColumns('id', 'name', 'email');
}
```

### 2. The request is being unit-tested

```php
$dt = new Datatables($db, new RequestParser([
    'draw'  => '1',
    'start' => '0',
    'length' => '5',
    'search' => ['value' => 'Alice'],
]));
```

This is exactly how `tests/Utils/Datatables/DatatablesTest.php` exercises the helper.

### 3. The request is mediated through a framework

```php
// Symfony
$dt = new Datatables($db, new RequestParser(array_merge(
    $request->query->all(),
    $request->request->all()
)));
```

```php
// Laravel
$dt = new Datatables($db, new RequestParser($request->all()));
```

## RequestParser methods

For when you want to inspect / log / customise the parsed payload before handing it off:

| Method | Returns | Notes |
|---|---|---|
| `all()` | `array<string, mixed>` | The raw payload, verbatim. |
| `draw()` | `int` | DataTables echo value; 0 when missing. |
| `start()` | `int` | Pagination offset; clamped to ≥ 0. |
| `length()` | `int` | Page size; `-1` means "all". |
| `hasPagination()` | `bool` | True when `start` is set and `length !== -1`. |
| `searchValue()` | `?string` | The trimmed search string; `null` when empty or non-scalar. |
| `orders()` | `list<array{0: int, 1: 'ASC'\|'DESC'}>` | Normalised order directives. |

```php
$parser = new RequestParser($payload);
$count  = count($parser->orders());
if ($count > 5) {
    throw new RuntimeException('Too many sort columns'); // protect from abuse
}
$dt = new Datatables($db, $parser);
```

## Working through a Model

When you pass a `Model` instead of a `Database`, the model's gates and scopes apply transparently:

```php
$dt = new Datatables(new App\Model\Posts());
$dt->setColumns('id', 'title', 'created_at');
```

The `Posts` model's `$useSoftDeletes` and `$readable` settings flow through — soft-deleted rows are excluded from all three queries, and a `$readable = false` would make the helper throw `ReadableException` on the first read.

This is the right entry point when your application already exposes a Model layer; the helper does not need a parallel ACL.

## Renderer injection

You almost never need this, but for completeness:

```php
use InitPHP\Database\Utils\Datatables\Renderer;

$renderer = new Renderer();
$renderer->add('email', fn (string $e) => '<a>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</a>');
$renderer->add('name',  fn (string $n) => htmlspecialchars(strtoupper($n), ENT_QUOTES, 'UTF-8'));

$dt = new Datatables($db, null, $renderer);
$dt->setColumns('id', 'name', 'email');
```

Use the standalone `Renderer` when:

- You want to unit-test the renderer in isolation.
- You want to share the same render map across two `Datatables` instances.

For everything else, `$dt->addRender('col', fn …)` is the natural form.

## The captured-chain model

Every `__call`-able method (any QueryBuilder method) is **captured** by the helper and replayed against the database for each round-trip. So one fluent chain drives three database calls:

```php
$dt->from('posts')                  // captured
   ->leftJoin('users', '...')       // captured
   ->where('posts.status', 1)       // captured
   ->groupBy('posts.id')            // captured
   ->setColumns(...);               // not captured — config only
```

What is dropped during the **count** passes:

- All `select*` calls (counts do not need them).
- All `orderBy*` calls (counts do not need them).

What is dropped during the **page** pass:

- Captured `orderBy*` calls (the client's order replaces them) — unless you called `orderBySave()`.

What is **added** during each pass (by the helper, not by you):

- During filtered count + page: the search filter (a raw fragment + `setParameter()` calls).
- During count: the `COUNT(*)` or `COUNT(DISTINCT …)` projection.
- During page: the client-supplied `ORDER BY` and `LIMIT` / `OFFSET`.

Understanding this is the key to predicting what SQL the helper will emit.

## Parameter binding internals

The helper uses **named parameters** with a `:dt_search_N` prefix:

```sql
WHERE (col1 LIKE :dt_search_0 OR col2 LIKE :dt_search_1 OR col3 LIKE :dt_search_2)
```

Each placeholder is bound to `%value%`. If you also bind your own parameters in the captured chain (e.g. via `setParameter()`), pick names that won't collide — `:dt_search_*` is reserved by the helper.

## What the helper does NOT do

- It does not enforce **column-level search** (DataTables can send per-column `columns[i].search.value` — the helper currently ignores those). Global search only.
- It does not handle **regex search** (`search.regex: true`). Treated as plain `LIKE`.
- It does not stream — `data` is fully buffered in PHP memory before the JSON is emitted.

If you need any of those, drop down to the underlying `Database` and build the response by hand; the helper's source is a small ~250-line class you can read and adapt.

## Next

- [Introduction](DataTables-Introduction.md) — back to the basics.
- [Search, Sort, Paging](DataTables-Search-Sort-Paging.md) — the protocol the helper consumes.
- [Renderers](DataTables-Renderers.md) — output transformation.
- [Recipe — DataTables Bootstrap](Recipe-DataTables-Bootstrap.md) — a working end-to-end example.
