<?php
/**
 * Result
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

final class Result
{

    private \PDOStatement $statement;

    private string $query;

    private int $num_rows;

    public function __construct(\PDOStatement $statement)
    {
        $this->setStatement($statement);
    }

    public function setStatement(\PDOStatement $statement): self
    {
        $this->statement = $statement;
        $this->query = $statement->queryString;
        $this->num_rows = (int)$statement->rowCount();
        return $this;
    }

    public function withStatement(\PDOStatement $statement): self
    {
        $with = clone $this;
        return $with->setStatement($statement);
    }

    public function getStatement(): \PDOStatement
    {
        return $this->statement;
    }

    public function numRows(): int
    {
        return $this->num_rows;
    }

    public function rowCount(): int
    {
        return $this->numRows();
    }

    public function query(): string
    {
        return $this->query;
    }

    /**
     * @param string $entityClass
     * @return array|object|null
     */
    public function toEntity(string $entityClass = Entity::class)
    {
        if($this->num_rows === 0){
            return null;
        }
        $this->asEntity($entityClass);
        return $this->num_rows === 1 ? $this->getStatement()->fetch() : $this->getStatement()->fetchAll();
    }

    public function asEntity(string $entityClass = Entity::class): self
    {
        $this->getStatement()->setFetchMode(\PDO::FETCH_CLASS, $entityClass);
        return $this;
    }

    public function toAssoc(): array
    {
        if($this->num_rows === 0){
            return [];
        }
        $this->asAssoc();
        return $this->num_rows === 1 ? $this->getStatement()->fetch() : $this->getStatement()->fetchAll();
    }

    public function asAssoc(): self
    {
        $this->getStatement()->setFetchMode(\PDO::FETCH_ASSOC);
        return $this;
    }

    public function toArray(): array
    {
        if($this->num_rows === 0){
            return [];
        }
        $this->asArray();
        return $this->num_rows === 1 ? $this->getStatement()->fetch() : $this->getStatement()->fetchAll();
    }

    public function asArray(): self
    {
        $this->getStatement()->setFetchMode(\PDO::FETCH_BOTH);
        return $this;
    }

    /**
     * @return object|array|null
     */
    public function toObject()
    {
        if($this->num_rows === 0){
            return null;
        }
        $this->asObject();
        return $this->num_rows === 1 ? $this->getStatement()->fetch() : $this->getStatement()->fetchAll();
    }

    public function asObject(): self
    {
        $this->getStatement()->setFetchMode(\PDO::FETCH_OBJ);
        return $this;
    }

    public function toLazy()
    {
        if($this->num_rows === 0){
            return null;
        }
        $this->asLazy();
        return $this->num_rows === 1 ? $this->getStatement()->fetch() : $this->getStatement()->fetchAll();
    }

    public function asLazy(): self
    {
        $this->getStatement()->setFetchMode(\PDO::FETCH_LAZY);
        return $this;
    }

    public function result()
    {
        if($this->num_rows <= 0){
            return null;
        }
        $res = $this->getStatement()->fetch();

        return $res !== FALSE ? $res : null;
    }

    public function row()
    {
        return $this->result();
    }

    /**
     * @return array|object[]|null
     */
    public function results(): ?array
    {
        if($this->num_rows <= 0){
            return null;
        }
        $res = $this->getStatement()->fetchAll();

        return $res !== FALSE ? $res : null;
    }

    public function rows(): ?array
    {
        return $this->results();
    }


}
