# Troubleshooting

Symptom-driven fix list. Find the symptom; the fix follows. Cross-references take you to the deeper explanation when one exists.

## Connection problems

### `SQLSTATE[HY000] [2002] Connection refused`

The database server is not reachable at the host / port you configured.

- **Check** the host / port in your connection array.
- **Check** the server is actually running (`systemctl status mysql`, `pg_isready`).
- **Check** the firewall (especially in containers — `localhost` from inside a container is not `localhost` from the host).
- **Check** the credentials file isn't being read from the wrong path (`var_dump($creds)` right before `DB::createImmutable($creds)`).

### `SQLSTATE[28000] Access denied for user 'X'@'host'`

Either the username/password is wrong, or the user has no GRANTs on the database.

```sql
-- MySQL — confirm the user exists and has the right grants
SHOW GRANTS FOR 'X'@'host';
```

### `SQLSTATE[HY000] [14] unable to open database file` (SQLite)

- The parent directory does not exist.
- The PHP process has no write permission on the file or directory.
- A `:memory:` database was opened twice but you expected schema from one to be visible in the other — it isn't; each PDO handle has its own `:memory:`.

### `could not find driver`

The PDO driver extension is not loaded. Confirm with:

```bash
php -m | grep pdo
```

Install with your package manager:

```bash
# Debian / Ubuntu
apt install php-mysql php-pgsql php-sqlite3

# RHEL / Fedora
dnf install php-mysqlnd php-pgsql php-pdo
```

### `Database name cannot be empty`

You passed only `driver`, `host`, `port` without `database`. Either set `database` to the name, or pass a full `dsn`.

## Query problems

### `SQLSTATE[42S02] Base table or view not found: 1146 Table 'X.Y' doesn't exist`

The table does not exist in the connected database. Three common causes:

1. **You're on the wrong database** — `var_dump(DB::getDatabase()->getConnection()->getDriver())` and check the DSN.
2. **The table has been migrated to a different name** — search the schema with `SHOW TABLES`.
3. **A model's `$schema` was auto-derived to something you didn't expect** — `(new MyModel())->getSchema()` shows the value.

### `SQLSTATE[42000] You have an error in your SQL syntax`

Turn on `debug = true` and re-run — the exception message will include the compiled SQL and bound args. Most often:

- A column name collides with a reserved word **inside a `DB::raw(...)` fragment** (raw fragments are emitted verbatim — see [Reserved Keywords](Reserved-Keywords.md)).
- A raw SQL string passed to `DB::query()` has a syntax error.

### `SQLSTATE[HY093] Invalid parameter number`

The number of placeholders in the SQL doesn't match the number of bound parameters. Usually one of:

- You bound a parameter that the SQL doesn't reference.
- You forgot to bind a parameter the SQL **does** reference.
- You used the same `:name` twice — PDO allows it but the parameter must be bound only once.

`enableQueryLog()` shows both the SQL and the args side by side.

### `WHERE id = :id` matches nothing, but the row is there

Type coercion mismatch. `'id' = '13'` (string) vs `id = 13` (int) usually works through PDO's emulated prepares, but on real prepares against PostgreSQL the column type matters. Cast at the boundary:

```php
DB::where('id', (int) $id);
```

### `$res->numRows()` returns 0, but rows exist

`PDOStatement::rowCount()` is unreliable for SELECT on SQLite and unbuffered MySQL. Use:

```php
$rows = $res->asAssoc()->rows();
$count = count($rows);
```

For INSERT / UPDATE / DELETE on common drivers, `numRows()` / `affectedRows()` is reliable.

### `group()` with `orLike` returns nothing even though matches exist

Known limitation in `initorm/query-builder` 2.x — parameters bound inside `group()` callbacks don't propagate to the outer builder. Workaround:

```php
DB::where(DB::raw('(title LIKE :q OR content LIKE :q)'))
  ->setParameter(':q', '%' . $needle . '%');
```

See [Query Builder](Query-Builder.md) and [Recipe — Search & Filters](Recipe-Search-Filters.md).

## Facade problems

### `No immutable Database instance is configured`

You called `DB::select(...)` (or any method) before `DB::createImmutable([...])`. Bootstrap the facade once during application startup.

### `An immutable Database instance has already been set`

You called `DB::createImmutable([...])` a second time. Since 4.0 this throws. Use `DB::replaceImmutable($connection)` for explicit swapping. See [Migration Guide](Migration-Guide.md).

### Tests bleed state between cases

The facade is process-global. Reset it in `setUp` / `tearDown`:

```php
protected function setUp(): void
{
    DB::replaceImmutable(null);
    DB::createImmutable($testCredentials);
}
```

The package's own `tests/DBTest.php` follows this pattern.

## Model problems

### `ModelException: $useSoftDeletes enabled but $deletedField is not configured`

You turned soft deletes on but forgot to point them at a column:

```php
protected bool $useSoftDeletes = true;
protected ?string $deletedField = 'deleted_at'; // required
```

### `read()` doesn't return soft-deleted rows

That is the entire point of soft deletes. Two escape hatches:

- `$model->onlyDeleted()->read()` for the trash view.
- `$model->getDatabase()->select(...)` to bypass the model layer entirely.

### `update()` did not change anything

Three common causes:

1. The WHERE doesn't match any row (try a `SELECT` with the same WHERE first).
2. Soft deletes are on and the target row is already soft-deleted (the model adds `WHERE deleted_at IS NULL`).
3. The model's `$updatable` gate is `false`.

### Mutator runs on `new` but not on `read()`

PDO's `FETCH_CLASS` bypasses `__set`. Use a `get{Column}Attribute()` accessor for read-time transformation. See [Entities](Entities.md).

### Dynamic property deprecation warning on a mutator

Inside the mutator body, you wrote `$this->column = $value` instead of `$this->setAttribute('column', $value)`. Fix it — it never worked correctly and PHP is about to make it fatal.

## DataTables problems

### `recordsTotal` equals `recordsFiltered` even during a search

You are on 3.x. Upgrade to 4.0 or later — the helper now computes them separately. See [Migration Guide](Migration-Guide.md).

### `TypeError: limit(): Argument #1 ($limit) must be of type int, string given`

You are on 3.x and the DataTables payload arrived with `length` as a string. Upgrade to 4.0 or later — the new `RequestParser` coerces.

### DataTables shows "Loading..." indefinitely

Open the browser DevTools network tab and look at the AJAX request. Common causes:

- The endpoint returns HTML (a 500 page) instead of JSON.
- `Content-Type` is not `application/json`.
- The endpoint throws but PHP has `display_errors = on`, mixing the stack trace into the response.

Wrap the endpoint in a `try / catch` that always returns JSON and check the response body.

### "DataTables: Ajax error" with a 200 OK

The response is valid JSON but missing a field DataTables expects (`draw`, `recordsTotal`, `recordsFiltered`, `data`). Make sure you're returning `$dt->toArray()` (or `(string) $dt`) — not just `$dt->handle()`.

### Per-column search not working

The helper ignores `columns[i].search.value` — only the global `search.value` is applied. See [DataTables — Advanced](DataTables-Advanced.md).

## Performance problems

### A list view is slow at 10000+ rows

If you're using offset-pagination on a non-indexed sort column, both the SELECT and the `recordsTotal` count get slow:

- **Index the sort column** — usually fixes it on its own.
- **Cursor pagination** — see [Recipe — Pagination](Recipe-Pagination.md) → variations.
- **Drop the count** — clients that show "Page X of N" can sometimes live with "Page X, more →" instead.

### `enableQueryLog()` makes the request slow

The buffer grows without bound. In long-lived processes, leave logging off and turn it on temporarily around suspect code paths, or `disableQueryLog()` and drop the buffer periodically.

### Transactions hold locks too long

Move read-only work outside the transaction; keep the transaction body to writes only.

```php
$snapshot = DB::select('*')->from('users')->where('id', $id)->read()->row();
// ...do business logic on $snapshot...
DB::transaction(function ($db) use ($id, $patch) {
    $db->where('id', $id)->update('users', $patch);
});
```

## When all else fails

1. Enable `debug = true` on the connection (development only).
2. Enable `enableQueryLog()` around the suspect code.
3. Copy the prepared SQL out of the log and run it by hand in a database CLI / GUI.
4. If the SQL passes there, the bug is in your application logic; if it fails there too, you have an SQL bug.
5. If you're stuck, [open an issue](https://github.com/InitPHP/Database/issues/new) with the SQL + args + the expected behaviour. Documentation gaps are bugs and get fixed on the same day.

## Next

- [Debugging](Debugging.md) — the full debugging toolkit.
- [Query Logging](Query-Logging.md) — how the buffer works.
- [FAQ](FAQ.md) — for the "is this normal?" questions.
