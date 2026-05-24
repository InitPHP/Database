<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitPHP\Database\Database;
use PHPUnit\Framework\TestCase;
use Test\InitPHP\Database\Support\SqliteHelper;

/**
 * Regression coverage for a class of bugs the 2.x line shipped with: a column
 * named after a reserved SQL keyword ({@code order}, {@code select}, …) would
 * crash UPDATE / SELECT because the compiler emitted the identifier bare.
 *
 * Upstream's {@code InitORM\QueryBuilder\Drivers\AbstractDriver::escapeIdentifier()}
 * now quotes every identifier driver-specifically (backticks on MySQL/SQLite,
 * double quotes on PostgreSQL), so this test asserts the fix stays in place.
 * If a future upstream release skips the quoting, these tests fire first.
 *
 * @see https://github.com/InitPHP/Database/issues — original v2 report.
 */
final class ReservedKeywordRegressionTest extends TestCase
{
    private ConnectionInterface $connection;

    private DatabaseInterface $db;

    protected function setUp(): void
    {
        $this->connection = SqliteHelper::makeConnection();
        $this->db         = new Database($this->connection);

        // `order`, `select`, `from`, `where` are all reserved in standard SQL.
        // SQLite tolerates them inside double quotes when defined.
        $this->connection->getPDO()->exec(
            'CREATE TABLE posts (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                title    TEXT,
                "order"  INTEGER,
                "select" TEXT
            )'
        );
        $this->connection->getPDO()->exec(
            "INSERT INTO posts (title, \"order\", \"select\") VALUES
                ('First',  1, 'a'),
                ('Second', 2, 'b'),
                ('Third',  3, 'c')"
        );
    }

    public function testUpdateAcceptsReservedKeywordColumnInTheSetMap(): void
    {
        $this->db->where('id', 1)->update('posts', [
            'title' => 'Renamed',
            'order' => 10,
        ]);

        $row = $this->db
            ->select('id', 'title', 'order')
            ->from('posts')
            ->where('id', 1)
            ->read()
            ->asAssoc()
            ->row();

        self::assertIsArray($row);
        self::assertSame('Renamed', $row['title']);
        self::assertSame(10, (int) $row['order']);
    }

    public function testSelectAcceptsReservedKeywordColumnInTheProjection(): void
    {
        $rows = $this->db
            ->select('id', 'order', 'select')
            ->from('posts')
            ->read()
            ->asAssoc()
            ->rows();

        self::assertCount(3, $rows);
        self::assertArrayHasKey('order', $rows[0]);
        self::assertArrayHasKey('select', $rows[0]);
    }

    public function testWhereAcceptsReservedKeywordColumn(): void
    {
        $rows = $this->db
            ->select('id', 'title')
            ->from('posts')
            ->where('order', '>', 1)
            ->read()
            ->asAssoc()
            ->rows();

        self::assertCount(2, $rows);
    }

    public function testOrderByAcceptsReservedKeywordColumn(): void
    {
        $rows = $this->db
            ->select('id', 'title')
            ->from('posts')
            ->orderBy('order', 'DESC')
            ->read()
            ->asAssoc()
            ->rows();

        self::assertSame('Third', $rows[0]['title']);
        self::assertSame('First', $rows[2]['title']);
    }

    public function testCompiledSqlActuallyQuotesTheIdentifier(): void
    {
        $this->db->enableQueryLog();
        $this->db->where('id', 1)->update('posts', ['order' => 5]);

        $logs = $this->db->getQueryLogs();
        self::assertNotEmpty($logs);
        self::assertStringContainsString(
            '`order`',
            $logs[0]['query'],
            'Reserved identifiers must be emitted in their quoted form. If this assertion fails, '
            . 'upstream initorm/query-builder stopped escaping identifiers — file a bug there before '
            . 'shipping a release.'
        );
    }
}
