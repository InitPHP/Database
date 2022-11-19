<?php
/**
 * QueryBuilder
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.6
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

use \InitPHP\Database\Helpers\{Helper, Parameters};

abstract class QueryBuilder
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

    final public function selectCount(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'COUNT(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectMax(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'MAX(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectMin(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'MIN(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectAvg(string $column, ?string $alias = null): self
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
        $this->_STRUCTURE['select'][] = (string)$column . ' AS ' . $alias;
        return $this;
    }

    final public function selectUpper(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'UPPER(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectLower(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'LOWER(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectLength(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'LENGTH(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectMid(string $column, int $offset, int $length, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'MID(' . $column . ', ' . $offset . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectLeft(string $column, int $length, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'LEFT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectRight(string $column, int $length, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'RIGHT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectDistinct(string $column, ?string $alias = null): self
    {
        $this->_STRUCTURE['select'][] = 'DISTINCT(' . $column . ')'
            . ($alias !== null ? ' AS ' . $alias : '');
        return $this;
    }

    final public function selectCoalesce(string $column, $default = '0', ?string $alias = null): self
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

    final public function selectConcat(?string $alias = null, string ...$columnOrStr): self
    {
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
        $table = (string)$table;
        if(!\in_array($table, $this->_STRUCTURE['table'], true)){
            $this->_STRUCTURE['table'][] = $table;
        }
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
     * @param string|null $onStmt
     * @param string $type
     * @return $this
     */
    final public function join($table, ?string $onStmt = null, string $type = 'INNER'): self
    {
        if($table instanceof Raw){
            $this->_STRUCTURE['join'][$table] = $table->get();
            return $this;
        }
        if(!\is_string($table) || $onStmt === null){
            throw new \InvalidArgumentException('');
        }
        $type = \trim(\strtoupper($type));
        switch ($type) {
            case 'SELF' :
                $this->table($table)->where(new Raw($onStmt));
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

    final public function selfJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'SELF');
    }

    final public function innerJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'INNER');
    }

    final public function leftJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT');
    }

    final public function rightJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT');
    }

    final public function leftOuterJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT OUTER');
    }

    final public function rightOuterJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT OUTER');
    }

    final public function naturalJoin(string $table): self
    {
        return $this->join($table, '', 'NATURAL');
    }

    final public function orderBy(string $column, string $soft = 'ASC'): self
    {
        $soft = \trim(\strtoupper($soft));
        if(!\in_array($soft, ['ASC', 'DESC'], true)){
            throw new \InvalidArgumentException('It can only sort as ASC or DESC.');
        }
        $orderBy = \trim($column) . ' ' . $soft;
        if(!\in_array($orderBy, $this->_STRUCTURE['order_by'], true)){
            $this->_STRUCTURE['order_by'][] = $orderBy;
        }
        return $this;
    }

    /**
     * @param Raw|string $column
     * @param mixed $value
     * @param string $mark
     * @param string $logical
     * @return $this
     */
    final public function where($column, $value = null, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = \str_replace(['&&', '||'], ['AND', 'OR'], \strtoupper($logical));
        if(!\in_array($logical, ['AND', 'OR'], true)){
            throw new \InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->_STRUCTURE['where'][$logical][] = ($column instanceof Raw) ? $column->get() : $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @param Raw|string $column
     * @param mixed $value
     * @param string $mark
     * @param string $logical
     * @return $this
     */
    final public function having($column, $value = null, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = \str_replace(['&&', '||'], ['AND', 'OR'], \strtoupper($logical));
        if(!\in_array($logical, ['AND', 'OR'], true)){
            throw new \InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->_STRUCTURE['having'][$logical][] = ($column instanceof Raw) ? $column->get() : $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    final public function andWhere($column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'AND');
    }

    final public function orWhere($column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'OR');
    }

    final public function between(string $column, array $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'BETWEEN', $logical);
    }

    final public function orBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'OR');
    }

    final public function andBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'AND');
    }

    final public function notBetween(string $column, array $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', $logical);
    }

    final public function orNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'OR');
    }

    final public function andNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'AND');
    }

    final public function findInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'FINDINSET', $logical);
    }

    final public function orFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'OR');
    }

    final public function andFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'AND');
    }

    final public function notFindInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', $logical);
    }

    final public function andNotFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'AND');
    }

    final public function orNotFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'OR');
    }

    final public function in(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IN', $logical);
    }

    final public function orIn(string $column, $value): self
    {
        return $this->where($column, $value, 'IN', 'OR');
    }

    final public function andIn(string $column, $value): self
    {
        return $this->where($column, $value, 'IN', 'AND');
    }

    final public function notIn(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTIN', $logical);
    }

    final public function orNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'OR');
    }

    final public function andNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'AND');
    }

    final public function regexp(string $column, string $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'REGEXP', $logical);
    }

    final public function andRegexp(string $column, string $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'AND');
    }

    final public function orRegexp(string $column, string $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'OR');
    }

    final public function like(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'LIKE', $logical);
    }

    final public function orLike(string $column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'OR');
    }

    final public function andLike(string $column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'AND');
    }

    final public function startLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTLIKE', $logical);
    }

    final public function orStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'OR');
    }

    final public function andStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'AND');
    }

    final public function endLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDLIKE', $logical);
    }

    final public function orEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'OR');
    }

    final public function andEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'AND');
    }

    final public function notLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTLIKE', $logical);
    }

    final public function orNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'OR');
    }

    final public function andNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'AND');
    }

    final public function startNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', $logical);
    }

    final public function orStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'OR');
    }

    final public function andStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'AND');
    }

    final public function endNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', $logical);
    }

    final public function orEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'OR');
    }

    final public function andEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'AND');
    }

    final public function soundex(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'SOUNDEX', $logical);
    }

    final public function orSoundex(string $column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'OR');
    }

    final public function andSoundex(string $column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'AND');
    }

    final public function is(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IS', $logical);
    }

    final public function orIs(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'OR');
    }

    final public function andIs(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'AND');
    }

    final public function isNot(string $column, $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ISNOT', $logical);
    }

    final public function orIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'OR');
    }

    final public function andIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'AND');
    }

    final public function offset(int $offset = 0): self
    {
        $this->_STRUCTURE['offset'] = (int)\abs($offset);
        return $this;
    }

    final public function limit(int $limit): self
    {
        $this->_STRUCTURE['limit'] = (int)\abs($limit);
        return $this;
    }

    private function whereOrHavingStatementPrepare(string $column, $value, string $mark = '='): string
    {
        $mark = \trim($mark);
        if(\in_array($mark, ['=', '!=', '<=', '>=', '>', '<'], true)){
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
                    $value = Parameters::add($column, '%' . $value . '%');
                }
                return $column . ' LIKE ' . $value;
            case 'STARTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, '%' . $value);
                }
                return $column . ' LIKE ' . $value;
            case 'ENDLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, $value . '%');
                }
                return $column . ' LIKE ' . $value;
            case 'NOTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, '%' . $value . '%');
                }
                return $column . ' NOT LIKE ' . $value;
            case 'STARTNOTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, '%' . $value);
                }
                return $column . ' NOT LIKE ' . $value;
            case 'ENDNOTLIKE':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, $value . '%');
                }
                return $column . ' NOT LIKE ' . $value;
            case 'REGEXP':
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, $value);
                }
                return $column . ' REGEXP ' . $value;
            case 'BETWEEN':
            case 'NOTBETWEEN':
                $start = $value[0] ?? 0;
                $end = $value[1] ?? 0;
                if(!Helper::isSQLParameterOrFunction($start)){
                    $start = Parameters::add(($column . '_start'), $start);
                }
                if(!Helper::isSQLParameterOrFunction($end)){
                    $end = Parameters::add(($column . '_end'), $end);
                }
                return $column . ' '
                    . ($searchMark === 'NOTBETWEEN' ? 'NOT ':'')
                    . 'BETWEEN ' . $start . ' AND ' . $end;
            case 'IN':
            case 'NOTIN':
                if(\is_array($value)){
                    $values = [];
                    foreach ($value as $val) {
                        if(\is_numeric($val)){
                            $values[] = $val;
                            continue;
                        }
                        if($val === null){
                            $values[] = 'NULL';
                            continue;
                        }
                        $values[] = Helper::isSQLParameterOrFunction($val) ? $val : Parameters::add($column, $val);
                    }
                    $value = \implode(', ', \array_unique($values));
                }elseif(\is_string($value) || \is_numeric($value)){
                    $value = \is_numeric($value) || Helper::isSQLParameterOrFunction($value) ? $value : Parameters::add($column, \trim($value, '()'));
                }else{
                    throw new \InvalidArgumentException();
                }
                return $column
                    . ($searchMark === 'NOTIN' ? ' NOT ' : ' ')
                    . 'IN (' . $value . ')';
            case 'FINDINSET':
            case 'NOTFINDINSET':
                if(\is_array($value)){
                    $value = \implode(", ", $value);
                }
                if(!Helper::isSQLParameterOrFunction($value)){
                    $value = Parameters::add($column, $value);
                }
                return ($searchMark === 'NOTFINDINSET' ? 'NOT ' : '')
                    . 'FIND_IN_SET(' . $value . ', ' . $column . ')';
            case 'SOUNDEX':
                if(!\is_string($value)){
                    throw new \InvalidArgumentException('Only a string value can be defined for Soundex.');
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
