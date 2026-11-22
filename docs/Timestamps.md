# Timestamps

`Model` can fill `created_at` / `updated_at` for you on every insert / update. The mechanism is opt-in per model — set the column names, optionally override the format, and the rest is automatic.

## Enable on a model

```php
namespace App\Model;

use InitPHP\Database\Model;

final class Posts extends Model
{
    protected ?string $createdField = 'created_at';
    protected ?string $updatedField = 'updated_at';

    protected string $timestampFormat = 'Y-m-d H:i:s';
}
```

The columns must exist on the table:

```sql
ALTER TABLE posts
    ADD COLUMN created_at DATETIME NULL,
    ADD COLUMN updated_at DATETIME NULL;
```

`NULL`-able is fine; the model writes the value on insert / update either way.

## What gets filled, when

| Call | Effect on `createdField` | Effect on `updatedField` |
|---|---|---|
| `create($set)` | set to `date($timestampFormat)` | — |
| `createBatch($set)` | set on every row (one `date()` call, shared value) | — |
| `update($set)` | — | set to `date($timestampFormat)` |
| `updateBatch($set)` | — | set on every row (shared value) |
| `delete()` | — | — (use [Soft Deletes](Soft-Deletes.md) for that) |

If the column is already present in `$set`, the auto-fill **overwrites** it. The intent is "the package owns this column" — if you want to opt out for one call, set the field to `null` (or `''`) on the model before that call, or use the underlying `Database` directly:

```php
$posts->getDatabase()->update('posts', ['title' => 'X'], ['id' => 13]);
// no updated_at touched — chain is on Database, not Model
```

## Disabling individually

Set either field to `null` to disable that side. Both fields default to `null` on the base class — you have to opt in.

```php
protected ?string $createdField = 'created_at';  // enabled
protected ?string $updatedField = null;           // disabled
```

Now `create()` writes `created_at`; `update()` is unchanged.

## Custom format

`$timestampFormat` is fed to `date()`, so anything `date()` accepts works:

```php
// ISO-8601 (good for APIs)
protected string $timestampFormat = 'c';

// Unix timestamp (good for integer columns)
protected string $timestampFormat = 'U';

// MySQL DATETIME (the default — works with DATETIME / TIMESTAMP / VARCHAR(19))
protected string $timestampFormat = 'Y-m-d H:i:s';
```

If you switch to `'U'`, change the column type to `INT UNSIGNED` / `BIGINT UNSIGNED`.

## Time zones

`date()` reads the current process default time zone (`date_default_timezone_get()`). Set it once during bootstrap:

```php
date_default_timezone_set('UTC');
```

Storing in UTC is almost always the right call — convert to the user's zone on display.

## Worked example

```php
date_default_timezone_set('UTC');

$posts = new App\Model\Posts();

$posts->create(['title' => 'Hello']);
// INSERT INTO `posts` (`title`, `created_at`) VALUES ('Hello', '2026-05-25 10:00:00')

$posts->update(['id' => 1, 'title' => 'Edited']);
// UPDATE `posts` SET `title` = 'Edited', `updated_at` = '2026-05-25 10:05:00'
// WHERE `id` = 1

$posts->createBatch([
    ['title' => 'A'],
    ['title' => 'B'],
]);
// INSERT INTO `posts` (`title`, `created_at`) VALUES
//     ('A', '2026-05-25 10:06:00'),
//     ('B', '2026-05-25 10:06:00');
// Note: same timestamp across the batch — one date() call.
```

## Interaction with soft deletes

When [Soft Deletes](Soft-Deletes.md) are also enabled, the `deletedField` shares the same format string. All three columns (`createdField`, `updatedField`, `deletedField`) live on the same clock.

## Database-side defaults vs application-side fills

You could push timestamp management into the schema:

```sql
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

This works too, and the package will not fight you — disable `$createdField` / `$updatedField` and let the schema do it.

**Tradeoffs:**

| Approach | Pros | Cons |
|---|---|---|
| Application-side (this page) | Consistent across drivers; you can mock time in tests | The PHP process clock has to be accurate |
| Database-side defaults | DB clock is authoritative; works even for direct SQL imports | Subtle differences between MySQL `ON UPDATE` and PostgreSQL trigger syntax |

Pick one and stay consistent within a model.

## Next

- [Models](Models.md) — the broader configuration surface.
- [Soft Deletes](Soft-Deletes.md) — `deletedField` shares the format string.
