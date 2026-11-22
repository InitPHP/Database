# Multiple Connections

The package is built around **one shared connection** — `DB::createImmutable([...])` — because that is what almost every application needs. The helpers below cover the remaining 10% of cases: secondary databases, per-tenant routing, test fixtures.

## The shared facade vs side-band connections

```php
use InitPHP\Database\DB;

// 1. The shared (main) connection.
DB::createImmutable([
    'dsn'      => 'mysql:host=primary;dbname=app',
    'username' => 'app',
    'password' => '…',
]);

// 2. A side-band connection — NOT routed through the facade.
$analytics = DB::connect([
    'dsn'      => 'mysql:host=analytics;dbname=warehouse',
    'username' => 'reader',
    'password' => '…',
]);

$rows = $analytics->select('*')
    ->from('events')
    ->where('day', '2026-05-25')
    ->read()
    ->rows();
```

`DB::connect($credentials)` builds a fresh `Database` and returns it — the shared facade slot is left untouched. Treat the returned object as you would `DB::` itself; the surface is identical.

## Models on a non-shared connection

A model whose `$credentials` is non-null spins up its **own** `Database` instance on construct rather than reaching for `DB::getDatabase()`.

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

```php
$events = new WarehouseEvents();
$events->where('day', '2026-05-25')->read()->rows();
```

> **Per-instance connection cost:** A new connection is opened **per model instance**. `new WarehouseEvents()` ten times in a request = ten TCP connections to the warehouse. For long-lived processes (workers, queues) this is fine; for synchronous web requests, share one instance via a container.

## Swapping the shared connection at runtime

Use case: a long-lived worker routes per-tenant requests to per-tenant databases.

```php
DB::replaceImmutable($tenantConnection);
```

`replaceImmutable()` accepts:

- An **array of credentials** (built into a new `Database`).
- A `ConnectionInterface` (built into a new `Database`).
- A `DatabaseInterface` (stored as-is — useful when you cache per-tenant `Database` instances).
- `null` (clears the facade slot entirely; subsequent `DB::getDatabase()` calls throw until you call `createImmutable()` or `replaceImmutable()` again).

```php
// At request start
$tenant = $resolveTenant($request);
DB::replaceImmutable($connectionCache[$tenant] ??=
    new Database($credentialsByTenant[$tenant]));

// Whole request runs against the right database now.
```

### What happens to outstanding references?

Anything that holds a reference to the previous `Database` keeps working against the **old** connection. `replaceImmutable()` only changes what `DB::getDatabase()` returns from that point onward; existing model instances stay bound to whichever Database they were constructed with.

This is usually what you want — but watch out:

```php
$model = new App\Model\Posts(); // binds to current shared connection
DB::replaceImmutable($otherConnection);
$model->read(); // still hits the OLD database
```

Re-instantiate models after swapping if you want them to follow.

## Transactions across two connections

**Don't.** PDO transactions are per-connection; the helper rejects nested starts on the same handle and there is no two-phase-commit support in InitORM. If you need cross-database consistency, model it as a single source-of-truth database plus an outbox / event-stream that downstream databases subscribe to.

A safer "best effort" pattern when consistency loss is acceptable:

```php
DB::transaction(function ($db) use ($order) {
    $db->create('orders', $order); // primary
});

// Secondary write — best effort, idempotent.
try {
    $analytics->create('orders_mirror', $order);
} catch (Throwable $e) {
    // log it, queue a retry, but don't roll back the primary
}
```

## Detecting which connection a model is on

```php
$model->getDatabase(); // the DatabaseInterface this model is bound to
```

Useful in test code to assert that a model picked up the right connection:

```php
self::assertSame(
    DB::getDatabase(),
    (new App\Model\Posts())->getDatabase(),
    'Posts should be on the shared connection'
);

$warehouse = new WarehouseEvents();
self::assertNotSame(DB::getDatabase(), $warehouse->getDatabase());
```

## Recipe: read replica with write fallback

```php
$primary = DB::createImmutable($primaryCreds); // shared facade
$replica = DB::connect($replicaCreds);

// Reads go to the replica:
$rows = $replica->select('*')->from('users')->where('active', 1)->read()->rows();

// Writes go to the primary (the facade):
DB::create('users', ['email' => 'new@example.com']);
```

The pattern keeps the static facade for writes (low ceremony) while reads fan out to the replica. Both connections live on separate PDO handles — they have no shared transaction state.

## Cleaning up between tests

In a PHPUnit test that uses the shared facade:

```php
protected function setUp(): void
{
    DB::replaceImmutable(null);                 // clear any leftover state
    DB::createImmutable($testCredentials);      // fresh connection
}

protected function tearDown(): void
{
    DB::replaceImmutable(null);                 // don't leak into the next test
}
```

The package's own `tests/DBTest.php` follows exactly this pattern.

## Next

- [Configuration](Configuration.md) — the credential shape.
- [Transactions](Transactions.md) — why cross-connection atomicity is hard.
- [Recipe — Repository Pattern](Recipe-Repository-Pattern.md) — for code that should not depend on a global facade at all.
