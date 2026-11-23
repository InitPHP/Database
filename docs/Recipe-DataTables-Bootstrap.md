# Recipe — DataTables Bootstrap

A complete, working DataTables.js integration — client HTML, JavaScript, server endpoint — for the most common use case: list users with search, sort, paging, and per-row actions.

## Problem

`/admin/users` should render a table that:

- Loads its data over AJAX (server-side mode).
- Lets the user search across name and email.
- Lets the user sort by every column except the action buttons.
- Paginates 25 rows at a time.
- Shows an "Edit" button on every row.
- Formats `created_at` as a human-readable date.

## Schema

```sql
CREATE TABLE users (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    role        VARCHAR(32)  NOT NULL DEFAULT 'user',
    active      INTEGER      NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL
);
```

## Server — `/admin/users/datatables` endpoint

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use InitPHP\Database\DB;
use InitPHP\Database\Utils\Datatables\Datatables;

DB::createImmutable(require __DIR__ . '/../config/database.php');

header('Content-Type: application/json; charset=utf-8');

echo (new Datatables(DB::getDatabase()))
    ->from('users')
    ->where('active', 1) // soft filter — only active users
    ->select('id', 'name', 'email', 'role', 'created_at')

    // The DataTables column index <-> DB column mapping.
    //   index 0 → id
    //   index 1 → name
    //   index 2 → email
    //   index 3 → role
    //   index 4 → created_at
    //   index 5 → null → 'actions' (render-only, no sort/search)
    ->setColumns('id', 'name', 'email', 'role', 'created_at', null)

    // Render created_at as a human date.
    ->addRender('created_at', static fn (?string $ts) =>
        $ts === null ? '' : (new DateTimeImmutable($ts))->format('d M Y')
    )

    // Build an empty 'actions' column then render it client-side as a button.
    ->addPermanentSelect(DB::raw("'' AS actions"))
    ->addRender('actions', static fn (mixed $_v, array $row) => sprintf(
        '<a class="btn btn-sm btn-primary" href="/admin/users/%d/edit">Edit</a>',
        (int) $row['id']
    ));
```

That's the entire server side. Three database round-trips (`recordsTotal`, `recordsFiltered`, page query), the protocol is parsed for you, and the response is JSON-encoded automatically by the `__toString()` call inside `echo`.

## Client — `/admin/users` view

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Users</title>

    <!-- Bootstrap 5 (or your CSS of choice) -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- DataTables -->
    <link rel="stylesheet"
          href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body class="p-4">
    <h1 class="mb-4">Users</h1>

    <table id="users-table" class="table table-striped" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
                <th></th>
            </tr>
        </thead>
    </table>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(function () {
        $('#users-table').DataTable({
            serverSide: true,
            processing: true,
            pageLength: 25,
            ajax: {
                url: '/admin/users/datatables',
                type: 'GET'
            },
            columns: [
                { data: 'id',         title: 'ID' },
                // These columns hold user-supplied text, and DataTables inserts cell
                // values as HTML: render them as text so markup is not executed.
                { data: 'name',       title: 'Name',  render: $.fn.dataTable.render.text() },
                { data: 'email',      title: 'Email', render: $.fn.dataTable.render.text() },
                { data: 'role',       title: 'Role',  render: $.fn.dataTable.render.text() },
                { data: 'created_at', title: 'Joined' },
                { data: 'actions',    title: '', orderable: false, searchable: false }
            ],
            order: [ [ 4, 'desc' ] ] // newest first by default
        });
    });
    </script>
</body>
</html>
```

## How the pieces line up

| Server | Client |
|---|---|
| `setColumns('id', 'name', ...)` order | `columns[].data` order |
| `setColumns(null)` for actions | `orderable: false, searchable: false` |
| user-supplied text (`name`, `email`, `role`) | `render: $.fn.dataTable.render.text()` — cells are inserted as HTML |
| `addPermanentSelect("'' AS actions")` | gives the renderer something to attach to |
| `addRender('actions', fn)` | renders the cell HTML |
| `where('active', 1)` | invisible filter — only active users ever leave the server |
| `->orderBy(...)` (not used here) | the client's `order` directive replaces any captured order |

## A sanity check

Open the page, open the browser DevTools network tab, and trigger one search:

```
GET /admin/users/datatables?draw=2&start=0&length=25&search%5Bvalue%5D=ada&order%5B0%5D%5Bcolumn%5D=1&order%5B0%5D%5Bdir%5D=asc
```

The response will look like:

```json
{
    "draw": 2,
    "recordsTotal": 856,
    "recordsFiltered": 3,
    "data": [
        {
            "id": 12,
            "name": "Ada Lovelace",
            "email": "ada@example.com",
            "role": "admin",
            "created_at": "01 Apr 2026",
            "actions": "<a class=\"btn btn-sm btn-primary\" href=\"/admin/users/12/edit\">Edit</a>"
        },
        ...
    ],
    "post": { ... echoes the request ... }
}
```

## Variations

### Make it model-bound

If `Users` is already a Model with soft deletes:

```php
header('Content-Type: application/json; charset=utf-8');

echo (new Datatables(new App\Model\Users()))
    ->setColumns('id', 'name', 'email', 'role', 'created_at', null)
    // … renderers …
```

Soft-deleted users disappear from all three queries automatically. No `where('deleted_at', null, 'IS NULL')` needed.

### Add a "status" filter on top

A non-DataTables filter that constrains the query before DataTables does its thing:

```php
$activeOnly = filter_var($_GET['active'] ?? '1', FILTER_VALIDATE_BOOL);

$dt = new Datatables(DB::getDatabase());
$dt->from('users');

if ($activeOnly) {
    $dt->where('active', 1);
}

header('Content-Type: application/json; charset=utf-8');

echo $dt->setColumns('id', 'name', 'email', 'role', 'created_at', null)
        ->addRender(/* … */);
```

On the client, point the AJAX URL at `/admin/users/datatables?active=0` (or attach `ajax.data` as a function) to flip the filter.

### Protect the endpoint

The helper has no concept of authentication. Wrap the endpoint with whatever your framework's auth middleware provides — once the user is authenticated, the rest is the example above.

### Stream the JSON for huge pages

DataTables.js sets `pageLength: 25` by default; the helper buffers `data` in PHP memory. If you raise the page length to something pathological (e.g. `length = -1` from a CSV export button), the response can grow. For huge exports, build a separate endpoint that streams CSV with `php://output` — don't shoehorn it through DataTables.

## Next

- [DataTables — Search, Sort, Paging](DataTables-Search-Sort-Paging.md) — the protocol details.
- [DataTables — Renderers](DataTables-Renderers.md) — more renderer patterns.
- [DataTables — Advanced](DataTables-Advanced.md) — `groupBy`, custom request parsers, model integration.
