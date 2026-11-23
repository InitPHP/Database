# DataTables — Renderers

A renderer is a closure that transforms a single column's value just before the row is handed to the client. The helper applies every registered renderer to the page query's result rows, in place, so the client sees the post-render value in `data[i].column`.

## The signature

```php
function (mixed $value, array $row): mixed
```

| Argument | Meaning |
|---|---|
| `$value` | The current cell value. |
| `$row` | The full row, by value — so you can inspect sibling columns. Mutating `$row` does **not** affect the response (it is a copy). |

The return value replaces the cell's value in the response.

## Registering a renderer

```php
use InitPHP\Database\Utils\Datatables\Datatables;

$dt = new Datatables(DB::getDatabase());
$dt->from('users')
   ->setColumns('id', 'name', 'email', 'created_at')
   ->addRender('email', fn (?string $email) =>
       $email === null ? '' : sprintf(
           '<a href="mailto:%s">%s</a>',
           htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
           htmlspecialchars($email, ENT_QUOTES, 'UTF-8')
       )
   );
```

Now every `email` cell in the response is an `<a>` element. The unrendered `email` cell never escapes to the client.

## Combining sibling columns

```php
$dt->setColumns('id', 'first_name', 'last_name', 'full_name')
   ->addPermanentSelect('first_name', 'last_name')
   ->addRender('full_name', fn (mixed $_value, array $row) =>
       trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))
   );
```

`full_name` does not have to exist in the database — it is a virtual column. The trick:

1. `setColumns()` declares it (so the client has an index for it).
2. `addPermanentSelect()` ensures the source columns (`first_name`, `last_name`) ride along on every SELECT (see [Advanced](DataTables-Advanced.md)).
3. The renderer composes the value from those siblings.

## Formatting timestamps

```php
$dt->setColumns('id', 'name', 'created_at')
   ->addRender('created_at', static fn (?string $ts) =>
       $ts === null ? '' : (new DateTimeImmutable($ts))->format('d M Y H:i')
   );
```

Strings on the wire, `DateTimeImmutable` on the way out, formatted strings into the client. Three clean responsibilities.

## Action buttons

```php
$dt->setColumns('id', 'name', null) // null slot is the actions column
   ->addRender('actions', static fn (mixed $_value, array $row) => sprintf(
       '<a href="/users/%d/edit" class="btn btn-sm">Edit</a> '
       . '<button data-id="%d" class="btn btn-danger btn-sm js-delete">Delete</button>',
       $row['id'],
       $row['id']
   ));
```

Wait — the column was `null` in `setColumns()`. How does the renderer attach?

The renderer matches **by column name** in the result row. So if you want a virtual "actions" column, declare it client-side and back it with a placeholder on the server. The cleanest pattern:

```php
// Server
$dt->from('users')
   ->select('users.*', DB::raw("'' AS actions"))
   ->setColumns('id', 'name', null) // 'actions' has no DB column, no sort/search
   ->addRender('actions', $actionsRenderer);

// Client
columns: [
    { data: 'id',      title: 'ID' },
    { data: 'name',    title: 'Name' },
    { data: 'actions', title: '',     orderable: false, searchable: false }
]
```

`select(DB::raw("'' AS actions"))` adds an empty string column to the result set; the renderer then fills it in.

## Mutating sibling columns

```php
$dt->addRender('name', static function (string $name, array $row): string {
    $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); // the cell is rendered as HTML
    return $row['is_admin'] ? '👑 ' . $safe : $safe;
});
```

`$row` is passed by **value** — the helper copies it before invoking the renderer. So you can read sibling columns freely; you cannot use a renderer to mutate a sibling that has its own renderer running later.

If you need cross-column transformations that have to write to multiple cells, do it after `toArray()`:

```php
$response = $dt->toArray();
foreach ($response['data'] as $i => $row) {
    $response['data'][$i] = transformRow($row);
}
header('Content-Type: application/json; charset=utf-8'); // not text/html: "post" echoes the request
echo json_encode($response);
```

## Re-registering a column

`addRender()` overwrites any previous registration for the same column:

```php
$dt->addRender('name', fn ($v) => strtoupper($v));
$dt->addRender('name', fn ($v) => strtolower($v));
// strtolower wins.
```

## When renderers do NOT run

- On `null` (non-array) rows — when `read()->rows()` produces objects (e.g. via `asClass()`), the renderer skips them.
- On columns whose name does not exist in the result row — the renderer simply has nothing to attach to.

## Security — always escape on the way out

Renderers are the natural place to inject HTML. **Always escape** untrusted input:

```php
// 👍 safe
$dt->addRender('name', static fn (string $name) =>
    '<strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>'
);

// 👎 XSS waiting to happen
$dt->addRender('name', static fn (string $name) => "<strong>{$name}</strong>");
```

DataTables.js sets cell innerHTML directly — anything you emit ends up in the DOM as-is. That applies to columns **without** a renderer too: a plain `name` value goes into the cell unescaped. On the client, give every user-supplied text column `render: $.fn.dataTable.render.text()`:

```js
columns: [
    { data: 'name',  title: 'Name',  render: $.fn.dataTable.render.text() },
    { data: 'email', title: 'Email', render: $.fn.dataTable.render.text() }
]
```

Do not combine the two on the same column: a column that already returns escaped HTML from PHP would be escaped a second time by `text()`. Choose one place per column.

## Renderers and the count queries

Renderers only run on the **page query**, never on the two count queries. So expensive transformations in a renderer cost `length` calls per request (typically 10–100), not `recordsTotal` calls. Still, keep renderers cheap — they sit on the request's hot path.

## Next

- [Advanced](DataTables-Advanced.md) — `addPermanentSelect`, group-by counts, custom request parsers.
- [Search, Sort, Paging](DataTables-Search-Sort-Paging.md) — what shapes the data that renderers see.
- [Recipe — DataTables Bootstrap](Recipe-DataTables-Bootstrap.md) — a full client + server example.
