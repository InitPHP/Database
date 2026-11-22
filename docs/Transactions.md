# Transactions

```php
use InitPHP\Database\DB;

DB::transaction(function ($db) {
    $db->create('posts', ['title' => 'New', 'author_id' => 5]);
    $db->update('counters', ['posts' => DB::raw('posts + 1')], ['author_id' => 5]);
});
```

`transaction()` opens a PDO transaction, runs the closure, and commits when the closure returns without throwing. Any throwable triggers a rollback and propagates unchanged — your error handling stays normal.

## Signature

```php
DB::transaction(
    Closure $closure,
    int $attempt = 1,
    bool $testMode = false
): bool
```

| Argument | Meaning |
|---|---|
| `$closure` | Receives the `Database` (or `Model`) the transaction is bound to. Use the argument or `DB::` — both reach the same instance. |
| `$attempt` | How many times to retry on failure. `1` means "no retry" (the default). |
| `$testMode` | When `true`, rolls back at the end even if the closure succeeded. Useful for assertions inside tests / dry runs. |

Returns `true` on a successful commit. On exhausted retries it throws `InitORM\Database\Exceptions\DatabaseException` whose `getPrevious()` is the underlying error from the last attempt.

## Retry semantics

```php
DB::transaction(function ($db) {
    $db->create('orders', $order);
    // ... maybe a deadlock or transient lock contention ...
}, attempt: 3);
```

Between attempts the helper rolls back any partial transaction and retries the entire closure. Retry is appropriate for **transient** failures — deadlocks, lock-wait timeouts, replica failover. Do not use it as a substitute for validating input; a closure that throws `LogicException` will fail three times in a row and then give up.

A common production pattern:

```php
DB::transaction(function ($db) use ($payload) {
    $db->create('orders', $payload);
    $db->updateBatch('user_id', 'wallets', $walletDeltas);
}, attempt: 5);
```

## Test mode

```php
DB::transaction(function ($db) {
    $db->create('audit', ['action' => 'probe']);

    $row = $db->select('COUNT(*)')->from('audit')->read()->row();
    self::assertSame(1, (int) ($row['COUNT(*)'] ?? 0));
}, testMode: true);
```

In `testMode = true` the helper calls `rollBack()` even on the success path. The closure can write freely, run assertions against the new state, and the database walks away untouched. Combine with `attempt: 1` to keep tests deterministic.

## Nesting is rejected

Starting a transaction while another is already in progress **on the same PDO connection** throws `DatabaseException` — there is no savepoint emulation. If you need that pattern, model it as a single outer transaction that runs a small pipeline of operations.

```php
// ❌ this throws
DB::transaction(function ($db) {
    DB::transaction(function () { /* … */ });
});
```

Cross-connection transactions are allowed (each PDO handle has its own transaction state), but you have no atomicity across connections — see [Multiple Connections](Multiple-Connections.md).

## Rollback failures

If `rollBack()` itself fails mid-flight (e.g. the connection died), the helper wraps the rollback error and the original error into a single `DatabaseException` with a message that names both. `getCode()` and `getPrevious()` preserve the original error so logging / monitoring keeps working.

```
Rollback failed after a failed transaction attempt: <rollback message> (original: <original message>)
```

## What `DB::raw()` buys you inside a transaction

When you need an atomic counter / increment:

```php
DB::transaction(function ($db) use ($userId) {
    $db->update('users',
        ['login_count' => DB::raw('login_count + 1')],
        ['id' => $userId]
    );
});
```

The `RawQuery` is interpolated verbatim — no PHP-side read-then-write race.

## Common pitfalls

### 1. Not using the closure parameter

The closure receives the `Database` so test substitutions work. If you reach for `DB::` directly inside the closure, your production code is harder to mock:

```php
// 👍 testable
DB::transaction(function ($db) use ($order) {
    $db->create('orders', $order);
});

// 👎 hard to substitute in a unit test
DB::transaction(function () use ($order) {
    DB::create('orders', $order);
});
```

### 2. Throwing user-facing exceptions inside the transaction

The transaction rolls back on **any** throwable — including validation errors you might want the caller to catch and re-render. Move validation outside the transaction:

```php
$validator->assert($payload);              // throws ValidationException early
DB::transaction(fn ($db) => $db->create('orders', $payload));
```

### 3. Long-running closures hold locks

PDO transactions hold row / range locks until commit. Keep transaction bodies tight — fetch data outside, then write inside.

## Next

- [CRUD Operations](CRUD-Operations.md) — what runs inside the closure.
- [Multiple Connections](Multiple-Connections.md) — why cross-connection atomicity is hard.
- [Query Logging](Query-Logging.md) — capture every statement a transaction ran.
