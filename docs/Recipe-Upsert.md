# Recipe — Upsert / REPLACE INTO

Insert-or-update is one of the most common operations that doesn't fit cleanly on `create()` / `update()`. The query builder has no native upsert method because the SQL spelling differs across drivers. Reach for `DB::query()` and the right dialect.

## Problem

A worker imports `items` from an external feed. Each item has a stable `external_id`. On every run the worker should:

- Insert items it has not seen before.
- Update items whose data has changed.
- Touch a `synced_at` column on every run.

## Schema

```sql
-- MySQL / MariaDB / SQLite syntax (PostgreSQL adapts the column types)
CREATE TABLE items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    external_id  VARCHAR(64) NOT NULL UNIQUE,
    name         TEXT        NOT NULL,
    price_cents  INTEGER     NOT NULL,
    synced_at    DATETIME    NOT NULL
);
```

## Approach 1 — MySQL / MariaDB / SQLite: `REPLACE INTO`

```php
use InitPHP\Database\DB;

DB::query(
    'REPLACE INTO items (external_id, name, price_cents, synced_at)
     VALUES (:external_id, :name, :price_cents, :synced_at)',
    [
        ':external_id' => $row['external_id'],
        ':name'        => $row['name'],
        ':price_cents' => (int) ($row['price'] * 100),
        ':synced_at'   => date('Y-m-d H:i:s'),
    ]
);
```

### What REPLACE INTO actually does

It tries to insert. If a UNIQUE / PRIMARY KEY conflict happens, it `DELETE`s the conflicting row and inserts the new one. Two things to know:

| Concern | Implication |
|---|---|
| `ON DELETE` triggers fire | Anything cascading off `items` runs. |
| Auto-increment ids change | The replaced row gets a fresh `id`. References to the old id break. |

SQLite accepts the same syntax and also `INSERT OR REPLACE INTO` as a synonym.

If either matters, use approach 2 or 3 instead.

## Approach 2 — MySQL 8: `INSERT ... ON DUPLICATE KEY UPDATE`

```php
DB::query(
    'INSERT INTO items (external_id, name, price_cents, synced_at)
     VALUES (:external_id, :name, :price_cents, :synced_at)
     ON DUPLICATE KEY UPDATE
         name        = VALUES(name),
         price_cents = VALUES(price_cents),
         synced_at   = VALUES(synced_at)',
    [
        ':external_id' => $row['external_id'],
        ':name'        => $row['name'],
        ':price_cents' => (int) ($row['price'] * 100),
        ':synced_at'   => date('Y-m-d H:i:s'),
    ]
);
```

No DELETE, no fresh id, no triggers cascading. `VALUES(col)` refers back to the value the INSERT clause would have written.

> **MySQL 8.0.20+**: `VALUES(col)` is deprecated. Use the table-alias form:
>
> ```sql
> INSERT INTO items (...) VALUES (...) AS new
> ON DUPLICATE KEY UPDATE name = new.name, …
> ```

## Approach 3 — PostgreSQL: `INSERT ... ON CONFLICT`

```php
DB::query(
    'INSERT INTO items (external_id, name, price_cents, synced_at)
     VALUES (:external_id, :name, :price_cents, :synced_at)
     ON CONFLICT (external_id) DO UPDATE SET
         name        = EXCLUDED.name,
         price_cents = EXCLUDED.price_cents,
         synced_at   = EXCLUDED.synced_at',
    [
        ':external_id' => $row['external_id'],
        ':name'        => $row['name'],
        ':price_cents' => (int) ($row['price'] * 100),
        ':synced_at'   => date('Y-m-d H:i:s'),
    ]
);
```

`ON CONFLICT` is generally the better choice when it's available. The conflict target is **explicit** (`(external_id)`) — no surprises if the table grows a new UNIQUE constraint later.

## Batching the import

For a feed of thousands of items, do one round-trip per row inside a single transaction. You get amortised connection cost and atomic rollback if the import fails halfway:

```php
DB::transaction(function ($db) use ($rows) {
    foreach ($rows as $row) {
        $db->query(
            'REPLACE INTO items (external_id, name, price_cents, synced_at)
             VALUES (:external_id, :name, :price_cents, :synced_at)',
            [
                ':external_id' => $row['external_id'],
                ':name'        => $row['name'],
                ':price_cents' => (int) ($row['price'] * 100),
                ':synced_at'   => date('Y-m-d H:i:s'),
            ]
        );
    }
});
```

For tens of thousands of rows, chunk the transaction (a long-running transaction holds locks):

```php
foreach (array_chunk($rows, 500) as $chunk) {
    DB::transaction(function ($db) use ($chunk) {
        foreach ($chunk as $row) { /* … */ }
    });
}
```

## Driver-aware wrapper

```php
namespace App\Support;

use InitORM\Database\Interfaces\DatabaseInterface;

final class Upserter
{
    public function __construct(private readonly DatabaseInterface $db) {}

    /**
     * @param array<string, scalar|null> $row
     */
    public function upsert(string $table, string $conflictColumn, array $row): void
    {
        $driver  = $this->db->getConnection()->getDriver();
        $columns = array_keys($row);
        $params  = [];

        foreach ($row as $key => $value) {
            $params[':' . $key] = $value;
        }

        $colList   = implode(', ', $columns);
        $valueList = implode(', ', array_map(fn ($c) => ':' . $c, $columns));

        if ($driver === 'pgsql') {
            $updates = implode(', ', array_map(
                fn ($c) => "{$c} = EXCLUDED.{$c}",
                array_filter($columns, fn ($c) => $c !== $conflictColumn)
            ));
            $sql = "INSERT INTO {$table} ({$colList}) VALUES ({$valueList})
                    ON CONFLICT ({$conflictColumn}) DO UPDATE SET {$updates}";
        } else {
            // mysql / mariadb / sqlite
            $sql = "REPLACE INTO {$table} ({$colList}) VALUES ({$valueList})";
        }

        $this->db->query($sql, $params);
    }
}
```

> The wrapper interpolates `$table` and `$conflictColumn` directly — identifiers cannot be bound. **Only call it with hard-coded strings**, never user input.

Use it:

```php
$upsert = new App\Support\Upserter(DB::getDatabase());
foreach ($rows as $row) {
    $upsert->upsert('items', 'external_id', [
        'external_id' => $row['external_id'],
        'name'        => $row['name'],
        'price_cents' => (int) ($row['price'] * 100),
        'synced_at'   => date('Y-m-d H:i:s'),
    ]);
}
```

## Tests

```php
public function testUpsertInsertsWhenNoConflict(): void
{
    $db = SqliteHelper::makeDatabase();
    $db->getConnection()->getPDO()->exec(
        'CREATE TABLE items (
            id INTEGER PRIMARY KEY,
            external_id TEXT UNIQUE,
            name TEXT
        )'
    );

    $upsert = new App\Support\Upserter($db);
    $upsert->upsert('items', 'external_id', ['external_id' => 'a', 'name' => 'A']);

    $row = $db->select('*')->from('items')->read()->asAssoc()->row();
    self::assertSame('a', $row['external_id']);
    self::assertSame('A', $row['name']);
}

public function testUpsertReplacesOnConflict(): void
{
    // ... insert with name 'A', upsert with name 'B', assert name is 'B'
}
```

## Variations

### "Insert if missing, do nothing on conflict"

```sql
-- MySQL
INSERT IGNORE INTO items (...) VALUES (...);

-- PostgreSQL
INSERT INTO items (...) VALUES (...) ON CONFLICT DO NOTHING;

-- SQLite
INSERT OR IGNORE INTO items (...) VALUES (...);
```

### Bulk REPLACE INTO

```sql
REPLACE INTO items (external_id, name) VALUES
    (:a_external_id, :a_name),
    (:b_external_id, :b_name),
    (:c_external_id, :c_name);
```

Build the value list with placeholders dynamically; bind in a loop. One round-trip for the whole batch. The builder's `createBatch()` does this for plain INSERT but not for REPLACE.

## Next

- [Raw Queries](Raw-Queries.md) — when the builder doesn't cover what you need.
- [Transactions](Transactions.md) — wrapping a batch import.
