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

use InitPHP\Database\Exceptions\DatabaseInvalidArgumentException;
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
        'dsn'               => null,
        'username'          => null,
        'password'          => null,
        'tableSchema'       => null,
        'tableSchemaID'     => null,
        'entity'            => Entity::class,
        'createdField'      => null,
        'updatedField'      => null,
        'deletedField'      => null,
        'timestampFormat'   => 'c',
    ];

    private bool $isOnlyDeletes = false;

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

            $this->getQueryBuilder()->where($attributeName, ':' . $attributeName);
            $query = $this->getQueryBuilder()->readQuery();
            $this->getQueryBuilder()->reset();
            $this->getDataMapper()->setParameter(':'. $attributeName, $arguments[0]);
            $this->getDataMapper()->persist($query, []);
            return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
        }
        if(Helper::str_starts_with($name, 'findOneBy')){
            $attrCamelCase = \substr($name, 9);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->getDataMapper()->setParameter(':' . $attributeName, $arguments[0]);
            $this->getQueryBuilder()->where($attributeName, ':' . $attributeName);
            $query = $this->getQueryBuilder()->readQuery();
            $this->getQueryBuilder()->reset();
            $this->getDataMapper()->persist($query, []);
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

    public function setSchemaID(?string $schemaID): self
    {
        $this->configurations['tableSchemaID'] = $schemaID;
        $this->getQueryBuilder()->setSchemaID($schemaID);
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
        $data = []; $parameters = [];
        $isCreatedField = !empty($this->configurations['createdField']);
        if(\count($fields) === \count($fields, \COUNT_RECURSIVE)){
            foreach ($fields as $column => $value) {
                $data[$column] = ':'.$column;
                $parameters[':' . $column] = $value;
            }
            if($isCreatedField){
                $data[$this->configurations['createdField']] = ':' . $this->configurations['createdField'];
            }
        }else{
            $i = 0;
            foreach ($fields as $row) {
                $data[$i] = [];
                foreach ($row as $column => $value) {
                    $data[$i][$column] = ':' . $column . '_' . $i;
                    $parameters[':' . $column . '_' . $i] = $value;
                }
                if($isCreatedField){
                    $data[$i][$this->configurations['createdField']] = ':' . $this->configurations['createdField'];
                }
                ++$i;
            }
        }
        if($isCreatedField){
            $parameters[':' . $this->configurations['createdField']] = \date($this->configurations['timestampFormat']);
        }
        $this->getDataMapper()->setParameters($parameters);
        unset($parameters);
        $query = $this->getQueryBuilder()->insertQuery($data);
        $this->getDataMapper()->persist($query, []);
        return $this->getDataMapper()->numRows() > 0;
    }


    /**
     * @param string[] $selector
     * @param array $conditions
     * @param array $parameters
     * @return array|Entity[]|object[]
     */
    public function read(array $selector = [], array $conditions = [], array $parameters = []): array
    {
        $this->readQueryHandler($selector, $conditions, $parameters);
        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
    }

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return null|array|Entity|object
     */
    public function readOne(array $selector = [], array $conditions = [], array $parameters = [])
    {
        $this->getQueryBuilder()->limit(1);
        $this->readQueryHandler($selector, $conditions, $parameters);
        return $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->result() : null;
    }

    /**
     * @param array $fields
     * @return bool
     */
    public function update(array $fields)
    {
        if(!empty($this->getSchemaID()) && isset($fields[$this->getSchemaID()])){
            $this->getQueryBuilder()->where($this->getSchemaID(), ':' . $this->getSchemaID());
            $this->getDataMapper()->setParameter(':' . $this->getSchemaID(), $fields[$this->getSchemaID()]);
            unset($fields[$this->getSchemaID()]);
        }
        if(empty($fields)){
            return false;
        }
        $data = [];
        foreach ($fields as $column => $value) {
            $data[$column] = ':' . $column;
            $this->getDataMapper()->setParameter(':' . $column, $value);
        }
        if(!empty($this->configurations['updatedField'])){
            $data[$this->configurations['updatedField']] = ':' . $this->configurations['updatedField'];
            $this->getDataMapper()->setParameter(':' . $this->configurations['updatedField'], \date($this->configurations['timestampFormat']));
        }
        $query = $this->getQueryBuilder()->updateQuery($data);
        $this->getQueryBuilder()->reset();

        $this->getDataMapper()->persist($query, []);
        return $this->getDataMapper()->numRows() > 0;
    }

    /**
     * @param array $conditions
     * @return bool
     */
    public function delete(array $conditions = [])
    {
        foreach ($conditions as $column => $value) {
            $this->getQueryBuilder()->where($column, ':'.$column);
            $this->getDataMapper()->setParameter(':'.$column, $value);
        }
        if(!empty($this->configurations['deletedField'])){
            if($this->isOnlyDeletes !== FALSE){
                $this->getQueryBuilder()->isNot($this->configurations['deletedField'], null);
                $query = $this->getQueryBuilder()->deleteQuery();
                $this->isOnlyDeletes = false;
            }else{
                $this->getQueryBuilder()->is($this->configurations['deletedField'], null);
                $this->getDataMapper()->setParameter(':' . $this->configurations['deletedField'], \date($this->configurations['timestampFormat']));
                $query = $this->getQueryBuilder()->updateQuery([
                    $this->configurations['deletedField'] => ':' . $this->configurations['deletedField'],
                ]);
            }
        }else{
            $query = $this->getQueryBuilder()->deleteQuery();
        }

        $this->getQueryBuilder()->reset();

        $this->getDataMapper()->persist($query, []);

        return $this->getDataMapper()->numRows() > 0;
    }

    /**
     * @param string $rawQuery
     * @param array $conditions
     * @return array
     */
    public function rawQuery(string $rawQuery, array $conditions = []): array
    {
        $this->getDataMapper()->persist($rawQuery, $conditions);
        return $this->getDataMapper()->numRows() < 1 ? [] : $this->getDataMapper()->results();
    }

    /**
     * @param bool $builder_reset
     * @return int
     */
    public function count(bool $builder_reset = false): int
    {
        $this->deletedFieldBuild($builder_reset);
        $query = $this->getQueryBuilder()->readQuery();
        if($builder_reset !== FALSE){
            $this->getQueryBuilder()->reset();
        }
        $parameters = $this->getDataMapper()->getParameters();
        $this->getDataMapper()->persist($query, []);
        if($builder_reset === FALSE && !empty($parameters)){
            $this->getDataMapper()->setParameters($parameters);
        }
        return $this->getDataMapper()->numRows();
    }

    /**
     * @return array|Entity|object|null
     */
    public function find()
    {
        return $this->readOne();
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
        return $this->read();
    }

    /**
     * @return array
     */
    public function rows(): array
    {
        return $this->read();
    }

    /**
     * @return array|Entity|object|null
     */
    public function row()
    {
        return $this->readOne();
    }

    /**
     * @param int $column
     * @return mixed
     */
    public function column(int $column = 0)
    {
        return $this->getDataMapper()->fetchColumn($column);
    }

    public function onlyDeleted(): self
    {
        $this->isOnlyDeletes = true;
        return $this;
    }

    public function onlyUndeleted(): self
    {
        $this->isOnlyDeletes = false;
        return $this;
    }

    private function readQueryHandler(array $selector = [], array $conditions = [], array $parameters = []): void
    {
        if(!empty($selector)){
            $this->getQueryBuilder()->select(...$selector);
        }
        if(!empty($conditions)){
            foreach ($conditions as $column => $value) {
                $this->getQueryBuilder()->where($column, ':'.$column);
                $this->getDataMapper()->setParameter(':' . $column, $value);
            }
        }
        $this->deletedFieldBuild();
        $query = $this->getQueryBuilder()->readQuery();
        $this->getQueryBuilder()->reset();

        $this->getDataMapper()->persist($query, $parameters);
    }

    private function deletedFieldBuild(bool $reset = true): void
    {
        if(!empty($this->configurations['deletedField'])){
            if($this->isOnlyDeletes === FALSE){
                $this->getQueryBuilder()->is($this->configurations['deletedField'], null);
            }else{
                $this->getQueryBuilder()->isNot($this->configurations['deletedField'], null);
            }
            if($reset !== FALSE){
                $this->isOnlyDeletes = false;
            }
        }
    }

}
