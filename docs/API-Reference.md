# API Reference

A flat index of the public surface — every method, grouped by class. Topical guides on the rest of the wiki link in here when you need a quick lookup.

## `InitPHP\Database\DB` — static facade

| Method | Signature |
|---|---|
| `createImmutable` | `static createImmutable(array\|ConnectionInterface $connection): DatabaseInterface` — throws if a shared instance is already set |
| `replaceImmutable` | `static replaceImmutable(array\|ConnectionInterface\|DatabaseInterface\|null $connection): ?DatabaseInterface` |
| `connect` | `static connect(array\|ConnectionInterface $connection): DatabaseInterface` — does **not** touch the facade slot |
| `getDatabase` | `static getDatabase(): DatabaseInterface` — throws if none configured |
| `__callStatic` | Forwards every other call to the shared `Database` |

The constructor is private — `DB` cannot be instantiated.

## `InitPHP\Database\Database` — extends `InitORM\Database\Database`

### CRUD

| Method | Signature |
|---|---|
| `create` | `create(?string $table = null, ?array $set = null): bool` |
| `createBatch` | `createBatch(?string $table = null, ?array $set = null): bool` |
| `read` | `read(?string $table = null, ?array $selectors = null, ?array $conditions = null): DataMapperInterface` |
| `update` | `update(?string $table = null, ?array $set = null, ?array $conditions = null): bool` |
| `updateBatch` | `updateBatch(string $referenceColumn, ?string $table = null, ?array $set = null, ?array $conditions = null): bool` |
| `delete` | `delete(?string $table = null, ?array $conditions = null): bool` |

### Raw and metadata

| Method | Signature |
|---|---|
| `query` | `query(string $sqlQuery, ?array $parameters = null, ?array $options = null): DataMapperInterface` |
| `getConnection` | `getConnection(): ConnectionInterface` |
| `getPDO` | `getPDO(): PDO` |
| `insertId` | `insertId(): string\|false` |
| `affectedRows` | `affectedRows(): int` |

### Transactions

| Method | Signature |
|---|---|
| `transaction` | `transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool` |

### Builder lifecycle

| Method | Signature |
|---|---|
| `withFreshBuilder` | `withFreshBuilder(): DatabaseInterface` — fresh builder, same connection |
| `builder` | `builder(): DatabaseInterface` — **deprecated** alias of `withFreshBuilder` |

### Query log

| Method | Signature |
|---|---|
| `enableQueryLog` | `enableQueryLog(): static` |
| `disableQueryLog` | `disableQueryLog(): static` |
| `getQueryLogs` | `getQueryLogs(): array<int, array{query: string, args: array, timer: float}>` |

## Query Builder surface (via `__call`)

Every call below returns `$this` (chainable) unless noted otherwise. The full list is reproduced in `InitORM\Database\Facade\DB` as `@method static …`. The most useful slice:

### SELECT clauses

| Method | Notes |
|---|---|
| `select(string\|RawQuery ...$columns)` | Variadic column list. |
| `clearSelect()` | Reset the projection. |
| `selectCount`, `selectCountDistinct`, `selectSum`, `selectAvg`, `selectMax`, `selectMin` | Aggregates. |
| `selectUpper`, `selectLower`, `selectLength` | String functions. |
| `selectMid`, `selectLeft`, `selectRight` | Substring helpers. |
| `selectConcat(array $columns, ?string $alias = null)` | `CONCAT(...)`. |
| `selectCoalesce` | `COALESCE(col, default)`. |
| `selectDistinct` | `DISTINCT col`. |
| `selectAs($column, string $alias)` | Explicit alias. |

### FROM / JOIN

| Method | Notes |
|---|---|
| `from($table, ?$alias = null)` | |
| `addFrom($table, ?$alias = null)` | Comma-joined extra FROM. |
| `table($table)` | Alias for `from`. |
| `join($table, $onStmt = null, string $type = 'INNER')` | |
| `innerJoin`, `leftJoin`, `rightJoin`, `leftOuterJoin`, `rightOuterJoin` | |
| `selfJoin($table, $onStmt)` | |
| `naturalJoin($table)` | |
| `on($col, $op = '=', $val = null, string $logical = 'AND')` | Inside a join closure. |

### WHERE

| Method | Notes |
|---|---|
| `where($col, $op = '=', $val = null, string $logical = 'AND')` | Operator defaults to `=`. |
| `andWhere`, `orWhere` | Same args without the logical. |
| `whereIn`, `whereNotIn`, `andWhereIn`, `orWhereIn`, `andWhereNotIn`, `orWhereNotIn` | |
| `whereIsNull`, `whereIsNotNull`, `andWhereIsNull`, `orWhereIsNull`, `andWhereIsNotNull`, `orWhereIsNotNull` | |
| `between($col, $first, $last, $logical = 'AND')` + `not/and/or` variants | |
| `like($col, $val, string $type = 'both', string $logical = 'AND')` + `or/and/notLike` | Type: `both`/`before`/`after`. |
| `startLike`, `endLike` + `or/and` and `not` variants | |
| `findInSet` + variants | MySQL `FIND_IN_SET`. |
| `regexp($col, string $val, string $logical = 'AND')` + variants | |
| `soundex` + variants | |

### GROUP BY / HAVING

| Method | Notes |
|---|---|
| `groupBy($col, ...)` | Variadic. |
| `having($col, $op = '=', $val = null, string $logical = 'AND')` | |

### ORDER / LIMIT

| Method | Notes |
|---|---|
| `orderBy($col, string $dir = 'ASC')` | |
| `offset(int $offset = 0)` | |
| `limit(int $limit)` | |

### Composition

| Method | Notes |
|---|---|
| `group(Closure $closure, string $logical = 'AND')` | Parenthesised sub-clause. **Caveat:** parameter binding inside the closure is broken in `initorm/query-builder` 2.x — see [Query Builder](Query-Builder.md). |
| `subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true): RawQuery` | |
| `raw(mixed $rawQuery): RawQuery` | |

### Parameter bag

| Method | Notes |
|---|---|
| `getParameter(): ParameterInterface` | Access the underlying bag. |
| `setParameter(string $key, mixed $value)` | Single binding. |
| `setParameters(array $parameters = [])` | Batch binding. |

### SET (used inside the chain by `create` / `update`)

| Method | Notes |
|---|---|
| `set($col, mixed $value = null, bool $strict = true)` | One column. |
| `addSet($col, mixed $value = null, bool $strict = true)` | Append. |

## `InitPHP\Database\Model` — extends `InitORM\ORM\Model`

### Configuration properties

| Property | Default | Purpose |
|---|---|---|
| `protected string $schema` | derived from class short name | Table name. |
| `protected string $schemaId` | `'id'` | Primary key column. |
| `protected string $entity` | `InitPHP\Database\Entity::class` | Class used by `read()` to hydrate. |
| `protected bool $useSoftDeletes` | `false` | Enable soft-delete behaviour. |
| `protected ?string $deletedField` | `null` | Required when `useSoftDeletes = true`. |
| `protected ?string $createdField` | `null` | Auto-fill on insert. |
| `protected ?string $updatedField` | `null` | Auto-fill on update. |
| `protected string $timestampFormat` | `'Y-m-d H:i:s'` | `date()` format. |
| `protected bool $readable` | `true` | Gate on `read()`. |
| `protected bool $writable` | `true` | Gate on `create()` / `createBatch()`. |
| `protected bool $updatable` | `true` | Gate on `update()` / `updateBatch()`. |
| `protected bool $deletable` | `true` | Gate on `delete()`. |
| `protected ?array $credentials` | `null` | Non-shared connection. |

### Methods

| Method | Signature |
|---|---|
| `getSchema` | `getSchema(): string` |
| `getSchemaId` | `getSchemaId(): string` |
| `getDatabase` | `getDatabase(): DatabaseInterface` |
| `create` | `create(array $set = []): bool` |
| `createBatch` | `createBatch(array $set = []): bool` |
| `read` | `read(array $selector = [], array $conditions = []): DataMapperInterface` |
| `update` | `update(array $set = [], ?array $conditions = null): bool` |
| `updateBatch` | `updateBatch(array $set = [], ?string $referenceColumn = null): bool` |
| `delete` | `delete(?array $conditions = null, bool $purge = false): bool` |
| `save` | `save(EntityInterface $entity): bool` — insert-or-update by primary key |
| `onlyDeleted` | `onlyDeleted(): static` — one-shot scope flag |
| `ignoreDeleted` | `ignoreDeleted(): static` — append `IS NULL` predicate |

### Exceptions thrown

| Gate | Exception |
|---|---|
| `$readable = false` | `InitORM\ORM\Exceptions\ReadableException` |
| `$writable = false` | `InitORM\ORM\Exceptions\WritableException` |
| `$updatable = false` | `InitORM\ORM\Exceptions\UpdatableException` |
| `$deletable = false` | `InitORM\ORM\Exceptions\DeletableException` |
| `$useSoftDeletes = true` but `$deletedField` is empty | `InitORM\ORM\Exceptions\ModelException` (in constructor) |

## `InitPHP\Database\Entity` — extends `InitORM\ORM\Entity`

### Methods

| Method | Signature |
|---|---|
| `__construct` | `__construct(?array $data = [])` — runs every key through `__set` (so mutators fire). |
| `__get` | `__get(string $name): mixed` — dispatches to `get{Column}Attribute` accessor when defined. |
| `__set` | `__set(string $name, mixed $value): void` — dispatches to `set{Column}Attribute` mutator when defined. |
| `__isset` | `__isset(string $name): bool` |
| `__unset` | `__unset(string $name): void` |
| `__debugInfo` | Returns the attribute bag — for `var_dump`. |
| `toArray` | `toArray(): array` |
| `getAttributes` | `getAttributes(): array` |
| `getOriginal` | `getOriginal(): array` — construct-time snapshot. |
| `getAttribute` | `getAttribute(string $name): mixed` |
| `setAttribute` | `setAttribute(string $name, mixed $value): static` — **use this from mutator bodies**. |
| `syncOriginal` | `syncOriginal(): static` — refresh the snapshot. |

## `InitPHP\Database\Utils\Datatables\Datatables`

| Method | Signature |
|---|---|
| `__construct` | `__construct(DatabaseInterface\|ModelInterface $db, ?RequestParser $request = null, ?Renderer $renderer = null)` |
| `__call` | Captures any builder method for replay. Returns `$this`. |
| `__toString` | `(string) $dt` — JSON envelope (`{}` on failure). |
| `toArray` | `toArray(): array{draw, recordsTotal, recordsFiltered, data, post}` |
| `handle` | `handle(): self` — runs the three queries and populates `toArray()`'s payload. |
| `setColumns` | `setColumns(?string ...$columns): self` — append. |
| `addRender` | `addRender(string $column, Closure $render): self` |
| `addPermanentSelect` | `addPermanentSelect(string ...$select): self` |
| `orderBySave` | `orderBySave(): self` — keep captured orders alongside the client's. |

## `InitPHP\Database\Utils\Datatables\RequestParser`

| Method | Signature |
|---|---|
| `__construct` | `__construct(array $payload)` |
| `fromGlobals` | `static fromGlobals(): self` — merges `$_GET`, `$_POST`, `php://input` JSON. |
| `all` | `all(): array` |
| `draw` | `draw(): int` |
| `start` | `start(): int` — clamped to ≥ 0. |
| `length` | `length(): int` — returns `-1` when "all rows". |
| `hasPagination` | `hasPagination(): bool` |
| `searchValue` | `searchValue(): ?string` |
| `orders` | `orders(): list<array{0: int, 1: 'ASC'\|'DESC'}>` |

## `InitPHP\Database\Utils\Datatables\Renderer`

| Method | Signature |
|---|---|
| `add` | `add(string $column, Closure $render): void` |
| `hasAny` | `hasAny(): bool` |
| `apply` | `apply(array $rows): array` |

## `InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface`

Returned by `read()` / `query()`.

| Method | Signature |
|---|---|
| `asAssoc` | `asAssoc(): self` |
| `asObject` | `asObject(?object $obj = null): self` |
| `asClass` | `asClass(?string $class = null): self` |
| `asLazy` | `asLazy(): self` |
| `asArray` | `asArray(): self` |
| `asBoth` | `asBoth(): self` — alias of `asArray`. |
| `row` | `row(): array\|object\|null` |
| `rows` | `rows(): array<int, array\|object>` |
| `numRows` | `numRows(): int` — **unreliable for SELECT on SQLite / unbuffered MySQL**. |
| `execute` | `execute(?array $params = null): bool` |
| `getStatement` | `getStatement(): PDOStatement` |
| `getQuery` | `getQuery(): string` |
| `bind`, `bindValue`, `bindValues` | Parameter binding primitives. |

Plus `@mixin PDOStatement` — every native `PDOStatement` method is forwarded through.
