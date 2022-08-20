<?php
/**
 * DB.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.13
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use InitPHP\Database\Validation\Validation;
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
        'validation'        => [
            'methods'   => [],
            'messages'  => [],
            'labels'    => [],
        ],
    ];

    private bool $isOnlyDeletes = false;

    private Validation $_validation;

    private array $errors = [];

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
        $this->_dataMapper = new DataMapper($this, $dataMapperOptions);
        $this->_queryBuilder = new QueryBuilder($this, [
            'allowedFields'     => ($this->configurations['allowedFields'] ?? null)
        ]);
        $this->_validation = new Validation($this->configurations['validation']['methods'], $this->configurations['validation']['messages'], $this->configurations['validation']['labels'], $this);
    }

    public function __call($name, $arguments)
    {
        if(Helper::str_starts_with($name, 'findBy')){
            $attrCamelCase = \substr($name, 6);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->getQueryBuilder()->where($attributeName, $arguments[0]);
            $query = $this->getQueryBuilder()->readQuery();
            $this->getQueryBuilder()->reset();
            $this->getDataMapper()->persist($query, []);
            $res = $this->getDataMapper()->results();
            return $res === null ? [] : $res;
        }
        if(Helper::str_starts_with($name, 'findOneBy')){
            $attrCamelCase = \substr($name, 9);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->getQueryBuilder()->where($attributeName, $arguments[0]);
            $query = $this->getQueryBuilder()->readQuery();
            $this->getQueryBuilder()->reset();
            $this->getDataMapper()->persist($query, []);
            return $this->getDataMapper()->result();
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

    public function newInstance(): self
    {
        return new self($this->configurations);
    }

    /**
     * @return string|null
     */
    public function getSchema()
    {
        return $this->configurations['tableSchema'];
    }

    public function setSchema(string $schema): self
    {
        if($schema === ''){
            $this->configurations['tableSchema'] = null;
        }else{
            $this->configurations['tableSchema'] = $schema;
        }
        return $this;
    }

    public function withSchema(string $schema): self
    {
        $with = clone $this;
        return $with->setSchema($schema);
    }

    /**
     * @return string|null
     */
    public function getSchemaID()
    {
        return $this->configurations['tableSchemaID'];
    }

    public function setSchemaID(string $schemaID): self
    {
        if($schemaID === ''){
            $this->configurations['tableSchemaID'] = null;
        }else{
            $this->configurations['tableSchemaID'] = $schemaID;
        }
        return $this;
    }

    public function withSchemaID(string $schemaID): self
    {
        $with = clone $this;
        return $with->setSchemaID($schemaID);
    }

    public function isError(): bool
    {
        $this->get_error_merge();
        return !empty($this->errors);
    }

    public function getError(): array
    {
        $this->get_error_merge();
        return $this->errors;
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
        return $this->_queryBuilder;
    }

    public function getValidation(): Validation
    {
        return $this->_validation;
    }

    /**
     * @param array $fields
     * @return bool
     * @throws Exceptions\ValidationException
     */
    public function create(array $fields)
    {
        $data = [];
        $isCreatedField = !empty($this->configurations['createdField']);
        if($isCreatedField){
            $createdFieldParameterName = $this->getDataMapper()->addParameter($this->configurations['createdField'], \date($this->configurations['timestampFormat']));
        }
        if(\count($fields) === \count($fields, \COUNT_RECURSIVE)){
            $this->getValidation()->setData($fields);
            foreach ($fields as $column => $value) {
                if($this->getValidation()->validation($column, null) === FALSE){
                    $this->errors[] = $this->getValidation()->getError();
                    return false;
                }
                $data[$column] = $value;
            }
            if(empty($data)){
                return false;
            }
            if($isCreatedField){
                $data[$this->configurations['createdField']] = $createdFieldParameterName;
            }
        }else{
            $i = 0;
            foreach ($fields as $row) {
                $data[$i] = [];
                $this->getValidation()->setData($row);
                foreach ($row as $column => $value) {
                    if($this->getValidation()->validation($column, null) === FALSE){
                        $this->errors[] = $this->getValidation()->getError();
                        return false;
                    }
                    $data[$i][$column] = $value;
                }
                if(empty($data[$i])){
                    continue;
                }
                if($isCreatedField){
                    $data[$i][$this->configurations['createdField']] = $createdFieldParameterName;
                }
                ++$i;
            }
        }
        $query = $this->getQueryBuilder()->insertQuery($data);
        $this->getQueryBuilder()->reset();
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
        $res = $this->getDataMapper()->results();
        return $res === null ? [] : $res;
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
        return $this->getDataMapper()->result();
    }

    /**
     * @param array $fields
     * @return bool
     */
    public function update(array $fields)
    {
        $schemaID = null;
        if(!empty($this->getSchemaID()) && isset($fields[$this->getSchemaID()])){
            $schemaID = $fields[$this->getSchemaID()];
            $this->getQueryBuilder()->where($this->getSchemaID(), $schemaID);
            unset($fields[$this->getSchemaID()]);
        }
        if(empty($fields)){
            return false;
        }
        $this->getValidation()->setData($fields);
        $data = [];
        foreach ($fields as $column => $value) {
            if($this->getValidation()->validation($column, $schemaID) === FALSE){
                $this->errors[] = $this->getValidation()->getError();
                return false;
            }
            $data[$column] = $value;
        }
        if(!empty($this->configurations['updatedField'])){
            $data[$this->configurations['updatedField']] = \date($this->configurations['timestampFormat']);
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
            $this->getQueryBuilder()->where($column, $value);
        }
        if(!empty($this->configurations['deletedField'])){
            if($this->isOnlyDeletes !== FALSE){
                $this->getQueryBuilder()->isNot($this->configurations['deletedField'], null);
                $query = $this->getQueryBuilder()->deleteQuery();
                $this->isOnlyDeletes = false;
            }else{
                $this->getQueryBuilder()->is($this->configurations['deletedField'], null);
                $query = $this->getQueryBuilder()->updateQuery([
                    $this->configurations['deletedField'] => \date($this->configurations['timestampFormat']),
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
        $parameters = $this->getDataMapper()->getParameters(false);
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
                $this->getQueryBuilder()->where($column, $value);
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

    private function get_error_merge(): void
    {
        $error = $this->getDataMapper()->lastError();
        if(!empty($error) && !\in_array($error, $this->errors)){
            $this->errors[] = $error;
        }
    }

}
