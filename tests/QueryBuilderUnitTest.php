<?php
declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitPHP\Database\Database;
use InitPHP\Database\Helpers\Parameters;

class QueryBuilderUnitTest extends \PHPUnit\Framework\TestCase
{

    protected Database $db;

    protected function setUp(): void
    {
        $this->db = new Database();
        parent::setUp();
    }

    public function testSelectBuilder()
    {
        $this->db->select('id', 'name');
        $this->db->table('user');

        $expected = "SELECT id, name FROM user WHERE 1";

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testBlankBuild()
    {
        $this->db->from('post');

        $expected = 'SELECT * FROM post WHERE 1';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testSelfJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name AS authorName')
            ->table('post')
            ->selfJoin('user', 'user.id = post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post, user WHERE user.id = post.user";

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testInnerJoinBuild()
    {
        $this->db->select('post.id, post.title', 'user.name as authorName')
            ->from('post')
            ->innerJoin('user', 'user.id = post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post INNER JOIN user ON user.id = post.user WHERE 1";

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testLeftJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post LEFT JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testRightJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post RIGHT JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testLeftOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post LEFT OUTER JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testRightOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post RIGHT OUTER JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->limit(5);

        $expected = 'SELECT id FROM book WHERE 1 LIMIT 5';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testOffsetStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(5);

        // Offset is specified If no limit is specified; The limit is 10000.
        $expected = 'SELECT id FROM book WHERE 1 LIMIT 5, 10000';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testOffsetLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(50)
            ->limit(25);

        $expected = 'SELECT id FROM book WHERE 1 LIMIT 50, 25';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testNegativeOffsetLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(-25)
            ->limit(-20);

        // If limit and offset are negative integers, their absolute values are taken.
        $expected = 'SELECT id FROM book WHERE 1 LIMIT 25, 20';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testSelectDistinctStatement()
    {
        $this->db->selectDistinct('name')
            ->from('book');
        $expected = 'SELECT DISTINCT(name) FROM book WHERE 1';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();

        $this->db->selectDistinct('author.name')
            ->from('book')
            ->innerJoin('author', 'author.id=book.author');
        $expected = 'SELECT DISTINCT(author.name) FROM book INNER JOIN author ON author.id=book.author WHERE 1';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testOrderByStatement()
    {
        $this->db->select('name')
            ->from('book')
            ->orderBy('authorId', 'ASC')
            ->orderBy('id', 'DESC')
            ->limit(10);

        $expected = 'SELECT name FROM book WHERE 1 ORDER BY authorId ASC, id DESC LIMIT 10';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testInsertStatementBuild()
    {
        $this->db->from('post');

        $data = [
            'title'     => 'Post Title',
            'content'   => 'Post Content',
            'author'    => 5,
            'status'    => true,
        ];

        $expected = 'INSERT INTO post (title, content, author, status) VALUES (:title, :content, :author, :status);';
        $this->assertEquals($expected, $this->db->_insertQuery($data));
        $this->db->reset();
    }

    public function testMultiInsertStatementBuild()
    {
        Parameters::reset();
        $this->db->from('post');

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
        $this->assertEquals($expected, $this->db->_insertQuery($data));
        $this->db->reset();
    }

    public function testUpdateStatementBuild()
    {
        Parameters::reset();
        $this->db->from('post')
            ->where('status', true)
            ->limit(5);

        $data = [
            'title'     => 'New Title',
            'status'    => false,
        ];

        $expected = 'UPDATE post SET title = :title, status = :status_1 WHERE status = :status LIMIT 5';

        $this->assertEquals($expected, $this->db->_updateQuery($data));
        $this->db->reset();
    }

    public function testDeleteStatementBuild()
    {
        Parameters::reset();
        $this->db->from('post')
            ->where('authorId', 5)
            ->limit(100);

        $expected = 'DELETE FROM post WHERE authorId = :authorId LIMIT 100';

        $this->assertEquals($expected, $this->db->_deleteQuery());
        $this->db->reset();
    }

    public function testWhereSQLFunctionStatementBuild()
    {
        $this->db->from('post')
            ->andBetween('date', ['2022-05-07', 'CURDATE()']);

        $expected = 'SELECT * FROM post WHERE date BETWEEN :date_start AND CURDATE()';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testWhereRegexpSQLStatementBuild()
    {
        $this->db->from('post')
            ->regexp('title', '^M[a-z]K$');

        $expected = 'SELECT * FROM post WHERE title REGEXP :title';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

    public function testSelectCoalesceSQLStatementBuild()
    {

        Parameters::reset();
        $this->db->select('post.title')
            ->selectCoalesce('stat.view', 0)
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, 0) FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = :postid';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();

        Parameters::reset();
        $this->db->select('post.title')
            ->selectCoalesce('stat.view', 'post.view', 'views')
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, post.view) AS views FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = :postid';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }


    public function testTableAliasSQLStatementBuild()
    {

        Parameters::reset();
        $this->db->select('p.title')
            ->select('s.view as s_view')
            ->from('post as p')
            ->leftJoin('stat as s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view as s_view FROM post as p LEFT JOIN stat as s ON s.id=p.id WHERE p.id = :pid';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();

        Parameters::reset();
        $this->db->select('p.title')
            ->select('s.view as s_view')
            ->from('post p')
            ->leftJoin('stat s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view as s_view FROM post p LEFT JOIN stat s ON s.id=p.id WHERE p.id = :pid';

        $this->assertEquals($expected, $this->db->_readQuery());
        $this->db->reset();
    }

}
