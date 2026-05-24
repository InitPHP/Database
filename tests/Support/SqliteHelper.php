<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitPHP\Database\Support;

use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Connection;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitPHP\Database\Database;

/**
 * In-memory SQLite test helper. Each {@see makeConnection()} call returns a
 * brand-new connection — SQLite's {@code :memory:} database is per-handle, so
 * the schema/data must be seeded against the same handle the test uses.
 */
final class SqliteHelper
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function makeConnection(array $overrides = []): ConnectionInterface
    {
        return new Connection(array_merge([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'charset'  => '',
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function makeDatabase(array $overrides = []): DatabaseInterface
    {
        return new Database(self::makeConnection($overrides));
    }

    /**
     * Seed a {@code users} table with a fixed three-row fixture suitable for
     * filter / order / paging tests.
     */
    public static function seedUsers(ConnectionInterface $connection): void
    {
        $pdo = $connection->getPDO();
        $pdo->exec(
            'CREATE TABLE users (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                name   TEXT    NOT NULL,
                email  TEXT    NOT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                score  INTEGER
            )'
        );
        $pdo->exec(
            "INSERT INTO users (name, email, active, score) VALUES
                ('Alice', 'alice@example.com', 1, 42),
                ('Bob',   'bob@example.com',   0, 13),
                ('Carol', 'carol@example.com', 1, 99)"
        );
    }

    /**
     * Seed a {@code posts} table with a fixture that exercises grouping —
     * three users own one or more posts each.
     */
    public static function seedPostsForGrouping(ConnectionInterface $connection): void
    {
        $pdo = $connection->getPDO();
        $pdo->exec(
            'CREATE TABLE posts (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title   TEXT    NOT NULL
            )'
        );
        $pdo->exec(
            "INSERT INTO posts (user_id, title) VALUES
                (1, 'Alice first'),
                (1, 'Alice second'),
                (2, 'Bob only'),
                (3, 'Carol first'),
                (3, 'Carol second'),
                (3, 'Carol third')"
        );
    }
}
