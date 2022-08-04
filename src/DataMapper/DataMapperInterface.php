<?php
/**
 * DataMapperInterface.php
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

namespace InitPHP\Database\DataMapper;

/**
 * @mixin \PDOStatement
 */
interface DataMapperInterface
{

    /**
     * @param string $sqlQuery
     * @return $this
     */
    public function prepare(string $sqlQuery): self;

    /**
     * @return string
     */
    public function lastSQL(): string;

    /**
     * @param $value
     * @return mixed
     */
    public function bind($value);

    /**
     * @param array $fields
     * @param bool $isSearch
     * @return $this
     */
    public function bindParameters(array $fields, bool $isSearch = false): self;

    /**
     * @return $this
     */
    public function asArray(): self;

    /**
     * @return $this
     */
    public function asAssoc(): self;

    /**
     * @param string $class
     * @return $this
     */
    public function asEntity(string $class): self;

    /**
     * @return $this
     */
    public function asObject(): self;

    /**
     * @return $this
     */
    public function asLazy(): self;

    /**
     * @return int
     */
    public function numRows(): int;

    /**
     * @return bool
     */
    public function execute(): bool;

    /**
     * @return array|\InitPHP\Database\Entity|object|null
     */
    public function result();

    /**
     * @return \InitPHP\Database\Entity[]|string[]|object[]|array
     */
    public function results(): array;

    /**
     * @return int|null
     */
    public function getLastID(): ?int;

    /**
     * @param array ...$parameters
     * @return array
     */
    public function buildQueryParameters(array ...$parameters): array;

    /**
     * @param string $sqlQuery
     * @param array $parameters
     * @return bool
     */
    public function persist(string $sqlQuery, array $parameters): bool;

    /**
     * @return \PDOStatement|null
     */
    public function getStatement(): ?\PDOStatement;

    /**
     * @param \PDOStatement $statement
     * @return $this
     */
    public function setStatement(\PDOStatement $statement): self;

}
