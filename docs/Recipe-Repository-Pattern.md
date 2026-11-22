# Recipe — Repository Pattern

The `DB::` facade is convenient but it ties every consumer to a process-global. For testable, dependency-injected code, push the database **into** your repository class via the constructor.

## Problem

A `UserService` needs to read users. With the facade, the service is hard to test in isolation:

```php
final class UserService
{
    public function findByEmail(string $email): ?array
    {
        return DB::select('*')
            ->from('users')
            ->where('email', $email)
            ->read()
            ->asAssoc()
            ->row();
    }
}
```

The test has to bootstrap `DB::createImmutable()` against a real database — heavy, slow, and globally stateful.

## Code

Move the database to a constructor argument and the consumer can be unit-tested against any `DatabaseInterface`:

```php
namespace App\Repository;

use InitORM\Database\Interfaces\DatabaseInterface;

final class UsersRepository
{
    public function __construct(
        private readonly DatabaseInterface $db,
    ) {
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db
            ->select('*')
            ->from('users')
            ->where('email', $email)
            ->read()
            ->asAssoc()
            ->row();
    }

    public function findById(int $id): ?array
    {
        return $this->db
            ->select('*')
            ->from('users')
            ->where('id', $id)
            ->read()
            ->asAssoc()
            ->row();
    }

    public function create(array $row): int
    {
        $this->db->create('users', $row);

        return (int) $this->db->insertId();
    }

    public function update(int $id, array $set): void
    {
        $this->db->where('id', $id)->update('users', $set);
    }

    public function delete(int $id): void
    {
        $this->db->where('id', $id)->delete('users');
    }
}
```

## Wire it up

### In a DI container

```php
$container->set(DatabaseInterface::class, function () {
    return new InitPHP\Database\Database(require __DIR__ . '/config/database.php');
});

$container->set(UsersRepository::class, function ($c) {
    return new UsersRepository($c->get(DatabaseInterface::class));
});
```

### Without a container (manual wiring)

```php
$db    = new InitPHP\Database\Database(require __DIR__ . '/config/database.php');
$users = new UsersRepository($db);

$ada = $users->findByEmail('ada@example.com');
```

### With the facade (the bridge pattern)

If your app uses the facade everywhere but you want one repository to be DI-friendly:

```php
DB::createImmutable($config); // legacy bootstrap
$users = new UsersRepository(DB::getDatabase());
```

The repository still takes a `DatabaseInterface`; the rest of the app keeps using `DB::`.

## Tests

In an isolated unit test:

```php
final class UsersRepositoryTest extends TestCase
{
    private DatabaseInterface $db;

    private UsersRepository $repo;

    protected function setUp(): void
    {
        $connection = SqliteHelper::makeConnection();
        $this->db   = new InitPHP\Database\Database($connection);
        $connection->getPDO()->exec(
            'CREATE TABLE users (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  TEXT,
                email TEXT
            )'
        );

        $this->repo = new UsersRepository($this->db);
    }

    public function testFindByEmailReturnsTheStoredRow(): void
    {
        $id = $this->repo->create(['name' => 'Ada', 'email' => 'ada@example.com']);

        $found = $this->repo->findByEmail('ada@example.com');

        self::assertNotNull($found);
        self::assertSame($id, (int) $found['id']);
    }

    public function testFindByEmailReturnsNullForMissingRows(): void
    {
        self::assertNull($this->repo->findByEmail('nope@example.com'));
    }
}
```

No `DB::createImmutable()`, no process-global state, no static singletons. Each test gets its own in-memory SQLite database.

## Repository vs Active-Record Model

Both are valid; the choice depends on how big the data access surface is.

| Active-Record `Model` | Repository |
|---|---|
| One class per table, CRUD lives on the class. | Each repository may span multiple tables (joins, aggregates). |
| Reaches for the facade by default; opt-in `$credentials` for secondary DBs. | Takes the database via the constructor — explicit. |
| Soft deletes, timestamps, gates baked in. | You implement those yourself if you need them. |
| Best for "row in, row out" CRUD. | Best for domain operations that span tables. |

A common shape: most tables get a `Model`; one or two heavy domain operations (the order-checkout pipeline, the report generator) get a dedicated repository that composes multiple Models or talks directly to `DatabaseInterface`.

## Variations

### Inject an interface, not the class

```php
namespace App\Repository;

interface UsersReadRepository
{
    public function findByEmail(string $email): ?array;
    public function findById(int $id): ?array;
}

final class UsersRepository implements UsersReadRepository
{
    // implementation as above
}
```

Now `UserService` depends on `UsersReadRepository` — a one-class read-only narrowing. Mocking it in a test is trivial; substituting it for an HTTP-backed read model later is also trivial.

### Add read-through caching

```php
final class CachedUsersRepository implements UsersReadRepository
{
    public function __construct(
        private readonly UsersReadRepository $inner,
        private readonly CacheInterface $cache,
    ) {
    }

    public function findById(int $id): ?array
    {
        return $this->cache->get('user:' . $id, fn () => $this->inner->findById($id));
    }
}
```

The decorator pattern is impossible with the active-record style; trivial with a repository interface.

## When NOT to use this pattern

For a 50-line script that does one INSERT, the facade is fine. The repository pattern earns its keep when you have a non-trivial number of consumers, multiple tests, or a need to swap implementations (read replica, in-memory test double, HTTP-backed read model).

## Next

- [Models](Models.md) — the alternative active-record shape.
- [Multiple Connections](Multiple-Connections.md) — when the repository needs a non-shared `DatabaseInterface`.
- [Recipe — Audit Log](Recipe-Audit-Log.md) — repository-shaped code in production use.
