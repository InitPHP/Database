<?php

declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitPHP\Database\DB;
use InitPHP\Database\Interfaces\QueryBuilderInterface;

class QueryBuilderUnitTest extends \PHPUnit\Framework\TestCase
{
    protected QueryBuilderInterface $db;

    protected function setUp(): void
    {
        $this->db = new DB([
            //Bu sadece test içindir. Normalde driver PDO tarafından sağlanır ve bu bilgi doğru apostrof için kullanılır.
            'driver'    => '_',
        ]);
        parent::setUp();
    }


    public function testSelectBuilder()
    {
        $this->db->select('id', 'name');
        $this->db->from('user');

        $expected = "SELECT id, name FROM user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testBlankBuild()
    {
        $this->db->from('post');

        $expected = 'SELECT * FROM post';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testSelfJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->selfJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post, user WHERE user.id=post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testInnerJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->innerJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post INNER JOIN user ON user.id=post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testLeftJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post LEFT JOIN user ON user.id=post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testRightJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post RIGHT JOIN user ON user.id=post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testLeftOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post LEFT OUTER JOIN user ON user.id=post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testRightOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post RIGHT OUTER JOIN user ON user.id=post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testWhereGrouping()
    {
        $this->db->from('post')
            ->selfJoin('user', 'user.id=post.user')
            ->group(function (QueryBuilderInterface $db) {
                $db->orWhere('user.group', 'admin');
                $db->orWhere('user.group', 'editor');
                $db->group(function (QueryBuilderInterface $db) {
                    $db->andWhere('post.publish', true);
                    $db->andWhere('user.status', true);
                });
            })
            ->andWhere('post.status', true);

        $expected = 'SELECT * FROM post, user WHERE user.id=post.user AND post.status = 1 AND (user.group = "admin" OR user.group = "editor" AND (post.publish = 1 AND user.status = 1))';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testHavingStatementBuild()
    {
        $this->db->select('typeId')
            ->selectCount('*')
            ->from('book')
            ->groupBy('typeId')
            ->having('typeId', [1,2,3], 'IN');

        $expected = 'SELECT typeId, COUNT(*) FROM book GROUP BY typeId HAVING typeId IN (1, 2, 3)';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testWhereInjectStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->andWhereInject('id = 10')
            ->andWhereInject('type != 1 && status = 1')
            ->orWhereInject('author = 5');

        $expected = 'SELECT id FROM book WHERE id = 10 AND type != 1 && status = 1 OR author = 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->limit(5);

        $expected = 'SELECT id FROM book LIMIT 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testOffsetStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(5);

        // Offset is specified If no limit is specified; The limit is 1000.
        $expected = 'SELECT id FROM book LIMIT 5, 1000';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testOffsetLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(50)
            ->limit(25);

        $expected = 'SELECT id FROM book LIMIT 50, 25';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testNegativeOffsetLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(-25)
            ->limit(-20);

        // If limit and offset are negative integers, their absolute values are taken.
        $expected = 'SELECT id FROM book LIMIT 25, 20';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testSelectDistinctStatement()
    {
        $this->db->selectDistinct('name')
            ->from('book');
        $expected = 'SELECT DISTINCT(name) FROM book';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();

        $this->db->selectDistinct('author.name')
                    ->from('book')
                    ->innerJoin('author', 'author.id=book.author');
        $expected = 'SELECT DISTINCT(author.name) FROM book INNER JOIN author ON author.id=book.author';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testOrderByStatement()
    {
        $this->db->select('name')
            ->from('book')
            ->orderBy('authorId', 'ASC')
            ->orderBy('id', 'DESC')
            ->limit(10);

        $expected = 'SELECT name FROM book ORDER BY authorId ASC, id DESC LIMIT 10';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
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

        $expected = 'INSERT INTO post (title, content, author, status) VALUES ("Post Title", "Post Content", 5, 1);';
        $this->assertEquals($expected, $this->db->insertStatementBuild($data));
        $this->db->clear();
    }

    public function testMultiInsertStatementBuild()
    {
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

        $expected = 'INSERT INTO post (title, content, author, status) VALUES ("Post Title #1", "Post Content #1", 5, 1), ("Post Title #2", "Post Content #2", NULL, 0);';
        $this->assertEquals($expected, $this->db->insertStatementBuild($data));
        $this->db->clear();
    }

    public function testUpdateStatementBuild()
    {
        $this->db->from('post')
            ->where('status', true)
            ->limit(5);

        $data = [
            'title'     => 'New Title',
            'status'    => false,
        ];

        $expected = 'UPDATE post SET title = "New Title", status = 0 WHERE status = 1 LIMIT 5';

        $this->assertEquals($expected, $this->db->updateStatementBuild($data));
        $this->db->clear();
    }

    public function testDeleteStatementBuild()
    {
        $this->db->from('post')
            ->where('authorId', 5)
            ->limit(100);

        $expected = 'DELETE FROM post WHERE authorId = 5 LIMIT 100';

        $this->assertEquals($expected, $this->db->deleteStatementBuild());
        $this->db->clear();
    }

    public function testWhereSQLFunctionStatementBuild()
    {
        $this->db->from('post')
            ->andBetween('date', ['2022-05-07', 'CURDATE()']);

        $expected = 'SELECT * FROM post WHERE date BETWEEN "2022-05-07" AND CURDATE()';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testWhereRegexpSQLStatementBuild()
    {
        $this->db->from('post')
            ->regexp('title', '^M[a-z]K$');

        $expected = 'SELECT * FROM post WHERE title REGEXP "^M[a-z]K$"';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testSelectCoalesceSQLStatementBuild()
    {

        $this->db->select('post.title')
            ->selectCoalesce('stat.view as view', 0)
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, 0) AS view FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();

        $this->db->select('post.title')
            ->selectCoalesce('stat.view as view', 'post.view')
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, post.view) AS view FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }


    public function testTableAliasSQLStatementBuild()
    {

        $this->db->select('p.title')
            ->select('s.view as s_view')
            ->from('post as p')
            ->leftJoin('stat as s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view AS s_view FROM post AS p LEFT JOIN stat AS s ON s.id=p.id WHERE p.id = 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();

        $this->db->select('p.title')
            ->select('s.view as s_view')
            ->from('post p')
            ->leftJoin('stat s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view AS s_view FROM post AS p LEFT JOIN stat AS s ON s.id=p.id WHERE p.id = 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

}
