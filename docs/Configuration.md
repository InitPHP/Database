# Configuration

Every entry point — `DB::createImmutable()`, `DB::connect()`, `new Database(...)`, `Model::$credentials` — accepts the **same connection array shape**. This page is the canonical reference for every supported field, with examples for the common drivers.

## The full credential shape

```php
[
    // 1. EITHER pass a fully-formed DSN ...
    'dsn'          => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',

    // 2. ... OR let the package build one from the parts:
    'driver'       => 'mysql',     // mysql | pgsql | sqlite | any PDO driver name
    'host'         => '127.0.0.1',
    'port'         => 3306,
    'database'     => 'test',

    'username'     => 'root',
    'password'     => '',

    // Connection-level character set (applied via SET NAMES after connect, MySQL only)
    'charset'      => 'utf8mb4',
    'collation'    => 'utf8mb4_unicode_ci',

    // PDO options (passed to `new PDO(..., ..., ..., $options)`)
    'options'      => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],

    // Options forwarded to every prepare() (e.g. PDO::ATTR_CURSOR)
    'queryOptions' => [],

    // Optional channels
    'log'          => null,        // null | string | callable | object | Psr\Log\LoggerInterface
    'debug'        => false,       // include compiled SQL in error messages
    'queryLogs'    => false,       // start with the in-memory query log enabled
]
```

If both `dsn` and the driver-shaped fields are present, **`dsn` wins**. The other fields are still kept on the credential object for code that reads them (`getDriver()`, `getDatabaseName()`).

## Per-driver examples

### MySQL / MariaDB

```php
DB::createImmutable([
    'dsn'      => 'mysql:host=db.internal;port=3306;dbname=app;charset=utf8mb4',
    'username' => 'app',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset'  => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
```

Equivalent without a DSN:

```php
DB::createImmutable([
    'driver'   => 'mysql',
    'host'     => 'db.internal',
    'port'     => 3306,
    'database' => 'app',
    'username' => 'app',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset'  => 'utf8mb4',
]);
```

### PostgreSQL

```php
DB::createImmutable([
    'driver'   => 'pgsql',
    'host'     => 'pg.internal',
    'port'     => 5432,
    'database' => 'app',
    'username' => 'app',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset'  => 'UTF8',
]);
```

`charset` and `collation` apply only to MySQL (the package issues `SET NAMES … COLLATE …` after connect). On PostgreSQL the encoding comes from the cluster / database itself; leave the fields default.

### SQLite — file

```php
DB::createImmutable([
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/data/app.sqlite',
    'charset'  => '',
]);
```

### SQLite — in-memory

```php
DB::createImmutable([
    'driver'   => 'sqlite',
    'database' => ':memory:',
    'charset'  => '',
]);
```

Each PDO handle opens its own `:memory:` database, so any schema / seed data must be applied to the same handle the application uses.

## Field reference

| Field | Type | Default | Notes |
|---|---|---|---|
| `dsn` | `string` | `''` | Full PDO DSN. Wins over the field-shaped form when present. |
| `driver` | `string` | `'mysql'` | Used to pick the QueryBuilder dialect (`mysql`, `pgsql`, `sqlite`, generic). |
| `host` | `string` | `'127.0.0.1'` | Ignored for `sqlite`. |
| `port` | `int\|string` | `3306` | Ignored for `sqlite`. |
| `database` | `string` | `''` | Database name (or path for SQLite). |
| `username` | `string\|null` | `null` | |
| `password` | `string\|null` | `null` | |
| `charset` | `string` | `'utf8mb4'` | Used in `SET NAMES` (MySQL only). Must match `^[A-Za-z0-9_]+$`. |
| `collation` | `string\|null` | `null` | Appended as `COLLATE …` after `SET NAMES`. Same identifier rule. |
| `options` | `array<int, mixed>` | `[]` | Passed to `new PDO(..., ..., ..., $options)`. |
| `queryOptions` | `array<int, mixed>` | `[]` | Passed to every `prepare()`. |
| `log` | mixed | `null` | The log channel — see [Query Logging](Query-Logging.md). |
| `debug` | `bool` | `false` | Include compiled SQL in `SQLExecuteException` messages — **dev only**. |
| `queryLogs` | `bool` | `false` | Start the in-memory query log already enabled. |

## Identifier safety

The package validates `charset`, `collation` and `driver` against `^[A-Za-z0-9_]+$` before interpolating them into `SET NAMES` — that statement cannot be parameter-bound. If you set any of those to a value containing other characters, the constructor throws `InitORM\DBAL\Connection\Exceptions\ConnectionInvalidArgumentException`.

The rest of the package never interpolates user input into SQL — every column / table identifier flows through driver-specific escaping (backticks on MySQL/SQLite, double quotes on PostgreSQL), and every value goes through prepared-statement parameter binding. See [Reserved Keywords](Reserved-Keywords.md) for a worked example.

## Bootstrapping patterns

### Single config file

```php
// config/database.php
return [
    'dsn'      => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1',
        (int) (getenv('DB_PORT') ?: 3306),
        getenv('DB_NAME') ?: 'app',
    ),
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'debug'    => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL),
];
```

```php
// public/index.php
\InitPHP\Database\DB::createImmutable(require __DIR__ . '/../config/database.php');
```

### Dependency-injected, no facade

```php
$db = new \InitPHP\Database\Database($credentials);
$container->set(\InitORM\Database\Interfaces\DatabaseInterface::class, $db);
```

This skips the static facade entirely — your code references the `DatabaseInterface` directly. Useful in long-lived workers where you want to test substitution without globals.

## Next

- [Quick Start](Quick-Start.md) — get something running first.
- [Query Builder](Query-Builder.md) — the chainable surface.
- [Multiple Connections](Multiple-Connections.md) — secondary databases.
- [Debugging](Debugging.md) — what `debug=true` actually does.
