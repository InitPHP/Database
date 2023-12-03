<?php

namespace InitPHP\Database\QueryBuilder;

use \InitPHP\Database\QueryBuilder\Exceptions\{QueryBuilderException, QueryGeneratorException};
use InitPHP\Database\QueryBuilder\Interfaces\ParameterInterface;
use InvalidArgumentException;
use Closure;

class QueryBuilder implements Interfaces\QueryBuilderInterface
{

    protected const STRUCTURE = [
        'select'        => [],
        'table'         => [],
        'join'          => [],
        'where'         => [
            'AND'           => [],
            'OR'            => [],
        ],
        'having'        => [
            'AND'           => [],
            'OR'            => [],
        ],
        'group_by'      => [],
        'order_by'      => [],
        'offset'        => null,
        'limit'         => null,
        'set'           => [],
        'on'            => [
            'AND'           => [],
            'OR'            => [],
        ],
    ];

    protected array $structure;

    protected ParameterInterface $parameters;

    public function __construct()
    {
        $this->structure = self::STRUCTURE;
        $this->parameters = new Parameters();
    }

    /**
     * @return string
     * @throws QueryGeneratorException
     */
    public function __toString(): string
    {
        if (empty($this->structure['set'])) {
            return $this->generateSelectQuery();
        }

        $isBatch = $this->isBatch();
        $isInsert = empty($this->structure['where']['OR']) && empty($this->structure['where']['AND']) && empty($this->structure['having']['OR']) && empty($this->structure['having']['AND']);

        if ($isInsert) {
            return $isBatch ? $this->generateBatchInsertQuery() : $this->generateInsertQuery();
        }

        return $this->generateUpdateQuery();
    }

    /**
     * @inheritDoc
     */
    public function newBuilder(): self
    {
        return new self();
    }

    /**
     * @param string[]|string|null $ignoreOrCare
     * @param null|bool $isIgnore
     * @return $this
     */
    public function resetStructure(null|array|string $ignoreOrCare = null, ?bool $isIgnore = null): self
    {
        if ($ignoreOrCare === null) {
            $this->structure = self::STRUCTURE;
        } else {
            if (is_string($ignoreOrCare)) {
                $ignoreOrCare = [$ignoreOrCare];
            }

            $newStructure = self::STRUCTURE;
            foreach ($ignoreOrCare as $key) {
                if (!isset($this->structure[$key])) {
                    continue;
                }
                if ($isIgnore) {
                    $newStructure[$key] = $this->structure[$key];
                } else {
                    $newStructure[$key] = self::STRUCTURE[$key] ?? [];
                }
            }

            $this->structure = $newStructure;
        }

        return $this;
    }

    public function clone(): self
    {
        return (clone $this);
    }

    /**
     * @inheritDoc
     */
    public function importQB(array $structure, bool $merge = false): self
    {
        $this->structure = array_merge(($merge ? $this->structure : self::STRUCTURE), $structure);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function exportQB(): array
    {
        return $this->structure;
    }

    /**
     * @inheritDoc
     */
    public function getParameter(): ParameterInterface
    {
        return $this->parameters;
    }

    /**
     * @inheritDoc
     */
    public function setParameter(string $key, mixed $value): self
    {
        $this->parameters->set($key, $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setParameters(array $parameters = []): self
    {
        foreach ($parameters as $key => $value) {
            $this->parameters->set($key, $value);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function select(...$columns): self
    {
        foreach ($columns as $column) {
            $column = (string)$column;
            $this->structure['select'][] = $column;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function clearSelect(): self
    {
        $this->structure['select'] = [];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCount(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'COUNT(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCountDistinct(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'COUNT(DISTINCT ' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMax(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'MAX(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMin(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'MIN(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAvg(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'AVG(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAs(RawQuery|string $column, string $alias): self
    {
        $this->structure['select'][] = $column . ' AS ' . $alias;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectUpper(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'UPPER(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLower(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'LOWER(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLength(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'LENGTH(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMid(RawQuery|string $column, int $offset, int $length, ?string $alias = null): self
    {
        $this->structure['select'][] = 'MID(' . $column . ', ' . $offset . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLeft(RawQuery|string $column, int $length, ?string $alias = null): self
    {
        $this->structure['select'][] = 'LEFT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectRight(RawQuery|string $column, int $length, ?string $alias = null): self
    {
        $this->structure['select'][] = 'RIGHT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectDistinct(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'DISTINCT(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCoalesce(RawQuery|string $column, $default = '0', ?string $alias = null): self
    {
        $this->structure['select'][] = 'COALESCE(' . $column . ', ' . $default .  ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectSum(RawQuery|string $column, ?string $alias = null): self
    {
        $this->structure['select'][] = 'SUM(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectConcat(array $columns, ?string $alias = null): self
    {
        foreach ($columns as &$column) {
            $column = (string)$column;
        }
        $this->structure['select'][] = 'CONCAT(' . implode(', ', $columns) . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function from(RawQuery|string $table, ?string $alias = null): self
    {
        $this->structure['table'] = [];

        return $this->addFrom($table, $alias);
    }

    /**
     * @inheritDoc
     */
    public function addFrom(RawQuery|string $table, ?string $alias = null): self
    {
        $table = $table . ($alias !== null ? ' AS ' . $alias : '');
        if (!in_array($table, $this->structure['table'], true)) {
            $this->structure['table'][] = $table;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function table(RawQuery|string $table): self
    {
        $this->structure['table'] = [(string)$table];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function groupBy(string|RawQuery|array ...$columns): self
    {
        foreach ($columns as $column) {
            if (is_array($column)) {
                $this->groupBy(...$column);
                continue;
            }

            $column = (string)$column;
            if (!in_array($column, $this->structure['group_by'])) {
                $this->structure['group_by'][] = $column;
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function join(RawQuery|string $table, RawQuery|Closure|string $onStmt = null, string $type = 'INNER'): self
    {
        $table = (string)$table;

        if ($onStmt instanceof Closure) {
            $builder = $this->clone()->resetStructure();
            $onStmt = call_user_func_array($onStmt, [&$builder]);
            if ($onStmt === null) {
                if ($where = $builder->__generateWhereQuery()) {
                    $this->where($this->raw($where));
                }
                if ($having = $builder->__generateHavingQuery()) {
                    if (str_starts_with($having, ' HAVING ')) {
                        $having = substr($having, 8);
                    }
                    $this->having($this->raw($having));
                }
                $onStmt = $builder->__generateOnQuery();
            }
        }

        $type = trim(strtoupper($type));
        switch ($type) {
            case 'SELF':
                $this->addFrom($table);
                $this->where(is_string($onStmt) ? $this->raw($onStmt) : $onStmt);
                break;
            case 'NATURAL':
            case 'NATURAL JOIN':
                $this->structure['join'][$table] = 'NATURAL JOIN ' . $table;
                break;
            default:
                $this->structure['join'][$table] =  trim($type . ' JOIN ' . $table . ' ON ' . $onStmt);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selfJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): self
    {
        return $this->join($table, $onStmt, 'SELF');
    }

    /**
     * @inheritDoc
     */
    public function innerJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): self
    {
        return $this->join($table, $onStmt);
    }

    /**
     * @inheritDoc
     */
    public function leftJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT');
    }

    /**
     * @inheritDoc
     */
    public function rightJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT');
    }

    /**
     * @inheritDoc
     */
    public function leftOuterJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT OUTER');
    }

    /**
     * @inheritDoc
     */
    public function rightOuterJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT OUTER');
    }

    /**
     * @inheritDoc
     */
    public function naturalJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): self
    {
        return $this->join($table, null, 'NATURAL');
    }

    /**
     * @inheritDoc
     */
    public function orderBy(RawQuery|string $column, string $soft = 'ASC'): self
    {
        $soft = trim(strtoupper($soft));
        if (!in_array($soft, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('It can only sort as ASC or DESC.');
        }
        $orderBy = trim((string)$column) . ' ' . $soft;

        !in_array($orderBy, $this->structure['order_by'], true) && $this->structure['order_by'][] = $orderBy;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function where(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): self
    {

        $this->whereOrHavingPrepare($operator, $value, $logical);

        $this->structure['where'][$logical][] = $this->whereOrHavingStatementPrepare($column, $operator, $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function having(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): self
    {
        $this->whereOrHavingPrepare($operator, $value, $logical);
        $this->structure['having'][$logical][] = $this->whereOrHavingStatementPrepare($column, $operator, $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function on(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): self
    {
        $this->whereOrHavingPrepare($operator, $value, $logical);

        $this->structure['on'][$logical][] = $this->whereOrHavingStatementPrepare($column, $operator, $value);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function set(RawQuery|array|string $column, mixed $value = null, bool $strict = true): self
    {
        return $this->addSet($column, $value, $strict);
    }

    /**
     * @inheritDoc
     */
    public function addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true): self
    {
        if (is_array($column) && $value === null) {
            $set = [];
            foreach ($column as $name => $value) {
                $name = (string)$name;
                $set[$name] = $this->isSQLParameterOrFunction($value) ? $value : $this->parameters->add($name, $value);
            }
            $this->structure['set'][] = $set;

            return $this;
        }

        $column = (string)$column;
        $value = $this->isSQLParameterOrFunction($value) ? $value : $this->parameters->add($column, $value);

        $this->structure['set'][][$column] = $value;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function andWhere(RawQuery|string $column, mixed $operator = '=', mixed $value = null): self
    {
        return $this->where($column, $operator, $value);
    }

    /**
     * @inheritDoc
     */
    public function orWhere(RawQuery|string $column, mixed $operator = '=', mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function between(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): self
    {
        if (is_array($firstValue) && count($firstValue) == 2 && $lastValue === null) {
            $value = $firstValue;
        } else {
            $value = [$firstValue, $lastValue];
        }

        return $this->where($column, 'BETWEEN', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function orBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): self
    {
        return $this->between($column, [$firstValue, $lastValue], 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): self
    {
        return $this->between($column, $firstValue, $lastValue);
    }

    /**
     * @inheritDoc
     */
    public function notBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): self
    {
        if (is_array($firstValue) && count($firstValue) == 2 && $lastValue === null) {
            $value = $firstValue;
        } else {
            $value = [$firstValue, $lastValue];
        }

        return $this->where($column, 'NOT BETWEEN', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): self
    {
        return $this->notBetween($column, $firstValue, $lastValue, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): self
    {
        return $this->notBetween($column, $firstValue, $lastValue);
    }

    /**
     * @inheritDoc
     */
    public function findInSet(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, 'FIND_IN_SET', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function andFindInSet(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'FIND_IN_SET', $value);
    }

    /**
     * @inheritDoc
     */
    public function orFindInSet(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'FIND_IN_SET', $value, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function notFindInSet(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, 'NOT FIND_IN_SET', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function andNotFindInSet(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'NOT FIND_IN_SET', $value);
    }

    /**
     * @inheritDoc
     */
    public function orNotFindInSet(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'NOT FIND_IN_SET', $value, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function whereIn(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, 'IN', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function whereNotIn(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, 'NOT IN', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function orWhereIn(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'IN', $value, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function orWhereNotIn(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'NOT IN', $value, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andWhereIn(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'IN', $value);
    }

    /**
     * @inheritDoc
     */
    public function andWhereNotIn(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'IN', $value);
    }

    /**
     * @inheritDoc
     */
    public function regexp(RawQuery|string $column, RawQuery|string $value, string $logical = 'AND'): self
    {
        return $this->where($column, 'REGEXP', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function andRegexp(RawQuery|string $column, RawQuery|string $value): self
    {
        return $this->where($column, 'REGEXP', $value);
    }

    /**
     * @inheritDoc
     */
    public function orRegexp(RawQuery|string $column, RawQuery|string $value): self
    {
        return $this->where($column, 'REGEXP', $value, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function soundex(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, 'SOUNDEX', $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function andSoundex(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'SOUNDEX', $value);
    }

    /**
     * @inheritDoc
     */
    public function orSoundex(RawQuery|string $column, mixed $value = null): self
    {
        return $this->where($column, 'SOUNDEX', $value, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function whereIsNull(RawQuery|string $column, string $logical = 'AND'): self
    {
        return $this->where($column, 'IS', null, $logical);
    }

    /**
     * @inheritDoc
     */
    public function orWhereIsNull(RawQuery|string $column): self
    {
        return $this->where($column, 'IS', null, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andWhereIsNull(RawQuery|string $column): self
    {
        return $this->where($column, 'IS');
    }

    /**
     * @inheritDoc
     */
    public function whereIsNotNull(RawQuery|string $column, string $logical = 'AND'): self
    {
        return $this->where($column, 'IS NOT', null, $logical);
    }

    /**
     * @inheritDoc
     */
    public function orWhereIsNotNull(RawQuery|string $column): self
    {
        return $this->where($column, 'IS NOT', null, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andWhereIsNotNull(RawQuery|string $column): self
    {
        return $this->where($column, 'IS NOT');
    }

    /**
     * @inheritDoc
     */
    public function offset(int $offset = 0): self
    {
        $this->structure['offset'] = (int)abs($offset);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function limit(int $limit): self
    {
        $this->structure['limit'] = (int)abs($limit);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function like(RawQuery|array|string $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): self
    {
        $operator = match (strtolower($type)) {
            'before', 'start'       => 'START LIKE',
            'after', 'end'          => 'END LIKE',
            default                 => 'LIKE'
        };

        return $this->where($column, $operator, $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function orLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): self
    {
        return $this->like($column, $value, $type);
    }

    /**
     * @inheritDoc
     */
    public function andLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): self
    {
        return $this->like($column, $value, $type, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function notLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): self
    {
        $operator = match (strtolower($type)) {
            'before', 'start'       => 'NOT START LIKE',
            'after', 'end'          => 'NOT END LIKE',
            default                 => 'NOT LIKE'
        };

        return $this->where($column, $operator, $value, $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): self
    {
        return $this->notLike($column, $value, $type, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): self
    {
        return $this->notLike($column, $value, $type);
    }

    /**
     * @inheritDoc
     */
    public function startLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->like($column, $value, 'before', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->like($column, $value, 'before', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->like($column, $value, 'before');
    }

    /**
     * @inheritDoc
     */
    public function notStartLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->notLike($column, $value, 'before', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartNotLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->notLike($column, $value, 'before', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartNotLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->notLike($column, $value, 'before');
    }

    /**
     * @inheritDoc
     */
    public function endLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->like($column, $value, 'after', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->like($column, $value, 'after', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->like($column, $value, 'after');
    }

    /**
     * @inheritDoc
     */
    public function notEndLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): self
    {
        return $this->notLike($column, $value, 'after', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndNotLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->notLike($column, $value, 'after', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndNotLike(RawQuery|array|string $column, mixed $value = null): self
    {
        return $this->notLike($column, $value, 'after');
    }

    /**
     * @inheritDoc
     */
    public function subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true): RawQuery
    {
        $builder = $this->clone()->resetStructure();

        call_user_func_array($closure, [&$builder]);
        if ($alias !== null && $isIntervalQuery !== TRUE) {
            throw new QueryBuilderException('To define alias to a subquery, it must be an inner query.');
        }

        $rawQuery = ($isIntervalQuery ? '(' : '')
            . $builder->generateSelectQuery()
            . ($isIntervalQuery ? ')' : '')
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this->raw($rawQuery);
    }

    /**
     * @inheritDoc
     */
    public function group(Closure $closure, string $logical = 'AND'): self
    {
        $logical = str_replace(['&&', '||'], ['AND', 'OR'], strtoupper($logical));
        if(!in_array($logical, ['AND', 'OR'], true)){
            throw new QueryBuilderException('Logical operator OR, AND, && or || it could be.');
        }

        $builder = $this->clone();
        call_user_func_array($closure, [$builder->resetStructure()]);



        foreach (['where', 'on', 'having'] as $stmt) {
            $statement = $builder->__generateStructure($stmt);
            !empty($statement) && $this->structure[$stmt][$logical][] = '(' . $statement . ')';
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function raw(mixed $rawQuery): RawQuery
    {
        return new RawQuery($rawQuery);
    }

    /**
     * @return string
     * @throws QueryGeneratorException
     */
    public function generateInsertQuery(): string
    {
        $columns = [];
        $values = [];
        $set = array_merge(...$this->structure['set']);
        foreach ($set as $column => $value) {
            $columns[] = $column;
            $values[] = $value;
        }
        if (empty($columns)) {
            throw new QueryGeneratorException('The data set for the insert could not be found.');
        }

        return 'INSERT INTO'
            . ' ' . $this->__generateSchemaName() . ' '
            . '(' . implode(', ', $columns) . ')'
            . ' VALUES '
            . '(' . implode(', ', $values) . ');';
    }

    /**
     * @return string
     * @throws QueryGeneratorException
     */
    public function generateBatchInsertQuery(): string
    {
        $columns = array_keys(array_merge(...$this->structure['set']));
        if (empty($columns)) {
            throw new QueryGeneratorException('The data set for the insert could not be found.');
        }
        $values = [];
        foreach ($this->structure['set'] as $set) {
            $value = [];
            foreach ($columns as $column) {
                $value[$column] = $set[$column] ?? 'NULL';
            }
            $values[] = '(' . implode(', ', $value) . ')';
        }

        return 'INSERT INTO'
            . ' ' . $this->__generateSchemaName() . ' '
            . '(' . implode(', ', $columns) . ')'
            . ' VALUES '
            . implode(', ', $values) . ';';
    }

    /**
     * @return string
     * @throws QueryGeneratorException
     */
    public function generateDeleteQuery(): string
    {
        return 'DELETE FROM'
            . ' '
            . $this->__generateSchemaName()
            . ' WHERE '
            . (($where = $this->__generateWhereQuery()) !== null ? $where : '1')
            . ($this->__generateLimitQuery() ?? '');
    }

    /**
     * @param array $selector
     * @param array $conditions
     * @return string
     */
    public function generateSelectQuery(array $selector = [], array $conditions = []): string
    {
        !empty($selector) && $this->select(...$selector);
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (is_string($column)) {
                    $this->where($column, $value);
                } else {
                    $this->where($value);
                }
            }
        }

        return 'SELECT '
            . (empty($this->structure['select']) ? '*' : implode(', ', $this->structure['select']))
            . ' FROM '
            . implode(', ', $this->structure['table'])
            . (!empty($this->structure['join']) ? ' ' . implode(' ', $this->structure['join']) : '')
            . ' WHERE '
            . (($where = $this->__generateWhereQuery()) ? $where : '1')
            . (!empty($this->structure['group_by']) ? ' GROUP BY ' . implode(', ', $this->structure['group_by']) : '')
            . ($this->__generateHavingQuery() ?? '')
            . (!empty($this->structure['order_by']) ? ' ORDER BY ' . implode(', ', $this->structure['order_by']) : '')
            . ($this->__generateLimitQuery() ?? '');
    }

    /**
     * @return string
     * @throws QueryGeneratorException
     */
    public function generateUpdateQuery(): string
    {
        $set = array_merge(...$this->structure['set']);
        $updateSet = [];
        foreach ($set as $column => $value) {
            $updateSet[] = $column . ' = ' . $value;
        }
        if (empty($updateSet)) {
            throw new QueryGeneratorException('The data set for the insert could not be found.');
        }

        return 'UPDATE ' . $this->__generateSchemaName()
            . ' SET ' . implode(', ', $updateSet)
            . ' WHERE '
            . (($where = $this->__generateWhereQuery()) ? $where : '1')
            . ($this->__generateHavingQuery() ?? '')
            . ($this->__generateLimitQuery() ?? '');
    }

    /**
     * @param string $referenceColumn
     * @return string
     * @throws QueryGeneratorException
     */
    public function generateUpdateBatchQuery(string $referenceColumn): string
    {
        $update = [];
        $data = $this->structure['set'];
        $updateData = $columns = $where = [];
        foreach ($data as $set) {
            if (!isset($set[$referenceColumn])) {
                throw new QueryGeneratorException('The reference column does not exist in one or more of the set arrays.');
            }
            $setData = [];
            $where[] = $set[$referenceColumn];
            unset($set[$referenceColumn]);
            foreach ($set as $key => $value) {
                $setData[$key] = $value;
                (!in_array($key, $columns)) && $columns[] = $key;
            }
            $updateData[] = $setData;
        }
        foreach ($columns as $column) {
            $syntax = $column . ' = CASE';
            foreach ($updateData as $key => $values) {
                if (!array_key_exists($column, $values)) {
                    continue;
                }
                $syntax .= ' WHEN ' . $referenceColumn . ' = '
                    . ($this->isSQLParameterOrFunction($where[$key]) ? $where[$key] : $this->parameters->add($referenceColumn, $where[$key]))
                    . ' THEN '
                    . $values[$column];
            }
            $update[] = $syntax . ' ELSE ' . $column .' END';
        }

        $this->whereIn($referenceColumn, $where);

        return 'UPDATE ' . $this->__generateSchemaName()
            . ' SET '
            . implode(', ', $update)
            . ' WHERE '
            . (($where = $this->__generateWhereQuery()) ? $where : '1')
            . ($this->__generateHavingQuery() ?? '')
            . ($this->__generateLimitQuery() ?? '');
    }

    protected function isSQLParameter($value): bool
    {
        return (is_string($value)) && ($value === '?' || preg_match('/^:[(\w)]+$/', $value));
    }

    protected function isSQLParameterOrFunction($value): bool
    {
        return ((is_string($value)) && (
                $value === '?'
                || preg_match('/^:[(\w)]+$/', $value)
                || preg_match('/^[a-zA-Z_]+[.]+[a-zA-Z_]+$/', $value)
                || preg_match('/^[a-zA-Z_]+\(\)$/', $value)
            )) || ($value instanceof RawQuery) || is_int($value);
    }

    public function isBatch(): bool
    {
        foreach ($this->structure['set'] as $set) {
            if (is_array($set) && count($set) > 1) {
                return true;
            }
        }

        return false;
    }

    private function whereOrHavingStatementPrepare($column, $operator, $value): string
    {
        $operator = trim($operator);
        $column = (string)$column;

        if ($value !== null && in_array($operator, [
                '=', '!=', '>', '<', '>=', '<=', '<>',
                '+', '-', '*', '/', '%',
                '+=', '-=', '*=', '/=', '%=', '&=', '^-=', '|*='
            ], true)) {
            return $column . ' ' . $operator . ' '
                . ($this->isSQLParameterOrFunction($value) ? $value : $this->parameters->add($column, $value));
        }
        $upperCaseOperator = strtoupper($operator);
        $searchOperator = str_replace([' ', '_'], '', $upperCaseOperator);
        if ($value === null && !in_array($searchOperator, ['IS', 'ISNOT'])) {
            return $column;
        }

        switch ($searchOperator) {
            case 'IS':
                return $column . ' IS '
                    . ((($value === null) ? 'NULL' : ($this->isSQLParameterOrFunction($value) ? $value : $this->parameters->add($column, $value))));
            case 'ISNOT':
                return $column . ' IS NOT '
                    . ((($value === null) ? 'NULL' : ($this->isSQLParameterOrFunction($value) ? $value : $this->parameters->add($column, $value))));
            case 'LIKE':
            case 'NOTLIKE':
            case 'STARTLIKE':
            case 'NOTSTARTLIKE':
            case 'ENDLIKE':
            case 'NOTENDLIKE':
                if (!$this->isSQLParameter($value)) {
                    $value = (in_array($searchOperator, ['LIKE', 'NOTLIKE', 'STARTLIKE', 'NOTSTARTLIKE']) ? '%' : '')
                        . $value
                        . (in_array($searchOperator, ['LIKE', 'NOTLIKE', 'ENDLIKE', 'NOTENDLIKE']) ? '%' : '');

                    $value = $this->parameters->add($column, $value);
                }

                return $column
                    . (in_array($searchOperator, ['NOTSTARTLIKE', 'NOTLIKE', 'NOTENDLIKE']) ? ' NOT' : '')
                    . ' LIKE ' . $value;
            case 'BETWEEN':
            case 'NOTBETWEEN':
                return $column . ' '
                    . ($searchOperator === 'NOTBETWEEN' ? 'NOT ' : '')
                    . 'BETWEEN '
                    . ($this->isSQLParameterOrFunction($value[0]) ? $value[0] : $this->parameters->add($column, $value[0]))
                    . ' AND '
                    . ($this->isSQLParameterOrFunction($value[1]) ? $value[1] : $this->parameters->add($column, $value[1]));
            case 'IN':
            case 'NOTIN':
                if (is_array($value)) {
                    $values = [];
                    array_map(function ($item) use (&$values, $column) {
                        if (is_numeric($item)) {
                            $values[] = $item;
                        } else {
                            $values[] = $this->isSQLParameterOrFunction($item) ? $item : $this->parameters->add($column, $item);
                        }
                    }, array_unique($value));
                    $value = '(' . implode(', ', $values) . ')';
                }
                return $column
                    . ($searchOperator === 'NOTIN' ? ' NOT' : '')
                    . ' IN ' . $value;
            case 'REGEXP':
                return $column . ' REGEXP '
                    . ($this->isSQLParameterOrFunction($value) ? $value : $this->parameters->add($column, $value));
            case 'FINDINSET':
            case 'NOTFINDINSET':
                if (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif ($this->isSQLParameterOrFunction($value)) {
                    $value = $this->parameters->add($column, $value);
                }
                return ($searchOperator === 'NOTFINDINSET' ? 'NOT ' : '')
                    . 'FIND_IN_SET(' . $value . ', ' . $column . ')';
            case 'SOUNDEX':
                if (!$this->isSQLParameterOrFunction($value)) {
                    $value = $this->parameters->add($column, $value);
                }
                return "SOUNDEX(" . $column . ") LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(" . $value . ")), '%')";
            default:
                if ($value === null && preg_match('/([\w_]+)\((.+)\)$/iu', $column, $matches) !== FALSE) {
                    return strtoupper($matches[1]) . '(' . $matches[2] . ')';
                }
                return $column . ' ' . $operator . ' ' . $this->parameters->add($column, $value);
        }
    }

    /**
     * @return string
     * @throws QueryGeneratorException
     */
    private function __generateSchemaName(): string
    {
        if (!empty($this->structure['table'])) {
            $table = end($this->structure['table']);
        } else {
            throw new QueryGeneratorException('Table name not found when query.');
        }

        return $table;
    }

    private function __generateLimitQuery(): ?string
    {
        if ($this->structure['limit'] === null && $this->structure['offset'] === null) {
            return null;
        }
        $statement = ' ';
        if ($this->structure['limit'] === null) {
            $statement .= 'OFFSET ' . $this->structure['offset'];
        } else {
            $statement .= 'LIMIT '
                . ($this->structure['offset'] !== null ?  $this->structure['offset'] . ', ' : '')
                . $this->structure['limit'];
        }

        return $statement;
    }

    private function __generateOnQuery(): ?string
    {
        return $this->__generateStructure('on');
    }

    private function __generateHavingQuery(): ?string
    {
        $stmt = $this->__generateStructure('having');

        return $stmt === null ? null : ' HAVING ' . $stmt;
    }

    private function __generateWhereQuery(): ?string
    {
        return $this->__generateStructure('where');
    }

    private function __generateStructure(string $key): ?string
    {
        $isAndEmpty = empty($this->structure[$key]['AND']);
        $isOrEmpty = empty($this->structure[$key]['OR']);
        if ($isOrEmpty && $isAndEmpty) {
            return null;
        }

        return (!$isAndEmpty ? implode(' AND ', $this->structure[$key]['AND']) : '')
            . (!$isAndEmpty && !$isOrEmpty ? ' AND ' : '')
            . (!$isOrEmpty ?  implode(' OR ', $this->structure[$key]['OR']) : '');
    }

    private function whereOrHavingPrepare(&$operator, &$value, &$logical): void
    {
        $logical = strtoupper(strtr($logical, [
            '&&'        => 'AND',
            '||'        => 'OR',
        ]));
        if (!in_array($logical, ['AND', 'OR'], true)) {
            throw new InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }

        if ($value === null && !in_array($operator, [
                'IS', 'IS NOT',
                '=', '!=', '>', '<', '>=', '<=', '<>',
                '+', '-', '*', '/', '%',
                '+=', '-=', '*=', '/=', '%=', '&=', '^-=', '|*='
            ])) {
            $value = $operator;
            $operator = '=';
        }
    }

}
