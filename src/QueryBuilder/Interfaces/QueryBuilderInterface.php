<?php

namespace InitPHP\Database\QueryBuilder\Interfaces;


use Closure;
use InitPHP\Database\QueryBuilder\Exceptions\QueryBuilderException;
use InitPHP\Database\QueryBuilder\RawQuery;

interface QueryBuilderInterface
{

    /**
     * @return self
     */
    public function newBuilder(): self;

    /**
     * @param array $structure
     * @param bool $merge
     * @return self
     */
    public function importQB(array $structure, bool $merge = false): self;

    /**
     * @return array
     */
    public function exportQB(): array;

    /**
     * @return ParameterInterface
     */
    public function getParameter(): ParameterInterface;

    /**
     * @param string $key
     * @param string|int|float|bool|null $value
     * @return $this
     */
    public function setParameter(string $key, mixed $value): self;

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters = []): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] ...$columns
     * @return $this
     */
    public function select(...$columns): self;

    /**
     * @return self
     */
    public function clearSelect(): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectCount(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param RawQuery|string $column
     * @param string|null $alias
     * @return self
     */
    public function selectCountDistinct(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectMax(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectMin(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectAvg(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string $alias
     * @return $this
     */
    public function selectAs(RawQuery|string $column, string $alias): self;


    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectUpper(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectLower(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectLength(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param int $offset
     * @param int $length
     * @param string|null $alias
     * @return $this
     */
    public function selectMid(RawQuery|string $column, int $offset, int $length, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param int $length
     * @param string|null $alias
     * @return $this
     */
    public function selectLeft(RawQuery|string $column, int $length, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param int $length
     * @param string|null $alias
     * @return $this
     */
    public function selectRight(RawQuery|string $column, int $length, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return $this
     */
    public function selectDistinct(RawQuery|string $column, ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param mixed $default
     * @param string|null $alias
     * @return $this
     */
    public function selectCoalesce(RawQuery|string $column, mixed $default = '0', ?string $alias = null): self;

    /**
     * @param string|RawQuery $column
     * @param string|null $alias
     * @return self
     */
    public function selectSum(string|RawQuery $column, ?string $alias = null): self;

    /**
     * @param string[]|RawQuery[] $columns
     * @param string|null $alias
     * @return self
     */
    public function selectConcat(array $columns, ?string $alias = null): self;


    /**
     * @param string|RawQuery $table
     * @param string|null $alias
     * @return self
     */
    public function from(RawQuery|string $table, ?string $alias = null): self;

    /**
     * @param string|RawQuery $table
     * @param string|null $alias
     * @return self
     */
    public function addFrom(RawQuery|string $table, ?string $alias = null): self;

    /**
     * @param string|RawQuery $table
     * @return self
     */
    public function table(string|RawQuery $table): self;

    /**
     * @param string|RawQuery|array ...$columns
     * @return self
     */
    public function groupBy(string|RawQuery|array ...$columns): self;


    /**
     * @param string|RawQuery $table
     * @param string|Closure|RawQuery|null $onStmt
     * @param string $type
     * @return self
     */
    public function join(RawQuery|string $table, RawQuery|string|Closure $onStmt = null, string $type = 'INNER'): self;


    /**
     * @param string|RawQuery $table
     * @param string|RawQuery|Closure $onStmt
     * @return self
     */
    public function selfJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): self;

    /**
     * @param string|RawQuery $table
     * @param string|RawQuery|Closure $onStmt
     * @return self
     */
    public function innerJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): self;


    /**
     * @param string|RawQuery $table
     * @param string|RawQuery|Closure $onStmt
     * @return self
     */
    public function leftJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): self;

    /**
     * @param string|RawQuery $table
     * @param string|RawQuery|Closure $onStmt
     * @return self
     */
    public function rightJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): self;

    /**
     * @param string|RawQuery $table
     * @param string|RawQuery|Closure $onStmt
     * @return self
     */
    public function leftOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): self;

    /**
     * @param string|RawQuery $table
     * @param string|RawQuery|Closure $onStmt
     * @return self
     */
    public function rightOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): self;

    /**
     * @param string|RawQuery $table
     * @param string|RawQuery|Closure $onStmt
     * @return self
     */
    public function naturalJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): self;

    /**
     * @param string|RawQuery $column
     * @param string $soft [ASC|DESC]
     * @return $this
     */
    public function orderBy(RawQuery|string $column, string $soft = 'ASC'): self;

    /**
     * @param string|RawQuery $column
     * @param string $operator
     * @param mixed|null $value
     * @param string $logical []
     * @return self
     */
    public function where(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery $column
     * @param string $operator
     * @param mixed|null $value
     * @param string $logical []
     * @return self
     */
    public function having(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND'): self;

    /**
     * @param RawQuery|string $column
     * @param string $operator
     * @param mixed|null $value
     * @param string $logical
     * @return self
     */
    public function on(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND'): self;


    /**
     * @param array|string|RawQuery $column
     * @param mixed|null $value
     * @param bool $strict
     * @return $this
     */
    public function set(RawQuery|array|string $column, mixed $value = null, bool $strict = true): self;


    /**
     * @param array|string|RawQuery $column
     * @param mixed|null $value
     * @param bool $strict
     * @return $this
     */
    public function addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true): self;


    /**
     * @param string|RawQuery $column
     * @param string $operator
     * @param mixed|null $value
     * @return self
     */
    public function andWhere(string|RawQuery $column, string $operator = '=', mixed $value = null): self;


    /**
     * @param string|RawQuery $column
     * @param string $operator
     * @param mixed|null $value
     * @return self
     */
    public function orWhere(string|RawQuery $column, string $operator = '=', mixed $value = null): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $firstValue
     * @param mixed|null $lastValue
     * @param string $logical
     * @return self
     */
    public function between(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $firstValue
     * @param mixed|null $lastValue
     * @return self
     */
    public function orBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $firstValue
     * @param mixed|null $lastValue
     * @return self
     */
    public function andBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $firstValue
     * @param mixed|null $lastValue
     * @param string $logical
     * @return self
     */
    public function notBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $firstValue
     * @param mixed|null $lastValue
     * @return self
     */
    public function orNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $firstValue
     * @param mixed|null $lastValue
     * @return self
     */
    public function andNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @param string $logical
     * @return self
     */
    public function findInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function andFindInSet(string|RawQuery $column, mixed $value = null): self;



    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function orFindInSet(string|RawQuery $column, mixed $value = null): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @param string $logical
     * @return self
     */
    public function notFindInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function andNotFindInSet(string|RawQuery $column, mixed $value = null): self;



    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function orNotFindInSet(string|RawQuery $column, mixed $value = null): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @param string $logical
     * @return self
     */
    public function whereIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @param string $logical
     * @return self
     */
    public function whereNotIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function orWhereIn(string|RawQuery $column, mixed $value = null): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function orWhereNotIn(string|RawQuery $column, mixed $value = null): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function andWhereIn(string|RawQuery $column, mixed $value = null): self;


    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function andWhereNotIn(string|RawQuery $column, mixed $value = null): self;


    /**
     * @param string|RawQuery $column
     * @param string|RawQuery $value
     * @param string $logical
     * @return self
     */
    public function regexp(string|RawQuery $column, string|RawQuery $value, string $logical = 'AND'): self;


    /**
     * @param string|RawQuery $column
     * @param string|RawQuery $value
     */
    public function andRegexp(string|RawQuery $column, string|RawQuery $value): self;

    /**
     * @param string|RawQuery $column
     * @param string|RawQuery $value
     */
    public function orRegexp(string|RawQuery $column, string|RawQuery $value): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @param string $logical
     * @return self
     */
    public function soundex(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function andSoundex(string|RawQuery $column, mixed $value = null): self;

    /**
     * @param string|RawQuery $column
     * @param mixed|null $value
     * @return self
     */
    public function orSoundex(string|RawQuery $column, mixed $value = null): self;

    /**
     * @param string|RawQuery $column
     * @param string $logical
     * @return self
     */
    public function whereIsNull(string|RawQuery $column, string $logical = 'AND'): self;


    /**
     * @param string|RawQuery $column
     * @return self
     */
    public function orWhereIsNull(string|RawQuery $column): self;


    /**
     * @param string|RawQuery $column
     * @return self
     */
    public function andWhereIsNull(string|RawQuery $column): self;

    /**
     * @param string|RawQuery $column
     * @param string $logical
     * @return self
     */
    public function whereIsNotNull(string|RawQuery $column, string $logical = 'AND'): self;


    /**
     * @param string|RawQuery $column
     * @return self
     */
    public function orWhereIsNotNull(string|RawQuery $column): self;


    /**
     * @param string|RawQuery $column
     * @return self
     */
    public function andWhereIsNotNull(string|RawQuery $column): self;

    /**
     * @param int $offset
     * @return self
     */
    public function offset(int $offset = 0): self;


    /**
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $type [both|before|after|none]
     * @param string $logical
     * @return self
     */
    public function like(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $type [both|before|after|none]
     * @return self
     */
    public function orLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): self;


    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $type [both|before|after|none]
     * @return self
     */
    public function andLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): self;



    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $type [both|before|after|none]
     * @param string $logical
     * @return self
     */
    public function notLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $type [both|before|after|none]
     * @return self
     */
    public function orNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): self;


    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $type [both|before|after|none]
     * @return self
     */
    public function andNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $logical
     * @return self
     */
    public function startLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function orStartLike(string|RawQuery|array $column, mixed $value = null): self;


    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function andStartLike(string|RawQuery|array $column, mixed $value = null): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $logical
     * @return self
     */
    public function notStartLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function orStartNotLike(string|RawQuery|array $column, mixed $value = null): self;


    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function andStartNotLike(string|RawQuery|array $column, mixed $value = null): self;


    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $logical
     * @return self
     */
    public function endLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function orEndLike(string|RawQuery|array $column, mixed $value = null): self;


    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function andEndLike(string|RawQuery|array $column, mixed $value = null): self;



    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @param string $logical
     * @return self
     */
    public function notEndLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): self;

    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function orEndNotLike(string|RawQuery|array $column, mixed $value = null): self;


    /**
     * @param string|RawQuery|string[]|RawQuery[] $column
     * @param mixed $value
     * @return self
     */
    public function andEndNotLike(string|RawQuery|array $column, mixed $value = null): self;


    /**
     * @param Closure $closure
     * @param string|null $alias
     * @param bool $isIntervalQuery
     * @return RawQuery
     * @throws QueryBuilderException
     */
    public function subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true): RawQuery;

    /**
     * Where|On|Having
     *
     * @param Closure $closure
     * @return self
     * @throws QueryBuilderException
     */
    public function group(Closure $closure): self;

    /**
     * @param mixed $rawQuery
     * @return RawQuery
     */
    public function raw(mixed $rawQuery): RawQuery;

}
