# InitPHP Database

A Composer-friendly, batteries-included facade over the [InitORM](https://github.com/InitORM) stack — query builder, DBAL connection, ORM models and entities — plus a server-side helper for [DataTables.js](https://datatables.net/).

[![CI](https://github.com/InitPHP/Database/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/Database/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/initphp/database/v)](https://packagist.org/packages/initphp/database)
[![Total Downloads](https://poser.pugx.org/initphp/database/downloads)](https://packagist.org/packages/initphp/database)
[![License](https://poser.pugx.org/initphp/database/license)](https://packagist.org/packages/initphp/database)
[![PHP Version Require](https://poser.pugx.org/initphp/database/require/php)](https://packagist.org/packages/initphp/database)

## What is this?

`initphp/database` does not reimplement an ORM. It is the InitPHP-branded entry point to the InitORM stack:

| You write | You actually get |
| --- | --- |
| `InitPHP\Database\DB` | A static facade over `InitORM\Database\Database`. |
| `InitPHP\Database\Database` | The InitORM Database class. |
| `InitPHP\Database\Model` | The InitORM active-record Model. |
| `InitPHP\Database\Entity` | The InitORM Entity with accessor / mutator hooks. |
| `InitPHP\Database\Utils\Datatables\Datatables` | **Original** server-side DataTables.js helper — the one piece that lives in this package. |

If a feature is documented for InitORM, it works here under the InitPHP namespace.

## Requirements

- PHP **8.1** or later
- `ext-pdo` and a PDO driver for your target database (`pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, …)

## Installation

```bash
composer require initphp/database
```

## Quick start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Database\DB;

DB::createImmutable([
    'dsn'      => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
]);

$rows = DB::select('id', 'title', 'author_id')
    ->from('posts')
    ->where('status', '=', 1)
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->read()
    ->asAssoc()
    ->rows();

foreach ($rows as $row) {
    echo $row['title'], PHP_EOL;
}
```

## Documentation

Topic-by-topic guides live in [`docs/`](docs/):

| # | Guide |
| --- | --- |
| 01 | [Getting started](docs/01-getting-started.md) — install, connect, first query, debug & logging |
| 02 | [Query Builder](docs/02-query-builder.md) — `select` / `where` / `join` / `groupBy` / `orderBy` / `limit` / raw |
| 03 | [CRUD](docs/03-crud.md) — `create` / `read` / `update` / `delete` and their `*Batch` siblings |
| 04 | [Models](docs/04-models.md) — table binding, soft deletes, timestamp columns, access gates |
| 05 | [Entities](docs/05-entities.md) — attribute bag, accessor / mutator hooks (and the one PHP 8.2+ pitfall) |
| 06 | [Transactions](docs/06-transactions.md) — automatic retry, dry-run / test mode |
| 07 | [Query log](docs/07-query-log.md) — `enableQueryLog` + the `log` connection channel |
| 08 | [Datatables](docs/08-datatables.md) — server-side DataTables.js integration end-to-end |
| 09 | [Multiple connections](docs/09-multi-connection.md) — secondary databases via `DB::connect()` / `Model::$credentials` |
| 10 | [Upgrading from 3.x / 4.x](docs/10-upgrading.md) — breaking changes in 5.0 and the migration path |

## At a glance

- Fluent CRUD that compiles to prepared statements (named parameter bag is handled internally).
- Per-driver SQL dialects (MySQL, PostgreSQL, SQLite, generic).
- Models with auto-derived schemas, configurable primary key, soft deletes, and `created_at` / `updated_at` plumbing.
- Entities with Laravel-style `getColumnAttribute()` / `setColumnAttribute()` hooks and dirty tracking via `getOriginal()`.
- Transaction helper with retry attempts and a `testMode` flag that always rolls back.
- Query log channel that accepts a file path (with `{year}`/`{month}`/`{day}` placeholders), a callable, or any object exposing a `critical()` method.
- Server-side DataTables.js helper that handles search, sort, paging and per-column render callbacks — see [docs/08-datatables.md](docs/08-datatables.md).

## Contributing

Org-wide guidelines (PSR-12, `declare(strict_types=1)`, Conventional Commits, PHPStan + PHPUnit on every PR) live in the InitPHP `.github` repo:

- [CONTRIBUTING.md](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md)
- [CODE_OF_CONDUCT.md](https://github.com/InitPHP/.github/blob/main/CODE_OF_CONDUCT.md)

Local checks before opening a PR:

```bash
composer qa     # phpcs + phpstan + phpunit
composer cs-fix # auto-format
```

## Security

Please **do not** open a public issue for security vulnerabilities. The org-wide [SECURITY.md](https://github.com/InitPHP/.github/blob/main/SECURITY.md) describes the private disclosure channels (GitHub PVR + email).

## Credits

Maintained by [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) (`info@muhammetsafak.com.tr`).

## License

[MIT](LICENSE).
