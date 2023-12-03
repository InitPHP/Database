<?php
/**
 * Facade/DB
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     3.0
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Facade;

use \InitPHP\Database\Connection\{Connection,
    Interfaces\ConnectionInterface};
use \InitPHP\Database\DBAL\{CRUD,
    Database,
    Interfaces\CRUDInterface,
    Interfaces\ResultInterface};
use \InitPHP\Database\QueryBuilder\{QueryBuilder,
    RawQuery,
    Interfaces\ParameterInterface,
    Interfaces\QueryBuilderInterface};
use PDO;
use Closure;

/**
 * @mixin CRUDInterface
 * @method static mixed getCredentials(?string $key = null, mixed $default = null)
 * @method static PDO getPDO()
 * @method static bool beginTransaction(bool $testMode = false)
 * @method static bool completeTransaction()
 * @method static bool commit()
 * @method static bool rollBack()
 * @method static bool disconnect()
 * @method static CRUDInterface enableQueryLog()
 * @method static CRUDInterface disableQueryLog()
 * @method static CRUDInterface getQueryLogs()
 * @method static QueryBuilderInterface getQueryBuilder()
 * @method static ConnectionInterface getConnection()
 * @method static QueryBuilderInterface builder()
 * @method static CRUDInterface newInstance(ConnectionInterface|array $connectionOrCredentials, ?QueryBuilderInterface $builder = null)
 * @method static ResultInterface get(?string $table = null, ?array $selection = null, ?array $conditions = null)
 * @method static ResultInterface query(string $rawSQL, ?array $arguments = null, ?array $options = null)
 * @method static int count()
 * @method static int insertId()
 * @method static bool transaction(Closure $closure, int $attempt = 1, bool $testMode = false)
 * @method static bool create(?string $table = null, ?array $set = null)
 * @method static bool createBatch(?string $table = null, ?array $set = null)
 * @method static ResultInterface read(?string $table = null, array $selector = [], array $conditions = [], array $parameters = [])
 * @method static ResultInterface readOne(?string $table = null, array $selector = [], array $conditions = [], array $parameters = [])
 * @method static bool update(?string $table = null, ?array $set = null)
 * @method static bool updateBatch(?string $table = null, ?array $set = null, ?string $referenceColumn = null)
 * @method static bool delete(?string $table = null, ?array $conditions = [])
 * @method static CRUDInterface newBuilder()
 * @method static CRUDInterface importQB(array $structure, bool $merge = false)
 * @method static array exportQB()
 * @method static ParameterInterface getParameter()
 * @method static CRUDInterface setParameter(string $key, mixed $value)
 * @method static CRUDInterface setParameters(array $parameters = [])
 * @method static CRUDInterface select(string|RawQuery|string[]|RawQuery[] ...$columns)
 * @method static CRUDInterface selectCount(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectCountDistinct(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectMax(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectMin(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectAvg(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectAs(RawQuery|string $column, string $alias)
 * @method static CRUDInterface selectUpper(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectLower(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectLength(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectMid(RawQuery|string $column, int $offset, int $length, ?string $alias = null)
 * @method static CRUDInterface selectLeft(RawQuery|string $column, int $length, ?string $alias = null)
 * @method static CRUDInterface selectRight(RawQuery|string $column, int $length, ?string $alias = null)
 * @method static CRUDInterface selectDistinct(RawQuery|string $column, ?string $alias = null)
 * @method static CRUDInterface selectCoalesce(RawQuery|string $column, mixed $default = '0', ?string $alias = null)
 * @method static CRUDInterface selectSum(string|RawQuery $column, ?string $alias = null)
 * @method static CRUDInterface selectConcat(array $columns, ?string $alias = null)
 * @method static CRUDInterface from(RawQuery|string $table, ?string $alias = null)
 * @method static CRUDInterface addFrom(RawQuery|string $table, ?string $alias = null)
 * @method static CRUDInterface table(string|RawQuery $table)
 * @method static CRUDInterface groupBy(string|RawQuery|array ...$columns)
 * @method static CRUDInterface join(RawQuery|string $table, RawQuery|string|Closure $onStmt = null, string $type = 'INNER')
 * @method static CRUDInterface selfJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static CRUDInterface innerJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static CRUDInterface leftJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static CRUDInterface rightJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static CRUDInterface leftOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static CRUDInterface rightOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static CRUDInterface naturalJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static CRUDInterface orderBy(RawQuery|string $column, string $soft = 'ASC')
 * @method static CRUDInterface where(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface having(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface on(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface set(RawQuery|array|string $column, mixed $value = null, bool $strict = true)
 * @method static CRUDInterface addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true)
 * @method static CRUDInterface andWhere(string|RawQuery $column, string $operator = '=', mixed $value = null)
 * @method static CRUDInterface orWhere(string|RawQuery $column, string $operator = '=', mixed $value = null)
 * @method static CRUDInterface between(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND')
 * @method static CRUDInterface orBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static CRUDInterface andBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static CRUDInterface notBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND')
 * @method static CRUDInterface orNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static CRUDInterface andNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static CRUDInterface findInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface andFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface orFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface notFindInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface andNotFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface orNotFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface whereIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface whereNotIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface orWhereIn(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface orWhereNotIn(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface andWhereIn(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface andWhereNotIn(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface regexp(string|RawQuery $column, string|RawQuery $value, string $logical = 'AND')
 * @method static CRUDInterface andRegexp(string|RawQuery $column, string|RawQuery $value)
 * @method static CRUDInterface orRegexp(string|RawQuery $column, string|RawQuery $value)
 * @method static CRUDInterface soundex(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface andSoundex(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface orSoundex(string|RawQuery $column, mixed $value = null)
 * @method static CRUDInterface whereIsNull(string|RawQuery $column, string $logical = 'AND')
 * @method static CRUDInterface orWhereIsNull(string|RawQuery $column)
 * @method static CRUDInterface andWhereIsNull(string|RawQuery $column)
 * @method static CRUDInterface whereIsNotNull(string|RawQuery $column, string $logical = 'AND')
 * @method static CRUDInterface orWhereIsNotNull(string|RawQuery $column)
 * @method static CRUDInterface andWhereIsNotNull(string|RawQuery $column)
 * @method static CRUDInterface offset(int $offset = 0)
 * @method static CRUDInterface limit(int $limit)
 * @method static CRUDInterface like(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND')
 * @method static CRUDInterface orLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static CRUDInterface andLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static CRUDInterface notLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND')
 * @method static CRUDInterface orNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static CRUDInterface andNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static CRUDInterface startLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface orStartLike(string|RawQuery|array $column, mixed $value = null)
 * @method static CRUDInterface andStartLike(string|RawQuery|array $column, mixed $value = null)
 * @method static CRUDInterface notStartLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface orStartNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static CRUDInterface andStartNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static CRUDInterface endLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface orEndLike(string|RawQuery|array $column, mixed $value = null)
 * @method static CRUDInterface andEndLike(string|RawQuery|array $column, mixed $value = null)
 * @method static CRUDInterface notEndLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static CRUDInterface orEndNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static CRUDInterface andEndNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static RawQuery subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true)
 * @method static CRUDInterface group(Closure $closure)
 * @method static RawQuery raw(mixed $rawQuery)
 * @method static string[] getErrors()
 */
class DB
{

    private static CRUDInterface $databaseInstance;

    public function __call($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    public static function createImmutable(array|ConnectionInterface $credentialsOrConnection): CRUDInterface
    {
        if (isset(self::$databaseInstance)) {
            return self::$databaseInstance;
        }

        return self::$databaseInstance = new CRUD(new Database(($credentialsOrConnection instanceof ConnectionInterface) ? $credentialsOrConnection : new Connection($credentialsOrConnection), new QueryBuilder()));
    }

    public static function connect(array|ConnectionInterface $credentialsOrConnection): CRUDInterface
    {
        return new CRUD(new Database((($credentialsOrConnection instanceof ConnectionInterface) ? $credentialsOrConnection : new Connection($credentialsOrConnection)), new QueryBuilder()));
    }

    public static function getDatabase(): CRUDInterface
    {
        return self::$databaseInstance;
    }

}
