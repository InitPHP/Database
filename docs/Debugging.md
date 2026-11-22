# Debugging

When a query goes wrong in production, the default `PDOException` message is often not enough on its own — you get the SQLSTATE code and a vendor message, but not the actual SQL or parameters that ran. The package gives you three complementary tools.

## 1. `debug = true` — surface SQL in exception messages

```php
DB::createImmutable([
    'dsn'      => '...',
    'username' => '...',
    'password' => '...',
    'debug'    => true,
]);
```

When `debug` is on, any `SQLExecuteException` thrown by the driver is wrapped with the **compiled SQL** and the **bound parameters** appended to the message. The original `PDOException` is preserved as `getPrevious()` so stack traces stay intact.

Example of a debug-enabled message:

```
SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax;
check the manual that corresponds to your MySQL server version for the right syntax to use
near 'fROM users' at line 1
-- Query: sELECT * fROM users WHERE id = :id
-- Args:  {":id":13}
```

> **Production caveat:** debug-mode messages can leak sensitive data into error reporters / log files. Keep it off in production, or sanitise before forwarding to third-party services.

## 2. `enableQueryLog()` — see what's running, even when nothing throws

For "why is this slow" or "is this query even being issued" questions:

```php
DB::enableQueryLog();
// run the suspect code path...
print_r(DB::getQueryLogs());
DB::disableQueryLog();
```

See [Query Logging](Query-Logging.md) for the full surface — including how to wire it into a request middleware.

## 3. The `log` connection channel — production-grade error capture

```php
DB::createImmutable([
    'dsn' => '...',
    'log' => function (string $msg): void {
        $GLOBALS['app']->logger->error('[DB] ' . $msg);
    },
]);
```

Connection-level errors land in the channel even when `debug` is off — see [Query Logging → the log channel](Query-Logging.md) for the four supported shapes.

## A debugging checklist

When a query is misbehaving, work down this list:

### Step 1 — confirm the query ran at all

```php
DB::enableQueryLog();
$result = $myCode();
var_dump(DB::getQueryLogs());
```

If the buffer is empty, the suspect code never reached the database. The bug is upstream.

### Step 2 — confirm the compiled SQL is what you expect

The log buffer prints the **exact prepared SQL**. Eyeball it before reaching for any other tool. Common surprises:

- A `where()` you thought was there is missing (chain broken by an early return).
- An `orderBy()` is duplicated (you called it twice in different code paths).
- Identifier escaping wrapped a function name (`COUNT(*)`) — use `DB::raw()` for SQL fragments.

### Step 3 — confirm the parameters

The log buffer also prints the bound args:

```php
['query' => '… WHERE id = :id', 'args' => [':id' => '13']]
```

Note the string `'13'` — if your query is failing on a strict type comparison, this might be why. Coerce earlier.

### Step 4 — run the SQL by hand

Copy the `query` field straight into a database CLI / GUI and substitute the placeholders. If the SQL runs there, the bug is in your application logic; if it fails there too, you have an SQL bug, not a PHP bug.

### Step 5 — turn `debug = true` on, briefly

For exceptions that escape your normal `try / catch`, switch debug on, reproduce once, switch it back off. The full SQL + args in the exception message is usually enough to find the cause.

## Diagnosing connection failures

When the package cannot reach the database at all, the error comes from `Connection` itself rather than from a query:

```
InitORM\DBAL\Connection\Exceptions\ConnectionException:
SQLSTATE[HY000] [2002] Connection refused
```

Things to check:

| Symptom | Likely cause |
|---|---|
| `Connection refused` | Wrong host / port; database server not running. |
| `Access denied for user` | Wrong username / password / database name. |
| `Unknown database` | `database` field points at a database that doesn't exist. |
| `could not find driver` | `ext-pdo_<driver>` not installed (`pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`). |
| `SQLSTATE[HY000] [14] unable to open database file` (SQLite) | The file path is not writable, or the parent directory does not exist. |

For SQLite specifically: every PDO handle gets its own `:memory:` database, so any schema you applied to handle A is invisible to handle B. Either share the handle or use a file path.

## Diagnosing "no rows came back"

```php
$res = DB::select('*')->from('users')->read();
var_dump($res->numRows()); // 0 — but I have data?!
```

This is the **SQLite SELECT caveat** — `PDOStatement::rowCount()` is unreliable for SELECT on drivers that do not buffer results. Use `count($res->rows())` instead:

```php
$rows = DB::select('*')->from('users')->read()->asAssoc()->rows();
var_dump(count($rows));
```

For INSERT / UPDATE / DELETE on common drivers, `numRows()` / `affectedRows()` is reliable.

## Diagnosing "the search returned nothing"

If you used `group()` with bound parameters inside the callback (e.g. via `orLike`) and the result is empty even though matching rows exist, you are hitting a known limitation in `initorm/query-builder` 2.x — parameters bound inside `group()` do not propagate to the outer builder. See the warning in [Query Builder](Query-Builder.md).

Workaround pattern (the DataTables helper uses this):

```php
$clauses = [];
$params  = [];
foreach ($columns as $i => $col) {
    $key = ':search_' . $i;
    $clauses[]    = "{$col} LIKE {$key}";
    $params[$key] = '%' . $needle . '%';
}

DB::where(DB::raw('(' . implode(' OR ', $clauses) . ')'));
foreach ($params as $key => $value) {
    DB::setParameter($key, $value);
}
```

## Next

- [Query Logging](Query-Logging.md) — `enableQueryLog` and the `log` channel.
- [Configuration](Configuration.md) — the `debug` / `log` / `queryLogs` fields.
- [Troubleshooting](Troubleshooting.md) — symptom-driven fix list.
