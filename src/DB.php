<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitPHP\Database;

use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;

/**
 * Static facade over a single shared {@see DatabaseInterface} instance —
 * convenient for application code that does not want to thread a Database
 * around. Call {@see self::createImmutable()} once during bootstrap and use
 * {@code DB::select(...)}, {@code DB::create(...)} etc. from anywhere.
 *
 * Calls that are not declared on this class fall through {@see __callStatic()}
 * to the shared {@see DatabaseInterface}, which itself forwards unknown calls
 * to its inner query builder — so the full CRUD + QueryBuilder surface is
 * available via the bare {@code DB::} prefix.
 *
 * @mixin DatabaseInterface
 */
final class DB
{
    private static ?DatabaseInterface $database = null;

    /**
     * Prevent instantiation — this class is a pure static facade.
     */
    private function __construct()
    {
    }

    /**
     * Build a Database from the supplied connection (array or
     * {@see ConnectionInterface}) and store it as the shared facade target.
     *
     * "Immutable" here means: once set, the shared instance cannot be silently
     * replaced. A second call throws {@see DatabaseException} — callers that
     * truly need to swap the connection should use {@see self::replaceImmutable()}.
     *
     * @param array<string, mixed>|ConnectionInterface $connection
     *
     * @throws DatabaseException When an immutable instance is already set.
     */
    public static function createImmutable(array|ConnectionInterface $connection): DatabaseInterface
    {
        if (self::$database !== null) {
            throw new DatabaseException(
                'An immutable Database instance has already been set. '
                . 'Call DB::replaceImmutable() to swap it explicitly.'
            );
        }

        self::$database = self::connect($connection);

        return self::$database;
    }

    /**
     * Explicitly replace the shared facade target. Use when an application
     * truly needs to reset the connection (e.g. between test cases).
     *
     * Pass {@code null} to clear the facade entirely.
     *
     * @param array<string, mixed>|ConnectionInterface|DatabaseInterface|null $connection
     */
    public static function replaceImmutable(
        array|ConnectionInterface|DatabaseInterface|null $connection
    ): ?DatabaseInterface {
        if ($connection === null) {
            self::$database = null;

            return null;
        }

        self::$database = $connection instanceof DatabaseInterface
            ? $connection
            : self::connect($connection);

        return self::$database;
    }

    /**
     * Build a fresh, non-facade Database. The returned instance does not touch
     * the shared facade slot — useful for working with secondary connections.
     *
     * @param array<string, mixed>|ConnectionInterface $connection
     */
    public static function connect(array|ConnectionInterface $connection): DatabaseInterface
    {
        return new Database($connection);
    }

    /**
     * The shared facade instance.
     *
     * @throws DatabaseException When no immutable instance has been set yet.
     */
    public static function getDatabase(): DatabaseInterface
    {
        if (self::$database === null) {
            throw new DatabaseException(
                'No immutable Database instance is configured. Call DB::createImmutable($connection) first.'
            );
        }

        return self::$database;
    }

    /**
     * Forward unknown static calls to the shared Database instance.
     *
     * @param array<int, mixed> $arguments
     *
     * @throws DatabaseException When no immutable instance is set, or when the
     *         underlying Database / query builder does not declare the method.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::getDatabase()->{$name}(...$arguments);
    }
}
