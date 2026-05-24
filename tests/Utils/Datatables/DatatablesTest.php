<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitPHP\Database\Utils\Datatables;

use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitPHP\Database\Database;
use InitPHP\Database\Utils\Datatables\Datatables;
use InitPHP\Database\Utils\Datatables\RequestParser;
use PHPUnit\Framework\TestCase;
use Test\InitPHP\Database\Support\SqliteHelper;

/**
 * End-to-end {@see Datatables} behaviour against an in-memory SQLite. The
 * suite intentionally exercises the bugs the 4.x audit uncovered (B5–B12)
 * so any regression would surface immediately.
 */
final class DatatablesTest extends TestCase
{
    private ConnectionInterface $connection;

    private DatabaseInterface $db;

    protected function setUp(): void
    {
        $this->connection = SqliteHelper::makeConnection();
        $this->db         = new Database($this->connection);
        SqliteHelper::seedUsers($this->connection);
    }

    public function testEmptyRequestReturnsAllRowsWithCorrectTotals(): void
    {
        $datatables = new Datatables($this->db, new RequestParser([]));
        $datatables
            ->from('users')
            ->setColumns('id', 'name', 'email', 'active', 'score');

        $response = $datatables->toArray();

        self::assertSame(3, $response['recordsTotal']);
        self::assertSame(3, $response['recordsFiltered']);
        self::assertCount(3, $response['data']);
    }

    public function testDrawValueIsEchoedBack(): void
    {
        $datatables = new Datatables($this->db, new RequestParser(['draw' => '17']));
        $datatables->from('users')->setColumns('id', 'name');

        self::assertSame(17, $datatables->toArray()['draw']);
    }

    /**
     * B12 regression — when search is active, recordsFiltered MUST diverge
     * from recordsTotal so client-side pagination sees the smaller filtered
     * set.
     */
    public function testRecordsFilteredIsComputedIndependentlyOfRecordsTotal(): void
    {
        $request    = new RequestParser([
            'search' => ['value' => 'Alice'],
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->setColumns('id', 'name', 'email');

        $response = $datatables->toArray();

        self::assertSame(3, $response['recordsTotal'], 'recordsTotal must ignore the search filter');
        self::assertSame(1, $response['recordsFiltered'], 'recordsFiltered must reflect the search filter');
        self::assertCount(1, $response['data']);
        self::assertSame('Alice', $response['data'][0]['name']);
    }

    public function testGlobalSearchMatchesAcrossAllRegisteredColumns(): void
    {
        $request    = new RequestParser([
            'search' => ['value' => 'example.com'],
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->setColumns('id', 'name', 'email');

        $response = $datatables->toArray();

        self::assertSame(3, $response['recordsFiltered']);
    }

    public function testColumnsWithNullDbDoNotParticipateInSearch(): void
    {
        $request    = new RequestParser([
            'search' => ['value' => 'will-not-match-anything'],
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->setColumns(null, 'name'); // first slot is a render-only column

        $response = $datatables->toArray();

        self::assertSame(0, $response['recordsFiltered']);
    }

    public function testClientSuppliedOrderIsApplied(): void
    {
        $request    = new RequestParser([
            'order' => [['column' => 4, 'dir' => 'asc']], // score column
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->setColumns('id', 'name', 'email', 'active', 'score');

        $rows = $datatables->toArray()['data'];

        self::assertSame('Bob', $rows[0]['name'], 'lowest score (13) should come first under ASC order');
        self::assertSame('Carol', $rows[2]['name']);
    }

    /**
     * B5 + B6 regression — pagination payload arrives as strings on real
     * HTTP requests; the helper must coerce them without raising TypeError
     * or notices.
     */
    public function testPaginationArgumentsAreCoercedFromStringPayload(): void
    {
        $request    = new RequestParser([
            'start'  => '1',
            'length' => '2',
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->setColumns('id', 'name');

        $rows = $datatables->toArray()['data'];

        self::assertCount(2, $rows);
        self::assertSame('Bob', $rows[0]['name']);
        self::assertSame('Carol', $rows[1]['name']);
    }

    public function testLengthOfMinusOneReturnsAllRows(): void
    {
        $request    = new RequestParser([
            'start'  => '0',
            'length' => '-1',
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->setColumns('id', 'name');

        self::assertCount(3, $datatables->toArray()['data']);
    }

    public function testRendererTransformsTheReturnedData(): void
    {
        $datatables = new Datatables($this->db, new RequestParser([]));
        $datatables
            ->from('users')
            ->setColumns('id', 'name')
            ->addRender('name', fn (string $name): string => '*' . $name . '*');

        $rows = $datatables->toArray()['data'];
        $names = array_column($rows, 'name');
        sort($names);

        self::assertSame(['*Alice*', '*Bob*', '*Carol*'], $names);
    }

    public function testCapturedOrderByIsResetByDefault(): void
    {
        $request    = new RequestParser([
            'order' => [['column' => 1, 'dir' => 'asc']], // name ASC
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->orderBy('score', 'DESC') // captured but should be reset
            ->setColumns('id', 'name', 'email', 'active', 'score');

        $rows = $datatables->toArray()['data'];

        self::assertSame('Alice', $rows[0]['name'], 'client order must win over captured order by default');
    }

    public function testOrderBySaveKeepsTheCapturedOrderAlongsideClientOrder(): void
    {
        $request    = new RequestParser([
            'order' => [['column' => 1, 'dir' => 'asc']], // name ASC
        ]);
        $datatables = new Datatables($this->db, $request);
        $datatables
            ->from('users')
            ->orderBy('active', 'DESC') // captured; preserved
            ->orderBySave()
            ->setColumns('id', 'name', 'email', 'active', 'score');

        $rows = $datatables->toArray()['data'];

        // active=1 rows must come before active=0; within active=1, name ASC.
        self::assertSame(1, (int) $rows[0]['active']);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame(1, (int) $rows[1]['active']);
        self::assertSame('Carol', $rows[1]['name']);
        self::assertSame(0, (int) $rows[2]['active']);
    }

    public function testGroupByDrivesRecordsCountViaCountDistinct(): void
    {
        SqliteHelper::seedPostsForGrouping($this->connection);

        $datatables = new Datatables($this->db, new RequestParser([]));
        $datatables
            ->from('posts')
            ->groupBy('user_id')
            ->setColumns('user_id');

        $response = $datatables->toArray();

        // Three distinct user_ids in the seed.
        self::assertSame(3, $response['recordsTotal']);
        self::assertSame(3, $response['recordsFiltered']);
    }

    /**
     * B8 regression — groupBy(['a', 'b']) used to crash because the helper
     * fed the array directly into selectCountDistinct. With the fix the call
     * is simply skipped (we fall back to COUNT(*)).
     */
    public function testGroupByWithArrayArgumentDoesNotCrash(): void
    {
        SqliteHelper::seedPostsForGrouping($this->connection);

        $datatables = new Datatables($this->db, new RequestParser([]));
        $datatables
            ->from('posts')
            ->groupBy(['user_id'])
            ->setColumns('user_id');

        // Should not throw; the count is opaque here (driver-dependent) so
        // we only assert that the call completed and produced an envelope.
        $response = $datatables->toArray();
        self::assertArrayHasKey('recordsTotal', $response);
    }

    public function testToStringEmitsJsonEnvelope(): void
    {
        $datatables = new Datatables($this->db, new RequestParser(['draw' => 4]));
        $datatables->from('users')->setColumns('id', 'name');

        $json = (string) $datatables;
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertSame(4, $decoded['draw']);
        self::assertSame(3, $decoded['recordsTotal']);
    }

    public function testPostEchoesTheFullRequestPayload(): void
    {
        $payload = ['draw' => 9, 'custom' => 'value', 'start' => '0'];
        $datatables = new Datatables($this->db, new RequestParser($payload));
        $datatables->from('users')->setColumns('id', 'name');

        self::assertSame($payload, $datatables->toArray()['post']);
    }

    public function testPermanentSelectIsAppliedOnEverySelect(): void
    {
        $datatables = new Datatables($this->db, new RequestParser([]));
        $datatables
            ->from('users')
            ->addPermanentSelect('name', 'email')
            ->setColumns('name', 'email');

        $rows = $datatables->toArray()['data'];

        self::assertArrayHasKey('name', $rows[0]);
        self::assertArrayHasKey('email', $rows[0]);
    }
}
