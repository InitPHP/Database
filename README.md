# InitPHP Database

Manage your database with or without abstraction. This library is built on the PHP PDO plugin and is mainly used to build and execute SQL queries.

[![Latest Stable Version](http://poser.pugx.org/initphp/database/v)](https://packagist.org/packages/initphp/database) [![Total Downloads](http://poser.pugx.org/initphp/database/downloads)](https://packagist.org/packages/initphp/database) [![Latest Unstable Version](http://poser.pugx.org/initphp/database/v/unstable)](https://packagist.org/packages/initphp/database) [![License](http://poser.pugx.org/initphp/database/license)](https://packagist.org/packages/initphp/database) [![PHP Version Require](http://poser.pugx.org/initphp/database/require/php)](https://packagist.org/packages/initphp/database)

## Requirements

- PHP 8.0 and later.
- PHP PDO extension.

## Supported Databases

This library should work correctly in almost any database that uses basic SQL syntax.
Databases supported by PDO and suitable drivers are available at [https://www.php.net/manual/en/pdo.drivers.php](https://www.php.net/manual/en/pdo.drivers.php).

## Installation

```
composer require initphp/database
```

## Usage

### QueryBuilder & DBAL and CRUD

```php
require_once "vendor/autoload.php";
use \InitPHP\Database\DB;

// Connection
DB::createImmutable([
    'dsn'       => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_general_ci',
]);
```

#### Create

```php
use \InitPHP\Database\DB;
$data = [
    'title'     => 'Post Title',
    'content'   => 'Post Content',
];

$isInsert = DB::create('post', $data);

/**
* This executes the following query.
* 
* INSERT INTO post 
* (title, content) 
* VALUES 
* ("Post Title", "Post Content");
*/
if($isInsert){
    // Success
}
```

##### Create Batch

```php
use \InitPHP\Database\DB;

$data = [
    [
        'title'     => 'Post Title 1',
        'content'   => 'Post Content 1',
        'author'    => 5
    ],
    [
        'title'     => 'Post Title 2',
        'content'   => 'Post Content 2'
    ],
];

$isInsert = DB::createBatch('post', $data);

/**
* This executes the following query.
* 
* INSERT INTO post 
* (title, content, author) 
* VALUES 
* ("Post Title 1", "Post Content 1", 5),
* ("Post Title 2", "Post Content 2", NULL);
*/

if($isInsert){
    // Success
}
```

#### Read

```php
use \InitPHP\Database\DB;


/**
* This executes the following query.
* 
* SELECT user.name AS author_name, post.id, post.title 
* FROM post, user 
* WHERE user.id = post.author AND post.status = 1
* ORDER BY post ASC, post.created_at DESC
* LIMIT 20, 10
*/

/** @var \InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface $res */
$res = DB::select('user.name as author_name', 'post.id', 'post.title')
    ->from('post')
    ->selfJoin('user', 'user.id=post.author')
    ->where('post.status', 1)
    ->orderBy('post.id', 'ASC')
    ->orderBy('post.created_at', 'DESC')
    ->offset(20)->limit(10)
    ->read();
    
if($res->numRows() > 0){
    $results = $res->asAssoc()
                    ->rows();
    foreach ($results as $row) {
        echo $row['title'] . ' by ' . $row['author_name'] . '<br />';
    }
}
```

#### Update

```php
use \InitPHP\Database\DB;
$data = [
    'title'     => 'New Title',
    'content'   => 'New Content',
];

$isUpdate = DB::where('id', 13)
                ->update('post', $data);
    
/**
* This executes the following query.
* 
* UPDATE post 
* SET title = "New Title", content = "New Content"
* WHERE id = 13
*/
if ($isUpdate) {
    // Success
}
```

##### Update Batch

```php
use \InitPHP\Database\DB;
$data = [
    [
        'id'        => 5,
        'title'     => 'New Title #5',
        'content'   => 'New Content #5',
    ],
    [
        'id'        => 10,
        'title'     => 'New Title #10',
    ]
];

$isUpdate = DB::where('status', '!=', 0)
                ->updateBatch('id', 'post', $data);
    
/**
* This executes the following query.
* 
* UPDATE post SET 
* 	title = CASE 
* 		WHEN id = 5 THEN 'New Title #5' 
* 		WHEN id = 10 THEN 'New Title #10' 
* 		ELSE title END, 
* 	content = CASE 
* 		WHEN id = 5 THEN 'New Content #5'
* 		ELSE content END 
* WHERE status != 0 AND id IN (5, 10)
*/
if ($isUpdate) {
    // Success
}
```

#### Delete

```php
use \InitPHP\Database\DB;

$isDelete = DB::where('id', 13)
                ->delete('post');
    
/**
* This executes the following query.
* 
* DELETE FROM post WHERE id = 13
*/
if ($isUpdate) {
    // Success
}
```

### RAW

```php
use \InitPHP\Database\DB;

/** @var \InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface $res */
$res = DB::query("SELECT id FROM post WHERE user_id = :id", [
    ':id'   => 5
]);
```

#### Builder for RAW

```php
use \InitPHP\Database\DB;

/** @var \InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface $res */
$res = DB::select(DB::raw("CONCAT(name, ' ', surname) AS fullname"))
        ->where(DB::raw("title = '' AND (status = 1 OR status = 0)"))
        ->limit(5)
        ->read('users');
        
/**
 * SELECT CONCAT(name, ' ', surname) AS fullname 
 * FROM users 
 * WHERE title = '' AND (status = 1 OR status = 0)
 * LIMIT 5
 */
$results = $res->asAssoc()
                ->rows();
foreach ($results as $row) {
    echo $row['fullname'];
}
```

#### Working With A Different Connection

This library was developed with the thought that you would work with a single database and connection, but I know that in some projects you work with more than one connection and database.

If you want to work with a different non-global connection, use the `connect()` method.

```php
use \InitPHP\Database\DB;

DB::connect([
    'dsn'       => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_general_ci',
]);
```

### Model and Entity

Model and Entity; are two common concepts used in database abstraction. To explain these two concepts in the roughest way; 

- **Model :** Each model is a class that represents a table in the database.
- **Entity :** Entity is a class that represents a single row of data.

The most basic example of a model class would look like this.

```php
namespace App\Model;

class Posts extends \InitPHP\Database\Model
{

    /**
    * If your model will use a connection other than your global connection, provide connection information.
    * @var array|null <p>Default : NULL</p> 
    */
    protected array $credentials = [
        'dsn'               => '',
        'username'          => 'root',
        'password'          => '',
        'charset'           => 'utf8mb4',
        'collation'         => 'utf8mb4_unicode_ci',
    ];

    /**
     * If not specified, \InitPHP\Database\Entity::class is used by default.
     * 
     * @var string<\InitPHP\Database\Entity>
     */
    protected $entity = \App\Entities\PostEntity::class;

    /**
     * If not specified, the name of your model class is used.
     * 
     * @var string
     */
    protected string $schema = 'posts';

    /**
     * The name of the PRIMARY KEY column. If not, define it as NULL.
     * 
     * @var string
     */
    protected string $schemaId = 'id';

    /**
     * Specify FALSE if you want the data to be permanently deleted.
     * 
     * @var bool
     */
    protected bool $useSoftDeletes = true;

    /**
     * Column name to hold the creation time of the data.
     * 
     * @var string|null
     */
    protected ?string $createdField = 'created_at';

    /**
     * The column name to hold the last time the data was updated.
     * 
     * @var string|null
     */
    protected ?string $updatedField = 'updated_at';

    /**
     * Column name to keep deletion time if $useSoftDeletes is active.
     * 
     * @var string|null
     */
    protected ?string $deletedField = 'deleted_at';

    protected bool $readable = true;

    protected bool $writable = true;

    protected bool $deletable = true;

    protected bool $updatable = true;
    
}
```

The most basic example of a entity class would look like this.

```php
namespace App\Entities;

class PostEntity extends \InitPHP\Database\Entity 
{
    /**
     * An example of a getter method for the "post_title" column.
     * 
     * Usage : 
     * echo $entity->post_title;
     */
    public function getPostTitleAttribute($title)
    {
        return strtoupper($title);
    }
    
    /**
     * An example of a setter method for the "post_title" column.
     * 
     * Usage : 
     * $entity->post_title = 'New Post Title';
     */
    public function setPostTitleAttribute($title)
    {
        $this->post_title = strtolower($title);
    }
    
}
```

## Development Tools

Below I have mentioned some developer tools that you can use during and after development.

### Logger

```php
use \InitPHP\Database\DB;

DB::createImmutable([
    'dsn'       => 'mysql:host=localhost;dbname=test;port=3306;charset=utf8mb4;',
    'username'  => 'root',
    'password'  => '',
    
    'log'       => __DIR__ '/logs/db.log', // string, callable or object
]);
```

If you define a file path as a String; Attempts are made to write into it with `file_put_contents()`.

_Note :_ You can define variables such as `{year}`, `{month}`, `{day}` in the filename.

- You can also define an object with the `critical` method. The database library will pass the log message to this method as a parameter. Or define it as callable array to use any method of the object.

```php
use \InitPHP\Database\DB;

class Logger {
    
    public function critical(string $msg)
    {
        $path = __DIR__ . '/log.log';
        file_put_contents($path, $msg, FILE_APPEND);
    }

}

$logger = new Logger();

DB::createImmutable([
    'dsn'       => 'mysql:host=localhost;dbname=test;port=3306;charset=utf8mb4;',
    'username'  => 'root',
    'password'  => '',
    
    'log'       => $logger, // or [$logger, 'critical']
]);
```

- Similarly it is possible to define it in a callable method.

```php
use \InitPHP\Database\DB;

DB::createImmutable([
    'dsn'       => 'mysql:host=localhost;dbname=test;port=3306;charset=utf8mb4;',
    'username'  => 'root',
    'password'  => '',
    
    'log'       => function (string $msg) {
        $path = __DIR__ . '/log.log';
        file_put_contents($path, $msg, FILE_APPEND);
    },
]);
```

### DeBug Mode

Debug mode is used to include the executed SQL statement in the error message. *__It should only be activated in the development environment__*.

```php
use \InitPHP\Database\DB;

DB::createImmutable([
    'dsn'       => 'mysql:host=localhost;dbname=test;port=3306;charset=utf8mb4;',
    'username'  => 'root',
    'password'  => '',
    
    'debug'     => true, // boolean
]);
```

### Profiler Mode

Profiler mode is a developer tool available in v3 and above. It is a feature that allows you to see the executed queries along with their execution times.

```php
use InitPHP\Database\DB;

DB::enableQueryLog();

DB::table('users')->where('name', 'John')->read();

var_dump(DB::getQueryLogs());

/**
 * The output of the above example looks like this;
 * [
 *      [
 *          'query' => 'SELECT * FROM users WHERE name = :name',
 *          'time'  => '0.00064',
 *          'args'  => [
 *              ':name'     => 'John',
 *          ]
 *      ]
 * ]
 * 
 */
```

## To Do

- [ ] A more detailed documentation will be prepared.

## Getting Help

If you have questions, concerns, bug reports, etc, please file an issue in this repository's Issue Tracker.

## Getting Involved

> All contributions to this project will be published under the MIT License. By submitting a pull request or filing a bug, issue, or feature request, you are agreeing to comply with this waiver of copyright interest.

There are two primary ways to help:

- Using the issue tracker, and
- Changing the code-base.

### Using the issue tracker

Use the issue tracker to suggest feature requests, report bugs, and ask questions. This is also a great way to connect with the developers of the project as well as others who are interested in this solution.

Use the issue tracker to find ways to contribute. Find a bug or a feature, mention in the issue that you will take on that effort, then follow the Changing the code-base guidance below.

### Changing the code-base

Generally speaking, you should fork this repository, make changes in your own fork, and then submit a pull request. All new code should have associated unit tests that validate implemented features and the presence or lack of defects. Additionally, the code should follow any stylistic and architectural guidelines prescribed by the project. In the absence of such guidelines, mimic the styles and patterns in the existing code-base.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>> 

## License

Copyright &copy; 2022 [MIT License](./LICENSE) 
