# Migration Guide — 4.x → 5.0

5.0 is the first release that crossed the bar of breaking BC on purpose. The 4.x line worked but shipped with a handful of mismatches against upstream and a few latent bugs in the DataTables helper. This page is the migration path.

## Why 5.0

- The 4.x `composer.json` declared `php: >= 8.0` but its required dependencies refused to install on PHP 8.0. The runtime requirement is being raised to match reality.
- `DB::createImmutable()` diverged from the upstream `InitORM\Database\Facade\DB` contract — silently overwriting the shared instance on a second call. Aligning the two changes public behaviour.
- The DataTables helper carried bugs (SQLite-incompatible result handling, type errors on string-typed payloads, `recordsFiltered === recordsTotal` regardless of the search filter). Fixing them required reshaping the class.

## At a glance

| Area | 4.x | 5.0 |
|---|---|---|
| PHP minimum | declared `>= 8.0` (broken — install failed there) | `^8.2` |
| `DB::createImmutable()` second call | silently overwrote the shared instance | throws `DatabaseException` |
| `DB::createImmutable()` return type | `void` | `DatabaseInterface` |
| Swapping the shared instance | call `createImmutable()` again | call `DB::replaceImmutable($connection)` |
| Instance use of `DB` (`(new DB())->foo()`) | worked via instance `__call` | constructor is now `private`; the class is `final` |
| DataTables namespace | `InitPHP\Database\Utils\Datatables` | `InitPHP\Database\Utils\Datatables\Datatables` |
| DataTables request handling | reads globals directly | accepts a `RequestParser` (still falls back to globals) |
| DataTables render registry | inline closure array | dedicated `Renderer` class behind `addRender()` |
| `recordsFiltered` | always equal to `recordsTotal` | computed separately when a search is active |
| Identifier escaping | already correct in 3.x+ via InitORM | unchanged — regression tests added |

## Step-by-step migration

### 1. Bump PHP to 8.2+

Update your CI / Dockerfile / runtime to PHP 8.2 or later. The 5.0 release raises the floor from 8.0 to `^8.2`. The package will not install on older versions.

```diff
- "php": "8.0",
+ "php": "8.2",
```

### 2. Adjust `DB::createImmutable()` callers

If you call `createImmutable()` more than once intentionally (between requests in a worker, in test fixtures), switch the subsequent calls to `replaceImmutable()`:

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

### 5. (Optional) Inject the DataTables request payload

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

Not strictly a 5.0 change — this was always wrong — but PHP 8.2+ emits deprecation notices and a future PHP release makes it fatal. Inside a `set{Column}Attribute($value)` method, always write through `setAttribute()`:

```diff
  public function setTitleAttribute(string $value): void
  {
-     $this->title = strtolower($value);
+     $this->setAttribute('title', strtolower($value));
  }
```

See [Entities](Entities.md) for the long version.

## Migrating from 3.x

3.x is the version that moved off the pre-InitORM compiler and onto the upstream stack. Most code that worked on 3.x still works on 5.0 — the additional 5.0 changes apply on top. The big things to watch out for relative to 3.x:

- `replaceImmutable()` did not exist; the only way to swap was a second `createImmutable()` (which used to be silent). Same fix as above — switch to `replaceImmutable()`.
- The DataTables namespace move (step 4 above) applies.

## Migrating from 2.x

2.x predated InitORM. The migration is closer to a rewrite — `getError()`, identifier escaping rules, model schema, and entity hooks all changed shape. If you are still on 2.x, the practical recommendation is:

1. Cut a branch.
2. Replace every `getError()` call with `try / catch` — exceptions now drive failure paths.
3. Remove any manual backtick / quote wrapping you did to work around the v2 reserved-keyword bug (see [Reserved Keywords](Reserved-Keywords.md)).
4. Audit `protected $entity = '\App\…';` declarations — the 5.0 type is `string` (was untyped).
5. Run your test suite; expect the bulk of changes to be ergonomic, not semantic.

If you hit something that does not have a clear migration path, [open an issue](https://github.com/InitPHP/Database/issues/new) — we'll add it here.

## Things that did NOT change in 5.0

- The QueryBuilder surface (`select`, `where`, `join`, `groupBy`, …).
- CRUD signatures (`create`, `read`, `update`, `delete`, plus the `*Batch` siblings).
- Model configuration (`$schema`, `$schemaId`, `$entity`, `$useSoftDeletes`, `$createdField`, `$updatedField`, `$deletedField`, access gates, `$credentials`).
- `Entity` accessors / mutators / dirty tracking.
- Transaction semantics (`transaction()` with retry and `testMode`).
- The `log` / `debug` / `queryLogs` connection channels.

## Reporting migration issues

If something breaks during the upgrade and the fix isn't on this page, please open an issue with:

- The version you upgraded from.
- A minimal code sample that worked on the old version.
- The error you see on 5.0.

Documentation gaps are bugs and get fixed on the same day.
