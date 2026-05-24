# 06 — Transactions

```php
use InitPHP\Database\DB;

DB::transaction(function ($db) {
    $db->create('posts',    ['title' => 'New', 'author_id' => 5]);
    $db->update('counters', ['posts' => DB::raw('posts + 1')], ['author_id' => 5]);
});
```

`transaction()` opens a PDO transaction, runs the closure, and commits when the closure returns without throwing. Any throwable triggers a rollback and the exception propagates unchanged — your error handling stays normal.

## Method signature

```php
DB::transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool
```

| Argument | Meaning |
| --- | --- |
| `$closure` | Receives the `Database` (or `Model`) the transaction is bound to. Use it for the writes — `DB::` works too, since the facade points at the same instance. |
| `$attempt` | How many times to retry on failure. `1` means "no retry" (the default). |
| `$testMode` | When `true`, rolls back at the end even if the closure succeeded. Useful for assertions inside tests / dry runs. |

The method returns `true` on a successful commit. On exhausted retries it throws `InitORM\Database\Exceptions\DatabaseException` whose `getPrevious()` is the underlying error from the last attempt.

## Retry semantics

```php
DB::transaction(function ($db) {
    $db->create('orders', $order);
    // ... maybe a deadlock or transient lock contention ...
}, attempt: 3);
```

Between attempts the helper rolls back any partial transaction and retries the entire closure. Retry is appropriate for **transient** failures — deadlocks, lock-wait timeouts, replica failover. Do not use it as a substitute for validating input; a closure that throws a `LogicException` will fail three times in a row.

## Test mode

```php
DB::transaction(function ($db) {
    $db->create('audit', ['action' => 'probe']);

    self::assertSame(1, (int) $db->select('COUNT(*)')->from('audit')->read()->row()['COUNT(*)']);
}, testMode: true);
```

In `testMode = true` the helper calls `rollBack()` even on the success path. The closure can write freely, run assertions against the new state, and the database walks away untouched. Combine with attempt = 1 to keep tests deterministic.

## Nesting is rejected

Starting a transaction while another is already in progress on the same PDO connection throws `DatabaseException` — there is no savepoint emulation. If you need that pattern, model it as a single outer transaction that runs a small pipeline of operations.

## Rollback failures

If `rollBack()` itself fails mid-flight (e.g. the connection died), the helper wraps the rollback error and the original error into a single `DatabaseException` with a message that names both. `getCode()` and `getPrevious()` preserve the original error.

## Closing a connection inside the closure

Don't. Transactions live on the underlying PDO handle, and if that goes away mid-flight the rollback path cannot run. If you need to swap connections, do it outside the transaction — for example via `DB::replaceImmutable(...)` between two unrelated transactions.
