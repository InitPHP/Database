<?php

namespace InitPHP\Database\DBAL;

use InitPHP\Database\DBAL\Interfaces\ResultInterface;
use InitPHP\Database\ORM\Entity;
use PDO;
use PDOStatement;

class Result implements ResultInterface
{

    private PDOStatement $statement;

    private string $query;

    private int $numRows;

    public function __construct(PDOStatement $statement)
    {
        $this->setStatement($statement);
    }

    /**
     * @inheritDoc
     */
    public function setStatement(PDOStatement $statement): self
    {
        $this->statement = $statement;
        $this->query = $statement->queryString;
        $this->numRows = $statement->rowCount();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withStatement(PDOStatement $statement): self
    {
        return new self($statement);
    }

    /**
     * @inheritDoc
     */
    public function getStatement(): PDOStatement
    {
        return $this->statement;
    }

    /**
     * @inheritDoc
     */
    public function numRows(): int
    {
        return $this->numRows;
    }

    /**
     * @inheritDoc
     */
    public function query(): string
    {
        return $this->query;
    }

    /**
     * @inheritDoc
     */
    public function setFetchMode(int $mode): self
    {
        $this->getStatement()->setFetchMode($mode);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asClass(string $class = Entity::class): self
    {
        $this->getStatement()->setFetchMode(PDO::FETCH_CLASS, $class);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asObject(): self
    {
        $this->getStatement()->setFetchMode(PDO::FETCH_OBJ);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asAssoc(): self
    {
        $this->getStatement()->setFetchMode(PDO::FETCH_ASSOC);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asArray(): self
    {
        $this->getStatement()->setFetchMode(PDO::FETCH_BOTH);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asLazy(): self
    {
        $this->getStatement()->setFetchMode(PDO::FETCH_LAZY);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function row(): array|object|false
    {
        return $this->getStatement()->fetch();
    }

    /**
     * @inheritDoc
     */
    public function rows(): array|false
    {
        return $this->getStatement()->fetchAll();
    }

}
