# 09 — Multiple connections

The package is built around one **shared** connection — `DB::createImmutable([...])` — because that is what almost every application needs. The remaining 10% of applications need to talk to two (or more) databases, and the helpers below cover that.

## The shared facade vs side-band connections

```php
use InitPHP\Database\DB;

// 1. The shared connection (your "main" database).
DB::createImmutable([
    'dsn'      => 'mysql:host=primary;dbname=app',
    'username' => 'app',
    'password' => '…',
]);

// 2. A second connection, NOT routed through the facade.
$analytics = DB::connect([
    'dsn'      => 'mysql:host=analytics;dbname=warehouse',
    'username' => 'reader',
    'password' => '…',
]);

$rows = $analytics->select('*')->from('events')->where('day', '2026-05-24')->read()->rows();
```

`DB::connect($credentials)` builds a fresh `Database` and returns it — the shared facade slot is left untouched. Treat the returned object as you would `DB::` itself; the surface is identical.

## Models on a non-shared connection

```php
namespace App\Model;

use InitPHP\Database\Model;

final class WarehouseEvents extends Model
{
    protected string $schema = 'events';

    protected ?array $credentials = [
        'dsn'      => 'mysql:host=analytics;dbname=warehouse',
        'username' => 'reader',
        'password' => '…',
    ];
}
```

A model whose `$credentials` is non-null spins up its **own** `Database` instance on construct rather than reaching for `DB::getDatabase()`. The shared facade is not consulted.

> A new connection is opened **per model instance**. If you instantiate `new WarehouseEvents()` ten times in a request, that is ten TCP connections to the warehouse. For long-lived processes (workers, queues) this is fine; for synchronous web requests, share one instance via a container.

## Swapping the shared connection at runtime

Use case: routing per-tenant requests to per-tenant databases inside a long-lived worker process.

```php
DB::replaceImmutable($tenantConnection);
```

`replaceImmutable()` accepts the same shapes as `createImmutable()` (an array of credentials or a `ConnectionInterface`), plus an already-built `DatabaseInterface` (handy when you cache per-tenant `Database` instances) and `null` (clears the slot entirely).

Anything that holds a reference to the previous `Database` keeps working against the old connection — `replaceImmutable()` only changes what `DB::getDatabase()` returns from that point onward.

## Transactions across two connections

Don't. PDO transactions are per-connection; the helper rejects nested starts on the same handle and there is no two-phase-commit support in InitORM. If you need cross-database consistency, model it as a single source-of-truth database plus an outbox / event-stream that downstream databases subscribe to.

## Detecting which connection a model is on

```php
$model->getDatabase(); // the DatabaseInterface this model is bound to
```

Useful in test code to assert that a model picked up the connection you expected:

```php
self::assertSame(
    DB::getDatabase(),
    (new App\Model\Posts())->getDatabase(),
    'Posts should be on the shared connection'
);
```
