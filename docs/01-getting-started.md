# 01 — Getting started

## Install

```bash
composer require initphp/database
```

Requirements:

- PHP 8.1 or later
- `ext-pdo` plus the driver for your database (`pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, …)

## The connection array

Every entry point — `DB::createImmutable()`, `DB::connect()`, `new Database(...)`, and `Model::$credentials` — accepts the same shape:

```php
[
    // 1. EITHER pass a fully-formed DSN ...
    'dsn'          => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    // 2. ... OR let the package build one from the parts:
    'driver'       => 'mysql',     // mysql | pgsql | sqlite | (any PDO driver name)
    'host'         => '127.0.0.1',
    'port'         => 3306,
    'database'     => 'test',

    'username'     => 'root',
    'password'     => '',

    'charset'      => 'utf8mb4',   // applied via SET NAMES after connect (MySQL only)
    'collation'    => 'utf8mb4_unicode_ci',

    'options'      => [],          // passed straight to new PDO(..., ..., ..., $options)
    'queryOptions' => [],          // passed to prepare() on every statement

    // Optional channels — see docs/07-query-log.md
    'log'          => null,        // string | callable | object with critical()
    'debug'        => false,       // include the executed SQL in error messages
    'queryLogs'    => false,       // start with the in-memory query log enabled
]
```

If both `dsn` and the driver-shaped fields are present, `dsn` wins.

## Bootstrap one connection (the common case)

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Database\DB;

DB::createImmutable([
    'dsn'      => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
]);
```

`createImmutable()` may be called **once per process**. A second call throws `InitORM\Database\Exceptions\DatabaseException` — if you genuinely need to swap the shared connection (test fixtures, multi-tenant routing), use `DB::replaceImmutable()` instead.

## Your first query

```php
use InitPHP\Database\DB;

$rows = DB::select('id', 'title')
    ->from('posts')
    ->where('status', '=', 1)
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->read()
    ->asAssoc()
    ->rows();
```

The fluent chain returns the shared `Database` instance until you call `read()`, at which point you get back a `DataMapperInterface`. From there `asAssoc()` / `asObject()` / `asClass()` pick the fetch mode and `row()` / `rows()` consume the result.

## SQLite in two lines (handy for tests / scripts)

```php
DB::createImmutable([
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/data.sqlite',
    'charset'  => '',
]);
```

For an in-memory database use `'database' => ':memory:'` — keep in mind that each PDO handle gets its own `:memory:` instance.

## Debug mode

```php
DB::createImmutable([
    'dsn'      => 'mysql:host=localhost;dbname=test;charset=utf8mb4',
    'username' => 'root',
    'password' => '',

    'debug'    => true, // include the compiled SQL in any thrown exception
]);
```

Debug mode is a development convenience — it leaks query text into error messages, so **never enable it in production**.

## Where to go next

- [02 — Query Builder](02-query-builder.md) — the chainable surface in depth.
- [03 — CRUD](03-crud.md) — `create` / `update` / `delete` and the batch variants.
- [04 — Models](04-models.md) — active-record-style table classes.
- [07 — Query log](07-query-log.md) — the `log` channel and per-call instrumentation.
