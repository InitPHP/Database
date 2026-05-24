# InitPHP Database — Documentation

Topic-focused guides for `initphp/database`. Each page is self-contained — read in order if you are new, jump around if you are looking something up.

| # | Page | What you will find |
| --- | --- | --- |
| 01 | [Getting started](01-getting-started.md) | Installation, the connection array, your first query, debug & log channels. |
| 02 | [Query Builder](02-query-builder.md) | `select`, `where`, `join`, `groupBy`, `orderBy`, `limit`, raw fragments, sub-queries. |
| 03 | [CRUD](03-crud.md) | `create` / `read` / `update` / `delete`, their `*Batch` siblings, and raw SQL via `query()`. |
| 04 | [Models](04-models.md) | Subclassing `Model`, table binding, primary keys, soft deletes, timestamp columns, gates. |
| 05 | [Entities](05-entities.md) | The attribute bag, accessor / mutator hooks, dirty tracking, and the one PHP 8.2+ pitfall to avoid. |
| 06 | [Transactions](06-transactions.md) | `transaction()` with retry attempts and `testMode`. |
| 07 | [Query log](07-query-log.md) | `enableQueryLog` / `getQueryLogs` and the connection-level `log` channel. |
| 08 | [Datatables](08-datatables.md) | Server-side [DataTables.js](https://datatables.net/) integration end-to-end. |
| 09 | [Multiple connections](09-multi-connection.md) | Secondary databases via `DB::connect()` and `Model::$credentials`. |
| 10 | [Upgrading](10-upgrading.md) | Breaking changes in 5.0 and the migration path from 3.x / 4.x. |

If something is missing or unclear, please [open an issue](https://github.com/InitPHP/Database/issues) or start a [Discussion](https://github.com/orgs/InitPHP/discussions).
