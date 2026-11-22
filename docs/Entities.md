# Entities

An entity is a typed-ish row container. Each instance carries:

- An **attribute bag** (`protected array $attributes`) — the actual column → value map.
- A **snapshot** (`protected array $attributesOriginal`) taken at construction time, useful for dirty tracking.

Reading or writing a column through `$entity->column` dispatches through `__get` / `__set`, which gives subclasses a clean place to hang accessor / mutator hooks.

## A minimal entity

```php
namespace App\Entity;

use InitPHP\Database\Entity;

final class PostEntity extends Entity
{
}
```

Wire it into a model:

```php
final class Posts extends \InitPHP\Database\Model
{
    protected string $entity = \App\Entity\PostEntity::class;
}
```

Now `$posts->read()` hydrates rows into instances of your subclass.

## Reading and writing attributes

```php
$post = new PostEntity(['id' => 1, 'title' => 'Hello']);

echo $post->title;        // 'Hello'  — routed through __get
$post->title = 'Edited';  // routed through __set
$post->toArray();         // ['id' => 1, 'title' => 'Edited']
$post->getAttributes();   // same as toArray()
$post->getOriginal();     // ['id' => 1, 'title' => 'Hello']  — snapshot at construct
```

`isset($post->title)` and `unset($post->title)` work too (via `__isset` / `__unset`).

### `syncOriginal()` — refresh the dirty snapshot

```php
$post = new PostEntity(['title' => 'First']);
$post->title = 'Second';
$post->getOriginal()['title']; // 'First' — still the construct-time value
$post->syncOriginal();
$post->getOriginal()['title']; // 'Second' — fresh snapshot
```

Call this after a successful `save()` when you want subsequent dirty-tracking to be relative to the just-persisted state.

## Accessors (read hooks)

Define `get{Column}Attribute($value)` and the entity routes property reads through it:

```php
final class PostEntity extends Entity
{
    public function getTitleAttribute(?string $value): string
    {
        return (string) ($value ?? '(untitled)');
    }
}

$post = new PostEntity(['title' => null]);
echo $post->title; // '(untitled)'
```

The method receives whatever sits in the attribute bag (or `null` when nothing has been stored) and may return anything.

A common pattern — derived columns:

```php
public function getFullNameAttribute(): string
{
    return trim(($this->attributes['first_name'] ?? '') . ' ' . ($this->attributes['last_name'] ?? ''));
}
```

`$post->full_name` then "just works" even though there is no `full_name` column.

## Mutators (write hooks)

Define `set{Column}Attribute($value)` and the entity routes property writes through it. **This is where almost every entity bug in the wild comes from**, so read this section carefully.

### ✅ Correct — write back via `setAttribute()`

```php
final class PostEntity extends Entity
{
    public function setTitleAttribute(string $value): void
    {
        $this->setAttribute('title', mb_strtolower($value));
    }
}

$post = new PostEntity();
$post->title = 'Hello';
echo $post->title; // 'hello'
```

### ❌ Wrong — direct assignment from inside a class method

```php
final class PostEntity extends Entity
{
    public function setTitleAttribute(string $value): void
    {
        $this->title = mb_strtolower($value); // ⚠️ does NOT go through __set
    }
}
```

Inside a class method, PHP resolves `$this->column = $value` **directly on the object**, bypassing `__set`. That means:

1. The transformed value never reaches `$attributes`.
2. PHP creates a dynamic property instead — deprecated since 8.2, fatal in a future PHP release.

**Always** use `$this->setAttribute($name, $value)` inside a mutator.

## Column-name to method-name translation

The translation is `snake_case` → `PascalCase`:

| Column | Accessor | Mutator |
|---|---|---|
| `title` | `getTitleAttribute` | `setTitleAttribute` |
| `author_id` | `getAuthorIdAttribute` | `setAuthorIdAttribute` |
| `post_meta_data` | `getPostMetaDataAttribute` | `setPostMetaDataAttribute` |

`Entity::__call()` also handles the `*Attribute` family when you don't define a real method — the fallback simply forwards to the attribute bag. So calls like `$entity->getTitleAttribute()` always work, defined or not.

## Two hydration paths — only one runs the mutators

There are two ways an entity gets populated:

| Path | Mutators run? |
|---|:---:|
| `new PostEntity(['title' => 'X'])` — direct construction | ✅ yes — the constructor dispatches every key through `__set`. |
| `$model->read()->asClass(PostEntity::class)` — PDO hydration | ❌ no — PDO writes properties directly on the object via `FETCH_CLASS`. |

If you need transformation on read, do it via a `get{Column}Attribute()` accessor — accessors *always* run, even after PDO hydration.

## Debug-friendly output

```php
var_dump($post);
```

`__debugInfo()` returns the attribute bag, so `var_dump` shows the row data rather than the internal `$attributes` / `$attributesOriginal` fields. Useful in test output.

## A worked example — typed entity with hooks

```php
namespace App\Entity;

use DateTimeImmutable;
use InitPHP\Database\Entity;

final class UserEntity extends Entity
{
    /** Lower-case + trimmed email on write. */
    public function setEmailAttribute(string $value): void
    {
        $this->setAttribute('email', mb_strtolower(trim($value)));
    }

    /** Always return a typed object on read. */
    public function getCreatedAtAttribute(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value);
    }

    /** Derived column — never persisted. */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->attributes['name'] ?? '';
        return $name !== '' ? $name : ($this->attributes['email'] ?? '(no name)');
    }
}
```

```php
$user = new UserEntity(['email' => 'ADA@example.com', 'created_at' => '2026-05-25 10:00:00']);

$user->email;        // 'ada@example.com'  — mutator on construct
$user->created_at;   // DateTimeImmutable  — accessor on read
$user->display_name; // 'ada@example.com'  — derived from the email
```

## Next

- [Models](Models.md) — the table side of the pair.
- [CRUD Operations](CRUD-Operations.md) — `save()` (insert-or-update) semantics.
