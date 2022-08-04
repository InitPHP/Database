<?php
/**
 * DB.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.7
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use \PDO;
use \InitPHP\Database\Connection\{Connection, ConnectionInterface};
use \InitPHP\Database\DataMapper\{DataMapper, DataMapperInterface};
use InitPHP\Database\Exceptions\DatabaseException;
use \InitPHP\Database\QueryBuilder\{QueryBuilder, QueryBuilderInterface};

/**
 * @mixin QueryBuilderInterface
 * @mixin DataMapperInterface
 * @mixin ConnectionInterface
 */
class DB
{

    public const FETCH_ASSOC = PDO::FETCH_ASSOC;
    public const FETCH_BOTH = PDO::FETCH_BOTH;
    public const FETCH_LAZY = PDO::FETCH_LAZY;
    public const FETCH_OBJ = PDO::FETCH_OBJ;
    public const FETCH_ENTITY = PDO::FETCH_CLASS;

    /** @var ConnectionInterface */
    private $_connection;

    /** @var QueryBuilderInterface */
    private $_queryBuilder;

    /** @var DataMapperInterface */
    private $_dataMapper;

    /** @var array */
    private array $configurations = [
        'dsn'           => null,
        'username'      => null,
        'password'      => null,
        'tableSchema'   => null,
        'tableSchemaID' => null,
        'entity'        => Entity::class,
    ];

    /** @var array */
    private array $_parameters = [];

    private bool $is_get_execute = false;

    public function __construct(array $configurations)
    {
        $this->configurations = \array_merge($this->configurations, $configurations);

        $this->_connection = new Connection([
            'dsn'       => ($this->configurations['dsn'] ?? ''),
            'username'  => ($this->configurations['username'] ?? ''),
            'password'  => ($this->configurations['password'] ?? ''),
            'charset'   => ($this->configurations['charset'] ?? 'utf8mb4'),
            'collation' => ($this->configurations['collation'] ?? 'utf8mb4_unicode_ci')
        ]);
        $dataMapperOptions = [];
        if(isset($this->configurations['entity'])){
            $dataMapperOptions['entity'] = $this->configurations['entity'];
        }
        if(isset($this->configurations['fetch'])){
            $dataMapperOptions['fetch'] = $this->configurations['fetch'];
        }
        $this->_dataMapper = new DataMapper($this->_connection, $dataMapperOptions);
        $this->_queryBuilder = new QueryBuilder([
            'allowedFields'     => ($this->configurations['allowedFields'] ?? null),
            'schema'            => ($this->configurations['tableSchema'] ?? null),
            'schemaID'          => ($this->configurations['tableSchemaID'] ?? null),
        ]);
    }

    public function __call($name, $arguments)
    {
        if(Helper::str_starts_with($name, 'findBy')){
            $attrCamelCase = \substr($name, 6);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->setParameter(':'. $attributeName, $arguments[0]);
            $this->getQueryBuilder()->where($attributeName, ':' . $attributeName);
            if($this->getSchema() !== null){
                $this->getQueryBuilder()->table($this->getSchema());
            }
            $query = $this->getQueryBuilder()->readQuery();
            $this->getQueryBuilder()->reset();
            $this->getDataMapper()->persist($query, $this->getDataMapper()->buildQueryParameters($this->getParameters()));
            return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
        }
        if(Helper::str_starts_with($name, 'findOneBy')){
            $attrCamelCase = \substr($name, 9);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->setParameter(':' . $attributeName, $arguments[0]);
            $this->getQueryBuilder()->where($attributeName, ':' . $attributeName);
            if($this->getSchema() !== null){
                $this->getQueryBuilder()->table($this->getSchema());
            }
            $query = $this->getQueryBuilder()->readQuery();
            $this->getQueryBuilder()->reset();
            $this->getDataMapper()->persist($query, $this->getDataMapper()->buildQueryParameters($this->getParameters()));
            return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->result() : null;
        }
        if(\method_exists($this->_queryBuilder, $name)){
            $res = $this->getQueryBuilder()->{$name}(...$arguments);
            if($res instanceof QueryBuilderInterface){
                return $this;
            }
            return $res;
        }
        if(\method_exists($this->_dataMapper, $name)){
            $res = $this->getDataMapper()->{$name}(...$arguments);
            if($res instanceof DataMapperInterface){
                return $this;
            }
            return $res;
        }
        if(\method_exists($this->_connection, $name)){
            return $this->getConnection()->{$name}(...$arguments);
        }
        throw new DatabaseException('The "' . $name . '" method does not exist.');
    }

    public function table(string $schema, ?string $schemaID = null): self
    {
        $this->configurations['tableSchema'] = $schema;
        $this->configurations['tableSchemaID'] = $schemaID;
        $this->getQueryBuilder()->table($schema);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSchema()
    {
        return $this->configurations['tableSchema'];
    }

    /**
     * @return string|null
     */
    public function getSchemaID()
    {
        return $this->configurations['tableSchemaID'];
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->_connection;
    }

    /**
     * @return DataMapperInterface
     */
    public function getDataMapper(): DataMapperInterface
    {
        return $this->_dataMapper;
    }

    /**
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        $this->is_get_execute = false;
        return $this->_queryBuilder;
    }

    /**
     * @param array $fields
     * @return bool
     */
    public function create(array $fields)
    {
        $data = [];
        if(\count($fields) === \count($fields, \COUNT_RECURSIVE)){
            foreach ($fields as $column => $value) {
                $data[$column] = ':'.$column;
                $this->setParameter(':'.$column, $value);
            }
        }else{
            $i = 0; $parameters = [];
            foreach ($fields as $row) {
                $data[$i] = [];
                foreach ($row as $column => $value) {
                    $data[$i][$column] = ':' . $column . '_' . $i;
                    $parameters[':' . $column . '_' . $i] = $value;
                }
                ++$i;
            }
            $this->setParameters($parameters);
            unset($parameters);
        }
        $query = $this->getQueryBuilder()->insertQuery($data);
        $this->getDataMapper()->persist($query, $this->getParameters(true));
        return $this->getDataMapper()->numRows() > 0;
    }


    /**
     * @param string[] $selector
     * @param array $conditions
     * @param array $parameters
     * @return array|Entity[]|object[]|string[]
     */
    public function read(array $selector = [], array $conditions = [], array $parameters = []): array
    {
        if(!empty($selector)){
            $this->getQueryBuilder()->select(...$selector);
        }
        if(!empty($conditions)){
            foreach ($conditions as $column => $value) {
                $this->getQueryBuilder()->where($column, ':'.$column);
                $this->setParameter(':' . $column, $value);
            }
        }
        $query = $this->getQueryBuilder()->readQuery();
        $this->getQueryBuilder()->reset();

        $parameters = $this->getDataMapper()->buildQueryParameters($this->getParameters(true), $parameters);

        $this->getDataMapper()->persist($query, $parameters);

        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
    }

    /**
     * @param array $fields
     * @return bool
     */
    public function update(array $fields)
    {
        if(!empty($this->getSchemaID()) && isset($fields[$this->getSchemaID()])){
            $this->getQueryBuilder()->where($this->getSchemaID(), ':' . $this->getSchemaID());
            $this->setParameter(':' . $this->getSchemaID(), $fields[$this->getSchemaID()]);
            unset($fields[$this->getSchemaID()]);
        }
        if(empty($fields)){
            return false;
        }
        $data = [];
        foreach ($fields as $column => $value) {
            $data[$column] = ':' . $column;
            $this->setParameter(':' . $column, $value);
        }
        $query = $this->getQueryBuilder()->updateQuery($data);
        $this->getQueryBuilder()->reset();

        $this->getDataMapper()->persist($query, $this->getDataMapper()->buildQueryParameters($this->getParameters()));
        return $this->getDataMapper()->numRows() > 0;
    }

    /**
     * @param array $conditions
     * @return bool
     */
    public function delete(array $conditions = [])
    {
        if(!empty($this->getSchema())){
            $this->getQueryBuilder()->table($this->getSchema());
        }
        foreach ($conditions as $column => $value) {
            $this->getQueryBuilder()->where($column, ':'.$column);
            $this->setParameter(':'.$column, $value);
        }
        $query = $this->getQueryBuilder()->deleteQuery();
        $this->getQueryBuilder()->reset();

        $this->getDataMapper()->persist($query, $this->getDataMapper()->buildQueryParameters($this->getParameters()));

        return $this->getDataMapper()->numRows() > 0;
    }

    /**
     * @param string $rawQuery
     * @param array $conditions
     * @return array
     */
    public function rawQuery(string $rawQuery, array $conditions = []): array
    {
        $this->getDataMapper()->persist($rawQuery, $this->getDataMapper()->buildQueryParameters($conditions, $this->getParameters()));
        return $this->getDataMapper()->numRows() < 1 ? [] : $this->getDataMapper()->results();
    }

    /**
     * @param string $name
     * @param $value
     * @return $this
     */
    public function setParameter(string $name, $value): self
    {
        $this->_parameters[$name] = $value;
        return $this;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters = []): self
    {
        $this->_parameters = \array_merge($this->_parameters, $parameters);
        return $this;
    }

    /**
     * @param bool $reset
     * @return array
     */
    public function getParameters(bool $reset = true): array
    {
        $params = $this->_parameters;
        if($reset !== FALSE){
            $this->_parameters = [];
        }
        return $params;
    }

    /**
     * @return \PDOStatement
     */
    public function get(): \PDOStatement
    {
        $query = $this->getQueryBuilder()->readQuery();
        $this->getQueryBuilder()->reset();

        $this->getDataMapper()->prepare($query)
            ->bindParameters($this->getParameters());

        $statement = $this->getDataMapper()->getStatement();
        $this->getDataMapper()->execute();

        $this->is_get_execute = true;

        return $statement;
    }

    /**
     * @param bool $builder_reset
     * @return int
     */
    public function count(bool $builder_reset = false): int
    {
        $query = $this->getQueryBuilder()->readQuery();
        if($builder_reset !== FALSE){
            $this->getQueryBuilder()->reset();
        }
        $this->getDataMapper()->persist($query, $this->getDataMapper()->buildQueryParameters($this->getParameters()));
        return $this->getDataMapper()->numRows();
    }

    /**
     * @return array|Entity|object|null
     */
    public function first()
    {
        if($this->is_get_execute === FALSE){
            $this->getQueryBuilder()->limit(1);
            $this->get();
        }
        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->result() : null;
    }

    /**
     * @return array|Entity|object|null
     */
    public function find()
    {
        if($this->is_get_execute === FALSE){
            $this->get();
        }
        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->result() : null;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        if($limit > 0){
            $this->getQueryBuilder()->limit($limit);
        }
        if($offset > 0){
            $this->getQueryBuilder()->offset($offset);
        }
        if($this->is_get_execute === FALSE){
            $this->get();
        }
        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
    }

    /**
     * @return array
     */
    public function rows(): array
    {
        if($this->is_get_execute === FALSE){
            $this->get();
        }
        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
    }

    /**
     * @return array|Entity|object|null
     */
    public function row()
    {
        if($this->is_get_execute === FALSE){
            $this->get();
        }
        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->result() : null;
    }

    /**
     * @param int $column
     * @return mixed
     */
    public function column(int $column = 0)
    {
        return $this->getDataMapper()->fetchColumn($column);
    }

}
