# Query Builder

The query builder is **fluent and forgiving**: every chainable method returns the surrounding `Database` (or `Model`) so calls compose naturally. Methods that are not declared on the facade fall through to the underlying [InitORM query builder](https://github.com/InitORM/QueryBuilder) via `__call`, so the surface is wide. This page covers the slice you will reach for daily.

> The examples below assume `DB::createImmutable([...])` ran during bootstrap and `use InitPHP\Database\DB;` is at the top of the file.

## SELECT

```php
$res = DB::select('id', 'title', 'author_id')
    ->from('posts')
    ->where('status', '=', 1)
    ->read();
```

`select()` accepts:

- plain column names: `'id'`
- aliased strings: `'COUNT(*) AS total'`
- `DB::raw($sql)` fragments for SQL that should be emitted verbatim

For an explicit alias use `selectAs('column', 'alias')`.

### Aggregates and function wrappers

| Helper | SQL emitted |
|---|---|
| `selectCount($col, $alias = null)` | `COUNT($col) AS $alias` |
| `selectCountDistinct($col, $alias)` | `COUNT(DISTINCT $col) AS $alias` |
| `selectSum`, `selectAvg`, `selectMax`, `selectMin` | corresponding aggregate |
| `selectUpper`, `selectLower`, `selectLength` | string functions |
| `selectMid`, `selectLeft`, `selectRight` | substring helpers |
| `selectConcat([$a, $b], $alias)` | `CONCAT(...)` (driver-specific) |
| `selectCoalesce($col, $default, $alias)` | `COALESCE($col, $default)` |
| `selectDistinct($col, $alias)` | `DISTINCT $col` |

```php
DB::selectCount('id', 'total')
  ->selectMax('score', 'highest')
  ->from('players')
  ->read()
  ->asAssoc()
  ->row();
// ['total' => 1024, 'highest' => 99]
```

## FROM / TABLE

```php
DB::select('*')->from('posts');                   // FROM posts
DB::select('*')->from('posts', 'p');              // FROM posts AS p
DB::select('*')->from('posts')->addFrom('users'); // FROM posts, users
```

`table('posts')` is an alias of `from('posts')` and is the conventional name when starting a chain off a `Model`.

## WHERE

```php
DB::select('*')
    ->from('posts')
    ->where('status', '=', 1)
    ->andWhere('author_id', 5)
    ->orWhere('pinned', true);
```

The operator defaults to `=` — these two are identical:

```php
DB::select('*')->from('users')->where('email', '=', $email);
DB::select('*')->from('users')->where('email', $email);
```

### NULL / NOT NULL

```php
DB::select('*')->from('users')->whereIsNull('deleted_at');
DB::select('*')->from('users')->whereIsNotNull('email_verified_at');
```

### IN / NOT IN

```php
DB::select('*')->from('posts')->whereIn('id', [1, 2, 3]);
DB::select('*')->from('posts')->whereNotIn('status', ['draft', 'spam']);
```

### BETWEEN

```php
DB::select('*')
    ->from('orders')
    ->between('created_at', '2026-01-01', '2026-12-31');
```

Siblings: `notBetween()`, `andBetween()`, `orBetween()`, `andNotBetween()`, `orNotBetween()`.

### LIKE

```php
DB::select('*')->from('users')->like('email', '%@example.com');
DB::select('*')->from('users')->like('name', 'ada', 'after');   // 'ada%'
DB::select('*')->from('users')->like('name', 'ace', 'before');  // '%ace'
```

The third argument selects the wildcard placement:

| Value | Wildcard pattern |
|---|---|
| `'both'` *(default)* | `%value%` |
| `'before'` | `%value` |
| `'after'` | `value%` |

`startLike()` / `endLike()` are friendlier wrappers for `'after'` / `'before'`.

### REGEXP / SOUNDEX

```php
DB::select('*')->from('users')->regexp('email', '@(test|dev)\.');
DB::select('*')->from('users')->soundex('name', 'Smith');
```

### Grouped predicates

```php
DB::select('*')
    ->from('users')
    ->where('active', 1)
    ->group(function ($builder) {
        $builder->where('role', 'admin')
                ->orWhere('role', 'editor');
    });
// WHERE active = 1 AND (role = 'admin' OR role = 'editor')
```

> **Known limitation in `initorm/query-builder` 2.x:** parameters bound *inside* the `group()` callback do not propagate back to the outer builder's parameter bag, so a search that needs `:placeholder` binding inside the group will produce SQL with unbound parameters. Use a raw fragment + explicit `setParameter()` until upstream fixes this — the [DataTables helper](DataTables-Introduction.md) does exactly that.

## JOIN

```php
DB::select('posts.id', 'posts.title', 'users.name AS author')
    ->from('posts')
    ->leftJoin('users', 'users.id = posts.author_id')
    ->where('posts.status', 1)
    ->read();
```

Variants: `join()` (defaults to `INNER`), `innerJoin()`, `leftJoin()`, `rightJoin()`, `leftOuterJoin()`, `rightOuterJoin()`, `naturalJoin()`, `selfJoin()`. The ON clause may be a string, a `DB::raw(...)` fragment, or a closure that uses `on()`:

```php
DB::leftJoin('users', function ($q) {
    $q->on('users.id', '=', DB::raw('posts.author_id'))
      ->on('users.deleted_at', null, 'IS NULL');
});
```

## GROUP BY / HAVING

```php
DB::select('author_id', DB::raw('COUNT(*) AS post_count'))
    ->from('posts')
    ->groupBy('author_id')
    ->having('post_count', '>', 5)
    ->orderBy('post_count', 'DESC')
    ->read();
```

`groupBy()` accepts one or many columns, strings or `RawQuery` fragments. `having()` mirrors `where()` — same operator / value / logical signature.

## ORDER BY / LIMIT / OFFSET

```php
DB::select('*')
    ->from('posts')
    ->orderBy('created_at', 'DESC')
    ->orderBy('id', 'DESC')      // tie-breaker
    ->offset(20)
    ->limit(10)
    ->read();
```

`offset()` and `limit()` are integer-typed. If the values come from user input (query strings, request bodies), coerce them yourself — the builder enforces `int` and PHP 8.1+ will raise `TypeError` on a string.

## Raw SQL fragments

```php
$res = DB::select(DB::raw("CONCAT(name, ' ', surname) AS full_name"))
    ->from('users')
    ->where(DB::raw("status IN (1, 2) AND deleted_at IS NULL"))
    ->limit(5)
    ->read();
```

`DB::raw($sql)` wraps a string in a `RawQuery` value object that the compiler emits verbatim. **Use it only for SQL that is fully under your control.** User input must always flow through parameter binding — `where('col', $value)` or `setParameter(':name', $value)` — never through `raw()`.

## Sub-queries

```php
$res = DB::select('*')
    ->from('users')
    ->whereIn('id', DB::subQuery(function ($builder) {
        $builder->select('user_id')
                ->from('orders')
                ->where('total', '>', 1000);
    }))
    ->read();
```

`subQuery($closure, $alias = null, $isIntervalQuery = true)` returns a `RawQuery` you can drop anywhere a value is accepted.

## Parameter binding manually

When you write a raw fragment that contains placeholders:

```php
DB::where(DB::raw('LOWER(email) = :email'))
    ->setParameter(':email', strtolower($input))
    ->select('*')
    ->from('users')
    ->read();
```

`setParameter()` registers a single key/value pair on the builder's parameter bag; `setParameters($map)` does it for a whole map at once.

## Resetting between queries

`read()`, `create()`, `update()` and `delete()` reset the builder structure for you. If you build a chain and decide not to execute it, call `DB::withFreshBuilder()` for a clean sibling (or `DB::builder()` — the deprecated alias).

## Identifier escaping

Every column and table identifier is escaped driver-specifically — backticks on MySQL/SQLite, double quotes on PostgreSQL. Reserved keywords (`order`, `select`, `from`, `where`, …) used as column names work without extra ceremony:

```php
DB::select('id', 'order', 'title')
  ->from('posts')
  ->where('order', '>', 5)
  ->read();
// SQL emitted: SELECT `id`, `order`, `title` FROM `posts` WHERE `order` > 5
```

See [Reserved Keywords](Reserved-Keywords.md) for details and the regression tests that lock this behaviour in.

## Next

- [CRUD Operations](CRUD-Operations.md) — `create`, `update`, `delete` and the batch family.
- [Raw Queries](Raw-Queries.md) — bypass the builder entirely.
- [Query Logging](Query-Logging.md) — inspect the actual SQL emitted.
