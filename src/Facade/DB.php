<?php
/**
 * Facade/DB
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.6
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Facade;

use InitPHP\Database\{Database, Raw, Result, Utils\Pagination};

/**
 * @mixin Database
 * @method static Database newInstance(array $credentials = [])
 * @method static bool isError()
 * @method static string[] getError()
 * @method static \PDO getPDO()
 * @method static Database withPDO(\PDO $pdo)
 * @method static null|string getSchemaID()
 * @method static Database setSchemaID(null|string $column)
 * @method static Database withSchemaID(null|string $column)
 * @method static null|string getSchema()
 * @method static Database setSchema(null|string $table)
 * @method static Database withSchema(null|string $table)
 * @method static bool beginTransaction(bool $testMode = false)
 * @method static bool completeTransaction()
 * @method static bool commit()
 * @method static bool rollBack()
 * @method static bool transaction(\Closure $closure)
 * @method static Raw raw(string $rawQuery)
 * @method static Database connection(array $credentials = [])
 * @method static false|float|int|string escape_str(mixed $value)
 * @method static Database setParameter(string $key, mixed $value)
 * @method static Database setParameters(array $parameters = [])
 * @method static Result query(string $sqlQuery, array $parameters = [])
 * @method static int insertId()
 * @method static Result get(?string $table = null)
 * @method static bool create(array $set)
 * @method static bool createBatch(array $set)
 * @method static int count()
 * @method static Pagination pagination(int $page = 1, int $per_page_limit = 10, string $link = '?page={page}')
 * @method static Result read(array $selector = [], array $conditions = [], array $parameters = [])
 * @method static Result readOne(array $selector = [], array $conditions = [], array $parameters = [])
 * @method static bool update(array $set)
 * @method static bool delete(array $conditions = [])
 * @method static Result all(int $limit = 100, int $offset = 0)
 * @method static Database onlyDeleted()
 * @method static Database onlyUndeleted()
 * @method static void reset()
 * @method static Database select(string|Raw ...$columns)
 * @method static Database selectCount(string $column, ?string $alias = null)
 * @method static Database selectMax(string $column, ?string $alias = null)
 * @method static Database selectMin(string $column, ?string $alias = null)
 * @method static Database selectAvg(string $column, ?string $alias = null)
 * @method static Database selectAs(string $column, ?string $alias = null)
 * @method static Database selectUpper(string $column, ?string $alias = null)
 * @method static Database selectLower(string $column, ?string $alias = null)
 * @method static Database selectLength(string $column, ?string $alias = null)
 * @method static Database selectMid(string $column, int $offset, int $length, ?string $alias = null)
 * @method static Database selectLeft(string $column, int $length, ?string $alias = null)
 * @method static Database selectRight(string $column, int $length, ?string $alias = null)
 * @method static Database selectDistinct(string $column, ?string $alias = null)
 * @method static Database selectCoalesce(string $column, $default = '0', ?string $alias = null)
 * @method static Database selectSum(string $column, ?string $alias = null)
 * @method static Database selectConcat(?string $alias = null, string ...$columnOrStr)
 * @method static Database from(string|Raw ...$tables)
 * @method static Database table(string|Raw $table)
 * @method static Database groupBy(string|Raw ...$columns)
 * @method static Database join(string|Raw $table, ?string $onStmt = null, string $type = 'INNER')
 * @method static Database selfJoin(string $table, string $onStmt)
 * @method static Database innerJoin(string $table, string $onStmt)
 * @method static Database leftJoin(string $table, string $onStmt)
 * @method static Database rightJoin(string $table, string $onStmt)
 * @method static Database leftOuterJoin(string $table, string $onStmt)
 * @method static Database rightOuterJoin(string $table, string $onStmt)
 * @method static Database naturalJoin(string $table)
 * @method static Database orderBy(string $column, string $soft = 'ASC')
 * @method static Database where(string|Raw $column, mixed $value = null, string $mark = '=', string $logical = 'AND')
 * @method static Database having(string|Raw $column, mixed $value, string $mark = '=', string $logical = 'AND')
 * @method static Database andWhere(string|Raw $column, $value, string $mark = '=')
 * @method static Database orWhere(string|Raw $column, $value, string $mark = '=')
 * @method static Database between(string $column, array $values, string $logical = 'AND')
 * @method static Database orBetween(string $column, array $values)
 * @method static Database andBetween(string $column, array $values)
 * @method static Database notBetween(string $column, array $values, string $logical = 'AND')
 * @method static Database orNotBetween(string $column, array $values)
 * @method static Database andNotBetween(string $column, array $values)
 * @method static Database findInSet(string $column, $value, string $logical = 'AND')
 * @method static Database orFindInSet(string $column, $value)
 * @method static Database andFindInSet(string $column, $value)
 * @method static Database notFindInSet(string $column, $value, string $logical = 'AND')
 * @method static Database andNotFindInSet(string $column, $value)
 * @method static Database orNotFindInSet(string $column, $value)
 * @method static Database in(string $column, array $value, string $logical = 'AND')
 * @method static Database orIn(string $column, array $value)
 * @method static Database andIn(string $column, array $value)
 * @method static Database notIn(string $column, array $value, string $logical = 'AND')
 * @method static Database orNotIn(string $column, array $value)
 * @method static Database andNotIn(string $column, array $value)
 * @method static Database regexp(string $column, string $value, string $logical = 'AND')
 * @method static Database andRegexp(string $column, string $value)
 * @method static Database orRegexp(string $column, string $value)
 * @method static Database like(string $column, $value, string $logical = 'AND')
 * @method static Database orLike(string $column, $value)
 * @method static Database andLike(string $column, $value)
 * @method static Database startLike(string $column, $value, string $logical = 'AND')
 * @method static Database orStartLike(string $column, $value)
 * @method static Database andStartLike(string $column, $value)
 * @method static Database endLike(string $column, $value, string $logical = 'AND')
 * @method static Database orEndLike(string $column, $value)
 * @method static Database andEndLike(string $column, $value)
 * @method static Database notLike(string $column, $value, string $logical = 'AND')
 * @method static Database orNotLike(string $column, $value)
 * @method static Database andNotLike(string $column, $value)
 * @method static Database startNotLike(string $column, $value, string $logical = 'AND')
 * @method static Database orStartNotLike(string $column, $value)
 * @method static Database andStartNotLike(string $column, $value)
 * @method static Database endNotLike(string $column, $value, string $logical = 'AND')
 * @method static Database orEndNotLike(string $column, $value)
 * @method static Database andEndNotLike(string $column, $value)
 * @method static Database soundex(string $column, $value, string $logical = 'AND')
 * @method static Database orSoundex(string $column, $value)
 * @method static Database andSoundex(string $column, $value)
 * @method static Database is(string $column, $value, string $logical = 'AND')
 * @method static Database orIs(string $column, $value = null)
 * @method static Database andIs(string $column, $value = null)
 * @method static Database isNot(string $column, $value = null, string $logical = 'AND')
 * @method static Database orIsNot(string $column, $value = null)
 * @method static Database andIsNot(string $column, $value = null)
 * @method static Database offset(int $offset = 0)
 * @method static Database limit(int $limit)
 */
class DB
{

    private static Database $databaseInstance;

    public function __call($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    public static function createImmutable(array $credentials)
    {
        self::$databaseInstance = new Database($credentials);
    }

    private static function getDatabase(): Database
    {
        return self::$databaseInstance;
    }

}
