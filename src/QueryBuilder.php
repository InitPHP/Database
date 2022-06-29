<?php
/**
 * QueryBuilder.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.9
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use const COUNT_RECURSIVE;

use function trim;
use function in_array;
use function strtoupper;
use function str_replace;
use function preg_match;
use function call_user_func_array;
use function abs;
use function explode;
use function implode;
use function end;
use function count;
use function ucfirst;
use function property_exists;
use function is_array;
use function is_int;
use function is_numeric;
use function is_bool;
use function is_iterable;
use function strpos;
use function stripos;

trait QueryBuilder
{

    protected string $_QB_Prefix = '';

    private array $_supported_join_types = [
        'INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER', 'SELF'
    ];

    protected array $QB_Select = [];
    protected array $QB_From = [];
    protected array $QB_Where = [];
    protected array $QB_Having = [];
    protected array $QB_OrderBy = [];
    protected array $QB_GroupBy = [];
    protected ?int $QB_Offset = null;
    protected ?int $QB_Limit = null;

    protected array $QB_Join = [];

    protected static int $QBGroupId = 0;

    protected ?string $_QBStatementTemp = null;

    /** @var string */
    protected string $table;

    /** @var string[]|null */
    protected ?array $allowedFields = null;

    public function __destruct()
    {
        $this->clear();
    }

    /**
     * @inheritDoc
     */
    public function clear(): self
    {
        $this->_QBStatementTemp = null;
        return $this->reset();
    }

    /**
     * @inheritDoc
     */
    public function reset(): self
    {
        static::$QBGroupId = 0;
        $this->QB_Select = [];
        $this->QB_From = [];
        $this->QB_Where = [];
        $this->QB_Having = [];
        $this->QB_OrderBy = [];
        $this->QB_GroupBy = [];
        $this->QB_Offset = null;
        $this->QB_Limit = null;
        $this->QB_Join = [];
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function union(): self
    {
        $this->_QBStatementTemp = $this->selectStatementBuild()
                                    . ' UNION ';
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function unionAll(): self
    {
        $this->_QBStatementTemp = $this->selectStatementBuild()
                                    . ' UNION ALL ';
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function select(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->prepareSelect($column);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCount(string $column): self
    {
        $this->prepareSelect($column, null, 'COUNT');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMax(string $column): self
    {
        $this->prepareSelect($column, null, 'MAX');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMin(string $column): self
    {
        $this->prepareSelect($column, null, 'MIN');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAvg(string $column): self
    {
        $this->prepareSelect($column, null, 'AVG');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAs(string $column, string $alias): self
    {
        $this->prepareSelect($column, $alias);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectUpper(string $column): self
    {
        $this->prepareSelect($column, null, 'UPPER', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLower(string $column): self
    {
        $this->prepareSelect($column, null, 'LOWER', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLength(string $column): self
    {
        $this->prepareSelect($column, null, 'LENGTH', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMid(string $column, int $offset, int $length): self
    {
        $this->prepareSelect($column, null, 'MID', '{function}({column}, ' . $offset . ', ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLeft(string $column, int $length): self
    {
        $this->prepareSelect($column, null, 'LEFT', '{function}({column}, ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectRight(string $column, int $length): self
    {
        $this->prepareSelect($column, null, 'RIGHT', '{function}({column}, ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectDistinct(string $column): self
    {
        $this->prepareSelect($column, null, 'DISTINCT');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCoalesce(string $column, $default = '0'): self
    {
        if(!is_numeric($default)){
            if(((bool)preg_match('/^[a-zA-Z\d]+\.[a-zA-Z\d]+$/', $default)) === FALSE){
                $default = "'" . str_replace("'", "\'", trim($default, "\\'\" \r\n")) . "'";
            }
        }
        $this->prepareSelect($column, null, 'COALESCE', '{function}({column}, ' . $default . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectSum(string $column): self
    {
        $this->prepareSelect($column, null, 'SUM');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function from(string $table): self
    {
        $table = $this->fromResolve($table);
        if(is_string($table)){
            if(!isset($this->table)){
                $this->table = $table;
            }
            if(!in_array($table, $this->QB_From, true)){
                $this->QB_From[] = $table;
            }

            return $this;
        }

        foreach ($table as $tab) {
            if(!in_array($tab, $this->QB_From, true)){
                $this->QB_From[] = $tab;
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function join(string $table, string $onStmt, string $type = 'INNER'): self
    {
        $type = strtoupper(trim($type));
        if(in_array($type, $this->_supported_join_types, true) === FALSE){
            throw new \InvalidArgumentException($type . ' Join type is not supported.');
        }
        $table = $this->fromResolve($table);
        if(isset($this->QB_Join[$table]) || in_array($table, $this->QB_From, true)){
            return $this;
        }
        $onStmt = str_replace(' = ', '=', $onStmt);
        if((bool)preg_match('/([\w\_\-]+)\.([\w\_\-]+)=([\w\_\-]+)\.([\w\_\-]+)/u', $onStmt, $stmt) === FALSE){
            throw new \InvalidArgumentException('Join syntax is not in the correct format. Example : "post.author=user.id"');
        }

        if($type === 'SELF'){
            if(isset($this->table) && !in_array($this->table, $this->QB_From, true)){
                $this->QB_From[] = $this->table;
            }
            $this->QB_From[] = $table;
            $this->QB_Where[0]['AND'][] = Helper::sqlDriverQuotesStructure(($stmt[1] . '.' . $stmt[2]), $this->_Driver)
                . '='
                . Helper::sqlDriverQuotesStructure(($stmt[3] . '.' . $stmt[4]), $this->_Driver);
        }else{
            $this->QB_Join[$table] = $type . ' JOIN ' . $table
                . ' ON '
                . Helper::sqlDriverQuotesStructure(($stmt[1] . '.' . $stmt[2]), $this->_Driver)
                . '='
                . Helper::sqlDriverQuotesStructure(($stmt[3] . '.' . $stmt[4]), $this->_Driver);
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
    public function group(\Closure $group): self
    {
        ++static::$QBGroupId;
        call_user_func_array($group, [$this]);
        --static::$QBGroupId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function where(string $column, $value, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = str_replace(['&&', '||'], ['AND', 'OR'], strtoupper($logical));
        if(in_array($logical, ['AND', 'OR'], true) === FALSE){
            throw new \InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->QB_Where[static::$QBGroupId][$logical][] = $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function andWhereInject(string $statement): self
    {
        $this->QB_Where[static::$QBGroupId]['AND'][] = $statement;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orWhereInject(string $statement): self
    {
        $this->QB_Where[static::$QBGroupId]['OR'][] = $statement;
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
            throw new \InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->QB_Having[static::$QBGroupId][$logical][] = $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function andHavingInject(string $statement): self
    {
        $this->QB_Having[static::$QBGroupId]['AND'][] = $statement;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orHavingInject(string $statement): self
    {
        $this->QB_Having[static::$QBGroupId]['OR'][] = $statement;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orderBy(string $column, string $soft = 'ASC'): self
    {
        $soft = trim(strtoupper($soft));
        if(in_array($soft, ['ASC', 'DESC'], true) === FALSE){
            throw new \InvalidArgumentException('It can only sort as ASC or DESC.');
        }
        $orderBy = Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' ' . $soft;
        if(in_array($orderBy, $this->QB_OrderBy, true) === FALSE){
            $this->QB_OrderBy[] = $orderBy;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function groupBy(string $column): self
    {
        $column = Helper::sqlDriverQuotesStructure($column, $this->_Driver);
        if(in_array($column, $this->QB_GroupBy, true) === FALSE){
            $this->QB_GroupBy[] = $column;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offset(int $offset = 0): self
    {
        if($offset < 0){
            $offset = (int)abs($offset);
        }
        if($this->QB_Limit === null){
            $this->QB_Limit = 1000;
        }
        $this->QB_Offset = $offset;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function limit(int $limit): self
    {
        if($limit < 0){
            $limit = (int)abs($limit);
        }
        $this->QB_Limit = $limit;
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
        return $this->where($column, $values, 'NOT BETWEEN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOT BETWEEN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOT BETWEEN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function findInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'FIND_IN_SET', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FIND_IN_SET', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FIND_IN_SET', 'AND');
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
        return $this->where($column, $value, 'NOT IN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOT IN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOT IN', 'AND');
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
        return $this->where($column, $value, 'START_LIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'START_LIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'START_LIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function endLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'END_LIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'END_LIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'END_LIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOT LIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOT LIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOT LIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function startNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'START_NOT_LIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'START_NOT_LIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'START_NOT_LIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function endNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'END_NOT_LIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'END_NOT_LIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'END_NOT_LIKE', 'AND');
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
    public function is(string $column, $value = null, string $logical = 'AND'): self
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
        return $this->where($column, $value, 'IS NOT', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS NOT', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS NOT', 'AND');
    }

    /**
     * Constructs and returns an SQL SELECT statement.
     *
     * @return string
     */
    public function selectStatementBuild(): string
    {
        $sql = '';
        if(!empty($this->_QBStatementTemp)){
            $sql .= trim($this->_QBStatementTemp) . ' ';
        }
        if(empty($this->QB_Select)){
            $this->QB_Select[] = '*';
        }

        $sql .= 'SELECT ' . implode(', ', $this->QB_Select);

        if(empty($this->QB_From) && isset($this->table)){
            $this->QB_From[] = $this->table;
        }
        $sql .= ' FROM ' . implode(', ', $this->QB_From);

        if(!empty($this->QB_Join)){
            $sql .= ' ' . implode(' ', $this->QB_Join);
        }

        if(!empty($this->QB_Where)){
            $sql .= ' WHERE ' . $this->sqlWhereOrHavingStatementBuild('where');
        }

        if(!empty($this->QB_GroupBy)){
            $sql .= ' GROUP BY ' . implode(', ', $this->QB_GroupBy);
        }

        if(!empty($this->QB_Having)){
            $sql .= ' HAVING ' . $this->sqlWhereOrHavingStatementBuild('having');
        }

        if(!empty($this->QB_OrderBy)){
            $sql .= ' ORDER BY ' . implode(', ', $this->QB_OrderBy);
        }

        return trim($sql . $this->sqlLimitStatementBuild());
    }

    /**
     * Builds and returns an INSERT SQL statement.
     *
     * @param array $associativeData
     * @return string
     */
    public function insertStatementBuild(array $associativeData): string
    {
        $sql = 'INSERT INTO'
            . ' ' . (!empty($this->QB_From) ? end($this->QB_From) : ($this->table ?? ''));
        $columns = [];
        $values = [];
        if(count($associativeData) === count($associativeData, COUNT_RECURSIVE)){
            foreach ($associativeData as $column => $value) {
                $column = trim($column);
                if($this->allowedFields !== null && in_array($column, $this->allowedFields, true) === FALSE){
                    continue;
                }
                $columns[] = Helper::sqlDriverQuotesStructure($column, $this->_Driver);
                $values[] = $this->argumentPrepare($value);
            }
            $sql .= ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
        }else{
            foreach ($associativeData as &$row) {
                $value = [];
                foreach ($row as $column => $val){
                    $column = trim($column);
                    if($this->allowedFields !== null && in_array($column, $this->allowedFields, true) === FALSE){
                        continue;
                    }
                    if(in_array($column, $columns, true) === FALSE){
                        $columns[] = Helper::sqlDriverQuotesStructure($column, $this->_Driver);
                    }
                    $value[$column] = $this->argumentPrepare($val);
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
            $sql .= ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $insertValues) . ';';
        }
        return $sql;
    }

    /**
     * Builds and returns an UPDATE SQL statement.
     *
     * @param array $associativeData
     * @return string
     */
    public function updateStatementBuild(array $associativeData): string
    {
        $sql = 'UPDATE'
            . ' ' . (!empty($this->QB_From) ? end($this->QB_From) : ($this->table ?? ''));

        $sets = [];
        foreach ($associativeData as $column => $value) {
            $column = trim($column);
            if($this->allowedFields !== null && in_array($column, $this->allowedFields, true) === FALSE){
                continue;
            }
            $sets[] = Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' = '. $this->argumentPrepare($value);
        }
        if(empty($sets)){
            return '';
        }
        $sql .= ' SET ' . implode(', ', $sets) . ' WHERE ';

        $where = $this->sqlWhereOrHavingStatementBuild('where');
        $sql .= (empty($where) ? '1' : $where);
        $sql .= $this->sqlLimitStatementBuild();
        return $sql;
    }

    /**
     * Builds and returns a DELETE SQL statement.
     *
     * @return string
     */
    public function deleteStatementBuild(): string
    {
        $sql = 'DELETE FROM'
            . ' ' . (!empty($this->QB_From) ? end($this->QB_From) : ($this->table ?? ''));

        $where = $this->sqlWhereOrHavingStatementBuild('where');
        $sql .= ' WHERE ';
        $sql .= (empty($where) ? '1' : $where);
        $sql .= $this->sqlLimitStatementBuild();
        return $sql;
    }

    private function sqlWhereOrHavingStatementBuild(string $type): string
    {
        $property = 'QB_' . ucfirst($type);
        if(property_exists($this, $property) === FALSE){
            return '';
        }
        $statements = $this->{$property};
        if(!is_array($statements) || empty($statements)){
            return '';
        }
        return $this->groupWhereOrHavingStatementGenerator($statements, 0);
    }

    private function groupWhereOrHavingStatementGenerator($statements, int $level = 0): string
    {
        $stmt = '';
        if($level != 0){
            $stmt .= ' AND (';
        }
        $items = $statements[$level];
        $put = null;
        if(isset($items['AND']) && is_array($items['AND']) && !empty($items['AND'])){
            $stmt .= implode(' AND ', $items['AND']);
            $put = ' OR ';
        }
        if(isset($items['OR']) && is_array($items['OR']) && !empty($items['OR'])){
            $stmt .= $put . implode(' OR ', $items['OR']);
        }
        $nextLevel = $level + 1;
        if(isset($statements[$nextLevel])){
            $stmt .= $this->groupWhereOrHavingStatementGenerator($statements, $nextLevel);
        }
        if($level != 0){
            $stmt .= ')';
        }
        return $stmt;
    }

    private function sqlLimitStatementBuild(): string
    {
        if($this->QB_Limit !== null){
            return ' LIMIT '
                . ($this->QB_Offset !== null ? $this->QB_Offset . ', ' : '')
                . $this->QB_Limit;
        }
        return '';
    }

    private function whereOrHavingStatementPrepare(string $column, $value, string $mark = '='): string
    {
        $value = $this->argumentPrepare($value);
        $mark = trim($mark);
        if($mark === '='){
            return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' = ' . $value;
        }
        switch (strtoupper($mark)) {
            case '!=':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' != ' . $value;
            case '<':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' < ' . $value;
            case '<=':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' <= ' . $value;
            case '>':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' > ' . $value;
            case '>=':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' >= ' . $value;
            case 'LIKE' :
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' LIKE "%' . trim($value, '\\"') . '%"';
            case 'START_LIKE':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' LIKE "%' . trim($value, '\\"') . '"';
            case 'END_LIKE':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' LIKE "' . trim($value, '\\"') . '%"';
            case 'NOT_LIKE':
            case 'NOTLIKE':
            case 'NOT LIKE':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' NOT LIKE "%' . trim($value, '\\"') . '%"';
            case 'START_NOT_LIKE':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' NOT LIKE "%' . trim($value, '\\"') . '"';
            case 'END_NOT_LIKE':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' NOT LIKE "' . trim($value, '\\"') . '%"';
            case 'REGEXP':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' REGEXP "' . trim($value, '\\"') . '"';
            case 'BETWEEN':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' BETWEEN ' . $this->betweenArgumentPrepare($value);
            case 'NOT_BETWEEN':
            case 'NOTBETWEEN':
            case 'NOT BETWEEN':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' NOT BETWEEN ' . $this->betweenArgumentPrepare($value);
            case 'IN':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' IN (' . (is_array($value) ? implode(', ', $value) : $value) . ')';
            case 'NOT_IN':
            case 'NOTIN':
            case 'NOT IN':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' NOT IN (' . (is_array($value) ? implode(', ', $value) : $value) . ')';
            case 'FIND IN SET':
            case 'FINDINSET':
            case 'FIND_IN_SET':
                if(is_array($value)){
                    foreach ($value as &$val) {
                        if(is_int($val)){
                            continue;
                        }
                        $val = trim($val, "\"\\ \t\n\r\0\x0B");
                    }
                    $value = '"'.implode(',', $value).'"';
                }
                return 'FIND_IN_SET(' . $column . ', ' . ($value ?? 'NULL') . ')';
            case 'SOUNDEX':
                return "SOUNDEX(" . $column . ") LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(" . $value . ")), '%')";
            case 'IS':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' IS ' . $value;
            case 'IS_NOT':
            case 'ISNOT':
            case 'IS NOT':
                return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' IS NOT ' . $value;
            default:
                if(((bool)preg_match('/([\w\_]+)\((.+)\)$/iu', $column, $parse)) !== FALSE){
                    return strtoupper($parse[1]) . '('.Helper::sqlDriverQuotesStructure($parse[2], $this->_Driver).')';
                }else{
                    return Helper::sqlDriverQuotesStructure($column, $this->_Driver) . ' ' . $mark . ' ' .$value;
                }
        }
    }

    private function argumentPrepare($value)
    {
        if(is_numeric($value)){
            return $value;
        }
        if($value === null){
            return 'NULL';
        }
        if(is_bool($value)){
            return (int)$value;
        }
        if($value === '?'){
            return $value;
        }
        if(is_iterable($value)){
            foreach ($value as &$val) {
                $this->argumentPrepare($val);
            }
            return $value;
        }
        if(((bool)preg_match('/^:[\w]+$/', (string)$value)) !== FALSE){
            return $value;
        }
        if(((bool)preg_match('/^[A-Za-z]+\(\)$/', (string)$value)) !== FALSE){
            return $value;
        }
        return '"' . $this->escapeString($value, Connection::ESCAPE_STR) . '"';
    }

    private function betweenArgumentPrepare(array $value): string
    {
        return $this->argumentPrepare($value[0]) . ' AND ' . $this->argumentPrepare($value[1]);
    }

    private function aliasStatementPrepare(string $statement): false|array
    {
        if(stripos($statement, ' as ') === FALSE){
            return false;
        }
        $statement = str_replace(' AS ', ' as ', $statement);
        return explode(' as ', $statement);
    }

    private function prepareSelect($column, ?string $alias = null, ?string $fn = null, ?string $pattern  = null)
    {
        if(is_array($column)){
            foreach ($column as $item) {
                $this->prepareSelect($item, $alias, $fn, $pattern);
            }
            return;
        }
        $column = trim((string)$column);
        if($column === ''){
            return;
        }
        if(str_contains($column, ',')){
            $this->prepareSelect(explode(',', $column), $alias, $fn, $pattern);
            return;
        }
        if(($split = $this->aliasStatementPrepare($column)) !== FALSE){
            $this->prepareSelect($split[0], $split[1], $fn, $pattern);
            return;
        }
        if(((bool)preg_match('/([\w\_]+)\((.+)\)$/iu', $column, $parse)) !== FALSE){
            $this->prepareSelect($parse[2], $alias, $parse[1], $pattern);
            return;
        }
        if($alias !== null){
            $alias = trim($alias);
        }
        $select = $column;
        if(!empty($fn)){
            if(!empty($pattern)){
                $select = str_replace([
                    '{column}',
                    '{function}',
                ], [
                    Helper::sqlDriverQuotesStructure($select, $this->_Driver),
                    strtoupper($fn),
                ], $pattern);
            }else{
                $select = strtoupper($fn) . '(' .Helper::sqlDriverQuotesStructure($select, $this->_Driver) . ')';
            }
        }else{
            $select = Helper::sqlDriverQuotesStructure($select, $this->_Driver);
        }
        if(!empty($alias)){
            $select .= ' AS ' . Helper::sqlDriverQuotesStructure($alias, $this->_Driver);
        }
        if(in_array($select, $this->QB_Select, true) === FALSE){
            $this->QB_Select[] = $select;
        }
    }


    private function fromResolve(string $table, ?string $alias = null): string|array
    {
        $table = trim($table);
        if(str_contains($table, ',')){
            $parse = explode(',', $table);
            $res = [];
            foreach ($parse as $tab) {
                $res[] = $this->fromResolve($tab);
            }
            return $res;
        }
        if($alias !== null){
            return Helper::sqlDriverQuotesStructure($table, $this->_Driver) . ' AS ' . Helper::sqlDriverQuotesStructure($alias, $this->_Driver);
        }

        if(($split = $this->aliasStatementPrepare($table)) !== FALSE){
            return $this->fromResolve($split[0], $split[1]);
        }
        if(str_contains($table, ' ')){
            $split = explode(' ', $table, 2);
            return $this->fromResolve($split[0], $split[1]);
        }
        return Helper::sqlDriverQuotesStructure($table, $this->_Driver);
    }

}
