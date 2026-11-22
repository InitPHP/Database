# Soft Deletes

Most applications want a "trash" / "archive" semantic: deleted rows stay in the table but stop showing up in normal queries. The package builds this in at the `Model` layer — enable the feature, point it at a nullable column, and `delete()` / `read()` / `update()` all rewrite themselves automatically.

## Enable on a model

```php
namespace App\Model;

use InitPHP\Database\Model;

final class Posts extends Model
{
    protected string $schema   = 'posts';
    protected string $schemaId = 'id';

    protected bool $useSoftDeletes = true;
    protected ?string $deletedField = 'deleted_at';
}
```

The matching schema needs a nullable column to mark the deletion timestamp:

```sql
ALTER TABLE posts ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;
```

> If you turn `$useSoftDeletes` on but forget `$deletedField`, the constructor throws `InitORM\ORM\Exceptions\ModelException`. There is no silent fallback.

## What changes after enabling

| Call | Behaviour |
|---|---|
| `delete()` | Issues `UPDATE … SET deleted_at = <now>` instead of `DELETE`. |
| `delete([...], purge: true)` | Bypasses the soft-delete path and issues a real `DELETE`. |
| `read()` | Adds `WHERE deleted_at IS NULL` automatically. |
| `update()` / `updateBatch()` | Adds `WHERE deleted_at IS NULL` so soft-deleted rows are skipped. |
| `onlyDeleted()` | One-shot flip — the **next** `read()` sees soft-deleted rows only (`deleted_at IS NOT NULL`). |
| `ignoreDeleted()` | Appends `deleted_at IS NULL` to the current pending chain. Useful when you composed your own where clause and want the soft-delete predicate added too. |

## A worked example

```php
$posts = new App\Model\Posts();

// Soft-delete a row.
$posts->delete(['id' => 13]);
//   UPDATE `posts` SET `deleted_at` = '2026-05-25 14:30:00' WHERE `id` = 13

// Subsequent reads exclude it automatically.
$rows = $posts->read()->asAssoc()->rows();
//   SELECT * FROM `posts` WHERE `deleted_at` IS NULL

// "Trash" view — list only soft-deleted rows.
$trash = $posts->onlyDeleted()->read()->asAssoc()->rows();
//   SELECT * FROM `posts` WHERE `deleted_at` IS NOT NULL

// Restore (just NULL the column back out).
$posts->update(['id' => 13, 'deleted_at' => null]);

// Permanent delete — bypass soft-deletes.
$posts->delete(['id' => 13], purge: true);
//   DELETE FROM `posts` WHERE `id` = 13
```

## `onlyDeleted()` is one-shot

The flag is consumed by the next `read()` and resets immediately. If you want the same flag on a follow-up call you have to set it again:

```php
$posts->onlyDeleted()->read()->rows();
$posts->read()->rows(); // back to active rows
$posts->onlyDeleted()->read()->rows(); // explicit again
```

This is intentional — it prevents a "trash" call from accidentally leaking onto the next innocent read in the same request.

## Composing with custom where chains

```php
// Soft-delete is added automatically alongside your where clauses:
$posts->where('author_id', 5)->read()->rows();
// SELECT * FROM `posts` WHERE `deleted_at` IS NULL AND `author_id` = 5
```

If you want to bypass the automatic filter for one query (e.g. include deleted rows in a report), drop down to the raw `Database`:

```php
$posts->getDatabase()
      ->select('*')
      ->from('posts')
      ->where('author_id', 5)
      ->read();
// SELECT * FROM `posts` WHERE `author_id` = 5
// (no soft-delete filter — the chain is on Database, not Model)
```

## Counting active vs deleted

```php
// Active count
$active = (int) ($posts->selectCount('id', 'total')->read()->asAssoc()->row()['total'] ?? 0);

// Deleted count
$deleted = (int) ($posts->onlyDeleted()->selectCount('id', 'total')->read()->asAssoc()->row()['total'] ?? 0);

// Grand total (bypass the model and hit the underlying database)
$total = (int) ($posts->getDatabase()
                      ->selectCount('id', 'total')
                      ->from('posts')
                      ->read()
                      ->asAssoc()
                      ->row()['total'] ?? 0);
```

## Cascade considerations

Soft-deletes are **not transitive**. If `posts` references `comments` and you soft-delete a post, the comments stay visible until you delete them yourself. Two common patterns:

### 1. Delete cascade inside a transaction

```php
DB::transaction(function () use ($posts, $comments, $postId) {
    $comments->delete(['post_id' => $postId]);
    $posts->delete(['id' => $postId]);
});
```

### 2. JOIN filter on read

```php
$visibleComments = $comments
    ->getDatabase()
    ->select('comments.*')
    ->from('comments')
    ->innerJoin('posts', 'posts.id = comments.post_id')
    ->where('posts.deleted_at', null, 'IS NULL')
    ->where('comments.deleted_at', null, 'IS NULL')
    ->read()
    ->rows();
```

## Timestamp format

The column is set to `date($timestampFormat)` at delete time — same format string as the timestamp columns ([Timestamps](Timestamps.md)). Default is `'Y-m-d H:i:s'`.

```php
protected string $timestampFormat = 'Y-m-d H:i:s'; // change once, applies to created/updated/deleted
```

## Next

- [Models](Models.md) — the broader configuration surface.
- [Timestamps](Timestamps.md) — `createdField` / `updatedField` mechanics.
- [Recipe — Audit Log](Recipe-Audit-Log.md) — when "soft-deleted" is not enough and you need a full history.
