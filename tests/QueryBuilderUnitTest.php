<?php
declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitPHP\Database\QueryBuilder\QueryBuilder;

class QueryBuilderUnitTest extends \PHPUnit\Framework\TestCase
{

    protected QueryBuilder $db;

    protected function setUp(): void
    {
        $this->db = new QueryBuilder();
        parent::setUp();
    }

    public function testSelectBuilder()
    {
        $this->db->select('id', 'name');
        $this->db->table('user');

        $expected = "SELECT id, name FROM user WHERE 1";

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testBlankBuild()
    {
        $this->db->from('post');

        $expected = 'SELECT * FROM post WHERE 1';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testSelfJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name AS authorName')
            ->table('post')
            ->selfJoin('user', 'user.id = post.user');

        $expected = "SELECT post.id, post.title, user.name AS authorName FROM post, user WHERE user.id = post.user";

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testInnerJoinBuild()
    {
        $this->db->select('post.id, post.title', 'user.name as authorName')
            ->from('post')
            ->innerJoin('user', 'user.id = post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post INNER JOIN user ON user.id = post.user WHERE 1";

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testLeftJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post LEFT JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testRightJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post RIGHT JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testLeftOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->leftOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post LEFT OUTER JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testRightOuterJoinBuild()
    {
        $this->db->select('post.id', 'post.title', 'user.name as authorName');
        $this->db->from('post');
        $this->db->rightOuterJoin('user', 'user.id=post.user');

        $expected = "SELECT post.id, post.title, user.name as authorName FROM post RIGHT OUTER JOIN user ON user.id=post.user WHERE 1";

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->limit(5);

        $expected = 'SELECT id FROM book WHERE 1 LIMIT 5';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testOffsetStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(5);

        $expected = 'SELECT id FROM book WHERE 1 OFFSET 5';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testOffsetLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(50)
            ->limit(25);

        $expected = 'SELECT id FROM book WHERE 1 LIMIT 50, 25';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testNegativeOffsetLimitStatement()
    {
        $this->db->select('id')
            ->from('book')
            ->offset(-25)
            ->limit(-20);

        // If limit and offset are negative integers, their absolute values are taken.
        $expected = 'SELECT id FROM book WHERE 1 LIMIT 25, 20';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testSelectDistinctStatement()
    {
        $this->db->selectDistinct('name')
            ->from('book');
        $expected = 'SELECT DISTINCT(name) FROM book WHERE 1';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testSelectDistinctJoinStatement()
    {
        $this->db->selectDistinct('author.name')
            ->from('book')
            ->innerJoin('author', 'author.id=book.author');
        $expected = 'SELECT DISTINCT(author.name) FROM book INNER JOIN author ON author.id=book.author WHERE 1';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testOrderByStatement()
    {
        $this->db->select('name')
            ->from('book')
            ->orderBy('authorId', 'ASC')
            ->orderBy('id', 'DESC')
            ->limit(10);

        $expected = 'SELECT name FROM book WHERE 1 ORDER BY authorId ASC, id DESC LIMIT 10';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
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
        $this->db->set($data);


        $expected = 'INSERT INTO post (title, content, author, status) VALUES (:title, :content, 5, :status);';
        $this->assertEquals($expected, $this->db->generateInsertQuery());
        $this->db->resetStructure();
    }

    public function testInsertBatchStatementBuild()
    {
        
        $this->db->from('post');

        $this->db->set([
                'title'     => 'Post Title #1',
                'content'   => 'Post Content #1',
                'author'    => 5,
                'status'    => true,
            ])
            ->set([
                'title'     => 'Post Title #2',
                'content'   => 'Post Content #2',
                'status'    => false,
            ]);

        $expected = 'INSERT INTO post (title, content, author, status) VALUES (:title, :content, 5, :status), (:title_1, :content_1, NULL, :status_1);';
        $this->assertEquals($expected, $this->db->generateBatchInsertQuery());
        $this->db->resetStructure();
    }

    public function testUpdateStatementBuild()
    {
        
        $this->db->from('post')
            ->where('status', '=', true)
            ->limit(5);

        $data = [
            'title'     => 'New Title',
            'status'    => false,
        ];
        $this->db->set($data);

        $expected = 'UPDATE post SET title = :title, status = :status_1 WHERE status = :status LIMIT 5';

        $this->assertEquals($expected, $this->db->generateUpdateQuery());
        $this->db->resetStructure();
    }

    public function testUpdateBatchStatementBuild()
    {
        
        $this->db->from('post')
            ->where('status', '=', true);

        $this->db->set([
            'id'        => 5,
            'title'     => 'New Title #5',
            'content'   => 'New Content #5',
        ])->set([
            'id'        => 10,
            'title'     => 'New Title #10',
        ]);

        $expected = 'UPDATE post SET title = CASE WHEN id = 5 THEN :title WHEN id = 10 THEN :title_1 ELSE title END, content = CASE WHEN id = 5 THEN :content ELSE content END WHERE status = :status AND id IN (5, 10)';

        $this->assertEquals($expected, $this->db->generateUpdateBatchQuery('id'));
        $this->db->resetStructure();
    }

    public function testDeleteStatementBuild()
    {
        
        $this->db->from('post')
            ->where('authorId', '=', 5)
            ->limit(100);

        $expected = 'DELETE FROM post WHERE authorId = 5 LIMIT 100';

        $this->assertEquals($expected, $this->db->generateDeleteQuery());
        $this->db->resetStructure();
    }

    public function testWhereSQLFunctionStatementBuild()
    {
        $this->db->from('post')
            ->andBetween('date', ['2022-05-07', 'CURDATE()']);

        $expected = 'SELECT * FROM post WHERE date BETWEEN :date AND CURDATE()';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testWhereRegexpSQLStatementBuild()
    {
        $this->db->from('post')
            ->regexp('title', '^M[a-z]K$');

        $expected = 'SELECT * FROM post WHERE title REGEXP :title';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testSelectCoalesceSQLStatementBuild()
    {
        $this->db->select('post.title')
            ->selectCoalesce('stat.view', 0)
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, 0) FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = 5';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testSelectCoalesceDefaultValue()
    {
        $this->db->select('post.title')
            ->selectCoalesce('stat.view', 'post.view', 'views')
            ->from('post')
            ->leftJoin('stat', 'stat.id=post.id')
            ->where('post.id', 5);

        $expected = 'SELECT post.title, COALESCE(stat.view, post.view) AS views FROM post LEFT JOIN stat ON stat.id=post.id WHERE post.id = 5';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }


    public function testTableAliasSQLStatementBuild()
    {
        $this->db->select('p.title')
            ->select('s.view as s_view')
            ->from('post as p')
            ->leftJoin('stat as s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view as s_view FROM post as p LEFT JOIN stat as s ON s.id=p.id WHERE p.id = 5';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testTableJoinAliasSQLStatementBuild()
    {
        $this->db->select('p.title')
            ->select('s.view as s_view')
            ->from('post p')
            ->leftJoin('stat s', 's.id=p.id')
            ->where('p.id', 5);

        $expected = 'SELECT p.title, s.view as s_view FROM post p LEFT JOIN stat s ON s.id=p.id WHERE p.id = 5';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testWhereGroupStatement()
    {
        $this->db->select('id')
            ->from('users')
            ->where('status', 1)
            ->group(function (QueryBuilder $builder) {
                $builder->where('type', 3)
                    ->where('type', 4);
            });

        $expected = 'SELECT id FROM users WHERE status = 1 AND (type = 3 AND type = 4)';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testWhereGroupMultipleStatement()
    {
        $this->db->select('id, title, content, url')
            ->from('posts')
            ->where('status', 1)
            ->group(function (QueryBuilder $db) {
                $db->where('user_id', 1)
                    ->where('datetime', '>=', date("Y-m-d"));
            }, 'or')
            ->group(function (QueryBuilder $db) {
                $db->group(function (QueryBuilder $db) {
                    $db->where('id', 2)
                        ->where('status', 3);
                }, 'or')
                    ->group(function (QueryBuilder $db) {
                        $db->where('id', 4)
                            ->where('status', 5);
                    }, 'or');
            }, 'or');

        $expected = 'SELECT id, title, content, url FROM posts WHERE status = 1 AND (user_id = 1 AND datetime >= :datetime) OR ((id = 2 AND status = 3) OR (id = 4 AND status = 5))';

        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }


    public function testJoinClosureGive()
    {
        
        $this->db->select('u.id', 'u.name', 'u.status, p.title')
            ->from('users AS u')
            ->where('u.status', 1)
            ->join('posts AS p', function (QueryBuilder $builder) {
                $builder->on('p.user_id', 'u.id')
                    ->where('p.publisher_time', '>=', $builder->raw('NOW()'));
            })
            ->join('categories AS c', function (QueryBuilder $builder) {
                $builder->on('c.id', 'p.category_id')
                    ->on('c.blog_id', 'u.blog_id')
                    ->where('c.status', 1)
                    ->having($builder->raw('COUNT(p.category_id) > 1'));
            })->limit(5);

        $expected = 'SELECT u.id, u.name, u.status, p.title FROM users AS u INNER JOIN posts AS p ON p.user_id = u.id INNER JOIN categories AS c ON c.id = p.category_id AND c.blog_id = u.blog_id WHERE u.status = 1 AND p.publisher_time >= NOW() AND c.status = 1 HAVING COUNT(p.category_id) > 1 LIMIT 5';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testSubQuery()
    {
        
        $this->db->select('u.name')
            ->from('users AS u')
            ->whereIn('u.id', $this->db->subQuery(function (QueryBuilder $builder) {
                $builder->select('id')
                    ->from('roles')
                    ->where('name', 'admin');
            }));
        $expected = 'SELECT u.name FROM users AS u WHERE u.id IN (SELECT id FROM roles WHERE name = :name)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

    public function testSubQueryJoinTable()
    {
        $this->db->select('u.name, p.title')
            ->from('users AS u')
            ->join($this->db->subQuery(function (QueryBuilder $builder) {
                $builder->select('id, title, user_id')
                    ->from('posts')
                    ->where('user_id', 5);
            }, 'p'), 'p.user_id = u.id', '');

        $expected = 'SELECT u.name, p.title FROM users AS u JOIN (SELECT id, title, user_id FROM posts WHERE user_id = 5) AS p ON p.user_id = u.id WHERE 1';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
        $this->db->resetStructure();
    }

}
