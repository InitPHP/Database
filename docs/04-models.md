# 04 — Models

A `Model` is a class that binds one database table and exposes the same CRUD surface as `DB::`, with a few model-only conveniences layered on top:

- A configurable primary key column.
- Auto-derived schema name (`PostComment` → `post_comment`) when you don't set one.
- Soft deletes (`deleted_at` column).
- Auto-managed `created_at` / `updated_at` columns.
- Per-operation access gates (`$readable`, `$writable`, `$updatable`, `$deletable`).
- An `Entity` class used to hydrate `read()` results.

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

The base class' constructor binds the model to the shared `DB::getDatabase()` instance — so as long as you ran `DB::createImmutable([...])` during bootstrap, the model needs no extra wiring.

```php
$posts = new App\Model\Posts();
$posts->create(['title' => 'Hello', 'content' => 'World']);
```

## What you can configure

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

    // Entity class used by read() to hydrate rows. Defaults to InitPHP\Database\Entity.
    protected string $entity = PostEntity::class;

    // Soft deletes — see below.
    protected bool $useSoftDeletes = true;
    protected ?string $deletedField = 'deleted_at';

    // Timestamp columns — auto-filled with date($timestampFormat).
    protected ?string $createdField = 'created_at';
    protected ?string $updatedField = 'updated_at';
    protected string $timestampFormat = 'Y-m-d H:i:s';

    // Access gates. Setting any of these to false throws on the matching call.
    protected bool $readable  = true;
    protected bool $writable  = true;
    protected bool $updatable = true;
    protected bool $deletable = true;

    // Use a non-default connection — see docs/09-multi-connection.md
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

The fluent builder is available too — every unknown method falls through to the underlying `Database`:

```php
$rows = $posts
    ->where('status', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->read()
    ->rows();
```

## Soft deletes

Enable the feature and point it at a nullable column:

```php
protected bool $useSoftDeletes = true;
protected ?string $deletedField = 'deleted_at';
```

After that:

- `delete()` becomes `UPDATE ... SET deleted_at = NOW()` instead of `DELETE`.
- `read()` adds `WHERE deleted_at IS NULL` automatically.
- `update()` likewise scopes to non-deleted rows.
- `onlyDeleted()` flips the filter for the next `read()` only — useful for trash UIs:

  ```php
  $trash = $posts->onlyDeleted()->read()->rows();
  ```
- `ignoreDeleted()` appends `deleted_at IS NULL` to the current chain — call it when you compose your own builder chain and want the soft-delete predicate added.
- Pass `delete([...], purge: true)` to skip the soft-delete machinery and issue a real `DELETE`.

If you turn `$useSoftDeletes` on but forget `$deletedField`, the constructor throws an `InitORM\ORM\Exceptions\ModelException` — there is no silent fallback.

## Timestamp columns

```php
protected ?string $createdField = 'created_at';
protected ?string $updatedField = 'updated_at';
protected string $timestampFormat = 'Y-m-d H:i:s';
```

- `create()` / `createBatch()` set the `createdField` for the row(s) being inserted.
- `update()` / `updateBatch()` set the `updatedField` on the rows being modified.
- Set either field to `null` (or `''`) to disable that side.

Set `$timestampFormat` to anything `date()` accepts — `'U'` for a Unix timestamp, `'c'` for ISO-8601, etc.

## Access gates

```php
protected bool $readable  = true;
protected bool $writable  = true;
protected bool $updatable = true;
protected bool $deletable = true;
```

Flip any of these to `false` and the matching call throws (`ReadableException`, `WritableException`, `UpdatableException`, `DeletableException`). Handy for read-only views or audit-log tables that must never be updated.

## `save()` — insert-or-update via an entity

```php
$entity = new PostEntity(['title' => 'New post']);
$posts->save($entity); // no id ⇒ create()

$entity = new PostEntity(['id' => 13, 'title' => 'Edited']);
$posts->save($entity); // id present ⇒ update()
```

`save()` looks at `$schemaId` on the model and at the entity's attribute bag: present ⇒ update; absent / null / empty string ⇒ create.

## Where to go next

- [05 — Entities](05-entities.md) for the attribute bag, accessors and mutators (and the PHP 8.2+ pitfall).
- [06 — Transactions](06-transactions.md) for retry-aware atomic writes.
- [09 — Multiple connections](09-multi-connection.md) for `protected ?array $credentials`.
