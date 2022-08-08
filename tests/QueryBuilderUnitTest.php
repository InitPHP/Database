<?php
declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitPHP\Database\DB;
use \InitPHP\Database\QueryBuilder\{QueryBuilder, QueryBuilderInterface};

class QueryBuilderUnitTest extends \PHPUnit\Framework\TestCase
{

    private DB $db;
    protected QueryBuilderInterface $qb;

    protected function setUp(): void
    {
        $this->db = new DB([]);
        $this->qb = new QueryBuilder($this->db);
        parent::setUp();
    }

    public function testSelectBuilder()
    {
        $this->qb->select('id', 'name');
        $this->qb->table('user');

        $expected = "SELECT id, name FROM user WHERE 1";

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testBlankBuild()
    {
        $this->qb->from('post');

        $expected = 'SELECT * FROM post WHERE 1';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testSelfJoinBuild()
    {
        $this->qb->select('post.id', 'post.title', 'user.name as authorName');
        $this->qb->table('post');
        $this->qb->selfJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post, user WHERE user.id=post.user";

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testInnerJoinBuild()
    {
        $this->qb->select('post.id', 'post.title', 'user.name as authorName');
        $this->qb->from('post');
        $this->qb->innerJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post INNER JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testLeftJoinBuild()
    {
        $this->qb->select('post.id', 'post.title', 'user.name as authorName');
        $this->qb->from('post');
        $this->qb->leftJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post LEFT JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testRightJoinBuild()
    {
        $this->qb->select('post.id', 'post.title', 'user.name as authorName');
        $this->qb->from('post');
        $this->qb->rightJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post RIGHT JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testLeftOuterJoinBuild()
    {
        $this->qb->select('post.id', 'post.title', 'user.name as authorName');
        $this->qb->from('post');
        $this->qb->leftOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post LEFT OUTER JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testRightOuterJoinBuild()
    {
        $this->qb->select('post.id', 'post.title', 'user.name as authorName');
        $this->qb->from('post');
        $this->qb->rightOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post RIGHT OUTER JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testLimitStatement()
    {
        $this->qb->select('id')
            ->from('book')
            ->limit(5);

        $expected = 'SELECT id FROM book WHERE 1 LIMIT 5';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testOffsetStatement()
    {
        $this->qb->select('id')
            ->from('book')
            ->offset(5);

        // Offset is specified If no limit is specified; The limit is 1000.
        $expected = 'SELECT id FROM book WHERE 1 LIMIT 5, 1000';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testOffsetLimitStatement()
    {
        $this->qb->select('id')
            ->from('book')
            ->offset(50)
            ->limit(25);

        $expected = 'SELECT id FROM book WHERE 1 LIMIT 50, 25';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testNegativeOffsetLimitStatement()
    {
        $this->qb->select('id')
            ->from('book')
            ->offset(-25)
            ->limit(-20);

        // If limit and offset are negative integers, their absolute values are taken.
        $expected = 'SELECT id FROM book WHERE 1 LIMIT 25, 20';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testSelectDistinctStatement()
    {
        $this->qb->selectDistinct('name')
            ->from('book');
        $expected = 'SELECT DISTINCT(name) FROM book WHERE 1';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();

        $this->qb->selectDistinct('author.name')
            ->from('book')
            ->innerJoin('author', 'author.id=book.author');
        $expected = 'SELECT DISTINCT(author.name) FROM book INNER JOIN author ON author.id=book.author WHERE 1';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testOrderByStatement()
    {
        $this->qb->select('name')
            ->from('book')
            ->orderBy('authorId', 'ASC')
            ->orderBy('id', 'DESC')
            ->limit(10);

        $expected = 'SELECT name FROM book WHERE 1 ORDER BY authorId ASC, id DESC LIMIT 10';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testInsertStatementBuild()
    {
        $this->qb->from('post');

        $data = [
            'title'     => 'Post Title',
            'content'   => 'Post Content',
            'author'    => 5,
            'status'    => true,
        ];

        $expected = 'INSERT INTO post (title, content, author, status) VALUES (:title, :content, :author, :status);';
        $this->assertEquals($expected, $this->qb->insertQuery($data));
        $this->qb->reset();
    }

    public function testMultiInsertStatementBuild()
    {
        $this->qb->from('post');

        $data = [
            [
                'title'     => 'Post Title #1',
                'content'   => 'Post Content #1',
                'author'    => 5,
                'status'    => true,
            ],
            [
                'title'     => 'Post Title #2',
                'content'   => 'Post Content #2',
                'status'    => false,
            ]
        ];

        $expected = 'INSERT INTO post (title, content, author, status) VALUES (:title, :content, :author, :status), (:title_1, :content_1, NULL, :status_1);';
        $this->assertEquals($expected, $this->qb->insertQuery($data));
        $this->qb->reset();
    }

    public function testUpdateStatementBuild()
    {
        $this->qb->from('post')
            ->where('status', true)
            ->limit(5);

        $data = [
            'title'     => 'New Title',
            'status'    => false,
        ];

        $expected = 'UPDATE post SET title = :title, status = :status_1 WHERE status = :status LIMIT 5';

        $this->assertEquals($expected, $this->qb->updateQuery($data));
        $this->qb->reset();
    }

    public function testDeleteStatementBuild()
    {
        $this->qb->from('post')
            ->where('authorId', 5)
            ->limit(100);

        $expected = 'DELETE FROM post WHERE authorId = :authorId LIMIT 100';

        $this->assertEquals($expected, $this->qb->deleteQuery());
        $this->qb->reset();
    }

    public function testWhereSQLFunctionStatementBuild()
    {
        $this->qb->from('post')
            ->andBetween('date', ['2022-05-07', 'CURDATE()']);

        $expected = 'SELECT * FROM post WHERE date BETWEEN :date_start AND CURDATE()';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testWhereRegexpSQLStatementBuild()
    {
        $this->qb->from('post')
            ->regexp('title', '^M[a-z]K$');

        $expected = 'SELECT * FROM post WHERE title REGEXP :title';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testSelectCoalesceSQLStatementBuild()
    {

        $this->qb->select('post.title')
            ->selectCoalesce('stat.view as view', 0)
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, 0) AS view FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = :postid';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
        $this->db->getDataMapper()->getParameters();

        $this->qb->select('post.title')
            ->selectCoalesce('stat.view as view', 'post.view')
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, post.view) AS view FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = :postid';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }


    public function testTableAliasSQLStatementBuild()
    {

        $this->qb->select('p.title')
            ->select('s.view as s_view')
            ->from('post as p')
            ->leftJoin('stat as s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view AS s_view FROM post AS p LEFT JOIN stat AS s ON s.id=p.id WHERE p.id = :pid';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
        $this->db->getDataMapper()->getParameters(); // parameter reset

        $this->qb->select('p.title')
            ->select('s.view as s_view')
            ->from('post p')
            ->leftJoin('stat s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view AS s_view FROM post AS p LEFT JOIN stat AS s ON s.id=p.id WHERE p.id = :pid';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

}
