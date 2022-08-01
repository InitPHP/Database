<?php
/**
 * QueryBuilder.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.5
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder;

use \InitPHP\Database\QueryBuilder\Expressions\{From, GroupBy, Join, Limit, Select, WhereAndHaving, OrderBy};
use \InitPHP\Database\Exceptions\{QueryBuilderException, QueryBuilderInvalidArgumentException};
use InitPHP\Database\Helper;

class QueryBuilder implements QueryBuilderInterface
{

    use Select, From, Join, WhereAndHaving, OrderBy, Limit, GroupBy;

    protected const DEFAULT_EXPRESSIONS = [
        'fields'        => [],
        'primary_key'   => '',
    ];

    protected const SUPPORTED_JOIN_TYPES = [
        'INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER', 'SELF', 'NATURAL'
    ];

    /** @var string */
    private string $sqlQuery = '';

    private string $schema;

    private string $schemaID;

    /** @var null|string[] */
    private ?array $allowedFields = null;

    public function __construct(array $options = [])
    {
        if(isset($options['allowedFields']) && \is_array($options['allowedFields']) && !empty($options['allowedFields'])){
            $this->allowedFields = $options['allowedFields'];
        }
        if(isset($options['schema']) && \is_string($options['schema']) && !empty($options['schema'])){
            $this->schema = $options['schema'];
        }
        if(isset($options['schemaID']) && \is_string($options['schemaID']) && !empty($options['schemaID'])){
            $this->schemaID = $options['schemaID'];
        }
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
            . ' ' . ($this->endTableSchema() ?? $this->getSchema()) . ' ';

        $columns = [];
        $values = [];

        if(\count($data) === \count($data, \COUNT_RECURSIVE)){
            foreach ($data as $column => $value) {
                $column = \trim($column);
                if(!$this->isAllowedField($column)){
                    continue;
                }
                $columns[] = $column;
                $values[] = Helper::queryBindParameter($value, '{value}');
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
                $value[$column] = Helper::queryBindParameter($val, '{value}');
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
            . ' ' . ($this->endTableSchema() ?? $this->getSchema())
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
            if($this->getSchemaID() == $column){
                continue;
            }
            if(!$this->isAllowedField($column)){
                continue;
            }
            $update[] = $column . ' = ' . Helper::queryBindParameter($value, '{value}');
        }
        if(empty($update)){
            return '';
        }
        $schemaID = $this->getSchemaID();
        if($schemaID !== null && isset($data[$schemaID])){
            $this->where($schemaID, $data[$schemaID]);
        }
        $this->sqlQuery = 'UPDATE '
            . ($this->endTableSchema() ?? $this->getSchema())
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

    protected function getSchema(): ?string
    {
        return $this->schema ?? null;
    }

    protected function getSchemaID(): ?string
    {
        return  $this->schemaID ?? null;
    }

    protected function isAllowedField(string $field): bool
    {
        return empty($this->allowedFields) || \in_array($field, $this->allowedFields, true);
    }

}
