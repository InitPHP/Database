<?php
/**
 * QueryBuilderUnitTest.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Database
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt  GNU GPL 3.0
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitPHP\Database\DB;
use InitPHP\Database\Interfaces\QueryBuilderInterface;

class QueryBuilderUnitTest extends \PHPUnit\Framework\TestCase
{
    protected QueryBuilderInterface $db;

    protected function setUp(): void
    {
        $this->db = new DB(['prefix' => 'p_']);
        parent::setUp();
    }


    public function testSelectBuilder()
    {
        $this->db->select('id', 'name');
        $this->db->from('user');

        $expected = "SELECT id, name FROM p_user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testBlankBuild()
    {
        $this->db->from('post');

        $expected = 'SELECT * FROM p_post';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testSelfJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->selfJoin('user', 'user.id=post.user');

        $expected = "SELECT p_post.id, p_post.title, p_user.name AS authorName FROM p_post, p_user WHERE p_user.id=p_post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testInnerJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->innerJoin('user', 'user.id=post.user');

        $expected = "SELECT p_post.id, p_post.title, p_user.name AS authorName FROM p_post INNER JOIN p_user ON p_user.id=p_post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testLeftJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftJoin('user', 'user.id=post.user');

        $expected = "SELECT p_post.id, p_post.title, p_user.name AS authorName FROM p_post LEFT JOIN p_user ON p_user.id=p_post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testRightJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightJoin('user', 'user.id=post.user');

        $expected = "SELECT p_post.id, p_post.title, p_user.name AS authorName FROM p_post RIGHT JOIN p_user ON p_user.id=p_post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testLeftOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT p_post.id, p_post.title, p_user.name AS authorName FROM p_post LEFT OUTER JOIN p_user ON p_user.id=p_post.user";

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testRightOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT p_post.id, p_post.title, p_user.name AS authorName FROM p_post RIGHT OUTER JOIN p_user ON p_user.id=p_post.user";

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

        $expected = 'SELECT * FROM p_post, p_user WHERE p_user.id=p_post.user AND p_post.status = 1 AND (p_user.group = "admin" OR p_user.group = "editor" AND (p_post.publish = 1 AND p_user.status = 1))';

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

        $expected = 'SELECT typeId, COUNT(*) FROM p_book GROUP BY typeId HAVING typeId IN (1, 2, 3)';

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

        $expected = 'SELECT id FROM p_book WHERE id = 10 AND type != 1 && status = 1 OR author = 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->limit(5);

        $expected = 'SELECT id FROM p_book LIMIT 5';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testOffsetStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(5);

        // Offset is specified If no limit is specified; The limit is 1000.
        $expected = 'SELECT id FROM p_book LIMIT 5, 1000';

        $this->assertEquals($expected, $this->db->selectStatementBuild());
        $this->db->clear();
    }

    public function testOffsetLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(50)
            ->limit(25);

        $expected = 'SELECT id FROM p_book LIMIT 50, 25';

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
        $expected = 'SELECT id FROM p_book LIMIT 25, 20';

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

        $expected = 'SELECT name FROM p_book ORDER BY authorId ASC, id DESC LIMIT 10';

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

        $expected = 'INSERT INTO p_post (title, content, author, status) VALUES ("Post Title", "Post Content", 5, 1);';
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

        $expected = 'INSERT INTO p_post (title, content, author, status) VALUES ("Post Title #1", "Post Content #1", 5, 1), ("Post Title #2", "Post Content #2", NULL, 0);';
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

        $expected = 'UPDATE p_post SET title = "New Title", status = 0 WHERE status = 1 LIMIT 5';

        $this->assertEquals($expected, $this->db->updateStatementBuild($data));
        $this->db->clear();
    }

    public function testDeleteStatementBuild()
    {
        $this->db->from('post')
            ->where('authorId', 5)
            ->limit(100);

        $expected = 'DELETE FROM p_post WHERE authorId = 5 LIMIT 100';

        $this->assertEquals($expected, $this->db->deleteStatementBuild());
        $this->db->clear();
    }

}
