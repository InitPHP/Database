# Raw Queries

When the SQL is faster to write by hand, or when the operation does not map cleanly onto the builder (`REPLACE INTO`, vendor-specific DDL, complex CTEs), reach for `DB::query()`.

## Signature

```php
DB::query(
    string $sqlQuery,
    ?array $parameters = null,
    ?array $options = null
): DataMapperInterface
```

- `$sqlQuery` — the SQL to prepare. Named placeholders (`:name`) are supported throughout; the package does **not** rewrite `?` positional placeholders.
- `$parameters` — `[':name' => value, ...]`. The leading colon is optional; the binder normalises it. `null` means "no parameters".
- `$options` — passed straight to `PDO::prepare($sql, $options)`. Use for `PDO::ATTR_CURSOR`, `PDO::ATTR_EMULATE_PREPARES`, etc.

Returns a `DataMapperInterface` you consume exactly like a builder `read()` result.

## Basic SELECT

```php
$res = DB::query(
    'SELECT id, title FROM posts WHERE user_id = :uid AND status > :s',
    [':uid' => 5, ':s' => 0]
);

foreach ($res->asAssoc()->rows() as $row) {
    echo $row['title'], PHP_EOL;
}
```

## Basic INSERT / UPDATE / DELETE

```php
DB::query(
    'INSERT INTO audit (action, payload, created_at) VALUES (:action, :payload, :ts)',
    [
        ':action'  => 'login',
        ':payload' => json_encode(['ip' => $ip]),
        ':ts'      => date('Y-m-d H:i:s'),
    ]
);

$affected = DB::affectedRows(); // works for write operations
```

## Reading the inserted id

```php
DB::query('INSERT INTO items (name) VALUES (:n)', [':n' => 'first']);
$id = DB::insertId(); // string|false
```

`PDO::lastInsertId()` returns a string on most drivers — cast yourself when you need an `int`.

## Hydrating into a class

```php
$users = DB::query('SELECT * FROM users WHERE active = 1')
    ->asClass(App\Entity\UserEntity::class)
    ->rows();
```

PDO's `FETCH_CLASS` writes properties directly on the object — accessor / mutator methods on the class **do not** run on hydration. See [Entities](Entities.md) for the implications.

## Multi-row writes

`DB::query()` runs **one statement at a time**. For batches:

- Use `createBatch()` / `updateBatch()` when the builder fits — those compile to a single multi-row statement.
- Otherwise wrap a loop in a transaction so the round-trips are amortised and you get atomicity for free:

```php
DB::transaction(function ($db) use ($rows) {
    foreach ($rows as $row) {
        $db->query(
            'REPLACE INTO items (id, name) VALUES (:id, :name)',
            [':id' => $row['id'], ':name' => $row['name']]
        );
    }
});
```

## DDL — schema changes

```php
DB::query('CREATE TABLE IF NOT EXISTS audit (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(64)     NOT NULL,
    payload    JSON,
    created_at DATETIME        NOT NULL
)');
```

`query()` is the right tool here — there is no builder for DDL.

## Things to watch out for

### 1. Always bind values, never concatenate

```php
// ❌ wrong — SQL injection
DB::query("SELECT * FROM users WHERE email = '$email'");

// ✅ correct
DB::query('SELECT * FROM users WHERE email = :email', [':email' => $email]);
```

### 2. Identifiers cannot be parameter-bound

Table names, column names and reserved keywords cannot ride in `:placeholders`. If the identifier comes from user input, validate it against an allow-list first — never interpolate it directly.

```php
// only allow these column names from a query string
$allowed = ['name', 'email', 'created_at'];
if (!in_array($sortColumn, $allowed, true)) {
    throw new InvalidArgumentException('Unknown sort column');
}

DB::query("SELECT * FROM users ORDER BY {$sortColumn}");
```

### 3. The builder is reset on every CRUD call

`query()` does **not** reset the builder — it bypasses it entirely. If you mix builder calls with `query()` inside the same chain, the next `read()` will still see the builder state you left behind. Call `DB::withFreshBuilder()` between them or stick with one style.

### 4. Driver differences

| Feature | MySQL | PostgreSQL | SQLite |
|---|---|---|---|
| `REPLACE INTO` | ✅ | ❌ (use `ON CONFLICT`) | ✅ |
| `LIMIT … OFFSET …` | ✅ | ✅ | ✅ |
| `LIMIT n, m` (offset, limit) | ✅ | ❌ | ✅ |
| `JSON_EXTRACT` | ✅ | ❌ (use `->>`) | ✅ |
| `RETURNING` | ✅ (8.0+) | ✅ | ✅ |

Raw SQL bakes the dialect into your code. When you need to stay portable, prefer the builder.

## Next

- [Query Builder](Query-Builder.md) — when the builder fits.
- [CRUD Operations](CRUD-Operations.md) — `createBatch` / `updateBatch` are usually a better answer than a loop of `query()` calls.
- [Recipe — Upsert / REPLACE INTO](Recipe-Upsert.md) — patterns for each driver.
