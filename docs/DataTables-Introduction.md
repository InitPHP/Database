# DataTables — Introduction

[DataTables.js](https://datatables.net/) is the most widely used "smart" HTML table library in PHP land. It supports two modes:

- **Client-side** — the browser loads every row and does the paging / sorting / searching itself.
- **Server-side** — the browser sends a structured request (`draw`, `start`, `length`, `search`, `order`) to your backend and the backend returns just the matching slice.

Server-side mode is the right choice the moment your table has more than a few thousand rows — anything else hurts page load time and memory.

This package ships a server-side helper that takes care of the protocol so your controller stays a one-liner. **It is the one piece of original code in `initphp/database`** — the rest of the package re-exports the InitORM stack.

## What the helper does

Given a query and a column list, the helper:

1. Parses the DataTables request payload — `draw`, `start`, `length`, `search`, `order`.
2. Runs an **unfiltered count** → goes into `recordsTotal`.
3. Runs a **filtered count** that applies the global search → goes into `recordsFiltered`.
4. Runs the **page query** with `LIMIT`, `OFFSET`, the search filter and the client-requested ordering.
5. Applies any per-column render callbacks you registered.
6. Returns the JSON envelope DataTables expects.

That's three database round-trips per request — one for each count + one for the page — and one prepared statement per round-trip.

## The smallest possible example

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Database\DB;
use InitPHP\Database\Utils\Datatables\Datatables;

DB::createImmutable([
    'dsn'      => '...',
    'username' => '...',
    'password' => '...',
]);

// Always send a JSON content type: the response echoes the request back in
// "post", and a page served as text/html would run markup in it.
header('Content-Type: application/json; charset=utf-8');

echo (new Datatables(DB::getDatabase()))
        ->from('users')
        ->setColumns('id', 'name', 'email', 'created_at');
```

`(string) $datatables` is equivalent to `json_encode($datatables->toArray())`. That single chain runs all three queries and emits the JSON envelope.

## Client side — minimal markup

```html
<table id="users" class="display"></table>

<script>
$('#users').DataTable({
    serverSide: true,
    processing: true,
    ajax: '/api/users/datatables',
    columns: [
        { data: 'id',         title: 'ID' },
        // DataTables writes cell values into the page as HTML. Text columns must
        // use the text() renderer so that "<img onerror=…>" shows up as text.
        { data: 'name',       title: 'Name',  render: $.fn.dataTable.render.text() },
        { data: 'email',      title: 'Email', render: $.fn.dataTable.render.text() },
        { data: 'created_at', title: 'Created' }
    ]
});
</script>
```

> **Escape cell values.** DataTables.js puts what the server returns into the page as HTML. Names, e-mail addresses and any other user-supplied column must go through `render: $.fn.dataTable.render.text()` on the client, or through `htmlspecialchars()` in a server-side [renderer](DataTables-Renderers.md#security--always-escape-on-the-way-out) when you build the HTML yourself. Otherwise a user who registers with `<img src=x onerror=…>` as their name runs script in the browser of whoever opens the table.

The `columns[].data` keys on the client must match the column names you registered with `setColumns()` on the server. See [DataTables — Bootstrap recipe](Recipe-DataTables-Bootstrap.md) for a complete page.

## The response envelope

```json
{
    "draw": 7,
    "recordsTotal": 1024,
    "recordsFiltered": 12,
    "data": [
        { "id": 5, "name": "Ada", "email": "ada@example.com", "created_at": "2026-04-01 12:00:00" },
        { "id": 8, "name": "Bob", "email": "bob@example.com", "created_at": "2026-04-02 09:00:00" }
    ],
    "post": {
        "draw": 7,
        "start": "0",
        "length": "25",
        "search": { "value": "" }
    }
}
```

| Field | Meaning |
|---|---|
| `draw` | Opaque client token, echoed back. DataTables uses it to drop stale responses. |
| `recordsTotal` | The **unfiltered** total row count. |
| `recordsFiltered` | The row count **after** applying the global search filter. |
| `data` | The page of rows after paging + ordering + renderers. |
| `post` | The original request payload, verbatim. Not part of the DataTables spec — a debugging convenience the helper adds. |

## Constructor

```php
new Datatables(
    DatabaseInterface|ModelInterface $db,
    ?RequestParser $request = null,
    ?Renderer $renderer = null,
);
```

| Argument | Default | When to pass your own |
|---|---|---|
| `$db` | — required — | A `Model` instance picks up that model's soft-delete / writability gates automatically. |
| `$request` | `RequestParser::fromGlobals()` | Inject a `new RequestParser($psr7Body)` when feeding the helper from PSR-7. |
| `$renderer` | new `Renderer()` | Almost never — use `$dt->addRender('col', fn …)` instead. |

`RequestParser::fromGlobals()` merges `$_GET`, `$_POST` and the JSON body of `php://input`. Outside of an HTTP context (CLI scripts, tests) it returns an empty-payload parser.

## A model-bound chain

```php
$dt = new Datatables(new App\Model\Posts());
$dt->where('status', 1)
   ->setColumns('id', 'title', 'created_at');
```

When the helper is constructed with a `Model`, every model-side guarantee applies — soft-deleted posts are excluded, the `$readable` gate is honoured, and so on.

## Three pages, three roles

This page is the overview. The next three go deeper:

- [Search, Sort, Paging](DataTables-Search-Sort-Paging.md) — what `setColumns()` and the request payload actually drive.
- [Renderers](DataTables-Renderers.md) — `addRender()` and the closure signature.
- [Advanced](DataTables-Advanced.md) — `groupBy` count behaviour, `permanentSelect`, custom request parsers, parameter binding internals.

For an end-to-end working example, see [Recipe — DataTables Bootstrap](Recipe-DataTables-Bootstrap.md).
