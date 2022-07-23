<?php
/**
 * QueryBuilder.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder;

use \InitPHP\Database\Exceptions\{QueryBuilderException, QueryBuilderInvalidArgumentException};
use InitPHP\Database\Helper;

class QueryBuilder implements QueryBuilderInterface
{

    /** @var array */
    private array $key = [];

    protected const SQL_DEFAULT = [
        'select'        => [],
        'from'          => [],
        'where'         => [
            'AND'       => [],
            'OR'        => [],
        ],
        'having'        => [
            'AND'       => [],
            'OR'        => [],
        ],
        'orderBy'       => [],
        'groupBy'       => [],
        'join'          => [],
        'offset'        => null,
        'limit'         => null,
        'fields'        => [],
        'primary_key'   => '',
        'type'          => '',
        'conditions'    => [],
    ];

    protected const SUPPORTED_JOIN_TYPES = [
        'INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER', 'SELF', 'NATURAL'
    ];

    /** @var string */
    private $sqlQuery = '';

    /** @var null|string[] */
    private $allowedFields = null;

    public function __construct(?array $allowedFields = null)
    {
        $this->allowedFields = $allowedFields;
        $this->buildQuery([], true);
        if(!isset($this->key['table'])){
            $this->key['table'] = '';
        }
    }

    /**
     * @inheritDoc
     */
    public function buildQuery(array $args = [], bool $isReset = true): self
    {
        if(isset($args['table']) && empty($args['table'])){
            unset($args['table']);
        }
        if((isset($args['primary_key'])) && (empty($args['primary_key']) || $args['primary_key'] == '0')){
            unset($args['primary_key']);
        }
        $default = ($isReset === FALSE && !empty($this->key)) ? $this->key : self::SQL_DEFAULT;
        $this->key = \array_merge($default, $args);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function insertQuery(?array $data = null): string
    {
        if($data === null && !empty($this->key['fields'])){
            $data = [];
            foreach ($this->key['fields'] as $key => $value) {
                $data[$key] = ':'. $key;
            }
        }
        if(empty($data)){
            return '';
        }
        $this->sqlQuery = 'INSERT INTO'
            . ' ' . $this->getSchemaName() . ' ';

        $columns = [];
        $values = [];

        if(\count($data) === \count($data, \COUNT_RECURSIVE)){
            foreach ($data as $column => $value) {
                $column = \trim($column);
                if($this->allowedFields !== null && !\in_array($column, $this->allowedFields, true)){
                    continue;
                }
                $columns[] = $column;
                $values[] = $this->bindParameter($value, '{value}');
            }
            if(empty($columns)){
                return '';
            }
            $this->sqlQuery .= '(' . \implode(', ', $columns) . ') VALUES (' . \implode(', ', $values) . ');';
            return \trim($this->sqlQuery);
        }

        foreach ($data as &$row) {
            $value = [];
            foreach ($row as $column => $val){
                $column = \trim($column);
                if($this->allowedFields !== null && !\in_array($column, $this->allowedFields, true)){
                    continue;
                }
                if(\in_array($column, $columns, true) === FALSE){
                    $columns[] = $column;
                }
                $value[$column] = $this->bindParameter($val, '{value}');
            }
            $values[] = $value;
        }
        $insertValues = [];

        foreach ($values as $value) {
            $tmpValue = $value;
            $value = [];
            foreach ($columns as $column) {
                $value[$column] = $tmpValue[$column] ?? 'NULL';
            }
            $insertValues[] = '(' . implode(', ', $value) . ')';
        }
        $this->sqlQuery .= '(' . implode(', ', $columns) . ') VALUES ' . implode(', ', $insertValues) . ';';

        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function readQuery(): string
    {
        $selectors = !empty($this->key['select']) ? \implode(', ', $this->key['select']) : '*';

        $table = [$this->getSchemaName()];
        if(!empty($this->key['from'])){
            foreach ($this->key['from'] as $row) {
                if(!\in_array($row, $table, true)){
                    $table[] = $row;
                }
            }
        }

        $this->sqlQuery = 'SELECT ' . $selectors
            . ' FROM ' . \implode(', ', $table);

        if(!empty($this->key['join'])){
            $this->sqlQuery .= ' ' . \implode(' ', $this->key['join']);
        }

        if(($where = $this->whereAndHavingQuery('where')) != ''){
            $this->sqlQuery .= ' WHERE ' . $where;
        }
        if(!empty($this->key['groupBy'])){
            $this->sqlQuery .= ' GROUP BY ' . \implode(', ', $this->key['groupBy']);
        }
        if(($having = $this->whereAndHavingQuery('having')) != ''){
            $this->sqlQuery .= ' HAVING ' . $having;
        }
        if(!empty($this->key['orderBy'])){
            $this->sqlQuery .= ' ORDER BY ' . \implode(', ', $this->key['orderBy']);
        }
        $this->sqlQuery .= $this->sqlLimitQuery();

        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function deleteQuery(): string
    {
        $where = $this->whereAndHavingQuery('where');
        $this->sqlQuery = 'DELETE FROM'
            . ' ' . $this->getSchemaName() . ' WHERE ';
        $this->sqlQuery .= (empty($where) ? '1' : $where)
                        . $this->sqlLimitQuery();
        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function updateQuery(?array $data = null): string
    {
        if($data === null && !empty($this->key['fields'])){
            $data = [];
            foreach ($this->key['fields'] as $key => $value) {
                $data[$key] = ':' . $key;
            }
        }
        if(empty($data)){
            return '';
        }
        $update = [];
        foreach ($data as $column => $value) {
            if($this->key['primary_key'] == $column){
                continue;
            }
            if($this->allowedFields !== null && !\in_array($column, $this->allowedFields, true)){
                continue;
            }
            $update[] = $column . ' = ' . $this->bindParameter($value, '{value}');
        }
        if(empty($update)){
            return '';
        }
        if(!empty($this->key['primary_key']) && $this->key['primary_key'] != '0'){
            $this->where($this->key['primary_key'], ':'.$this->key['primary_key'])
                ->limit(1);
        }
        $this->sqlQuery = 'UPDATE '
            . $this->getSchemaName() . ' SET ' .\implode(', ', $update)
            . ' WHERE '
            . (($where = $this->whereAndHavingQuery('where')) != '' ? $where : '1')
            . $this->sqlLimitQuery();

        return \trim($this->sqlQuery);
    }

    private function getSchemaName(): string
    {
        if(isset($this->key['table']) && !empty($this->key['table'])){
            return $this->key['table'];
        }
        if(!isset($this->key['from']) || empty($this->key['from'])){
            throw new QueryBuilderException('The table name could not be found to build the operation.');
        }
        \end($this->key['from']);
        return \current($this->key['from']);
    }

    private function whereAndHavingQuery(string $key): string
    {
        $wheres = $this->key[$key];
        $res = '';
        if($key === 'where' && !empty($this->key['primary_key']) && $this->key['primary_key'] !== '0'){
            $wheres['AND'][] = $this->key['primary_key'] . ' = :' . $this->key['primary_key'];
        }
        if($key === 'where' && !empty($this->key['conditions'])){
            foreach ($this->key['conditions'] as $key => $value) {
                $wheres['AND'][] = $key . ' = :' . $key;
            }
        }
        $isRequiredAnd = !empty($wheres['AND']);
        $isRequiredOr = !empty($wheres['OR']);

        if($isRequiredAnd){
            $wheres['AND'] = \array_unique($wheres['AND']);
            $res .= \implode(' AND ', $wheres['AND']);
        }
        if($isRequiredOr){
            $wheres['OR'] = \array_unique($wheres['OR']);
            if($isRequiredAnd){
                $res .= ' OR ';
            }
            $res .= \implode(' OR ', $wheres['OR']);
        }
        return $res;
    }

    private function sqlLimitQuery(): string
    {
        if(!empty($this->key['limit'])){
            return ' LIMIT '
                . (\is_numeric($this->key['offset']) ? $this->key['offset'] . ', ' : '')
                . $this->key['limit'];
        }
        return '';
    }

    /**
     * @inheritDoc
     */
    public function reset(): self
    {
        foreach (self::SQL_DEFAULT as $key => $value) {
            $this->key[$key] = $value;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function select(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->selectorsPush($column);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCount(string $column): self
    {
        $this->selectorsPush($column, null, 'COUNT');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMax(string $column): self
    {
        $this->selectorsPush($column, null, 'MAX');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMin(string $column): self
    {
        $this->selectorsPush($column, null, 'MIN');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAvg(string $column): self
    {
        $this->selectorsPush($column, null, 'AVG');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAs(string $column, string $alias): self
    {
        $this->selectorsPush($column, $alias);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectUpper(string $column): self
    {
        $this->selectorsPush($column, null, 'UPPER', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLower(string $column): self
    {
        $this->selectorsPush($column, null, 'LOWER', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLength(string $column): self
    {
        $this->selectorsPush($column, null, 'LENGTH', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMid(string $column, int $offset, int $length): self
    {
        $this->selectorsPush($column, null, 'MID', '{function}({column}, ' . $offset . ', ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLeft(string $column, int $length): self
    {
        $this->selectorsPush($column, null, 'LEFT', '{function}({column}, ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectRight(string $column, int $length): self
    {
        $this->selectorsPush($column, null, 'RIGHT', '{function}({column}, ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectDistinct(string $column): self
    {
        $this->selectorsPush($column, null, 'DISTINCT');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCoalesce(string $column, $default = '0'): self
    {
        if(!\is_numeric($default)){
            if((bool)\preg_match('/^[a-zA-Z\d]+\.[a-zA-Z\d]+$/', (string)$default) === FALSE){
                $default = "'" . \str_replace("'", "\'", \trim($default, "\\'\" \r\n")) . "'";
            }
        }
        $this->selectorsPush($column, null, 'COALESCE', '{function}({column}, ' . $default . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectSum(string $column): self
    {
        $this->selectorsPush($column, null, 'SUM');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function table(string $table, ?string $tableSchemaID = null): self
    {
        $this->key['table'] = $this->fromCheck($table);
        if(!empty($tableSchemaID)){
            $this->key['primary_key'] = $tableSchemaID;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function from(string $table): self
    {
        $table = $this->fromCheck($table);
        if(\is_array($table)){
            foreach ($table as $from) {
                if(\in_array($from, $this->key['from'], true) || $this->key['table'] == $table){
                    continue;
                }
                $this->key['from'][] = $from;
            }
            return $this;
        }
        if(!\in_array($table, $this->key['from'], true) || $this->key['table'] != $table){
            $this->key['from'][] = $table;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function join(string $table, string $onStmt, string $type = 'INNER'): self
    {
        $type = \trim(\strtoupper($type));
        if(!\in_array($type, self::SUPPORTED_JOIN_TYPES, true)){
            throw new QueryBuilderInvalidArgumentException($type . ' Join type is not supported.');
        }
        $table = $this->fromCheck($table);
        if(isset($this->key['join'][$table]) || $table == $this->key['table'] || \in_array($table, $this->key['from'], true)){
            return $this;
        }
        if($type == 'NATURAL'){
            $this->key['join'][$table] = 'NATURAL JOIN ' . $table;
            return $this;
        }
        $onStmt = \str_replace(' = ', '=', $onStmt);
        if((bool)\preg_match('/([\w\_\-]+)\.([\w\_\-]+)=([\w\_\-]+)\.([\w\_\-]+)/u', $onStmt, $matches) === FALSE){
            throw new QueryBuilderInvalidArgumentException('Join syntax is not in the correct format. Example : "post.author=user.id". Give : "' . $onStmt . '"');
        }
        $onStmt = $matches[1] . '.' . $matches[2] . '=' . $matches[3] . '.' . $matches[4];
        if($type == 'SELF'){
            $this->key['from'][] = $table;
            $this->key['where']['AND'][] = $onStmt;
        }else{
            $this->key['join'][$table] = $type . ' JOIN ' . $table . ' ON ' . $onStmt;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selfJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'SELF');
    }

    /**
     * @inheritDoc
     */
    public function innerJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'INNER');
    }

    /**
     * @inheritDoc
     */
    public function leftJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT');
    }

    /**
     * @inheritDoc
     */
    public function rightJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT');
    }

    /**
     * @inheritDoc
     */
    public function leftOuterJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT OUTER');
    }

    /**
     * @inheritDoc
     */
    public function rightOuterJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT OUTER');
    }

    /**
     * @inheritDoc
     */
    public function naturalJoin(string $table): self
    {
        return $this->join($table, '', 'NATURAL');
    }

    /**
     * @inheritDoc
     */
    public function where(string $column, $value, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = \str_replace(['&&', '||'], ['AND', 'OR'], \strtoupper($logical));
        if(\in_array($logical, ['AND', 'OR'], true) === FALSE){
            throw new QueryBuilderInvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->key['where'][$logical][] = $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function andWhere(string $column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'AND');
    }

    /**
     * @inheritDoc
     */
    public function orWhere(string $column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function having(string $column, $value, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = str_replace(['&&', '||'], ['AND', 'OR'], strtoupper($logical));
        if(in_array($logical, ['AND', 'OR'], true) === FALSE){
            throw new QueryBuilderInvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->key['having'][$logical][] = $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orderBy(string $column, string $soft = 'ASC'): self
    {
        $soft = \trim(\strtoupper($soft));
        if(!\in_array($soft, ['ASC', 'DESC'], true)){
            throw new QueryBuilderInvalidArgumentException('It can only sort as ASC or DESC.');
        }
        $orderBy = \trim($column) . ' ' . $soft;
        if(!\in_array($orderBy, $this->key['orderBy'], true)){
            $this->key['orderBy'][] = $orderBy;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function groupBy(string $column): self
    {
        if(!\in_array($column, $this->key['groupBy'], true)){
            $this->key['groupBy'][] = $column;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offset(int $offset = 0): self
    {
        $this->key['offset'] = (int)\abs($offset);
        if(empty($this->key['limit'])){
            $this->key['limit'] = 1000;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function limit(int $limit): self
    {
        $this->key['limit'] = (int)\abs($limit);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function between(string $column, array $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'BETWEEN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notBetween(string $column, array $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function findInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'FINDINSET', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notFindInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', $logical);
    }

    /**
     * @inheritDoc
     */
    public function andNotFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function orNotFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function in(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orIn(string $column, $value): self
    {
        return $this->where($column, $value, 'IN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andIn(string $column, $value): self
    {
        return $this->where($column, $value, 'IN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notIn(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTIN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function regexp(string $column, string $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'REGEXP', $logical);
    }

    /**
     * @inheritDoc
     */
    public function andRegexp(string $column, string $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function orRegexp(string $column, string $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function like(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'LIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orLike(string $column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andLike(string $column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function startLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function endLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function startNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function endNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function soundex(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'SOUNDEX', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orSoundex(string $column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andSoundex(string $column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function is(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IS', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orIs(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andIs(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function isNot(string $column, $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ISNOT', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'AND');
    }

    private function whereOrHavingStatementPrepare(string $column, $value, string $mark = '='): string
    {
        $mark = \trim($mark);
        if(\in_array($mark, ['=', '!=', '>=', '<=', '<', '>'], true)){
            return $column . ' ' . $mark . ' ' . $this->bindParameter($value);
        }
        $markUpperCase = \strtoupper($mark);
        switch (\str_replace([' ', '_'], '', $markUpperCase)) {
            case 'IS':
                return $column . ' IS ' . $this->bindParameter($value, '{value}');
            case 'ISNOT':
                return $column . ' IS NOT ' . $this->bindParameter($value, '{value}');
            case 'LIKE':
                return $column . ' LIKE ' . $this->bindParameter($value, '%{value}%');
            case 'STARTLIKE':
                return $column . ' LIKE ' . $this->bindParameter($value, '%{value}');
            case 'ENDLIKE':
                return $column . ' LIKE ' . $this->bindParameter($value, '{value}%');
            case 'NOTLIKE':
                return $column . ' NOT LIKE ' . $this->bindParameter($value, '%{value}%');
            case 'STARTNOTLIKE':
                return $column . ' NOT LIKE ' . $this->bindParameter($value, '%{value}');
            case 'ENDNOTLIKE':
                return $column . ' NOT LIKE ' . $this->bindParameter($value, '{value}%');
            case 'REGEXP':
                return $column . ' REGEXP ' . $this->bindParameter($value, '{value}');
            case 'BETWEEN':
                return $column . ' BETWEEN '
                    . $this->bindParameter($value[0], '{value}')
                    . ' AND ' . $this->bindParameter($value[1], '{value}');
            case 'NOTBETWEEN':
                return $column . ' NOT BETWEEN '
                    . $this->bindParameter($value[0], '{value}')
                    . ' AND ' . $this->bindParameter($value[1], '{value}');
            case 'IN':
                return $column . ' IN '
                    . (\is_array($value) ? '(' . \implode(', ', $value) . ')' : $this->bindParameter($value, '({value})'));
            case 'NOTIN':
                return $column . ' NOT IN '
                    . (\is_array($value) ? '(' . \implode(', ', $value) . ')' : $this->bindParameter($value, '({value})'));
            case 'FINDINSET':
                return 'FIND_IN_SET('
                . \is_array($value) ? "'" . \implode(', ', $value) . "'" : $this->bindParameter($value, '{value}')
                    . ', ' . $column . ')';
            case 'NOTFINDINSET':
                return 'NOT FIND_IN_SET('
                . \is_array($value) ? "'" . \implode(', ', $value) . "'" : $this->bindParameter($value, '{value}')
                    . ', ' . $column . ')';
            case 'SOUNDEX':
                return "SOUNDEX(" . $column . ") LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(" . $this->bindParameter($value, '{value}') . ")), '%')";
        }

        if(((bool)\preg_match('/([\w\_]+)\((.+)\)$/iu', $column, $matches)) !== FALSE){
            return \strtoupper($matches[1]) . '(' . $matches[2] . ')';
        }

        return $column . ' ' . $mark . ' ' . $this->bindParameter($value, '{value}');
    }

    private function bindParameter($value, string $syntax = '{value}'): string
    {
        if(
            \is_string($value) && (
                $value == '?' ||
                (bool)\preg_match('/^:[\w]+$/', $value) ||
                (bool)\preg_match('/^[a-zA-Z\_]+\(\)$/', $value)
            )
        ){
            return $value;
        }
        if($value === null){
            return \str_replace('{value}', 'NULL', $syntax);
        }
        if($value === FALSE){
            $value = 0;
        }
        if(\is_bool($value) || \is_numeric($value)){
            return \str_replace('{value}', (string)$value, $syntax);
        }
        $value = \str_replace(['\\\"', '\\\\\"'], '\\"', \trim((string)$value, '\\"'));
        return '"' . \str_replace('{value}', $value, $syntax) . '"';
    }

    /**
     * @param string $string
     * @return false|string[]
     */
    private function aliasStatement(string $string)
    {
        if(\stripos($string, ' as ') === FALSE){
            return false;
        }
        $string = \str_replace(' AS ', ' as ', $string);
        return \explode(' as ', $string, 2);
    }

    private function selectorsPush($column, ?string $alias = null, ?string $fn = null, ?string $pattern = null): void
    {
        if(\is_array($column)){
            foreach ($column as $item) {
                $this->selectorsPush($item, $alias, $fn, $pattern);
            }
            return;
        }
        $column = \trim((string)$column);
        if($column == ''){
            return;
        }
        if(Helper::str_contains($column, ',')){
            $columns = \explode(',', $column);
            $this->selectorsPush($columns, $alias, $fn, $pattern);
            return;
        }
        if(($split = $this->aliasStatement($column)) !== FALSE){
            $this->selectorsPush($split[0], $split[1], $fn, $pattern);
            return;
        }
        if(((bool)\preg_match('/([\w\_]+)\((.+)\)$/iu', $column, $matches)) !== FALSE){
            $this->selectorsPush($matches[2], $alias, $matches[1], $pattern);
            return;
        }
        if($alias !== null){
            $alias = \trim($alias);
        }
        $select = $column;
        if(!empty($fn)){
            if(!empty($pattern)){
                $select = \str_replace(['{column}', '{function}'], [$select, \strtoupper($fn)], $pattern);
            }else{
                $select = \strtoupper($fn) . '(' . $select . ')';
            }
        }
        if(!empty($alias)){
            $select .= ' AS ' . $alias;
        }
        if(\in_array($select, $this->key['select'], true)){
            return;
        }
        $this->key['select'][] = $select;
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return string|string[]
     */
    private function fromCheck(string $table, ?string $alias = null)
    {
        $table = \trim($table);
        if(Helper::str_contains($table, ',')){
            $split = \explode(',', $table);
            $res = [];
            foreach ($split as $value) {
                $res[] = $this->fromCheck($value);
            }
            return $res;
        }
        if(!empty($alias)){
            return $table . ' AS ' . $alias;
        }
        $table = \str_replace(' AS ', ' as ', $table);
        foreach ([' as ', ' '] as $separator) {
            if(\stripos($table, $separator) === FALSE){
                continue;
            }
            $split = \explode($separator, $table, 2);
            return $this->fromCheck($split[0], $split[1]);
        }
        return $table;
    }

}
