<?php
/**
 * QueryBuilderInterface.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.10
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Interfaces;

interface QueryBuilderInterface
{

    /**
     * Resets QueryBuilder properties except temporary SQL statement memory.
     *
     * @uses QueryBuilderInterface::clear()
     * @return QueryBuilderInterface
     */
    public function reset(): QueryBuilderInterface;

    /**
     * Resets all QueryBuilder properties.
     *
     * @used-by QueryBuilderInterface::reset()
     * @return QueryBuilderInterface
     */
    public function clear(): QueryBuilderInterface;

    /**
     * ... UNION ...
     *
     * @return QueryBuilderInterface
     */
    public function union(): QueryBuilderInterface;

    /**
     * ... UNION ALL ...
     *
     * @return QueryBuilderInterface
     */
    public function unionAll(): QueryBuilderInterface;

    /**
     * SELECT id, title, ...$columns
     *
     * @param string ...$columns
     * @return QueryBuilderInterface
     */
    public function select(string ...$columns): QueryBuilderInterface;

    /**
     * SELECT COUNT($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectCount(string $column): QueryBuilderInterface;

    /**
     * SELECT MAX($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectMax(string $column): QueryBuilderInterface;

    /**
     * SELECT MIN($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectMin(string $column): QueryBuilderInterface;

    /**
     * SELECT AVG($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectAvg(string $column): QueryBuilderInterface;

    /**
     * SELECT $column AS $alias
     *
     * @param string $column
     * @param string $alias
     * @return QueryBuilderInterface
     */
    public function selectAs(string $column, string $alias): QueryBuilderInterface;

    /**
     * SELECT UPPER($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectUpper(string $column): QueryBuilderInterface;

    /**
     * SELECT LOWER($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectLower(string $column): QueryBuilderInterface;

    /**
     * SELECT LENGTH($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectLength(string $column): QueryBuilderInterface;

    /**
     * SELECT MID($column, $offset, $length)
     *
     * @param string $column
     * @param int $offset
     * @param int $length
     * @return QueryBuilderInterface
     */
    public function selectMid(string $column, int $offset, int $length): QueryBuilderInterface;

    /**
     * SELECT LEFT($column, $length)
     *
     * @param string $column
     * @param int $length
     * @return QueryBuilderInterface
     */
    public function selectLeft(string $column, int $length): QueryBuilderInterface;

    /**
     * SELECT RIGHT($column, $length)
     *
     * @param string $column
     * @param int $length
     * @return QueryBuilderInterface
     */
    public function selectRight(string $column, int $length): QueryBuilderInterface;

    /**
     * SELECT DISTINCT($column)
     *
     * @param string $column <p>Column name</p>
     * @return QueryBuilderInterface
     */
    public function selectDistinct(string $column): QueryBuilderInterface;

    /**
     * SELECT COALESCE($column, $default)
     *
     * @param string $column
     * @param string|int $default
     * @return QueryBuilderInterface
     */
    public function selectCoalesce(string $column, $default = '0'): QueryBuilderInterface;

    /**
     * SELECT SUM($column)
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function selectSum(string $column): QueryBuilderInterface;

    /**
     * FROM $table
     *
     * @param string $table
     * @return QueryBuilderInterface
     */
    public function from(string $table): QueryBuilderInterface;

    /**
     * @param string $table
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @param string $type
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>
     * $type is not supported or $onStmt is not in the correct format.
     * </p>
     */
    public function join(string $table, string $onStmt, string $type = 'INNER'): QueryBuilderInterface;

    /**
     * FROM post, user WHERE post.author = user.id
     *
     * @param string $table <p>The table name to include.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function selfJoin(string $table, string $onStmt): QueryBuilderInterface;

    /**
     * INNER JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function innerJoin(string $table, string $onStmt): QueryBuilderInterface;

    /**
     * LEFT JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function leftJoin(string $table, string $onStmt): QueryBuilderInterface;

    /**
     * RIGHT JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function rightJoin(string $table, string $onStmt): QueryBuilderInterface;

    /**
     * LEFT OUTER JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function leftOuterJoin(string $table, string $onStmt): QueryBuilderInterface;

    /**
     * RIGHT OUTER JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function rightOuterJoin(string $table, string $onStmt): QueryBuilderInterface;

    /**
     * NATURAL JOIN $table
     *
     * @param string $table <p>The name of the table to join.</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function naturalJoin(string $table): QueryBuilderInterface;

    /**
     * It is used to group where clauses.
     *
     * @param \Closure $group <p>QueryBuilderInterface is passed as a parameter to this callback function.</p>
     * @return QueryBuilderInterface
     */
    public function group(\Closure $group): QueryBuilderInterface;

    /**
     * Adds a SQL Where clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function where(string $column, $value, string $mark = '=', string $logical = 'AND'): QueryBuilderInterface;

    /**
     * Injects a string into the Where SQL clause.
     *
     * @param string $statement
     * @return QueryBuilderInterface
     */
    public function andWhereInject(string $statement): QueryBuilderInterface;

    /**
     * Injects a string into the Where SQL clause.
     *
     * @param string $statement
     * @return QueryBuilderInterface
     */
    public function orWhereInject(string $statement): QueryBuilderInterface;

    /**
     * Constructs a sentence to be combined with AND in a where clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @return QueryBuilderInterface
     */
    public function andWhere(string $column, $value, string $mark = '='): QueryBuilderInterface;

    /**
     * Constructs a sentence to be combined with OR in a where clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @return QueryBuilderInterface
     */
    public function orWhere(string $column, $value, string $mark = '='): QueryBuilderInterface;

    /**
     * Adds the having clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function having(string $column, $value, string $mark = '=', string $logical = 'AND'): QueryBuilderInterface;

    /**
     * Injects a string into the having clause.
     *
     * @param string $statement
     * @return QueryBuilderInterface
     */
    public function andHavingInject(string $statement): QueryBuilderInterface;

    /**
     * Injects a string into the having clause.
     *
     * @param string $statement
     * @return QueryBuilderInterface
     */
    public function orHavingInject(string $statement): QueryBuilderInterface;

    /**
     * Adds order by to the SQL statement.
     *
     * @param string $column
     * @param string $soft <p>[ASC|DESC]</p>
     * @return QueryBuilderInterface
     * @throws \InvalidArgumentException <p>If $soft is invalid.</p>
     */
    public function orderBy(string $column, string $soft = 'ASC'): QueryBuilderInterface;

    /**
     * Adds Group By to the SQL statement.
     *
     * @param string $column
     * @return QueryBuilderInterface
     */
    public function groupBy(string $column): QueryBuilderInterface;

    /**
     * It tells the SQL statement how many rows/data to skip.
     *
     * @param int $offset
     * @return QueryBuilderInterface
     */
    public function offset(int $offset = 0): QueryBuilderInterface;

    /**
     * Defines the number of rows/data that will be affected by the SQL statement.
     *
     * @param int $limit
     * @return QueryBuilderInterface
     */
    public function limit(int $limit): QueryBuilderInterface;

    /**
     * WHERE column BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function between(string $column, array $values, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return QueryBuilderInterface
     */
    public function orBetween(string $column, array $values): QueryBuilderInterface;

    /**
     * WHERE column BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return QueryBuilderInterface
     */
    public function andBetween(string $column, array $values): QueryBuilderInterface;

    /**
     * WHERE column NOT BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function notBetween(string $column, array $values, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column NOT BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return QueryBuilderInterface
     */
    public function orNotBetween(string $column, array $values): QueryBuilderInterface;

    /**
     * WHERE column NOT BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return QueryBuilderInterface
     */
    public function andNotBetween(string $column, array $values): QueryBuilderInterface;

    /**
     * WHERE FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function findInSet(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return QueryBuilderInterface
     */
    public function orFindInSet(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return QueryBuilderInterface
     */
    public function andFindInSet(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE NOT FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function notFindInSet(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE NOT FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return QueryBuilderInterface
     */
    public function andNotFindInSet(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE NOT FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return QueryBuilderInterface
     */
    public function orNotFindInSet(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|array $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function in(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return QueryBuilderInterface
     */
    public function orIn(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return QueryBuilderInterface
     */
    public function andIn(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function notIn(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column NOT IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return QueryBuilderInterface
     */
    public function orNotIn(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return QueryBuilderInterface
     */
    public function andNotIn(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE $column REGEXP "$value"
     *
     * @param string $column
     * @param string $value
     * @param string $logical [AND|OR]
     * @return QueryBuilderInterface
     */
    public function regexp(string $column, string $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE ... AND $column REGEXP "$value"
     *
     * @param string $column
     * @param string $value
     * @return QueryBuilderInterface
     */
    public function andRegexp(string $column, string $value): QueryBuilderInterface;

    /**
     * WHERE ... OR $column REGEXP "$value"
     *
     * @param string $column
     * @param string $value
     * @return QueryBuilderInterface
     */
    public function orRegexp(string $column, string $value): QueryBuilderInterface;

    /**
     * WHERE column LIKE "%value%"
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function like(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column LIKE "%value%"
     *
     * @param string $column
     * @param $value
     * @return QueryBuilderInterface
     */
    public function orLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function andLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function startLike(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function orStartLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function andStartLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical [AND|OR]
     * @return QueryBuilderInterface
     */
    public function endLike(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function orEndLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function andEndLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function notLike(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function orNotLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function andNotLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function startNotLike(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function orStartNotLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function andStartNotLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function endNotLike(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function orEndNotLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column NOT LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return QueryBuilderInterface
     */
    public function andEndNotLike(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE SOUNDEX(column) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(value)), '%')
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function soundex(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE SOUNDEX(column) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(value)), '%')
     *
     * @param string $column
     * @param $value
     * @return QueryBuilderInterface
     */
    public function orSoundex(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE SOUNDEX(column) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(value)), '%')
     *
     * @param string $column
     * @param $value
     * @return QueryBuilderInterface
     */
    public function andSoundex(string $column, $value): QueryBuilderInterface;

    /**
     * WHERE column IS value
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function is(string $column, $value, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column IS value
     *
     * @param string $column
     * @param $value
     * @return QueryBuilderInterface
     */
    public function orIs(string $column, $value = null): QueryBuilderInterface;

    /**
     * WHERE column IS value
     *
     * @param string $column
     * @param $value
     * @return QueryBuilderInterface
     */
    public function andIs(string $column, $value = null): QueryBuilderInterface;

    /**
     * WHERE column IS NOT value
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return QueryBuilderInterface
     */
    public function isNot(string $column, $value = null, string $logical = 'AND'): QueryBuilderInterface;

    /**
     * WHERE column IS NOT value
     *
     * @param string $column
     * @param $value
     * @return QueryBuilderInterface
     */
    public function orIsNot(string $column, $value = null): QueryBuilderInterface;

    /**
     * WHERE column IS NOT value
     * @param string $column
     * @param $value
     * @return QueryBuilderInterface
     */
    public function andIsNot(string $column, $value = null): QueryBuilderInterface;

}
