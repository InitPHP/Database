<?php
/**
 * QueryBuilder.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.3
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

    /** @var array */
    private array $expressions = [];

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
        $this->expressions = self::DEFAULT_EXPRESSIONS;
        if(isset($options['allowedFields']) && \is_array($options['allowedFields']) && !empty($options['allowedFields'])){
            $this->allowedFields = $options['allowedFields'];
        }
        if(isset($options['schema']) && \is_string($options['schema']) && !empty($options['schema'])){
            $this->schema = $options['schema'];
        }
        if(isset($options['schemaID']) && \is_string($options['schemaID']) && !empty($options['schemaID'])){
            $this->schemaID = $options['schemaID'];
            $this->expressions['primary_key'] = $this->schemaID;
        }
    }

    public function __destruct()
    {
        $this->reset();
    }

    /**
     * @inheritDoc
     */
    public function buildQuery(array $args = [], bool $isReset = true): self
    {
        $clone = clone $this;
        if($isReset !== FALSE){
            $clone->reset();
        }
        if(isset($args['table'])){
            if(!empty($args['table']) && \is_string($args['table'])){
                $clone->schema = $args['table'];
            }
            unset($args['table']);
        }
        if((isset($args['primary_key']))){
            if(!empty($args['primary_key']) && $args['primary_key'] != '0'){
                $clone->schemaID = $args['primary_key'];
            }
            unset($args['primary_key']);
        }
        if(isset($args['select'])){
            $clone->selectorAppend($args['select']);
            unset($args['select']);
        }
        if(isset($args['conditions']) && \is_array($args['conditions']) && !empty($args['conditions'])){
            $wheres = [];
            foreach ($args['conditions'] as $key => $value) {
                $wheres[] = $key . ' = :' . $key;
            }
            $clone->whereAppend(\implode(' AND ', $wheres));
            unset($wheres, $args['conditions']);
        }
        if(isset($args['offset'])){
            if(\is_numeric($args['offset']) && $args['offset'] >= 0){
                $clone->offset((int)$args['offset']);
            }
            unset($args['offset']);
        }
        if(isset($args['limit'])){
            if(\is_numeric($args['limit']) && $args['limit'] >= 1){
                $clone->limit((int)$args['limit']);
            }
            unset($args['limit']);
        }
        $clone->expressions = \array_merge(self::DEFAULT_EXPRESSIONS, $clone->expressions, $args);
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function insertQuery(?array $data = null): string
    {
        if($data === null && !empty($this->expressions['fields'])){
            $data = [];
            if(\count($this->expressions['fields']) === \count($this->expressions['fields'], \COUNT_RECURSIVE)){
                foreach ($this->expressions['fields'] as $key => $value) {
                    $data[$key] = ':'. $key;
                }
            }else{
                $data = $this->expressions['fields'];
            }
        }
        if(empty($data)){
            return '';
        }

        $this->sqlQuery = 'INSERT INTO'
            . ' ' . ($this->getSchema() ?? $this->endTableSchema()) . ' ';

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
            . ' ' . ($this->getSchema() ?? $this->endTableSchema())
            . $this->whereQuery()
            . $this->limitQuery();
        return \trim($this->sqlQuery);
    }

    /**
     * @inheritDoc
     */
    public function updateQuery(?array $data = null): string
    {
        if($data === null && !empty($this->expressions['fields'])){
            $data = [];
            foreach ($this->expressions['fields'] as $key => $value) {
                $data[$key] = ':' . $key;
            }
        }
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
            $this->whereAppend($schemaID . ' = :' . $schemaID);
        }
        $this->sqlQuery = 'UPDATE '
            . ($this->getSchema() ?? $this->endTableSchema())
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
        $this->expressions = self::DEFAULT_EXPRESSIONS;
        if(isset($this->schemaID) && !empty($this->schemaID)){
            $this->expressions['primary_key'] = $this->schemaID;
        }
        return $this;
    }

    protected function getSchema(): ?string
    {
        return $this->schema ?? null;
    }

    protected function getSchemaID(): ?string
    {
        if(isset($this->expressions['primary_key']) && !empty($this->expressions['primary_key']) && $this->expressions['primary_key'] != '0'){
            return $this->expressions['primary_key'];
        }
        return  $this->schemaID ?? null;
    }

    protected function isAllowedField(string $field): bool
    {
        return empty($this->allowedFields) || \in_array($field, $this->allowedFields, true);
    }

}
