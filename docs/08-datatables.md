# 08 — Datatables

Server-side helper for [DataTables.js](https://datatables.net/). Given a query and a list of columns, the helper takes care of:

1. Parsing the DataTables request payload (`draw`, `start`, `length`, `search`, `order`).
2. Running the **unfiltered count** (the value DataTables wants in `recordsTotal`).
3. Running the **filtered count** (the value DataTables wants in `recordsFiltered`).
4. Running the page-of-results SELECT with `LIMIT` / `OFFSET` and the requested ordering.
5. Applying per-column render callbacks.
6. Returning the response envelope DataTables expects.

The class lives at `InitPHP\Database\Utils\Datatables\Datatables`. It is the one piece of code that ships with this package rather than being re-exposed from InitORM.

## Minimal example

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Database\DB;
use InitPHP\Database\Utils\Datatables\Datatables;

DB::createImmutable([/* … */]);

header('Content-Type: application/json');

$response = (new Datatables(DB::getDatabase()))
    ->from('users')
    ->setColumns('id', 'name', 'email', 'created_at')
    ->toArray();

echo json_encode($response);
```

`(string) $datatables` is equivalent to `json_encode($datatables->toArray())`, so:

```php
header('Content-Type: application/json');
echo new Datatables(DB::getDatabase())
        ->from('users')
        ->setColumns('id', 'name', 'email');
```

works too.

## Constructor

```php
new Datatables(
    DatabaseInterface|ModelInterface $db,
    ?RequestParser $request = null,
    ?Renderer $renderer = null,
);
```

- `$db` — the connection / model to query against. Pass a `Model` and the helper inherits its soft-delete / timestamp scoping.
- `$request` — the DataTables payload, defaulting to `RequestParser::fromGlobals()` (which merges `$_GET`, `$_POST`, and the decoded JSON body if `php://input` carries one). Inject your own when feeding the helper from a PSR-7 request or a unit test.
- `$renderer` — the column → closure registry. Defaults to a fresh `Renderer`; you almost never need to pass one in (use `addRender()` instead).

## Building the query

Every `__call`-able method (anything the QueryBuilder exposes) is **captured** and replayed against the database for each round-trip — the count queries and the page query share the same chain. So you write the query exactly as you would on `DB::`:

```php
$dt = new Datatables(DB::getDatabase());
$dt->from('posts')
   ->leftJoin('users', 'users.id = posts.author_id')
   ->where('posts.status', 1)
   ->groupBy('posts.id')
   ->setColumns('posts.id', 'posts.title', 'users.name', 'posts.created_at');
```

What is captured: `select`, `from`, `where` and friends, `join`, `groupBy`, `having`, `orderBy`, `limit`, `offset`, anything else the builder accepts.

What gets stripped per pass:

- During the count queries, `select*` and `orderBy*` calls are dropped — counting doesn't need them.
- During the page query, captured `orderBy*` calls are dropped **by default** and replaced with the client's order. Call `orderBySave()` to keep both (captured first, client second).

## Columns

```php
$dt->setColumns('id', 'name', 'email', 'created_at');
```

The order you list columns here defines the `column[i]` indexing DataTables sends back in `order` directives. Two special cases:

- Pass `null` for a slot that is render-only / not orderable / not searchable:
  ```php
  $dt->setColumns('id', 'name', null, 'created_at');
  // column 2 (the null slot) never appears in WHERE/ORDER BY.
  ```
- Multiple `setColumns()` calls **append** rather than replace, so the existing indexing is preserved.

## Global search

DataTables' search box sends `{search: {value: '...'}}`. The helper turns it into:

```sql
WHERE (col1 LIKE :s1 OR col2 LIKE :s2 OR ...)
```

…using every column registered via `setColumns()` whose `db` slot is non-null. The search runs **on the filtered count** and **on the page query**, but not on the unfiltered total — so `recordsTotal` and `recordsFiltered` diverge when a search is active. That is exactly what DataTables expects.

> Implementation note: the helper builds the search predicate as a raw SQL chunk with explicit parameter binding instead of using the upstream `group()` API. The reason is documented inside `applySearchFilter()` in `src/Utils/Datatables/Datatables.php` — `initorm/query-builder`'s sub-builder loses bound parameters when re-merged into the outer builder, which would silently match zero rows. Once that upstream behaviour is fixed, this can fold back into the idiomatic `group()` form.

## Ordering

```js
// Client sends:
{ order: [{ column: 1, dir: 'asc' }] }
```

The helper applies `ORDER BY {columns[1].db} ASC` after dropping any captured `orderBy*` calls. If you wrote `orderBySave()` on the chain, your captured order goes first and the client's order goes second — useful for things like "active rows first, then whatever the user clicked":

```php
$dt->orderBy('active', 'DESC')
   ->orderBySave()
   ->setColumns(...);
```

## Pagination

DataTables sends `start` and `length` as strings on the wire. The helper coerces them and applies `LIMIT length OFFSET start`. When `length` is `-1` ("show all"), the helper skips both — you get the entire filtered set.

## Per-column render callbacks

```php
$dt->setColumns('id', 'name', 'email', 'created_at')
   ->addRender('email', fn (?string $email, array $row) =>
       sprintf('<a href="mailto:%s">%s</a>', htmlspecialchars((string) $email), htmlspecialchars((string) $email))
   )
   ->addRender('created_at', fn (?string $ts) =>
       $ts === null ? '' : (new DateTimeImmutable($ts))->format('d M Y')
   );
```

A render callback receives `($value, array $row)` and returns the value the client should see. The full row is passed by value — you cannot use a renderer to mutate sibling columns.

## Permanent SELECTs

```php
$dt->addPermanentSelect('users.role')
   ->setColumns('users.id', 'users.name', 'users.email');
```

Permanent selects are appended on **every** select pass — handy for columns you need in render callbacks but don't expose as orderable / searchable columns.

## Group-by counts

When the captured chain contains a `groupBy('col')`, the count query becomes `SELECT COUNT(DISTINCT col) AS data_length` instead of `COUNT(*)` — the helper assumes you grouped by the entity's identifier and want the count after grouping. Pass a single column (string or `RawQuery`) for the helper to pick it up; an array argument is ignored and the count falls back to `COUNT(*)`.

## Response shape

```php
[
    'draw'            => 7,
    'recordsTotal'    => 1024,
    'recordsFiltered' => 12,
    'data'            => [
        ['id' => 5, 'name' => 'Ada',  'email' => '<a href="…">ada@…</a>'],
        ['id' => 8, 'name' => 'Bob',  'email' => '<a href="…">bob@…</a>'],
        // …
    ],
    'post'            => [/* the original request payload, verbatim */],
]
```

`'post'` is not part of the DataTables spec — it is a convenience the helper adds so server logs can capture the exact request the client sent.

## Plugging in a Model

```php
$dt = new Datatables(new App\Model\Posts());
$dt->setColumns('id', 'title', 'created_at');
```

When you pass a model, the model's soft-delete / writability gates apply transparently — `read()` filters out soft-deleted rows by default, exactly as it does for direct model use.

## Testing the helper

The package ships with PHPUnit coverage at `tests/Utils/Datatables/`. Use `tests/Support/SqliteHelper.php` as a template if you want to write integration tests of your own:

```php
$connection = SqliteHelper::makeConnection();
$db         = new InitPHP\Database\Database($connection);
SqliteHelper::seedUsers($connection);

$dt = new Datatables($db, new RequestParser([
    'search' => ['value' => 'Alice'],
]));
$dt->from('users')->setColumns('id', 'name', 'email');

$response = $dt->toArray();
// assert on $response['recordsFiltered'], $response['data'], etc.
```
