# Recipe — Audit Log

[Soft Deletes](Soft-Deletes.md) capture "the row existed but is now hidden". An audit log captures "the row used to look like X, then became Y, then became Z". Different scope, different tool.

## Problem

Every write to `orders` should leave a row in `orders_audit` capturing:

- Which row was touched (`order_id`).
- What kind of write (`insert` / `update` / `delete`).
- The full row state before the write (`before`).
- The full row state after the write (`after`).
- Who made the change (`actor_id`) and when.

The audit row must be **atomic with the change**: a row in `orders_audit` should exist if and only if the change to `orders` committed.

## Schema

```sql
CREATE TABLE orders (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    total_cents INTEGER NOT NULL,
    status      VARCHAR(32) NOT NULL,
    created_at  DATETIME    NOT NULL,
    updated_at  DATETIME
);

CREATE TABLE orders_audit (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id   INTEGER NOT NULL,
    action     VARCHAR(16) NOT NULL,   -- insert | update | delete
    actor_id   INTEGER,                -- nullable for system jobs
    before     TEXT,                   -- JSON-encoded prior state, NULL on insert
    after      TEXT,                   -- JSON-encoded new state, NULL on delete
    created_at DATETIME NOT NULL
);
```

## Code

```php
namespace App\Repository;

use InitORM\Database\Interfaces\DatabaseInterface;

final class AuditedOrdersRepository
{
    public function __construct(
        private readonly DatabaseInterface $db,
    ) {
    }

    public function create(array $row, ?int $actorId = null): int
    {
        return $this->db->transaction(function ($db) use ($row, $actorId) {
            $db->create('orders', $row);
            $id = (int) $db->insertId();

            $after = $db->select('*')->from('orders')
                ->where('id', $id)->read()->asAssoc()->row();

            $this->writeAudit($db, $id, 'insert', $actorId, null, $after);

            return $id;
        }) ? $this->lastCreatedId : 0;
    }

    public function update(int $orderId, array $set, ?int $actorId = null): void
    {
        $this->db->transaction(function ($db) use ($orderId, $set, $actorId) {
            $before = $db->select('*')->from('orders')
                ->where('id', $orderId)->read()->asAssoc()->row();

            $db->where('id', $orderId)->update('orders', $set);

            $after = $db->select('*')->from('orders')
                ->where('id', $orderId)->read()->asAssoc()->row();

            $this->writeAudit($db, $orderId, 'update', $actorId, $before, $after);
        });
    }

    public function delete(int $orderId, ?int $actorId = null): void
    {
        $this->db->transaction(function ($db) use ($orderId, $actorId) {
            $before = $db->select('*')->from('orders')
                ->where('id', $orderId)->read()->asAssoc()->row();

            $db->where('id', $orderId)->delete('orders');

            $this->writeAudit($db, $orderId, 'delete', $actorId, $before, null);
        });
    }

    private function writeAudit(
        DatabaseInterface $db,
        int $orderId,
        string $action,
        ?int $actorId,
        ?array $before,
        ?array $after,
    ): void {
        $db->create('orders_audit', [
            'order_id'   => $orderId,
            'action'     => $action,
            'actor_id'   => $actorId,
            'before'     => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after'      => $after  === null ? null : json_encode($after,  JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
```

## Wire it up

```php
$orders = new App\Repository\AuditedOrdersRepository(DB::getDatabase());

$id = $orders->create([
    'user_id'     => 5,
    'total_cents' => 19900,
    'status'      => 'pending',
    'created_at'  => date('Y-m-d H:i:s'),
], actorId: $session->userId);

$orders->update($id, ['status' => 'paid'], actorId: $session->userId);
```

After those two calls, `orders_audit` contains:

| id | order_id | action | actor_id | before | after |
|---|---|---|---|---|---|
| 1 | $id | insert | 5 | NULL | `{ "id": …, "status": "pending", … }` |
| 2 | $id | update | 5 | `{ "id": …, "status": "pending", … }` | `{ "id": …, "status": "paid", … }` |

## Why a transaction is non-negotiable

```php
// ❌ wrong — order can land without an audit row if the audit insert fails
$db->create('orders', $row);
$db->create('orders_audit', $audit);
```

```php
// ✅ correct — either both land or neither
DB::transaction(function ($db) use ($row, $audit) {
    $db->create('orders', $row);
    $db->create('orders_audit', $audit);
});
```

The transaction is the **only** thing that guarantees the audit log can't drift away from reality. Without it, a network blip between the two writes will permanently desync your audit trail.

## Tests

```php
public function testInsertWritesPairedAuditRow(): void
{
    $db = SqliteHelper::makeDatabase();
    // … create both tables …

    $repo = new AuditedOrdersRepository($db);
    $id   = $repo->create(['user_id' => 1, 'total_cents' => 100,
        'status' => 'pending', 'created_at' => '2026-05-25 10:00:00']);

    $audit = $db->select('*')->from('orders_audit')->read()->asAssoc()->row();

    self::assertSame('insert', $audit['action']);
    self::assertSame($id, (int) $audit['order_id']);
    self::assertNull($audit['before']);
    self::assertNotNull($audit['after']);
}

public function testFailedOrderInsertLeavesNoAuditRow(): void
{
    $db = SqliteHelper::makeDatabase();
    // … create both tables …

    $repo = new AuditedOrdersRepository($db);

    try {
        $repo->create(['user_id' => null /* NOT NULL → fails */]);
    } catch (Throwable) {}

    $count = (int) ($db->selectCount('*', 'n')->from('orders_audit')
                      ->read()->asAssoc()->row()['n'] ?? -1);
    self::assertSame(0, $count);
}
```

## Variations

### Audit only on UPDATE, not on every read

The example above audits writes only. If you also need read auditing (compliance, GDPR), capture it in middleware rather than the repository — reads happen everywhere and threading audit into every read path is intrusive.

### Per-column diffing

`before` / `after` as full row JSON is easy to query but expensive to store. For mature audit needs, store only the columns that changed:

```php
$diff = array_diff_assoc($after, $before);
// store $diff instead of the whole $after
```

The query side gets harder ("what was the email at 3 PM?" requires reconstructing the state by walking the audit log), but storage cost drops sharply.

### Use database triggers instead

```sql
CREATE TRIGGER orders_audit_insert AFTER INSERT ON orders
FOR EACH ROW INSERT INTO orders_audit (...) VALUES (...);
```

Triggers move the work into the database — guaranteed atomicity, no application code path to forget. The downsides: harder to test, vendor-specific syntax, no easy access to the application-level `actor_id`.

A hybrid pattern works well: use triggers for the data, set the `actor_id` via a session variable (`SET @actor_id = ?` before the write).

## Next

- [Transactions](Transactions.md) — the atomicity guarantee this recipe stands on.
- [Soft Deletes](Soft-Deletes.md) — when "the row exists but is hidden" is enough.
- [Recipe — Repository Pattern](Recipe-Repository-Pattern.md) — the broader pattern this recipe is an instance of.
