# FAQ

Frequently-asked questions from issues, discussions, and the questions you would have asked second.

## General

### Q. Is this an ORM, a query builder, or a DBAL?

All three. The package is a Composer-friendly facade over the [InitORM](https://github.com/InitORM) stack:

- **DBAL** — `InitPHP\Database\Database` wraps the PDO connection and exposes a CRUD surface.
- **Query Builder** — chainable `select`, `where`, `join`, … available on the same object.
- **ORM** — `InitPHP\Database\Model` and `InitPHP\Database\Entity` give you active-record-style models.

Pick the layer that fits the use case; you don't have to commit to one.

### Q. Why does this package exist if InitORM already does everything?

So application code can refer to `\InitPHP\Database\Database` (matching the Composer package name `initphp/database`) without dragging the `InitORM` namespace into user-facing code. It also bundles one piece of original code — the [DataTables.js](https://datatables.net/) helper — that lives in this package only.

### Q. What PHP version is supported?

PHP 8.2 and later. Tested on 8.2, 8.3, 8.4, 8.5, 8.6. See [Installation](Installation.md).

### Q. Which databases work?

Anything PDO supports. The package ships per-driver SQL dialects for MySQL, PostgreSQL, SQLite, and a generic fallback. MariaDB works through the MySQL dialect. SQL Server / Oracle / SQL Anywhere work through the generic dialect but are not actively tested.

## Connection

### Q. Why does my second `DB::createImmutable()` throw?

That is the behaviour since 4.0 — `createImmutable()` enforces "set exactly once". If you need to swap the shared connection (test fixtures, multi-tenant routing), use `DB::replaceImmutable()`. See [Migration Guide](Migration-Guide.md).

### Q. Can I use the package without `DB::createImmutable()` at all?

Yes — construct a `Database` directly:

```php
$db = new InitPHP\Database\Database($credentials);
$db->select('*')->from('users')->read();
```

The facade is a convenience over a single shared `Database`; nothing in the package requires it. See [Recipe — Repository Pattern](Recipe-Repository-Pattern.md).

### Q. How do I connect to two databases at once?

`DB::connect([...])` returns a fresh `Database` that does **not** touch the facade slot. For models, set `protected ?array $credentials = [...]` on the subclass. See [Multiple Connections](Multiple-Connections.md).

### Q. My SQLite `:memory:` data disappears between operations.

`:memory:` is **per PDO handle**. Each call to `DB::connect([':memory:'])` opens a fresh, empty database. Either share one `Database` instance throughout the test, or use a file path.

## Query Builder

### Q. How do I escape a column named after a reserved keyword (`order`, `select`, …)?

You do not — the package escapes every identifier automatically. See [Reserved Keywords](Reserved-Keywords.md).

### Q. Why does my search using `group(fn ($b) => $b->orLike(...))` match zero rows?

Known limitation in `initorm/query-builder` 2.x — parameters bound inside the `group()` callback do not propagate to the outer builder's bag, so the SQL ships with unbound placeholders. Workaround: build the search clause as a raw fragment with explicit `setParameter()` calls. See [Query Builder](Query-Builder.md) and [Recipe — Search & Filters](Recipe-Search-Filters.md).

### Q. How do I do a `LIKE` search with `%value%` on both sides?

```php
DB::like('title', $query); // defaults to 'both' — produces %query%
DB::like('title', $query, 'before'); // %query
DB::like('title', $query, 'after');  // query%
```

`startLike()` / `endLike()` are friendlier wrappers around the latter two.

### Q. Can I run a sub-query in WHERE?

Yes:

```php
DB::whereIn('id', DB::subQuery(function ($b) {
    $b->select('user_id')->from('orders')->where('total', '>', 1000);
}));
```

### Q. How do I see the actual SQL the builder produced?

```php
DB::enableQueryLog();
// run your code
print_r(DB::getQueryLogs());
```

Each log entry includes the prepared SQL, the bound args, and the elapsed time. See [Query Logging](Query-Logging.md).

### Q. Why does `$res->numRows()` return 0 even though I have rows?

`PDOStatement::rowCount()` is unreliable for SELECT statements on SQLite and on unbuffered MySQL connections. Fetch with `rows()` and `count()` the array. See [CRUD Operations](CRUD-Operations.md).

## CRUD

### Q. How do I do `REPLACE INTO`?

The package has no native `replace()` — the operation is not standard SQL. Use `DB::query()` with raw SQL; see [Recipe — Upsert / REPLACE INTO](Recipe-Upsert.md) for MySQL / PostgreSQL / SQLite spellings.

### Q. How do I get the auto-increment id after an `INSERT`?

```php
DB::create('posts', ['title' => 'Hello']);
$id = DB::insertId(); // string|false from PDO::lastInsertId
```

### Q. Can `createBatch()` insert different columns per row?

Yes. The compiler unions the keys across all rows; missing columns are emitted as `NULL`:

```php
DB::createBatch('posts', [
    ['title' => 'A', 'tags' => 'php'],
    ['title' => 'B'], // tags = NULL
]);
```

### Q. Can I `RETURNING` clauses?

Not through the builder. `DB::query('INSERT ... RETURNING id', $params)` works on databases that support it (PostgreSQL, SQLite, MySQL 8.0).

## Models

### Q. Why does the auto-derived schema name confuse my plural / abbreviation names?

The conversion is `camelCase` → `snake_case`. `Posts` → `posts`, `BlogPost` → `blog_post`, `APIToken` → `api_token`. If you don't like the result, set `$schema` explicitly.

### Q. Can a soft-deleted row still be updated?

By default no — `update()` adds `WHERE deleted_at IS NULL` automatically. If you need to update a soft-deleted row (e.g. restore it), drop down to the underlying `Database`:

```php
$model->getDatabase()->update('posts', ['deleted_at' => null], ['id' => 13]);
```

### Q. Can I disable timestamps for one specific call?

Set `$createdField` / `$updatedField` to `null` on the model, or bypass the model entirely:

```php
$model->getDatabase()->update('posts', ['title' => 'X'], ['id' => 13]);
// no updated_at touched
```

## Entities

### Q. My mutator runs on `new Entity([...])` but not on `read()`. Why?

PDO's `FETCH_CLASS` writes properties directly on the object, bypassing `__set` and therefore bypassing your mutator. The package does this on `read()` for performance reasons. If you need transformation on read, define a `get{Column}Attribute()` accessor instead — accessors **always** run.

### Q. Why is `$this->column = $value` deprecated inside a mutator?

It bypasses the entity's attribute bag and writes a dynamic property instead. PHP 8.2 deprecated dynamic properties; a future PHP release will make them fatal. Write through `$this->setAttribute('column', $value)`. See [Entities](Entities.md).

## DataTables

### Q. How does `recordsTotal` differ from `recordsFiltered`?

| Field | Source |
|---|---|
| `recordsTotal` | Count of all rows the chain produces, **without** the global search filter. |
| `recordsFiltered` | Count of rows the chain produces **with** the global search filter applied. |

When no search is active they are equal. When a search is active `recordsFiltered ≤ recordsTotal`.

### Q. Why are there three database queries per request?

One `recordsTotal` count, one `recordsFiltered` count, one page query. Three round-trips is the minimum DataTables needs to render its UI correctly. The protocol does not offer a way to skip any of them.

### Q. Can I use it without the global facade?

Yes — the constructor takes any `DatabaseInterface` (or `ModelInterface`):

```php
$dt = new Datatables($yourDatabaseInstance);
```

### Q. Does it support per-column search?

Not currently. The protocol's `columns[i].search.value` is ignored; only the global `search.value` is applied. See [DataTables — Advanced](DataTables-Advanced.md) for the workaround if you need it.

## Testing

### Q. Can I use SQLite in-memory for tests?

Yes — that's how the package tests itself. `tests/Support/SqliteHelper.php` shows the pattern.

### Q. How do I reset the `DB::` facade between tests?

```php
protected function setUp(): void
{
    DB::replaceImmutable(null);
    DB::createImmutable($testCredentials);
}
```

The package's own `tests/DBTest.php` follows this pattern.

### Q. How do I write a transactional test that always rolls back?

`testMode: true` on `transaction()` always rolls back, even on the success path. The closure can write freely, run assertions, and the database walks away untouched.

```php
DB::transaction(function ($db) {
    $db->create('users', $row);
    self::assertSame(1, $db->affectedRows());
}, testMode: true);
```

## Troubleshooting

### Q. `Connection refused` — what now?

The database server is unreachable. Check host / port / firewall. See [Debugging](Debugging.md) and [Troubleshooting](Troubleshooting.md).

### Q. `could not find driver` — what now?

The PDO driver extension for your database is not installed. On Debian/Ubuntu: `apt install php-mysql` / `php-pgsql` / `php-sqlite3`. Check with `php -m | grep pdo`.

### Q. Where do I report a security issue?

**Not** in a public issue. The org-wide [SECURITY.md](https://github.com/InitPHP/.github/blob/main/SECURITY.md) describes the private disclosure channels.

## Anything else?

If your question isn't here, [open a Discussion](https://github.com/orgs/InitPHP/discussions) — common questions migrate to this page from there.
