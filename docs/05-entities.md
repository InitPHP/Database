# 05 — Entities

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

`read()` will then hydrate rows into instances of your subclass.

## Reading and writing attributes

```php
$post = new PostEntity(['id' => 1, 'title' => 'Hello']);

echo $post->title;        // 'Hello'
$post->title = 'Edited';  // routed through __set
$post->toArray();         // ['id' => 1, 'title' => 'Edited']
$post->getAttributes();   // same as toArray()
$post->getOriginal();     // ['id' => 1, 'title' => 'Hello'] — snapshot at construct time
```

`isset($post->title)` and `unset($post->title)` work via `__isset` / `__unset`.

`syncOriginal()` refreshes the snapshot — call it after a `save()` if you want subsequent dirty-tracking to be relative to the just-persisted state.

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

The method receives whatever currently sits in the attribute bag (or `null` when nothing has been stored yet) and may return anything.

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

Always use `$this->setAttribute($name, $value)` from inside a mutator.

## Camel-cased accessor / mutator names

The column-name → method-name translation is `snake_case` → `PascalCase`:

| Column | Accessor | Mutator |
| --- | --- | --- |
| `title` | `getTitleAttribute` | `setTitleAttribute` |
| `author_id` | `getAuthorIdAttribute` | `setAuthorIdAttribute` |
| `post_meta_data` | `getPostMetaDataAttribute` | `setPostMetaDataAttribute` |

`Entity::__call()` covers the `*Attribute` family even when you don't define a real method — the fallback simply forwards to the attribute bag.

## Hydration paths

There are two ways an entity gets populated:

1. **Direct construction** — `new PostEntity(['title' => 'Hello'])`. The constructor dispatches every key through `__set`, so mutators run.
2. **`read()->asClass(...)`** — PDO's `FETCH_CLASS` populates the entity for you. PDO writes properties directly on the object, **so mutators do NOT run on hydration**. If you need transformation on read, do it via a `get{Column}Attribute()` accessor.

## Debug-friendly output

```php
var_dump($post);
```

`__debugInfo()` returns the attribute bag, so `var_dump` shows the row data rather than the internal fields.

## Next up

- [04 — Models](04-models.md) for the table side of the pair.
- [03 — CRUD](03-crud.md) for `save()` semantics (insert-or-update from an entity).
