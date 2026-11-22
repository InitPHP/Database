# Recipes

Self-contained, copy-pasteable solutions to specific problems. Every recipe targets a real use case and includes the surrounding context (schema, controller wiring, test angle when it helps).

If a recipe you need is missing, please [open an issue](https://github.com/InitPHP/Database/issues/new) — recipes ship eagerly.

## Available recipes

| Recipe | Use case |
|---|---|
| [Pagination](Recipe-Pagination.md) | Build a simple `?page=&per_page=` paginator over a query, with the total page count. |
| [Search & Filters](Recipe-Search-Filters.md) | Build a search form whose fields combine into a single safe WHERE clause. |
| [Upsert / REPLACE INTO](Recipe-Upsert.md) | Insert-or-update across MySQL, PostgreSQL and SQLite — when the builder gets in the way. |
| [Audit Log](Recipe-Audit-Log.md) | Capture every write into a parallel `_audit` table inside a transaction. |
| [DataTables Bootstrap](Recipe-DataTables-Bootstrap.md) | A working DataTables.js page from client HTML to server controller. |
| [Repository Pattern](Recipe-Repository-Pattern.md) | Use the package without the global facade, for testability. |

## How to read a recipe

Each page follows the same skeleton:

1. **Problem** — the question the recipe answers.
2. **Schema** — any SQL you need before the code makes sense.
3. **Code** — the actual implementation, runnable.
4. **Tests** — what to assert against if you want to verify it works.
5. **Variations** — common extensions / what to change for adjacent use cases.

Recipes are pragmatic, not exhaustive. If something interesting comes up in the variations that deserves its own page, it gets one.
