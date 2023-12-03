<?php

namespace InitPHP\Database\DBAL\Interfaces;

use InitPHP\Database\ORM\Entity;
use PDOStatement;

interface ResultInterface
{

    /**
     * @param PDOStatement $statement
     * @return self
     */
    public function setStatement(PDOStatement $statement): self;

    /**
     * @param PDOStatement $statement
     * @return self
     */
    public function withStatement(PDOStatement $statement): self;

    /**
     * @return PDOStatement
     */
    public function getStatement(): PDOStatement;

    /**
     * @return int
     */
    public function numRows(): int;

    /**
     * @return string
     */
    public function query(): string;

    /**
     * @param int $mode <p>\PDO::FETCH_*</p>
     * @return self
     */
    public function setFetchMode(int $mode): self;

    /**
     * @param string $class
     * @return self
     */
    public function asClass(string $class = Entity::class): self;

    /**
     * @return self
     */
    public function asObject(): self;


    /**
     * @return self
     */
    public function asAssoc(): self;

    /**
     * @return self
     */
    public function asArray(): self;

    /**
     * @return self
     */
    public function asLazy(): self;

    /**
     * @return array|object|false
     */
    public function row(): array|object|false;

    /**
     * @return array[]|object[]|false
     */
    public function rows(): array|false;


}