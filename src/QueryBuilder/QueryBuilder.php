<?php
/**
 * QueryBuilder.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.11
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder;

use \InitPHP\Database\QueryBuilder\Expressions\{From, GroupBy, Join, Limit, Select, WhereAndHaving, OrderBy};
use InitPHP\Database\DB;
use InitPHP\Database\Helper;

class QueryBuilder implements QueryBuilderInterface
{

    use Select, From, Join, WhereAndHaving, OrderBy, Limit, GroupBy;

    protected const SUPPORTED_JOIN_TYPES = [
        'INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER', 'SELF', 'NATURAL'
    ];

    /** @var string */
    private string $sqlQuery = '';

    protected DB $db;

    /** @var null|string[] */
    private ?array $allowedFields = null;

    public function __construct(DB &$db, array $options = [])
    {
        if(isset($options['allowedFields']) && \is_array($options['allowedFields']) && !empty($options['allowedFields'])){
            $this->allowedFields = $options['allowedFields'];
        }
        $this->db = &$db;
    }

    public function __destruct()
    {
        $this->reset();
    }

    /**
     * @inheritDoc
     */
    public function insertQuery(array $data): string
    {
        if(empty($data)){
            return '';
        }

        $this->sqlQuery = 'INSERT INTO'
            . ' ' . ($this->endTableSchema() ?? $this->db->getSchema()) . ' ';

        $columns = [];
        $values = [];

        if(\count($data) === \count($data, \COUNT_RECURSIVE)){
            foreach ($data as $column => $value) {
                $column = \trim($column);
                if(!$this->isAllowedField($column)){
                    continue;
                }
                $columns[] = $column;
                if(Helper::isSQLParameterOrFunction($value)){
                    $values[] = $value;
                }else{
                    $values[] = $this->db->getDataMapper()->addParameter($column, $value);
                }
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
                if(!$this->isAllowedField($column)){
                    continue;
                }
                if(\in_array($column, $columns, true) === FALSE){
                    $columns[] = $column;
                }
                if(Helper::isSQLParameterOrFunction($val)){
                    $value[$column] = $val;
                }else{
                    $value[$column] = $this->db->getDataMapper()->addParameter($column, $val);
                }
            }
            $values[] = $value;
        }

        $multiValues = [];
        foreach ($values as $value) {
            $tmpValue = $value;
            $value = [];
            foreach ($columns as $column) {
                $value[$column] = Helper::queryBindParameter(($tmpValue[$column] ?? null), '{value}');
            }
            $multiValues[] = '(' . \implode(', ', $value) . ')';
        }
        $this->sqlQuery .= '(' . \implode(', ', $columns) . ') VALUES ' . \implode(', ', $multiValues) . ';';

        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function readQuery(): string
    {
        $this->sqlQuery = $this->selectorQuery()
            . $this->fromQuery()
            . $this->joinQuery()
            . $this->whereQuery()
            . $this->havingQuery()
            . $this->orderByQuery()
            . $this->limitQuery();

        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function deleteQuery(): string
    {
        $this->sqlQuery = 'DELETE FROM'
            . ' ' . ($this->endTableSchema() ?? $this->db->getSchema())
            . $this->whereQuery()
            . $this->limitQuery();
        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function updateQuery(array $data): string
    {
        if(empty($data)){
            return '';
        }
        $update = [];
        foreach ($data as $column => $value) {
            if($this->db->getSchemaID() == $column){
                continue;
            }
            if(!$this->isAllowedField($column)){
                continue;
            }
            if(Helper::isSQLParameterOrFunction($value)){
                $update[] = $column . ' = ' . $value;
            }else{
                $update[] = $column . ' = ' . $this->db->getDataMapper()->addParameter($column, $value);
            }
        }
        if(empty($update)){
            return '';
        }
        $schemaID = $this->db->getSchemaID();
        if($schemaID !== null && isset($data[$schemaID])){
            $this->where($schemaID, $data[$schemaID]);
        }
        $this->sqlQuery = 'UPDATE '
            . ($this->endTableSchema() ?? $this->db->getSchema())
            . ' SET '
            . \implode(', ', $update)
            . $this->whereQuery()
            . $this->limitQuery();
        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function reset(): self
    {
        $this->selectorReset();
        $this->fromReset();
        $this->joinReset();
        $this->havingReset();
        $this->whereReset();
        $this->orderByReset();
        $this->limitReset();
        $this->groupByReset();
        return $this;
    }

    protected function isAllowedField(string $field): bool
    {
        return empty($this->allowedFields) || \in_array($field, $this->allowedFields, true);
    }

}
