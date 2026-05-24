<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitPHP\Database\DB;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Test\InitPHP\Database\Support\SqliteHelper;

/**
 * Behavioural contract for the {@see DB} facade. The class is a pure static
 * facade with module-global state, so each test resets the slot via
 * {@see DB::replaceImmutable()} in {@see setUp()} / {@see tearDown()}.
 */
final class DBTest extends TestCase
{
    protected function setUp(): void
    {
        DB::replaceImmutable(null);
    }

    protected function tearDown(): void
    {
        DB::replaceImmutable(null);
    }

    public function testGetDatabaseThrowsBeforeCreateImmutable(): void
    {
        $this->expectException(DatabaseException::class);
        DB::getDatabase();
    }

    public function testCreateImmutableStoresAndReturnsTheInstance(): void
    {
        $database = DB::createImmutable(SqliteHelper::makeConnection());

        self::assertInstanceOf(DatabaseInterface::class, $database);
        self::assertSame($database, DB::getDatabase());
    }

    public function testCreateImmutableRejectsASecondCall(): void
    {
        DB::createImmutable(SqliteHelper::makeConnection());

        $this->expectException(DatabaseException::class);
        DB::createImmutable(SqliteHelper::makeConnection());
    }

    public function testReplaceImmutableSwapsTheStoredInstance(): void
    {
        $first  = DB::createImmutable(SqliteHelper::makeConnection());
        $second = DB::replaceImmutable(SqliteHelper::makeConnection());

        self::assertNotSame($first, $second);
        self::assertSame($second, DB::getDatabase());
    }

    public function testReplaceImmutableAcceptsAnExistingDatabaseInstance(): void
    {
        $database = SqliteHelper::makeDatabase();
        $stored   = DB::replaceImmutable($database);

        self::assertSame($database, $stored);
        self::assertSame($database, DB::getDatabase());
    }

    public function testReplaceImmutableWithNullClearsTheSlot(): void
    {
        DB::createImmutable(SqliteHelper::makeConnection());
        $cleared = DB::replaceImmutable(null);

        self::assertNull($cleared);
        $this->expectException(DatabaseException::class);
        DB::getDatabase();
    }

    public function testConnectReturnsAFreshDatabaseWithoutTouchingTheFacadeSlot(): void
    {
        $sideband = DB::connect(SqliteHelper::makeConnection());

        self::assertInstanceOf(DatabaseInterface::class, $sideband);
        // The facade slot stayed empty even though connect() was called.
        $this->expectException(DatabaseException::class);
        DB::getDatabase();
    }

    public function testCallStaticForwardsToTheUnderlyingDatabase(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        DB::createImmutable($connection);

        // numRows() is unreliable for SELECT on SQLite (see DataMapperInterface
        // docs); fetch and count instead.
        $rows = DB::select('name')->from('users')->where('active', '=', 1)->read()->asAssoc()->rows();

        self::assertCount(2, $rows);
    }

    public function testStaticFacadeCannotBeInstantiated(): void
    {
        $reflection  = new ReflectionClass(DB::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate(), 'DB constructor must be private.');
        self::assertTrue($reflection->isFinal(), 'DB facade must be final.');
    }
}
