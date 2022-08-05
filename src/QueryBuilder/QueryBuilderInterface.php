<?php
/**
 * QueryBuilderInterface.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.8
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder;

interface QueryBuilderInterface
{

    /**
     * @param string|null $schemaID
     * @return $this
     */
    public function setSchemaID(?string $schemaID): self;

    /**
     * INSERT SQL Query Build
     *
     * @param array $data
     * @return string
     */
    public function insertQuery(array $data): string;

    /**
     * SELECT SQL Query Build
     *
     * @return string
     */
    public function readQuery(): string;

    /**
     * DELETE SQL Query Build
     *
     * @return string
     */
    public function deleteQuery(): string;

    /**
     * UPDATE SQL Query Build
     *
     * @param array $data
     * @return string
     */
    public function updateQuery(array $data): string;


    /**
     * Resets QueryBuilder properties except temporary SQL statement memory.
     *
     * @uses QueryBuilderInterface::clear()
     * @return $this
     */
    public function reset(): self;

    /**
     * SELECT id, title, ...$columns
     *
     * @param string ...$columns
     * @return $this
     */
    public function select(string ...$columns): self;

    /**
     * SELECT COUNT($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectCount(string $column): self;

    /**
     * SELECT MAX($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectMax(string $column): self;

    /**
     * SELECT MIN($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectMin(string $column): self;

    /**
     * SELECT AVG($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectAvg(string $column): self;

    /**
     * SELECT $column AS $alias
     *
     * @param string $column
     * @param string $alias
     * @return $this
     */
    public function selectAs(string $column, string $alias): self;

    /**
     * SELECT UPPER($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectUpper(string $column): self;

    /**
     * SELECT LOWER($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectLower(string $column): self;

    /**
     * SELECT LENGTH($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectLength(string $column): self;

    /**
     * SELECT MID($column, $offset, $length)
     *
     * @param string $column
     * @param int $offset
     * @param int $length
     * @return $this
     */
    public function selectMid(string $column, int $offset, int $length): self;

    /**
     * SELECT LEFT($column, $length)
     *
     * @param string $column
     * @param int $length
     * @return $this
     */
    public function selectLeft(string $column, int $length): self;

    /**
     * SELECT RIGHT($column, $length)
     *
     * @param string $column
     * @param int $length
     * @return $this
     */
    public function selectRight(string $column, int $length): self;

    /**
     * SELECT DISTINCT($column)
     *
     * @param string $column <p>Column name</p>
     * @return $this
     */
    public function selectDistinct(string $column): self;

    /**
     * SELECT COALESCE($column, $default)
     *
     * @param string $column
     * @param string|int $default
     * @return $this
     */
    public function selectCoalesce(string $column, $default = '0'): self;

    /**
     * SELECT SUM($column)
     *
     * @param string $column
     * @return $this
     */
    public function selectSum(string $column): self;

    /**
     * @see QueryBuilderInterface::from()
     * @param string $table
     * @return $this
     */
    public function table(string $table): self;

    /**
     * FROM $table
     *
     * @param string $table
     * @return $this
     */
    public function from(string $table): self;

    /**
     * @param string $table
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException <p>
     * $type is not supported or $onStmt is not in the correct format.
     * </p>
     */
    public function join(string $table, string $onStmt, string $type = 'INNER'): self;

    /**
     * FROM post, user WHERE post.author = user.id
     *
     * @param string $table <p>The table name to include.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return $this
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function selfJoin(string $table, string $onStmt): self;

    /**
     * INNER JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return $this
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function innerJoin(string $table, string $onStmt): self;

    /**
     * LEFT JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return $this
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function leftJoin(string $table, string $onStmt): self;

    /**
     * RIGHT JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return $this
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function rightJoin(string $table, string $onStmt): self;

    /**
     * LEFT OUTER JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return $this
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function leftOuterJoin(string $table, string $onStmt): self;

    /**
     * RIGHT OUTER JOIN user ON post.author = user.id
     *
     * @param string $table <p>The name of the table to join.</p>
     * @param string $onStmt <p>Example : "post.author=user.id"</p>
     * @return $this
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function rightOuterJoin(string $table, string $onStmt): self;

    /**
     * NATURAL JOIN $table
     *
     * @param string $table <p>The name of the table to join.</p>
     * @return $this
     * @throws \InvalidArgumentException <p>$onStmt is not in the correct format.</p>
     */
    public function naturalJoin(string $table): self;

    /**
     * Adds a SQL Where clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @param string $logical
     * @return $this
     */
    public function where(string $column, $value, string $mark = '=', string $logical = 'AND'): self;

    /**
     * Constructs a sentence to be combined with AND in a where clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @return $this
     */
    public function andWhere(string $column, $value, string $mark = '='): self;

    /**
     * Constructs a sentence to be combined with OR in a where clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @return $this
     */
    public function orWhere(string $column, $value, string $mark = '='): self;

    /**
     * Adds the having clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $mark
     * @param string $logical
     * @return $this
     */
    public function having(string $column, $value, string $mark = '=', string $logical = 'AND'): self;

    /**
     * Adds order by to the SQL statement.
     *
     * @param string $column
     * @param string $soft <p>[ASC|DESC]</p>
     * @return $this
     * @throws \InvalidArgumentException <p>If $soft is invalid.</p>
     */
    public function orderBy(string $column, string $soft = 'ASC'): self;

    /**
     * Adds Group By to the SQL statement.
     *
     * @param string ...$column
     * @return $this
     */
    public function groupBy(string ...$column): self;

    /**
     * It tells the SQL statement how many rows/data to skip.
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset = 0): self;

    /**
     * Defines the number of rows/data that will be affected by the SQL statement.
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self;

    /**
     * WHERE column BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @param string $logical
     * @return $this
     */
    public function between(string $column, array $values, string $logical = 'AND'): self;

    /**
     * WHERE column BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orBetween(string $column, array $values): self;

    /**
     * WHERE column BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function andBetween(string $column, array $values): self;

    /**
     * WHERE column NOT BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @param string $logical
     * @return $this
     */
    public function notBetween(string $column, array $values, string $logical = 'AND'): self;

    /**
     * WHERE column NOT BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orNotBetween(string $column, array $values): self;

    /**
     * WHERE column NOT BETWEEN values[0] AND values[1]
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function andNotBetween(string $column, array $values): self;

    /**
     * WHERE FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @param string $logical
     * @return $this
     */
    public function findInSet(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return $this
     */
    public function orFindInSet(string $column, $value): self;

    /**
     * WHERE FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return $this
     */
    public function andFindInSet(string $column, $value): self;

    /**
     * WHERE NOT FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @param string $logical
     * @return $this
     */
    public function notFindInSet(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE NOT FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return $this
     */
    public function andNotFindInSet(string $column, $value): self;

    /**
     * WHERE NOT FIND_IN_SET(value, column)
     *
     * @param string $column
     * @param string|int|string[]|int[]|null $value
     * @return $this
     */
    public function orNotFindInSet(string $column, $value): self;

    /**
     * WHERE column IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|array $value
     * @param string $logical
     * @return $this
     */
    public function in(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return $this
     */
    public function orIn(string $column, $value): self;

    /**
     * WHERE column IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return $this
     */
    public function andIn(string $column, $value): self;

    /**
     * WHERE column NOT IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @param string $logical
     * @return $this
     */
    public function notIn(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column NOT IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return $this
     */
    public function orNotIn(string $column, $value): self;

    /**
     * WHERE column NOT IN (values[0], values[1], ...)
     *
     * @param string $column
     * @param int[]|string[]|string $value
     * @return $this
     */
    public function andNotIn(string $column, $value): self;

    /**
     * WHERE $column REGEXP "$value"
     *
     * @param string $column
     * @param string $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    public function regexp(string $column, string $value, string $logical = 'AND'): self;

    /**
     * WHERE ... AND $column REGEXP "$value"
     *
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function andRegexp(string $column, string $value): self;

    /**
     * WHERE ... OR $column REGEXP "$value"
     *
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function orRegexp(string $column, string $value): self;

    /**
     * WHERE column LIKE "%value%"
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return $this
     */
    public function like(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column LIKE "%value%"
     *
     * @param string $column
     * @param $value
     * @return $this
     */
    public function orLike(string $column, $value): self;

    /**
     * WHERE column LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function andLike(string $column, $value): self;

    /**
     * WHERE column LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return $this
     */
    public function startLike(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function orStartLike(string $column, $value): self;

    /**
     * WHERE column LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function andStartLike(string $column, $value): self;

    /**
     * WHERE column LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    public function endLike(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function orEndLike(string $column, $value): self;

    /**
     * WHERE column LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function andEndLike(string $column, $value): self;

    /**
     * WHERE column NOT LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return $this
     */
    public function notLike(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column NOT LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function orNotLike(string $column, $value): self;

    /**
     * WHERE column NOT LIKE "%value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function andNotLike(string $column, $value): self;

    /**
     * WHERE column NOT LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return $this
     */
    public function startNotLike(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column NOT LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function orStartNotLike(string $column, $value): self;

    /**
     * WHERE column NOT LIKE "%value"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function andStartNotLike(string $column, $value): self;

    /**
     * WHERE column NOT LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @param string $logical
     * @return $this
     */
    public function endNotLike(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column NOT LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function orEndNotLike(string $column, $value): self;

    /**
     * WHERE column NOT LIKE "value%"
     *
     * @param string $column
     * @param null|bool|int|float|string $value
     * @return $this
     */
    public function andEndNotLike(string $column, $value): self;

    /**
     * WHERE SOUNDEX(column) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(value)), '%')
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return $this
     */
    public function soundex(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE SOUNDEX(column) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(value)), '%')
     *
     * @param string $column
     * @param $value
     * @return $this
     */
    public function orSoundex(string $column, $value): self;

    /**
     * WHERE SOUNDEX(column) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(value)), '%')
     *
     * @param string $column
     * @param $value
     * @return $this
     */
    public function andSoundex(string $column, $value): self;

    /**
     * WHERE column IS value
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return $this
     */
    public function is(string $column, $value, string $logical = 'AND'): self;

    /**
     * WHERE column IS value
     *
     * @param string $column
     * @param $value
     * @return $this
     */
    public function orIs(string $column, $value = null): self;

    /**
     * WHERE column IS value
     *
     * @param string $column
     * @param $value
     * @return $this
     */
    public function andIs(string $column, $value = null): self;

    /**
     * WHERE column IS NOT value
     *
     * @param string $column
     * @param $value
     * @param string $logical
     * @return $this
     */
    public function isNot(string $column, $value = null, string $logical = 'AND'): self;

    /**
     * WHERE column IS NOT value
     *
     * @param string $column
     * @param $value
     * @return $this
     */
    public function orIsNot(string $column, $value = null): self;

    /**
     * WHERE column IS NOT value
     * @param string $column
     * @param $value
     * @return $this
     */
    public function andIsNot(string $column, $value = null): self;

}
