# Recipe — Pagination

Build a basic `?page=&per_page=` paginator over a query and return the metadata DataTables-less front-ends need (total rows, current page, total pages).

## Problem

A list view at `/posts?page=2&per_page=20` should return:

- The 20 posts that belong on page 2.
- The total number of posts across all pages.
- Enough metadata for the client to render "Page 2 of N".

## Schema

```sql
CREATE TABLE posts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT    NOT NULL,
    status     INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);
```

## Code — a tiny Paginator helper

```php
namespace App\Support;

use InitORM\Database\Interfaces\DatabaseInterface;

final class Paginator
{
    public function __construct(
        private readonly DatabaseInterface $db,
    ) {
    }

    /**
     * @param array<int|string, mixed> $where  Conditions to apply to both queries.
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     total_pages: int
     * }
     */
    public function paginate(
        string $table,
        int $page,
        int $perPage,
        array $where = [],
        string $orderBy = 'id',
        string $direction = 'DESC',
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage)); // cap to protect the server

        // 1. Total — captured outside of any builder chain so we know exactly
        //    which WHERE we are counting against.
        $total = (int) ($this->db
            ->selectCount('*', 'total')
            ->from($table)
            ->read(null, null, $where)
            ->asAssoc()
            ->row()['total'] ?? 0);

        // 2. Page slice.
        $rows = $this->db
            ->select('*')
            ->from($table)
            ->orderBy($orderBy, $direction)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->read(null, null, $where)
            ->asAssoc()
            ->rows();

        return [
            'data'        => $rows,
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}
```

## Wire it into a controller

```php
use InitPHP\Database\DB;
use App\Support\Paginator;

$page    = (int) ($_GET['page'] ?? 1);
$perPage = (int) ($_GET['per_page'] ?? 20);

$paginator = new Paginator(DB::getDatabase());
$response  = $paginator->paginate(
    'posts',
    $page,
    $perPage,
    ['status' => 1], // only published posts
    'created_at',
    'DESC'
);

header('Content-Type: application/json');
echo json_encode($response);
```

Example response:

```json
{
    "data": [ /* … 20 posts … */ ],
    "page": 2,
    "per_page": 20,
    "total": 87,
    "total_pages": 5
}
```

## Tests

```php
public function testPaginationReturnsTheRightSliceAndTotal(): void
{
    $db = SqliteHelper::makeDatabase();
    // … seed 87 posts …

    $paginator = new App\Support\Paginator($db);
    $page2     = $paginator->paginate('posts', 2, 20);

    self::assertSame(87, $page2['total']);
    self::assertSame(5,  $page2['total_pages']);
    self::assertCount(20, $page2['data']);
    self::assertSame(67, $page2['data'][0]['id']); // 87 - 20 = 67 — ordered DESC
}
```

## Variations

### Cursor pagination instead of offset

Offset pagination gets slow on huge tables (the database still has to scan past the offset). Cursor pagination uses the last-seen sort key as the next page's anchor:

```php
$rows = $db
    ->select('*')
    ->from('posts')
    ->where('id', '<', $beforeId) // cursor
    ->orderBy('id', 'DESC')
    ->limit(20)
    ->read()
    ->rows();

$nextCursor = $rows !== [] ? end($rows)['id'] : null;
```

The total count is harder to provide with this pattern — most cursor APIs drop it.

### Plug it into the Model layer

Add `paginate()` directly on your `Posts` model:

```php
public function paginate(int $page, int $perPage, array $where = []): array
{
    // identical body, but call $this->db and $this->schema.
}
```

### Use through the DataTables helper instead

If the client is DataTables.js, skip the custom paginator entirely — see [DataTables Introduction](DataTables-Introduction.md).

## Why three lines of metadata matter

Returning just the slice with no `total` forces the client to fetch and discard the unfiltered count anyway. One extra `selectCount(...)` round-trip on the server is almost always cheaper than the round-trip the client would otherwise make.

## Next

- [DataTables Introduction](DataTables-Introduction.md) — the helper that does this for you when DataTables is the consumer.
- [Recipe — Search & Filters](Recipe-Search-Filters.md) — combine pagination with a filter form.
