# Installation

## Requirements

- **PHP 8.2 or later** — older versions are not supported. The test matrix covers PHP 8.2, 8.3, 8.4 and 8.5.
- **`ext-pdo`** — always required.
- A PDO driver matching your target database — one of:
  - `ext-pdo_mysql` (MySQL / MariaDB)
  - `ext-pdo_pgsql` (PostgreSQL)
  - `ext-pdo_sqlite` (SQLite)
  - any other PDO driver PHP ships (`pdo_oci`, `pdo_sqlsrv`, …)

## Install via Composer

```bash
composer require initphp/database
```

To install a specific major version:

```bash
composer require initphp/database:^5.0
```

## Verify the installation

A two-line smoke test:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$db = new InitPHP\Database\Database([
    'driver'   => 'sqlite',
    'database' => ':memory:',
    'charset'  => '',
]);

$db->getPDO()->exec('CREATE TABLE ping (id INTEGER)');
$db->create('ping', ['id' => 1]);
var_dump($db->select('*')->from('ping')->read()->asAssoc()->rows());
```

If you see one row come back, you are good to go.

## Where things live

After install the package exposes four entry points:

| Class | Use case |
|---|---|
| `InitPHP\Database\DB` | Static facade — bootstrap once with `DB::createImmutable([...])` and call `DB::select(...)` from anywhere. |
| `InitPHP\Database\Database` | Direct instantiation — when you want to own the lifecycle yourself, e.g. multiple connections. |
| `InitPHP\Database\Model` | Subclass it to bind one table; pairs with `Entity`. |
| `InitPHP\Database\Entity` | Subclass it for typed row objects with accessor / mutator hooks. |
| `InitPHP\Database\Utils\Datatables\Datatables` | Server-side DataTables.js helper. |

## Optional development tooling

The package ships its own CI pipeline; if you fork or contribute, install the dev dependencies:

```bash
composer install
composer qa     # phpcs + phpstan + phpunit
composer cs-fix # auto-format
```

| Tool | Config file |
|---|---|
| PHPUnit | `phpunit.xml` |
| PHPStan (level 6) | `phpstan.neon.dist` |
| PHP_CodeSniffer (PSR-12) | `phpcs.xml.dist` |

## Next

- [Quick Start](Quick-Start.md) — a 10-minute tour.
- [Configuration](Configuration.md) — every supported connection option.
