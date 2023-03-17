<?php
/**
 * QueryBuilder
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.8
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

use \InitPHP\Database\Helpers\{Helper, Parameters};
use \InitPHP\Database\Exceptions\ValueException;

class QueryBuilder
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
    ];

    protected array $_STRUCTURE = self::STRUCTURE;

    public function reset(): void
    {
        $this->_STRUCTURE = self::STRUCTURE;
    }

    public function importQB(array $structure): self
    {
        $this->_STRUCTURE = $structure;

        return $this;
    }

    public function exportQB(): array
    {
        return $this->_STRUCTURE;
    }

    /**
     * @param string|Raw ...$columns
     * @return $this
     */
    final public function select(...$columns): self
    {
        foreach ($columns as $column) {
            $this->_STRUCTURE['select'][] = (string)$column;
        }

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectCount($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'COUNT(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectMax($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'MAX(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectMin($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'MIN(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectAvg($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'AVG(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string $alias
     * @return $this
     */
    final public function selectAs($column, string $alias): self
    {
        $this->_STRUCTURE['select'][] = $column . ' AS ' . $alias;
        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectUpper($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'UPPER(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectLower($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'LOWER(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectLength($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'LENGTH(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param int $offset
     * @param int $length
     * @param string|null $alias
     * @return $this
     */
    final public function selectMid($column, int $offset, int $length, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'MID(' . $column . ', ' . $offset . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param int $length
     * @param string|null $alias
     * @return $this
     */
    final public function selectLeft($column, int $length, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'LEFT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param int $length
     * @param string|null $alias
     * @return $this
     */
    final public function selectRight($column, int $length, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'RIGHT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param string|null $alias
     * @return $this
     */
    final public function selectDistinct($column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'DISTINCT(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param mixed $default
     * @param string|null $alias
     * @return $this
     */
    final public function selectCoalesce($column, $default = '0', ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'COALESCE(' . $column . ', ' . $default . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    final public function selectSum(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'SUM(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|null $alias
     * @param string|Raw ...$columnOrStr
     * @return $this
     */
    final public function selectConcat(?string $alias = null, ...$columnOrStr): self
    {
        foreach ($columnOrStr as &$item) {
            $item = (string)$item;
        }

        $this->_STRUCTURE['select'][] = 'CONCAT(' . \implode(', ', $columnOrStr) . ')'
            . ($alias !== null ? ' AS ' . $alias : '');

        return $this;
    }

    /**
     * @param string|Raw ...$tables
     * @return $this
     */
    final public function from(...$tables): self
    {
        $this->_STRUCTURE['table'] = [];
        $this->addFrom(...$tables);

        return $this;
    }

    /**
     * @param string|Raw ...$tables
     * @return $this
     */
    final public function addFrom(...$tables): self
    {
        foreach ($tables as $table) {
            $table = (string)$table;
            if(!\in_array($table, $this->_STRUCTURE['table'], true)){
                $this->_STRUCTURE['table'][] = $table;
            }
        }
        return $this;
    }

    /**
     * @param string|Raw $table
     * @return $this
     */
    final public function table($table): self
    {
        $this->_STRUCTURE['table'] = [(string)$table];

        return $this;
    }

    /**
     * @param string|Raw ...$columns
     * @return $this
     */
    final public function groupBy(...$columns): self
    {
        foreach ($columns as $column){
            $column = (string)$column;
            if(!\in_array($column, $this->_STRUCTURE['group_by'])){
                $this->_STRUCTURE['group_by'][] = $column;
            }
        }
        return $this;
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|null $onStmt
     * @param string $type
     * @return $this
     */
    final public function join($table, $onStmt = null, string $type = 'INNER'): self
    {
        $table = (string)$table;

        $type = \trim(\strtoupper($type));
        switch ($type) {
            case 'SELF' :
                $this->addFrom($table);
                $onStmt !== null && $this->where((\is_string($onStmt) ? new Raw($onStmt) : $onStmt));
                break;
            case 'NATURAL':
            case 'NATURAL JOIN':
                $this->_STRUCTURE['join'][$table] = 'NATURAL JOIN ' . $table;
                break;
            default:
                $this->_STRUCTURE['join'][$table] = $type . ' JOIN ' . $table . ' ON ' . $onStmt;
        }
        return $this;
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $onStmt
     * @return $this
     */
    final public function selfJoin($table, $onStmt): self
    {
        return $this->join($table, $onStmt, 'SELF');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $onStmt
     * @return $this
     */
    final public function innerJoin($table, $onStmt): self
    {
        return $this->join($table, $onStmt, 'INNER');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $onStmt
     * @return $this
     */
    final public function leftJoin($table, $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $onStmt
     * @return $this
     */
    final public function rightJoin($table, $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $onStmt
     * @return $this
     */
    final public function leftOuterJoin($table, $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT OUTER');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $onStmt
     * @return $this
     */
    final public function rightOuterJoin($table, $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT OUTER');
    }

    /**
     * @param string|Raw $table
     * @return $this
     */
    final public function naturalJoin($table): self
    {
        return $this->join($table, null, 'NATURAL');
    }

    /**
     * @param string|Raw $column
     * @param string $soft [ASC|DESC]
     * @return $this
     */
    final public function orderBy($column, string $soft = 'ASC'): self
    {
        $soft = \trim(\strtoupper($soft));
        if(!\in_array($soft, ['ASC', 'DESC'], true)){
            throw new \InvalidArgumentException('It can only sort as ASC or DESC.');
        }
        $orderBy = \trim((string)$column) . ' ' . $soft;
        if(!\in_array($orderBy, $this->_STRUCTURE['order_by'], true)){
            $this->_STRUCTURE['order_by'][] = $orderBy;
        }

        return $this;
    }

    /**
     * @param Raw|string $column
     * @param mixed $value
     * @param string $mark [=|!=|>|<|>=|<=]
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function where($column, $value = null, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = \str_replace(['&&', '||'], ['AND', 'OR'], \strtoupper($logical));
        if(!\in_array($logical, ['AND', 'OR'], true)){
            throw new \InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }

        $this->_STRUCTURE['where'][$logical][] = $this->whereOrHavingStatementPrepare($column, $value, $mark);

        return $this;
    }

    /**
     * @param Raw|string $column
     * @param mixed $value
     * @param string $mark [=|!=|>|<|>=|<=]
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function having($column, $value = null, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = \str_replace(['&&', '||'], ['AND', 'OR'], \strtoupper($logical));
        if(!\in_array($logical, ['AND', 'OR'], true)){
            throw new \InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->_STRUCTURE['having'][$logical][] =$this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @param string|Raw $column
     * @param mixed $value
     * @param string $mark [=|!=|>|<|>=|<=]
     * @return $this
     */
    final public function andWhere($column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'AND');
    }

    /**
     * @param string|Raw $column
     * @param mixed $value
     * @param string $mark [=|!=|>|<|>=|<=]
     * @return $this
     */
    final public function orWhere($column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'OR');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $values
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function between($column, $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'BETWEEN', $logical);
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $values
     * @return $this
     */
    final public function orBetween($column, $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $values
     * @return $this
     */
    final public function andBetween($column, $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $values
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function notBetween($column, $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', $logical);
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $values
     * @return $this
     */
    final public function orNotBetween($column, $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $values
     * @return $this
     */
    final public function andNotBetween($column, $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function findInSet($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'FINDINSET', $logical);
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function orFindInSet($column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function andFindInSet($column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function notFindInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', $logical);
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function andNotFindInSet($column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function orNotFindInSet($column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function in($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IN', $logical);
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function orIn($column, $value): self
    {
        return $this->where($column, $value, 'IN', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function andIn($column, $value): self
    {
        return $this->where($column, $value, 'IN', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function notIn($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTIN', $logical);
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function orNotIn($column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param array|Raw|string $value
     * @return $this
     */
    final public function andNotIn($column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function regexp($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'REGEXP', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andRegexp($column, $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orRegexp($column, $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function like($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'LIKE', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orLike($column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andLike($column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function startLike($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTLIKE', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orStartLike($column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andStartLike($column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function endLike($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDLIKE', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orEndLike($column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andEndLike($column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function notLike($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTLIKE', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orNotLike($column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andNotLike($column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function startNotLike($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orStartNotLike($column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andStartNotLike($column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function endNotLike($column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orEndNotLike($column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andEndNotLike($column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function soundex(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'SOUNDEX', $logical);
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function orSoundex(string $column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param string|Raw $value
     * @return $this
     */
    final public function andSoundex($column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param null $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function is($column, $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IS', $logical);
    }

    /**
     * @param string|Raw $column
     * @param null $value
     * @return $this
     */
    final public function orIs($column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param null $value
     * @return $this
     */
    final public function andIs($column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'AND');
    }

    /**
     * @param string|Raw $column
     * @param null $value
     * @param string $logical [AND|OR]
     * @return $this
     */
    final public function isNot($column, $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ISNOT', $logical);
    }

    /**
     * @param string|Raw $column
     * @param null $value
     * @return $this
     */
    final public function orIsNot($column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'OR');
    }

    /**
     * @param string|Raw $column
     * @param null $value
     * @return $this
     */
    final public function andIsNot($column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'AND');
    }

    /**
     * @param int $offset
     * @return $this
     */
    final public function offset(int $offset = 0): self
    {
        $this->_STRUCTURE['offset'] = (int)\abs($offset);
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    final public function limit(int $limit): self
    {
        $this->_STRUCTURE['limit'] = (int)\abs($limit);

        return $this;
    }

    /**
     * @param string|Raw $column
     * @param mixed $value
     * @param string $mark
     * @return string
     */
    private function whereOrHavingStatementPrepare($column, $value, string $mark = '='): string
    {
        $mark = \trim($mark);
        if(\in_array($mark, ['=', '!=', '<=', '>=', '>', '<'], true)){
            if ($value === null && ($column instanceof Raw)) {
                return (string)$column;
            }

            return $column . ' ' . $mark . ' '
                . (Helper::isSQLParameterOrFunction($value) ? $value : Parameters::add($column, $value));
        }
        $markUpperCase = \strtoupper($mark);
        $searchMark = \str_replace([' ', '_'], '', $markUpperCase);
        switch ($searchMark) {
            case 'IS':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = $value !== null ? Parameters::add($column, $value) : 'NULL';
                }
                return $column . ' IS ' . $value;
            case 'ISNOT':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = $value !== null ? Parameters::add($column, $value) : 'NULL';
                }
                return $column . ' IS NOT ' . $value;
            case 'LIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = (substr($value, -1) == '%' || substr($value, 0, 1) == '%')
                        ? $value
                        : '%' . $value . '%';

                    $value = Parameters::add($column, $value);
                }
                return $column . ' LIKE ' . $value;
            case 'STARTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, '%' . trim($value, '%'));
                }
                return $column . ' LIKE ' . $value;
            case 'ENDLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, trim($value, '%') . '%');
                }
                return $column . ' LIKE ' . $value;
            case 'NOTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = (substr($value, -1) == '%' || substr($value, 0, 1) == '%')
                        ? $value
                        : '%' . $value . '%';

                    $value = Parameters::add($column, $value);
                }
                return $column . ' NOT LIKE ' . $value;
            case 'STARTNOTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, '%' . trim($value, '%'));
                }
                return $column . ' NOT LIKE ' . $value;
            case 'ENDNOTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, trim($value, '%') . '%');
                }
                return $column . ' NOT LIKE ' . $value;
            case 'REGEXP':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, $value);
                }
                return $column . ' REGEXP ' . $value;
            case 'BETWEEN':
            case 'NOTBETWEEN':
                if (\is_array($value) && \count($value) == 2) {
                    $valueStmt = (Helper::isSQLParameterOrFunction($value[0]) ? $value[0] : Parameters::add($column, $value[0]))
                    . ' AND '
                    . (Helper::isSQLParameterOrFunction($value[1]) ? $value[1] : Parameters::add($column, $value[1]));
                } elseif (Helper::isSQLParameterOrFunction($value)) {
                    $valueStmt = (string)$value;
                } else {
                    throw new ValueException('An incorrect value was defined.');
                }
                return $column . ' '
                    . ($searchMark === 'NOTBETWEEN' ? 'NOT ':'')
                    . 'BETWEEN ' . $valueStmt;
            case 'IN':
            case 'NOTIN':
                if(\is_array($value)){
                    $values = [];
                    foreach ($value as $val) {
                        if(\is_numeric($val)){
                            !\in_array($val, $values) && $values[] = $val;
                            continue;
                        }
                        if($val === null){
                            !\in_array('NULL', $values) && $values[] = 'NULL';
                            continue;
                        }
                        $values[] = Helper::isSQLParameterOrFunction($val) ? $val : Parameters::add($column, $val);
                    }
                    $value = \implode(', ', \array_unique($values));
                } elseif (Helper::isSQLParameterOrFunction($value)) {
                    $value = (string)$value;
                }else{
                    throw new ValueException('An incorrect value was defined.');
                }
                return $column
                    . ($searchMark === 'NOTIN' ? ' NOT ' : ' ')
                    . 'IN (' . $value . ')';
            case 'FINDINSET':
            case 'NOTFINDINSET':
                if(\is_array($value)){
                    $value = \implode(", ", $value);
                } elseif (!Helper::isSQLParameterOrFunction($value)) {
                    $value = Parameters::add($column, $value);
                }
                return ($searchMark === 'NOTFINDINSET' ? 'NOT ' : '')
                    . 'FIND_IN_SET(' . $value . ', ' . $column . ')';
            case 'SOUNDEX':
                if(!\is_string($value) && !($value instanceof Raw)){
                    throw new ValueException('Only a string value can be defined for Soundex.');
                }
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, $value);
                }
                return "SOUNDEX(" . $column . ") LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(" . $value . ")), '%')";
        }
        if($value === null && (bool)\preg_match('/([\w_]+)\((.+)\)$/iu', $column, $matches) !== FALSE){
            return \strtoupper($matches[1]) . '(' . $matches[2] . ')';
        }
        return $column . ' ' . $mark . ' ' . Parameters::add($column, $value);
    }

}
