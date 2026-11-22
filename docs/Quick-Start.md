# Quick Start

A ten-minute tour of every layer the package ships, with copy-pasteable code.

## 1. Bootstrap the shared connection

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Database\DB;

DB::createImmutable([
    'dsn'      => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
]);
```

`createImmutable()` may be called **once per process**. A second call throws `InitORM\Database\Exceptions\DatabaseException` — if you truly need to swap the shared connection (test fixtures, multi-tenant routing), use `DB::replaceImmutable()`. See [Multiple Connections](Multiple-Connections.md).

## 2. SELECT

```php
$rows = DB::select('id', 'title', 'author_id')
    ->from('posts')
    ->where('status', '=', 1)
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->read()
    ->asAssoc()
    ->rows();
```

The fluent chain returns the shared `Database` until you call `read()`, which returns a `DataMapperInterface`. From there:

| Method | Effect |
|---|---|
| `asAssoc()` | Fetch as associative arrays (the common case). |
| `asObject()` | Fetch as `stdClass` objects. |
| `asClass(MyEntity::class)` | Hydrate each row into instances of a class. |
| `row()` | Next row, or `null`. |
| `rows()` | All remaining rows as an array. |

See [Query Builder](Query-Builder.md) for the full surface and [CRUD Operations](CRUD-Operations.md) for write paths.

## 3. INSERT

```php
DB::create('posts', [
    'title'   => 'Hello world',
    'content' => 'First post.',
]);

$id = DB::insertId(); // PDO::lastInsertId
```

## 4. UPDATE

```php
DB::where('id', 13)->update('posts', [
    'title'   => 'New title',
    'content' => 'New body',
]);
```

## 5. DELETE

```php
DB::where('id', 13)->delete('posts');
```

## 6. Raw SQL when the builder gets in the way

```php
$res = DB::query(
    'SELECT id, title FROM posts WHERE user_id = :uid AND status > :s',
    [':uid' => 5, ':s' => 0]
);

foreach ($res->asAssoc()->rows() as $row) {
    echo $row['title'], PHP_EOL;
}
```

See [Raw Queries](Raw-Queries.md).

## 7. A model in 4 lines

```php
namespace App\Model;

final class Posts extends \InitPHP\Database\Model
{
    protected string $schema   = 'posts';
    protected string $schemaId = 'id';
}
```

Now use it anywhere:

```php
$posts = new App\Model\Posts();

$posts->create(['title' => 'Hello', 'content' => 'World']);

$rows = $posts->where('status', 1)
              ->orderBy('id', 'DESC')
              ->limit(20)
              ->read()
              ->rows();
```

See [Models](Models.md) for soft deletes, timestamps and access gates.

## 8. Transactions

```php
DB::transaction(function ($db) {
    $db->create('orders',         ['user_id' => 5, 'total' => 199.0]);
    $db->update('users.balances', ['amount' => DB::raw('amount - 199')], ['user_id' => 5]);
});
```

Atomic by default. Add `$attempt` to retry on deadlocks and `$testMode = true` to always roll back (useful in tests). See [Transactions](Transactions.md).

## 9. Server-side DataTables.js

```php
use InitPHP\Database\Utils\Datatables\Datatables;

header('Content-Type: application/json');

echo (new Datatables(DB::getDatabase()))
        ->from('users')
        ->setColumns('id', 'name', 'email', 'created_at');
```

That single chain handles the DataTables request payload (`draw`, `start`, `length`, `search`, `order`), runs the unfiltered + filtered counts, applies paging + ordering, and emits the JSON envelope DataTables expects. See [DataTables Introduction](DataTables-Introduction.md).

## 10. Inspect what actually ran

```php
DB::enableQueryLog();

DB::table('users')->where('email', 'ada@example.com')->read();

print_r(DB::getQueryLogs());
// [
//   ['query' => 'SELECT * FROM `users` WHERE `email` = :email',
//    'args'  => [':email' => 'ada@example.com'],
//    'timer' => 0.000312],
// ]
```

See [Query Logging](Query-Logging.md).

## Next

- Customising the connection: [Configuration](Configuration.md).
- Building richer queries: [Query Builder](Query-Builder.md).
- Wiring an ORM layer: [Models](Models.md) and [Entities](Entities.md).
- Concrete patterns: [Recipes](Recipes.md).
