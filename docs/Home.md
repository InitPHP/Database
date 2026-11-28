# InitPHP Database — Wiki

Welcome to the official documentation for **`initphp/database`** — a Composer-friendly database facade for PHP 8.2+. It bundles a fluent **query builder**, a **DBAL** connection layer, an active-record-style **ORM** with soft deletes and timestamp columns, and a server-side **[DataTables.js](https://datatables.net/)** helper.

```bash
composer require initphp/database
```

```php
use InitPHP\Database\DB;

DB::createImmutable([
    'dsn'      => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
]);

$rows = DB::select('id', 'title')
    ->from('posts')
    ->where('status', 1)
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->read()
    ->asAssoc()
    ->rows();
```

The package re-exports the [InitORM](https://github.com/InitORM) stack under the `InitPHP\Database` namespace and ships one piece of original code on top — the DataTables helper.

| Layer | Class / Facade | Purpose |
|---|---|---|
| Facade | `InitPHP\Database\DB` | Static entry point over a shared `Database` instance |
| DBAL | `InitPHP\Database\Database` | Connection + builder + CRUD orchestration |
| ORM | `InitPHP\Database\Model` | Active-record table binding with soft deletes |
| ORM | `InitPHP\Database\Entity` | Attribute bag with accessor / mutator hooks |
| Utility | `InitPHP\Database\Utils\Datatables\Datatables` | Server-side DataTables.js integration |

## Start here

- **New to the package?** Read [Installation](Installation.md), then [Quick Start](Quick-Start.md).
- **Setting up a connection?** See [Configuration](Configuration.md) for every supported option.
- **Building queries?** See [Query Builder](Query-Builder.md) and [CRUD Operations](CRUD-Operations.md).
- **Building an ORM layer?** See [Models](Models.md) and [Entities](Entities.md).
- **Wiring up DataTables.js?** Start with [DataTables Introduction](DataTables-Introduction.md).
- **Need a specific pattern?** Browse the [Recipes](Recipes.md).
- **Upgrading from 3.x or 4.x?** Read the [Migration Guide](Migration-Guide.md).

## Feature matrix

| Capability | Status |
|---|:---:|
| Fluent SELECT / WHERE / JOIN / GROUP BY / HAVING / ORDER BY / LIMIT | ✅ |
| Insert / Update / Delete + batch variants (`createBatch`, `updateBatch`) | ✅ |
| Prepared statements with automatic parameter binding | ✅ |
| Driver-specific identifier escaping (backticks on MySQL/SQLite, `"…"` on PostgreSQL) | ✅ |
| Soft deletes (`deleted_at`) and auto-managed `created_at` / `updated_at` columns | ✅ |
| Per-model access gates (`$readable`, `$writable`, `$updatable`, `$deletable`) | ✅ |
| Entity accessor / mutator hooks (Laravel-style `get{Col}Attribute` / `set{Col}Attribute`) | ✅ |
| Dirty tracking via `getOriginal()` / `syncOriginal()` | ✅ |
| Transactions with retry count and `testMode` (always rolls back) | ✅ |
| In-memory query log (`enableQueryLog` / `getQueryLogs`) | ✅ |
| Connection-level `log` channel — file path, callable, PSR-3 logger, or object with `critical()` | ✅ |
| `debug=true` to surface compiled SQL in error messages | ✅ |
| Multiple connections (`DB::connect()` and `Model::$credentials`) | ✅ |
| Server-side DataTables.js (search / sort / paging / per-column renderers / group-by counts) | ✅ |

## Package metadata

- **License**: MIT
- **Minimum PHP**: 8.2 (tested on 8.2, 8.3, 8.4, 8.5, 8.6)
- **Runtime dependencies**: `ext-pdo`, `initorm/orm ^2.0`
- **Suggested PDO drivers**: `ext-pdo_mysql`, `ext-pdo_pgsql`, `ext-pdo_sqlite`
- **Packagist**: [`initphp/database`](https://packagist.org/packages/initphp/database)
- **Source**: [github.com/InitPHP/Database](https://github.com/InitPHP/Database)
- **Issues**: [github.com/InitPHP/Database/issues](https://github.com/InitPHP/Database/issues)
- **Discussions**: [github.com/orgs/InitPHP/discussions](https://github.com/orgs/InitPHP/discussions)
- **Security**: [`SECURITY.md`](https://github.com/InitPHP/.github/blob/main/SECURITY.md)

If a page is unclear, ambiguous, or wrong, please [open an issue](https://github.com/InitPHP/Database/issues/new) — documentation fixes ship eagerly.
