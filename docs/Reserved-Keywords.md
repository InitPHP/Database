# Reserved Keywords

A column named after a SQL reserved keyword — `order`, `select`, `from`, `where`, `group`, `desc`, `key` — used to be a problem in earlier versions of this package. As of 5.0 it **is not**. The underlying [InitORM QueryBuilder](https://github.com/InitORM/QueryBuilder) escapes every identifier driver-specifically, and the package has regression tests in place to lock this behaviour in.

## The short version

Just use the column name. The compiler will do the right thing.

```php
DB::select('id', 'order', 'title')
  ->from('posts')
  ->where('order', '>', 5)
  ->orderBy('order', 'DESC')
  ->read();
```

The SQL that actually goes to the database:

```sql
SELECT `id`, `order`, `title`
FROM `posts`
WHERE `order` > 5
ORDER BY `order` DESC
```

`order` is wrapped in backticks (on MySQL / SQLite) or double quotes (on PostgreSQL), and the statement runs.

## Per-driver escape character

| Driver | Identifier delimiter | Example |
|---|---|---|
| MySQL / MariaDB | backtick `` ` `` | `` `order` `` |
| SQLite | backtick `` ` `` | `` `order` `` |
| PostgreSQL | double quote `"` | `"order"` |
| Generic | backtick `` ` `` | `` `order` `` |

The package picks the right one based on the `driver` field of your connection (see [Configuration](Configuration.md)).

## What's covered

All four operations escape identifiers consistently:

```php
// CREATE
DB::create('posts', ['order' => 1, 'title' => 'First']);
// INSERT INTO `posts` (`order`, `title`) VALUES (1, 'First')

// READ
DB::select('id', 'order')->from('posts')->where('order', '>', 0)->read();
// SELECT `id`, `order` FROM `posts` WHERE `order` > 0

// UPDATE
DB::where('id', 1)->update('posts', ['order' => 10]);
// UPDATE `posts` SET `order` = 10 WHERE `id` = 1

// DELETE
DB::where('order', 0)->delete('posts');
// DELETE FROM `posts` WHERE `order` = 0
```

Joins, group-by, having, batch operations — all of them route through the same identifier-escaping path.

## Things that are NOT auto-escaped

### 1. `DB::raw(...)` fragments

A `RawQuery` is emitted **verbatim**. If you wrap a column name in `raw()`, you have to quote it yourself:

```php
// ❌ DB::raw drops the identifier on the floor — it's emitted literally
DB::select(DB::raw('order'))->from('posts')->read();
// SELECT order FROM `posts`  -- syntax error on MySQL

// ✅ quote it manually inside the raw fragment
DB::select(DB::raw('`order`'))->from('posts')->read();
```

The right answer is usually "do not use `raw()` for plain column names". Use it for expressions:

```php
DB::select(DB::raw('COUNT(`order`) AS placed'))->from('orders')->read();
```

### 2. Raw SQL through `DB::query()`

`query()` bypasses the builder entirely:

```php
DB::query('SELECT order FROM posts'); // syntax error
DB::query('SELECT `order` FROM `posts`'); // ok
```

If you are writing raw SQL, you own the escaping.

### 3. Identifiers cannot be parameter-bound

This is a PDO / SQL limitation, not a package decision. Placeholders (`:name`) only bind **values**. To make an identifier dynamic from user input, validate it against an allow-list first:

```php
$allowed = ['id', 'name', 'order', 'created_at'];
if (!in_array($sortColumn, $allowed, true)) {
    throw new InvalidArgumentException('Invalid sort column');
}

DB::orderBy($sortColumn, 'ASC')->select('*')->from('posts')->read();
```

## Regression test

The package ships `tests/ReservedKeywordRegressionTest.php` — five tests that exercise this behaviour against an in-memory SQLite. One of them asserts the literal SQL output to lock the escaping in place:

```php
public function testCompiledSqlActuallyQuotesTheIdentifier(): void
{
    $this->db->enableQueryLog();
    $this->db->where('id', 1)->update('posts', ['order' => 5]);

    $logs = $this->db->getQueryLogs();
    self::assertStringContainsString(
        '`order`',
        $logs[0]['query'],
        'Reserved identifiers must be emitted in their quoted form.'
    );
}
```

If a future upstream release ever stops escaping identifiers, that assertion fires on the first CI run.

## A worked example end-to-end

Schema with reserved-word columns:

```sql
CREATE TABLE menu (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    "order" INTEGER NOT NULL,
    "group" VARCHAR(64),
    "key"   VARCHAR(64),
    label   TEXT
);
```

Code:

```php
DB::create('menu', [
    'order' => 1,
    'group' => 'main',
    'key'   => 'home',
    'label' => 'Home',
]);

$rows = DB::select('id', 'label', 'order', 'group')
    ->from('menu')
    ->where('group', 'main')
    ->orderBy('order', 'ASC')
    ->read()
    ->asAssoc()
    ->rows();
```

Both statements compile and run on MySQL, PostgreSQL, and SQLite without changes.

## History — why this used to be an issue

In `initphp/database` 2.x (before the 3.0 rewrite onto InitORM), the package emitted identifiers bare. A column named `order` produced SQL like `SET order = 1`, which MySQL rejects with `error 1064`. The 3.0 release migrated the package to InitORM, which already escaped identifiers correctly, so the bug disappeared as a side-effect. The 5.0 release added the regression tests to keep it that way.

If you see the v2 issue described on the issue tracker, it has been closed — that behaviour does not exist on supported versions.

## Next

- [Query Builder](Query-Builder.md) — the broader API.
- [Raw Queries](Raw-Queries.md) — when escaping is your responsibility.
- [Migration Guide](Migration-Guide.md) — what else changed in 5.0.
