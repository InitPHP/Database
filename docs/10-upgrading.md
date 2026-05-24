# 10 — Upgrading

This page documents the breaking changes in **5.0** and the migration path from 3.x / 4.x. Everything not listed here is unchanged.

## Why 5.0?

- The 4.x line silently took `php: >=8.0` in `composer.json` even though one of its required dependencies (`initorm/orm ^2.0`) refused to install on PHP 8.0. The runtime requirement is being raised to match reality.
- `DB::createImmutable()` was the only caller that diverged from the upstream `InitORM\Database\Facade\DB` contract — fixing that divergence changes the public behaviour.
- The DataTables helper was the package's only non-trivial code path and it carried a handful of latent bugs (SQLite-incompatible result handling, type errors on string-typed payloads, `recordsFiltered === recordsTotal` regardless of the search filter). Fixing those required a re-shape of the class.

## At a glance

| Area | Before | After |
| --- | --- | --- |
| PHP minimum | `>= 8.0` (broken — install failed on 8.0) | `^8.1` |
| `DB::createImmutable()` second call | silently overwrote the shared instance | throws `DatabaseException` |
| `DB::createImmutable()` return type | `void` | `DatabaseInterface` |
| Swapping the shared instance | re-call `createImmutable()` | call `DB::replaceImmutable($connection)` |
| Instance use of `DB` (`(new DB())->foo()`) | worked via instance `__call` | constructor is now `private`; the class is `final` |
| DataTables namespace | `InitPHP\Database\Utils\Datatables` | `InitPHP\Database\Utils\Datatables\Datatables` |
| DataTables request handling | `$_GET` / `$_POST` / `php://input` directly | `RequestParser` (injectable; `fromGlobals()` for the live request) |
| DataTables render registry | inline closure array | `Renderer` class behind `addRender()` |
| `recordsFiltered` | always equal to `recordsTotal` | computed separately when a search is active |

## Migration steps

### 1. Bump PHP

Update your CI / Dockerfile / runtime to PHP 8.1 or later. The package will not install on 8.0.

```diff
- "php": "8.0",
+ "php": "8.1",
```

### 2. Adjust `DB::createImmutable()` callers

If you were calling `createImmutable()` more than once intentionally (between requests, in a long-lived worker, in test fixtures), switch the subsequent calls to `replaceImmutable()`:

```diff
  DB::createImmutable($firstConnection);
  // … later …
- DB::createImmutable($secondConnection); // silently overwrites
+ DB::replaceImmutable($secondConnection); // explicit swap
```

`replaceImmutable()` also accepts a `DatabaseInterface` instance directly (useful when you cache the `Database`) and `null` (clears the slot).

If you were relying on the `void` return of `createImmutable()`, no change is required — ignoring the new return value is harmless.

### 3. Drop instance-style facade use

```diff
- $db = new DB();
- $db->select('*')->from('posts')->read();
+ DB::select('*')->from('posts')->read();
```

The static surface is unchanged; only the (unintentional) instance path is gone.

### 4. Update the DataTables import path

```diff
- use InitPHP\Database\Utils\Datatables;
+ use InitPHP\Database\Utils\Datatables\Datatables;
```

The class name is the same — only the namespace moved one level deeper. The public API (`__construct`, `__call`, `setColumns`, `addRender`, `addPermanentSelect`, `orderBySave`, `handle`, `toArray`, `__toString`) is unchanged.

### 5. (Optional) Inject the request payload instead of relying on globals

The constructor signature is now:

```php
new Datatables(
    DatabaseInterface|ModelInterface $db,
    ?RequestParser $request = null,    // ← new
    ?Renderer $renderer = null,        // ← new
);
```

When `$request` is null the helper still reads `$_GET` / `$_POST` / `php://input` via `RequestParser::fromGlobals()` — so existing callers keep working unchanged. Injection is opt-in:

```php
$request = new RequestParser($psr7Request->getParsedBody());
$dt = new Datatables(DB::getDatabase(), $request);
```

### 6. Expect a different `recordsFiltered` value

If your client previously coped with `recordsFiltered === recordsTotal` even during a search, it will now see the **correct** smaller value when a search is active. The two paginate / show-X-of-Y behaviours converge to whatever DataTables.js does by default.

### 7. Fix entity mutators that wrote `$this->column = $value`

Not strictly a 5.0 change — this was always wrong — but PHP 8.2+ has started emitting deprecation notices for it, and a future PHP release will make it fatal. Inside a `set{Column}Attribute($value)` method, always write through `setAttribute()`:

```diff
  public function setTitleAttribute(string $value): void
  {
-     $this->title = strtolower($value);
+     $this->setAttribute('title', strtolower($value));
  }
```

See [05 — Entities](05-entities.md) for the long version.

## Things that did not change

- The QueryBuilder surface (`select`, `where`, `join`, `groupBy`, …).
- CRUD signatures (`create`, `read`, `update`, `delete`, plus the `*Batch` siblings).
- Model configuration (`$schema`, `$schemaId`, `$entity`, `$useSoftDeletes`, `$createdField`, `$updatedField`, `$deletedField`, access gates, `$credentials`).
- `Entity` accessors / mutators / dirty tracking.
- Transaction semantics (`transaction()` with retry and `testMode`).
- The `log` / `debug` / `queryLogs` connection channels.

## Reporting issues

If something else breaks during the upgrade, please open an issue with a minimal reproducer — see [SUPPORT.md](https://github.com/InitPHP/.github/blob/main/SUPPORT.md) for the channels.
