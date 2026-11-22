# Recipe — Search & Filters

A form at the top of a list view typically has three or four fields — a name search, a status dropdown, a date range. The challenge is composing them into one safe WHERE clause without giving up on parameter binding.

## Problem

A `/posts/search` endpoint accepts:

- `q` — fuzzy text search against `title` and `content`
- `status` — exact match against the `status` column
- `from`, `to` — inclusive date range on `created_at`

Any combination may be present. Missing fields should not filter.

## Schema

```sql
CREATE TABLE posts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT    NOT NULL,
    content    TEXT,
    status     INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);
```

## Code

```php
use InitPHP\Database\DB;

$q      = isset($_GET['q'])      ? trim((string) $_GET['q'])      : '';
$status = isset($_GET['status']) ? (int) $_GET['status']           : null;
$from   = $_GET['from']   ?? null;
$to     = $_GET['to']     ?? null;

$query = DB::select('id', 'title', 'status', 'created_at')->from('posts');

// 1. Fuzzy text search across two columns — group() with a Closure.
if ($q !== '') {
    // Sub-builder loses parameter binding in 2.x → use a raw fragment + setParameter.
    $query
        ->where(DB::raw('(title LIKE :search_q OR content LIKE :search_q)'))
        ->setParameter(':search_q', '%' . $q . '%');
}

// 2. Exact match — straightforward.
if ($status !== null) {
    $query->where('status', $status);
}

// 3. Date range.
if ($from !== null && $from !== '') {
    $query->where('created_at', '>=', $from);
}
if ($to !== null && $to !== '') {
    $query->where('created_at', '<=', $to);
}

$rows = $query
    ->orderBy('created_at', 'DESC')
    ->limit(50)
    ->read()
    ->asAssoc()
    ->rows();
```

## What the SQL looks like

For `?q=php&status=1&from=2026-01-01`:

```sql
SELECT `id`, `title`, `status`, `created_at`
FROM `posts`
WHERE (title LIKE :search_q OR content LIKE :search_q)
  AND `status` = :status
  AND `created_at` >= :created_at
ORDER BY `created_at` DESC
LIMIT 50
```

Args:

```json
{
  ":search_q": "%php%",
  ":status": 1,
  ":created_at": "2026-01-01"
}
```

All inputs are bound — no string concatenation, no injection surface.

## Why a raw fragment for the text search

`initorm/query-builder` 2.x has a known limitation where parameters bound inside `$db->group(fn ($b) => $b->orLike(...))` don't propagate to the outer builder's parameter bag, so the SQL ends up with unbound placeholders and matches zero rows. Until that's fixed upstream, the raw-fragment + `setParameter()` pattern is the safe form.

If you only need a single column search (no OR across multiple columns), the regular `like()` builder works fine:

```php
$query->like('title', $q); // safe, builder-managed
```

## Tests

```php
public function testSearchFiltersByQuery(): void
{
    $db = SqliteHelper::makeDatabase();
    SqliteHelper::seedPosts($db->getConnection());

    $rows = $this->controller(['q' => 'php']);

    self::assertNotEmpty($rows);
    foreach ($rows as $row) {
        self::assertThat(
            $row,
            self::logicalOr(
                self::stringContains('php', $row['title'] ?? ''),
                self::stringContains('php', $row['content'] ?? '')
            )
        );
    }
}

public function testEmptyQueryReturnsEverything(): void
{
    // … run with no GET params, assert count equals seeded total
}
```

## Variations

### Validate the inputs first

The example above trusts the input shape. In production add validation:

```php
if ($status !== null && !in_array($status, [0, 1, 2], true)) {
    throw new InvalidArgumentException('Invalid status');
}

if ($from !== null) {
    $from = \DateTimeImmutable::createFromFormat('Y-m-d', $from);
    if ($from === false) {
        throw new InvalidArgumentException('Invalid "from" date');
    }
    $from = $from->format('Y-m-d');
}
```

### Allow-list the sort column

Never accept a raw user-supplied `?sort=column` into `orderBy(...)`. Validate against an allow-list:

```php
$allowed = ['id', 'title', 'status', 'created_at'];
$sort = in_array($_GET['sort'] ?? 'id', $allowed, true) ? $_GET['sort'] : 'id';
$query->orderBy($sort, 'DESC');
```

`orderBy()` does not parameter-bind identifiers (it cannot — identifiers can't be bound), so the allow-list is your only defence.

### Reuse across multiple endpoints

Wrap the filter logic in a small class:

```php
final class PostSearchFilter
{
    public function apply($query, array $input): void
    {
        // exact logic from above, parameterised on $query and $input
    }
}
```

Then both `/posts/search` and the DataTables endpoint can share it.

## Next

- [Recipe — Pagination](Recipe-Pagination.md) — combine with paging.
- [Query Builder](Query-Builder.md) — every WHERE variant the builder offers.
- [DataTables — Search, Sort, Paging](DataTables-Search-Sort-Paging.md) — the helper's take on the same problem.
