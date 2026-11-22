# DataTables — Search, Sort, Paging

The three things every DataTables user expects to "just work" — and what the helper does for you on each.

## Columns first

Before search / sort / paging mean anything, the helper needs to know **which columns are exposed**:

```php
$dt = new Datatables(DB::getDatabase());
$dt->from('users')
   ->setColumns('id', 'name', 'email', 'created_at');
```

The order matters — it defines the `column[i]` indexing the client will use when it sends back `order` directives.

### Null slots — render-only columns

Pass `null` for a column position that should not participate in search / sort but still occupies a client-side index:

```php
$dt->setColumns('id', 'name', null, 'created_at');
```

The `null` slot (index 2) never appears in `WHERE` (no `LIKE`) or in `ORDER BY`. Use it for action buttons, derived columns, anything the client renders but the database doesn't store.

### Appending columns

`setColumns()` **appends** rather than replaces, so the existing indexing is preserved:

```php
$dt->setColumns('id', 'name');         // index 0, 1
$dt->setColumns('email');              // index 2
$dt->setColumns(null, 'created_at');   // index 3 (null), 4
```

## Global search

DataTables' search box sends:

```json
{ "search": { "value": "Ada" } }
```

The helper turns it into:

```sql
WHERE (col1 LIKE :s0 OR col2 LIKE :s1 OR col3 LIKE :s2)
```

…using every column registered via `setColumns()` whose `db` slot is non-null. The pattern is `%value%` on every column.

```php
$dt->from('users')->setColumns('id', 'name', 'email');
// search 'Ada' produces:
//   WHERE (id LIKE :dt_search_0 OR name LIKE :dt_search_1 OR email LIKE :dt_search_2)
//   args: { :dt_search_0: '%Ada%', :dt_search_1: '%Ada%', :dt_search_2: '%Ada%' }
```

### What gets counted

The search filter is applied to:

| Pass | Filter applied? |
|---|:---:|
| `recordsTotal` count | ❌ |
| `recordsFiltered` count | ✅ |
| Page query | ✅ |

So `recordsTotal` and `recordsFiltered` **diverge** when a search is active — exactly what DataTables wants for its "filtered from N total" label.

### Why a raw fragment instead of `orLike()`

> The helper builds the search predicate as a raw SQL chunk with explicit `setParameter()` binding instead of leaning on `$db->group(fn ($b) => $b->orLike(...))`. The reason is documented in `Datatables::applySearchFilter()`: `initorm/query-builder` 2.x does not propagate parameters bound inside a `group()` callback back to the outer builder's parameter bag, which would result in SQL placeholders with no bound values and zero matches. Once that upstream behaviour is fixed, the workaround can fold back into the idiomatic form.

You can rely on the helper's search exactly as documented — the workaround is internal.

## Sorting

DataTables sends:

```json
{ "order": [{ "column": 1, "dir": "asc" }] }
```

The helper applies `ORDER BY {columns[1].db} ASC` after dropping any captured `orderBy*` calls (because by default the client is the source of truth on ordering).

### Multi-column sort

```json
{
    "order": [
        { "column": 3, "dir": "desc" },
        { "column": 1, "dir": "asc"  }
    ]
}
```

Produces:

```sql
ORDER BY {columns[3].db} DESC, {columns[1].db} ASC
```

### Keep a captured ordering alongside the client's

```php
$dt->from('users')
   ->orderBy('active', 'DESC')   // captured
   ->orderBySave()                // ← keep me
   ->setColumns('id', 'name', 'email', 'active', 'created_at');
```

With `orderBySave()`:

- The captured `ORDER BY active DESC` goes first.
- The client's order (e.g. column 1 ASC) goes second.

So `active = 1` rows always come before `active = 0`, and within each group the user's selected order applies. Useful for "important rows first, then user choice" patterns.

### Reserved keywords as orderable columns

The helper passes column names through `DB::raw(...)` for `ORDER BY`, so reserved words work — the underlying driver escapes the identifier:

```php
$dt->setColumns('id', 'title', 'order');
// client sorts by column 2 (the 'order' column) → ORDER BY `order` DESC
```

See [Reserved Keywords](Reserved-Keywords.md) for the broader story.

## Paging

DataTables sends:

```json
{ "start": "20", "length": "10" }
```

Note both values are **strings** on the wire — the helper coerces them to integers before applying `LIMIT 10 OFFSET 20`.

### "Show all" mode

The protocol uses `length = -1` to mean "no paging". The helper skips both `LIMIT` and `OFFSET` in that case:

```php
$rows = $dt->setColumns('id', 'name')->toArray()['data'];
// All filtered rows, no paging applied.
```

Be careful — `length = -1` against a large table can dump millions of rows into a single response. Consider rejecting it at the API gateway for tables you do not control.

### Negative `start` is clamped

```json
{ "start": "-5", "length": "10" }
```

The helper clamps `start` to `0`. The protocol does not officially allow negative values; clamping is the friendliest behaviour for buggy clients.

## Edge cases the helper handles

| Payload | Behaviour |
|---|---|
| Empty payload (`new RequestParser([])`) | Returns all rows, no paging, no filter, no sort. |
| `search.value` is `''` or whitespace-empty | Treated as absent — no `LIKE` clauses, `recordsFiltered === recordsTotal`. |
| `search.value` is a nested array | Treated as absent — only scalar / string values are accepted. |
| `order[i].column` references an unknown index | That entry is silently dropped (other orders still apply). |
| `order[i].dir` is missing or not `'asc'/'desc'` | Defaults to `'DESC'`. |
| `order` payload is malformed (`order: "string"`) | All ordering is dropped. |

## A complete worked example

```php
$dt = new Datatables(DB::getDatabase(), new RequestParser([
    'draw'   => '4',
    'start'  => '10',
    'length' => '5',
    'search' => ['value' => 'example.com'],
    'order'  => [ ['column' => 1, 'dir' => 'asc'] ],
]));

$response = $dt
    ->from('users')
    ->where('active', 1)
    ->setColumns('id', 'name', 'email', 'created_at')
    ->toArray();
```

This runs three queries:

```sql
-- recordsTotal — captured chain only, no search filter, no client order
SELECT COUNT(*) AS `data_length` FROM `users` WHERE `active` = 1;

-- recordsFiltered — same + search filter
SELECT COUNT(*) AS `data_length` FROM `users` WHERE `active` = 1
AND (id LIKE :dt_search_0 OR name LIKE :dt_search_1
     OR email LIKE :dt_search_2 OR created_at LIKE :dt_search_3);

-- page query — same + search + client order + paging
SELECT * FROM `users` WHERE `active` = 1
AND (id LIKE :dt_search_0 OR name LIKE :dt_search_1
     OR email LIKE :dt_search_2 OR created_at LIKE :dt_search_3)
ORDER BY `name` ASC
LIMIT 5 OFFSET 10;
```

And produces:

```json
{
    "draw": 4,
    "recordsTotal": 856,
    "recordsFiltered": 42,
    "data": [
        { "id": 12, "name": "Ada Lovelace", "email": "ada@example.com", "created_at": "2026-04-01 12:00:00" },
        // … 4 more rows …
    ],
    "post": { /* the original request, echoed */ }
}
```

## Next

- [Renderers](DataTables-Renderers.md) — per-column transformation on output.
- [Advanced](DataTables-Advanced.md) — `groupBy`, permanent selects, custom request parsers.
- [Recipe — DataTables Bootstrap](Recipe-DataTables-Bootstrap.md) — front-to-back working example.
