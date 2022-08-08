<?php
/**
 * DataMapper.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.10
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\DataMapper;

use InitPHP\Database\DB;
use \InitPHP\Database\Exceptions\{DataMapperException, DataMapperInvalidArgumentException};

/**
 * @mixin \PDOStatement
 */
class DataMapper implements DataMapperInterface
{

    protected const SUPPORTED_FETCH = [
        DB::FETCH_BOTH, DB::FETCH_ENTITY, DB::FETCH_OBJ, DB::FETCH_LAZY, DB::FETCH_ASSOC
    ];

    private DB $db;

    private array $parameters = [];

    private \PDOStatement $statement;

    private string $last_sql = '';

    private array $options = [
        'entity'    => null,
        'fetch'     => DB::FETCH_BOTH,
        'as'        => null,
        'as_entity' => null,
    ];

    public function __construct(DB &$db, ?array $options = [])
    {
        if(!empty($options)){
            $this->options = \array_merge($this->options, $options);
        }
        $this->db = &$db;
    }

    public function __call($name, $arguments)
    {
        if(!isset($this->statement)){
            return null;
        }
        if(\method_exists($this->statement, $name)){
            return $this->getStatement()->{$name}(...$arguments);
        }
        throw new DataMapperException('The "' . $name . '" method does not exist.');
    }

    /**
     * @inheritDoc
     */
    public function setParameter(string $key, $value): self
    {
        $key = ':' . \ltrim(\str_replace('.', '', $key), ':');
        $this->parameters[$key] = $value;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setParameters(array $parameters): self
    {
        if($parameters === []){
            return $this;
        }
        foreach ($parameters as $key => $value) {
            $key = ':' . \ltrim(\str_replace('.', '', $key), ':');
            $this->parameters[$key] = $value;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addParameter(string $key, $value): string
    {
        if($value === null){
            return 'NULL';
        }
        $originKey = ':' . \ltrim(\str_replace('.', '', $key), ':');
        $i = 0;

        do {
            $key = $i === 0 ? $originKey : $originKey . '_' . $i;
            ++$i;
            $hasParameter = isset($this->parameters[$key]);
        }while($hasParameter);

        $this->parameters[$key] = $value;
        return $key;
    }

    /**
     * @inheritDoc
     */
    public function getParameters(bool $reset = true): array
    {
        $parameters = $this->parameters;
        if($reset !== FALSE){
            $this->parameters = [];
        }
        return $parameters;
    }

    /**
     * @inheritDoc
     */
    public function prepare(string $sqlQuery): self
    {
        if($sqlQuery == ''){
            throw new DataMapperException('SQL statement cannot be empty.');
        }
        $this->last_sql = $sqlQuery;
        try {
            if(($this->statement = $this->db->getPDO()->prepare($sqlQuery)) === FALSE){
                $this->db->getConnection()->failedTransaction();
            }
        }catch (\PDOException $e) {
            $this->db->getConnection()->failedTransaction();
            throw new DataMapperException($e->getMessage(), (int)$e->getCode());
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function lastSQL(): string
    {
        return $this->last_sql;
    }

    /**
     * @inheritDoc
     */
    public function lastError(): ?string
    {
        if(!isset($this->statement)){
            return null;
        }
        $errorCode = $this->statement->errorCode();
        if($errorCode === null || empty(\trim($errorCode, "0 \t\n\r\0\x0B"))){
            return null;
        }
        $errorInfo = $this->statement->errorInfo();
        if(!isset($errorInfo[2])){
            return null;
        }
        return $errorCode . ' - ' . $errorInfo[2];
    }

    /**
     * @inheritDoc
     */
    public function bind($value)
    {
        if(\is_bool($value) || \intval($value)){
            return \PDO::PARAM_INT;
        }
        if(\is_null($value)){
            return \PDO::PARAM_NULL;
        }
        return \PDO::PARAM_STR;
    }

    /**
     * @inheritDoc
     */
    public function asArray(): self
    {
        $this->options['as'] = DB::FETCH_BOTH;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asAssoc(): self
    {
        $this->options['as'] = DB::FETCH_ASSOC;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asObject(): self
    {
        $this->options['as'] = DB::FETCH_OBJ;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asLazy(): self
    {
        $this->options['as'] = DB::FETCH_LAZY;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asEntity(string $class): self
    {
        if(!\class_exists($class)){
            throw new DataMapperInvalidArgumentException('The alias Entity class "' . $class . '" could not be found.');
        }
        $this->options['as'] = DB::FETCH_ENTITY;
        $this->options['as_entity'] = $class;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function bindParameters(array $fields, bool $isSearch = false): self
    {
        if(empty($fields)){
            return $this;
        }
        $type = ($isSearch === FALSE) ? $this->bindValues($fields) : $this->bindSearchValues($fields);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function numRows(): int
    {
        return isset($this->statement) ? $this->statement->rowCount() : 0;
    }

    /**
     * @inheritDoc
     */
    public function execute(): bool
    {
        if(!isset($this->statement)){
            return false;
        }
        try {
            $parameters = $this->getParameters();
            if(!empty($parameters)){
                $this->bindParameters($parameters);
            }
            if(($res = $this->statement->execute()) === FALSE){
                $this->db->getConnection()->failedTransaction();
            }
        }catch (\PDOException $e) {
            $this->db->getConnection()->failedTransaction();
        }
        return $res ?? false;
    }


    /**
     * @inheritDoc
     */
    public function result()
    {
        if (!isset($this->statement)) {
            return null;
        }
        $this->fetchModePrepare();
        return $this->numRows() > 0 ? $this->statement->fetch() : null;
    }

    /**
     * @inheritDoc
     */
    public function results(): ?array
    {
        if(!isset($this->statement)){
            return [];
        }
        $this->fetchModePrepare();
        return $this->numRows() > 0 ? $this->statement->fetchAll() : null;
    }

    /**
     * @inheritDoc
     */
    public function getLastID(): ?int
    {
        $lastID = $this->db->getPDO()->lastInsertId();
        return !empty($lastID) ? \intval($lastID) : null;
    }

    /**
     * @inheritDoc
     */
    public function buildQueryParameters(array ...$parameters): array
    {
        return \array_merge(...$parameters);
    }

    /**
     * @inheritDoc
     */
    public function persist(string $sqlQuery, array $parameters): bool
    {
        return $this->prepare($sqlQuery)
            ->bindParameters($this->buildQueryParameters($parameters, $this->getParameters()))
            ->execute();
    }

    /**
     * @inheritDoc
     */
    public function getStatement(): ?\PDOStatement
    {
        return $this->statement ?? null;
    }

    /**
     * @inheritDoc
     */
    public function setStatement(\PDOStatement $statement): self
    {
        $this->statement = $statement;
        return $this;
    }

    protected function bindValues(array $fields): \PDOStatement
    {
        foreach ($fields as $key => $value) {
            $this->statement->bindValue(':' . \ltrim($key, ':'), $value, $this->bind($value));
        }
        return $this->statement;
    }

    protected function bindSearchValues(array $fields): \PDOStatement
    {
        foreach ($fields as $key => $value) {
            $this->statement->bindValue(':' . $key, '%' . $value . '%', $this->bind($value));
        }
        return $this->statement;
    }

    private function getFetch()
    {
        $fetch = $this->options['fetch'];
        if(!\in_array($fetch, self::SUPPORTED_FETCH, true)){
            $fetch = $this->options['fetch'] = DB::FETCH_BOTH;
        }
        if($this->options['as'] !== null){
            $as = $this->options['as'];
            $this->options['as'] = null;
            if(\in_array($as, self::SUPPORTED_FETCH, true)){
                $fetch = $as;
            }
        }
        return $fetch;
    }

    private function getEntityClass(): ?string
    {
        if(isset($this->options['as_entity']) && !empty($this->options['as_entity'])){
            $entity = $this->options['as_entity'];
        }else{
            if(!isset($this->options['entity']) || empty($this->options['entity'])){
                return null;
            }
            $entity = $this->options['entity'];
        }

        return \class_exists($entity) ? $entity : null;
    }

    private function fetchModePrepare(): void
    {
        $fetch = $this->getFetch();
        if($fetch === DB::FETCH_ENTITY){
            if(($entity = $this->getEntityClass()) !== null){
                $this->statement->setFetchMode(\PDO::FETCH_CLASS, $entity);
                return;
            }
            $fetch = DB::FETCH_BOTH;
        }
        if($fetch !== DB::FETCH_BOTH){
            $this->statement->setFetchMode($fetch);
        }
    }

}