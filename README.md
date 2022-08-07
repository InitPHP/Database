# InitPHP Database

Manage your database with or without abstraction. This library is built on the PHP PDO plugin and is mainly used to build and execute SQL queries.

[![Latest Stable Version](http://poser.pugx.org/initphp/database/v)](https://packagist.org/packages/initphp/database) [![Total Downloads](http://poser.pugx.org/initphp/database/downloads)](https://packagist.org/packages/initphp/database) [![Latest Unstable Version](http://poser.pugx.org/initphp/database/v/unstable)](https://packagist.org/packages/initphp/database) [![License](http://poser.pugx.org/initphp/database/license)](https://packagist.org/packages/initphp/database) [![PHP Version Require](http://poser.pugx.org/initphp/database/require/php)](https://packagist.org/packages/initphp/database)

## Requirements

- PHP 7.4 and later.
- PHP PDO extension.

## Supported Databases

This library should work correctly in almost any database that uses basic SQL syntax.
Databases supported by PDO and suitable drivers are available at [https://www.php.net/manual/en/pdo.drivers.php](https://www.php.net/manual/en/pdo.drivers.php).

## Installation

```
composer require initphp/database
```

## Usage

### QueryBuilder and CRUD

```php
require_once "vendor/autoload.php";
use \InitPHP\Database\DB;

// Connection
$db = new DB([
    'dsn'       => 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_general_ci',
]);

// If you are working with a single database, do not forget to make your connection global.
$db->connectionAsGlobal();
```

#### Create

Single Row : 

```php
$data = [
    'title'     => 'Post Title',
    'content'   => 'Post Content',
];

/** @var $db \InitPHP\Database\DB */
$isInsert = $db->table('post')
                ->create($data);

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

Multi Row:

```php
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

/** @var $db \InitPHP\Database\DB */
$isInsert = $db->table('post')
                ->create($data);

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
/** @var $db \InitPHP\Database\DB */
$db->select('user.name as author_name', 'post.id', 'post.title')
    ->from('post')
    ->selfJoin('user', 'user.id=post.author')
    ->where('post.status', true)
    ->orderBy('post.id', 'ASC')
    ->orderBy('post.created_at', 'DESC')
    ->offset(20)->limit(10);
    
/**
* This executes the following query.
* 
* SELECT user.name AS author_name, post.id, post.title 
* FROM post, user 
* WHERE user.id = post.author AND post.status = 1
* ORDER BY post ASC, post.created_at DESC
* LIMIT 20, 10
*/
$res = $db->read();
if($db->numRows() > 0){
    foreach ($res as $row) {
        echo $row['title'] . ' by ' . $row['author_name'] . '<br />';
    }
}
```

#### Update

```php
$data = [
    'title'     => 'New Title',
    'content'   => 'New Content',
];

/** @var $db \InitPHP\Database\DB */
$isUpdate = $db->from('post')
                ->where('id', 13)
                ->update($data);
    
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

#### Delete

```php
/** @var $db \InitPHP\Database\DB */
$isDelete = $db->from('post')
                ->where('id', 13)
                ->delete();
    
/**
* This executes the following query.
* 
* DELETE FROM post WHERE id = 13
*/
if ($isUpdate) {
    // Success
}
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
    * Only if you don't have global connectivity.
    * 
    * @var array|string[] 
    */
    protected array $connection = [
        'dsn'           => '', // Database connection address.
        'username'      => '', // Username with required privileges in the database.
        'password'      => '', // The password of the database user.
        'charset'       => 'utf8mb4', // The character set to use in the database.
        'collation'     => 'utf8mb4_general_ci', // Collection set to use in database
    ];

    /**
     * If not specified, \InitPHP\Database\Entity::class is used by default.
     * 
     * @var \InitPHP\Database\Entity|string
     */
    protected $entity = \App\Entities\PostEntity::class;

    /**
     * If not specified, the name of your model class is used.
     * 
     * @var string
     */
    protected string $table = 'post';

    /**
     * The name of the PRIMARY KEY column. If not, define it as NULL.
     * 
     * @var null|string
     */
    protected ?string $primaryKey = 'id';

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

    /**
     * An array that defines the columns that will be allowed to be used in Insert and Update operations.
     * If you want to give access to all columns; You can specify it as NULL.
     * 
     * @var null|string[]
     */
    protected ?array $allowedFields = [
        'title', 'content', // ...
    ];

    /**
     * Turns the use of callable functions on or off.
     * 
     * @var bool
     */
    protected bool $allowedCallbacks = false;

    /**
     * @var string[]|\Closure[]
     */
    protected array $beforeInsert = [];

    /**
     * @var string[]|\Closure[]
     */
    protected array $afterInsert = [];

    /**
     * @var string[]|\Closure[]
     */
    protected array $beforeUpdate = [];

    /**
     * @var string[]|\Closure[]
     */
    protected array $afterUpdate = [];

    /**
     * @var string[]|\Closure[]
     */
    protected array $beforeDelete = [];

    /**
     * @var string[]|\Closure[]
     */
    protected array $afterDelete = [];

    protected bool $readable = true;

    protected bool $writable = true;

    protected bool $deletable = true;

    protected bool $updatable = true;

    protected array $validation = [
        'id'    => ['is_unique', 'int'],
        'title' => ['required', 'string', 'length(0,255)'],
    ];

    protected array $validationMsg = [
        'id'    => [],
        'title' => [
            'required'      => '{field} cannot be left blank.',
            'string'        => '{field} must be a string.',
        ],
    ];

    protected array $validationLabels = [
        'id'    => 'Post ID',
        'title' => 'Post Title',
        // ...
    ];
    
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
