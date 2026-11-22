# Models

A `Model` binds one database table and exposes the same CRUD surface as `DB::`, with a few model-only conveniences layered on top:

- Configurable primary key column.
- Auto-derived schema name (`PostComment` → `post_comment`) when you don't set one.
- [Soft deletes](Soft-Deletes.md) (`deleted_at` column).
- [Timestamps](Timestamps.md) — auto-managed `created_at` / `updated_at`.
- Per-operation access gates (`$readable`, `$writable`, `$updatable`, `$deletable`).
- An [Entity](Entities.md) class used to hydrate `read()` results.

## A minimal model

```php
namespace App\Model;

use InitPHP\Database\Model;

final class Posts extends Model
{
    protected string $schema   = 'posts';
    protected string $schemaId = 'id';
}
```

The base constructor binds the model to the shared `DB::getDatabase()` instance — so as long as `DB::createImmutable([...])` ran during bootstrap, no extra wiring is needed:

```php
$posts = new App\Model\Posts();
$posts->create(['title' => 'Hello', 'content' => 'World']);
```

## Auto-derived schema name

If you omit `$schema`, the constructor derives it from the class' short name using `Helper::camelCaseToSnakeCase()`:

| Class | Derived schema |
|---|---|
| `Posts` | `posts` |
| `BlogPost` | `blog_post` |
| `UserProfile` | `user_profile` |
| `APIToken` | `api_token` |

```php
final class BlogPost extends \InitPHP\Database\Model {}
(new BlogPost())->getSchema(); // 'blog_post'
```

## Full configuration surface

```php
namespace App\Model;

use App\Entity\PostEntity;
use InitPHP\Database\Model;

final class Posts extends Model
{
    // Optional. Defaults to the snake_case form of the class' short name.
    protected string $schema = 'posts';

    // Primary key column. Set to '' to disable the PK lift-out in update().
    protected string $schemaId = 'id';

    // Entity class used by read() to hydrate rows.
    // Defaults to InitPHP\Database\Entity.
    protected string $entity = PostEntity::class;

    // Soft deletes — see Soft-Deletes page.
    protected bool $useSoftDeletes = true;
    protected ?string $deletedField = 'deleted_at';

    // Timestamp columns — see Timestamps page.
    protected ?string $createdField = 'created_at';
    protected ?string $updatedField = 'updated_at';
    protected string $timestampFormat = 'Y-m-d H:i:s';

    // Access gates. Setting any of these to false throws on the matching call.
    protected bool $readable  = true;
    protected bool $writable  = true;
    protected bool $updatable = true;
    protected bool $deletable = true;

    // Use a non-default connection — see Multiple-Connections.
    protected ?array $credentials = null;
}
```

## CRUD on a model

```php
$posts = new App\Model\Posts();

// Insert; $createdField is filled in automatically when set.
$posts->create(['title' => 'Hello', 'content' => 'World']);

// Select; soft-deleted rows are excluded automatically.
$rows = $posts->read(['id', 'title'], ['status' => 1])
              ->asAssoc()
              ->rows();

// Update; if the primary key sits in $set, it is lifted into a WHERE
// clause and removed from the SET map.
$posts->update(['id' => 13, 'title' => 'New title']);

// Or pass conditions explicitly:
$posts->update(['title' => 'New title'], ['id' => 13]);

// Delete; soft-deletes when $useSoftDeletes = true, hard-deletes otherwise.
$posts->delete(['id' => 13]);
$posts->delete(['id' => 13], purge: true); // force a real DELETE
```

## Fluent chains work too

Every method that does not live on `Model` itself falls through to the underlying `Database`, and chainable builder calls re-wrap to return the **Model** so the chain stays type-consistent:

```php
$rows = $posts
    ->select('id', 'title', 'created_at')
    ->where('status', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->read()
    ->asAssoc()
    ->rows();
```

## Read with an entity class

When `$entity` points at a subclass of `InitPHP\Database\Entity`, `read()` hydrates rows into instances of that class:

```php
foreach ($posts->read() as $post) {
    echo $post->title, PHP_EOL;
}
```

`->read()` returns a `DataMapperInterface`, which is iterable — each iteration gives back one entity. For the full attribute / accessor / mutator story, see [Entities](Entities.md).

## `save()` — insert-or-update via an entity

```php
$entity = new PostEntity(['title' => 'New post']);
$posts->save($entity); // no id ⇒ create()

$entity = new PostEntity(['id' => 13, 'title' => 'Edited']);
$posts->save($entity); // id present ⇒ update()
```

`save()` checks the entity for a value at `$schemaId`: present (non-null, non-empty-string) ⇒ update; absent ⇒ create. Useful in a controller that handles both flows with the same method body.

## Access gates

Each gate is a boolean on the subclass; flipping any to `false` makes the matching call throw:

```php
protected bool $readable  = true;
protected bool $writable  = true;
protected bool $updatable = true;
protected bool $deletable = true;
```

| Gate | Disabled call throws |
|---|---|
| `$readable = false` | `read()` → `InitORM\ORM\Exceptions\ReadableException` |
| `$writable = false` | `create()` / `createBatch()` → `WritableException` |
| `$updatable = false` | `update()` / `updateBatch()` → `UpdatableException` |
| `$deletable = false` | `delete()` → `DeletableException` |

Handy for read-only views (`$writable = $updatable = $deletable = false`) or audit-log tables that must never be updated after insert (`$updatable = $deletable = false`).

## Accessing the underlying Database

```php
$db = $posts->getDatabase();        // InitORM\Database\Interfaces\DatabaseInterface
$db->transaction(function () use ($posts) {
    $posts->create([...]);
});
```

Useful for transaction boundaries and for sharing the same connection across two models.

## What model state survives between calls

Each Model **instance** is bound to one Database. Builder state on that Database resets after every `read()` / `create()` / `update()` / `delete()` — so two model instances sharing the same underlying Database will not bleed into each other's queries, as long as one method runs at a time.

If you really need parallel chains, request a fresh builder:

```php
$model->getDatabase()->withFreshBuilder();
```

## Where to go next

- [Entities](Entities.md) — the row container that pairs with `$entity`.
- [Soft Deletes](Soft-Deletes.md) — the full soft-delete protocol.
- [Timestamps](Timestamps.md) — `createdField` / `updatedField` mechanics.
- [Multiple Connections](Multiple-Connections.md) — `protected ?array $credentials`.
- [Recipe — Repository Pattern](Recipe-Repository-Pattern.md) — when active record is the wrong shape.
