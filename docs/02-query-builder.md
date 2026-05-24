# 02 — Query Builder

The query builder is fluent: every method returns the surrounding `Database` (or `Model`) so chains compose naturally. Calls that you do not see on the `DB` facade fall through to the underlying [InitORM query builder](https://github.com/InitORM/QueryBuilder), so the surface is wide — what follows is the slice you will reach for daily.

All examples below assume `DB::createImmutable([...])` ran during bootstrap and `use InitPHP\Database\DB;` is at the top of the file.

## SELECT

```php
$res = DB::select('id', 'title', 'author_id')
    ->from('posts')
    ->where('status', '=', 1)
    ->read();
```

`select()` accepts plain column names, aliased strings (`'COUNT(*) AS total'` works), or `DB::raw(...)` fragments. Use `selectAs('column', 'alias')` for an explicit alias.

Built-in aggregate / function helpers:

| Helper | Emits |
| --- | --- |
| `selectCount($col, $alias = null)` | `COUNT($col) AS $alias` |
| `selectCountDistinct($col, $alias)` | `COUNT(DISTINCT $col) AS $alias` |
| `selectSum`, `selectAvg`, `selectMax`, `selectMin` | the matching SQL aggregate |
| `selectUpper`, `selectLower`, `selectLength` | string functions |
| `selectConcat([$a, $b, ...], $alias)` | `CONCAT(...)` (driver-specific) |
| `selectCoalesce($col, $default, $alias)` | `COALESCE(...)` |

## FROM

```php
DB::select('*')->from('posts');
DB::select('*')->from('posts', 'p');       // FROM posts AS p
DB::select('*')->from('posts')->addFrom('users', 'u'); // FROM posts, users AS u
```

`table('posts')` is equivalent to `from('posts')` and is the conventional name when you start the chain from `Model::__call`.

## WHERE

```php
DB::select('*')
    ->from('posts')
    ->where('status', '=', 1)
    ->andWhere('author_id', 5)
    ->orWhere('pinned', true);
```

The third argument is the value; the second is the operator. Most callers omit the operator (it defaults to `=`):

```php
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

`notBetween()`, `andBetween()`, `orBetween()` round out the family.

### LIKE

```php
DB::select('*')->from('users')->like('email', '%@example.com');
DB::select('*')->from('users')->like('name', 'ada', 'after');  // 'ada%'
DB::select('*')->from('users')->like('name', 'ace', 'before'); // '%ace'
```

Type values: `'both'` (default), `'before'`, `'after'`. `startLike()` / `endLike()` are friendlier wrappers around the latter two.

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

> Heads-up: as of `initorm/query-builder` 2.x, parameters bound inside the group callback do not propagate back to the outer builder's parameter bag. If a `group()` predicate references user input, prefer a raw fragment with explicit `setParameter()` calls until that is fixed upstream (the Datatables helper does exactly that — see `applySearchFilter()` in `src/Utils/Datatables/Datatables.php`).

## JOIN

```php
DB::select('posts.id', 'posts.title', 'users.name AS author')
    ->from('posts')
    ->leftJoin('users', 'users.id = posts.author_id')
    ->where('posts.status', 1)
    ->read();
```

Variants: `join()` (defaults to `INNER`), `innerJoin()`, `leftJoin()`, `rightJoin()`, `leftOuterJoin()`, `rightOuterJoin()`, `naturalJoin()`, `selfJoin()`. The ON clause may be a string, a `DB::raw(...)` fragment, or a closure that uses `on()` for richer predicates.

## GROUP BY / HAVING

```php
DB::select('author_id', 'COUNT(*) AS post_count')
    ->from('posts')
    ->groupBy('author_id')
    ->having('post_count', '>', 5)
    ->orderBy('post_count', 'DESC')
    ->read();
```

`groupBy()` accepts one or many columns, strings or `RawQuery` fragments.

## ORDER BY / LIMIT / OFFSET

```php
DB::select('*')
    ->from('posts')
    ->orderBy('created_at', 'DESC')
    ->offset(20)
    ->limit(10)
    ->read();
```

`offset()` / `limit()` are integer-typed — pass coerced values when they originate in user input.

## Raw fragments

```php
$res = DB::select(DB::raw("CONCAT(name, ' ', surname) AS full_name"))
    ->from('users')
    ->where(DB::raw("status IN (1, 2) AND deleted_at IS NULL"))
    ->limit(5)
    ->read();
```

`DB::raw($sql)` produces a `RawQuery` value object that the compiler emits verbatim — bypass it only when the SQL fragment is **fully under your control**. User input must flow through real parameters (`where('col', $value)` or `setParameter(':name', $value)`).

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

## Resetting between queries

`read()`, `create()`, `update()` and `delete()` reset the builder structure for you. If you build a chain and decide not to execute it, call `DB::withFreshBuilder()` (or `DB::builder()` — the deprecated alias) for a clean sibling.

## Next up

- [03 — CRUD](03-crud.md) for `create`, `update`, `delete` and the `*Batch` family.
- [07 — Query log](07-query-log.md) to inspect the SQL the builder actually produced.
