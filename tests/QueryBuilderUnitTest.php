<?php
/**
 * QueryBuilderUnitTest.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitPHP\Database\QueryBuilder\QueryBuilder;
use InitPHP\Database\QueryBuilder\QueryBuilderInterface;

class QueryBuilderUnitTest extends \PHPUnit\Framework\TestCase
{

    protected QueryBuilderInterface $qb;

    protected function setUp(): void
    {
        $this->qb = new QueryBuilder();
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

        $expected = 'INSERT INTO post (title, content, author, status) VALUES ("Post Title", "Post Content", 5, 1);';
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

        $expected = 'INSERT INTO post (title, content, author, status) VALUES ("Post Title #1", "Post Content #1", 5, 1), ("Post Title #2", "Post Content #2", NULL, 0);';
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

        $expected = 'UPDATE post SET title = "New Title", status = 0 WHERE status = 1 LIMIT 5';

        $this->assertEquals($expected, $this->qb->updateQuery($data));
        $this->qb->reset();
    }

    public function testDeleteStatementBuild()
    {
        $this->qb->from('post')
            ->where('authorId', 5)
            ->limit(100);

        $expected = 'DELETE FROM post WHERE authorId = 5 LIMIT 100';

        $this->assertEquals($expected, $this->qb->deleteQuery());
        $this->qb->reset();
    }

    public function testWhereSQLFunctionStatementBuild()
    {
        $this->qb->from('post')
            ->andBetween('date', ['2022-05-07', 'CURDATE()']);

        $expected = 'SELECT * FROM post WHERE date BETWEEN "2022-05-07" AND CURDATE()';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testWhereRegexpSQLStatementBuild()
    {
        $this->qb->from('post')
            ->regexp('title', '^M[a-z]K$');

        $expected = 'SELECT * FROM post WHERE title REGEXP "^M[a-z]K$"';

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

        $expected = 'SELECT post.title, COALESCE(stat.view, 0) AS view FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = 5';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();

        $this->qb->select('post.title')
            ->selectCoalesce('stat.view as view', 'post.view')
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, post.view) AS view FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = 5';

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

        $expected = 'SELECT p.title, s.view AS s_view FROM post AS p LEFT JOIN stat AS s ON s.id=p.id WHERE p.id = 5';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();

        $this->qb->select('p.title')
            ->select('s.view as s_view')
            ->from('post p')
            ->leftJoin('stat s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view AS s_view FROM post AS p LEFT JOIN stat AS s ON s.id=p.id WHERE p.id = 5';

        $this->assertEquals($expected, $this->qb->readQuery());
        $this->qb->reset();
    }

    public function testQBbuildQueryMethod()
    {
        $query = $this->qb->buildQuery([
            'table'     => 'post',
            'fields'    => [
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
            ],
        ])->insertQuery();
        $expected = 'INSERT INTO post (title, content, author, status) VALUES ("Post Title #1", "Post Content #1", 5, 1), ("Post Title #2", "Post Content #2", NULL, 0);';
        $this->assertEquals($expected, $query);


        $query = $this->qb->buildQuery([
            'select'    => 'id',
            'table'     => 'book',
            'conditions'    => [
                'author'    => 12
            ],
            'offset'    => 25,
            'limit'     => 20,
        ])->readQuery();
        $expected = 'SELECT id FROM book WHERE author = :author LIMIT 25, 20';
        $this->assertEquals($expected, $query);

        $this->qb->select('name, title');
        $query = $this->qb->buildQuery([
            'select'    => 'id',
            'table'     => 'book',
            'conditions'    => [
                'author'    => 12
            ],
            'offset'    => 25,
            'limit'     => 20,
        ], false)->readQuery();
        $this->qb->reset(); // isReset argümanı false olarak tanımlandığı için öncesindeki select yönteminin eklemlerini resetler.
        $expected = 'SELECT name, title, id FROM book WHERE author = :author LIMIT 25, 20';
        $this->assertEquals($expected, $query);

        $query = $this->qb->buildQuery([
            'table'     => 'post',
            'fields'    => [
                'title' => 'Yeni Title',
                'id'    => 14
            ],
            'primary_key'   => 'id'
        ])->updateQuery();
        $expected = 'UPDATE post SET title = :title WHERE id = :id';
        $this->assertEquals($expected, $query);

        $this->qb->where('status', 1);
        $query = $this->qb->buildQuery([
            'table'     => 'post',
            'fields'    => [
                'title' => 'Yeni Title',
                'id'    => 14
            ],
            'primary_key'   => 'id'
        ], false)->updateQuery();
        $this->qb->reset();
        $expected = 'UPDATE post SET title = :title WHERE status = 1 AND id = :id';
        $this->assertEquals($expected, $query);

        $query = $this->qb->buildQuery([
            'table'         => 'post',
            'conditions'    => [
                'id'        => 15,
                'status'    => 0
            ]
        ])->deleteQuery();
        $expected = 'DELETE FROM post WHERE id = :id AND status = :status';
        $this->assertEquals($expected, $query);

        $this->qb->is('deleted_at', null);
        $query = $this->qb->buildQuery([
            'table'         => 'post',
            'conditions'    => [
                'id'        => 15,
                'status'    => 0
            ]
        ], false)->deleteQuery();
        $this->qb->reset();
        $expected = 'DELETE FROM post WHERE deleted_at IS NULL AND id = :id AND status = :status';
        $this->assertEquals($expected, $query);
    }
}
