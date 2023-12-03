<?php

namespace InitPHP\Database\DBAL\Interfaces;

use Closure;
use InitPHP\Database\Connection\Exceptions\ConnectionException;
use InitPHP\Database\Connection\Interfaces\ConnectionInterface;
use InitPHP\Database\DBAL\Exceptions\SQLQueryExecuteException;
use InitPHP\Database\QueryBuilder\Interfaces\QueryBuilderInterface;

/**
 * @mixin QueryBuilderInterface
 * @mixin ConnectionInterface
 */
interface DatabaseInterface
{

    /**
     * @return DatabaseInterface
     */
    public function enableQueryLog(): DatabaseInterface;

    /**
     * @return DatabaseInterface
     */
    public function disableQueryLog(): DatabaseInterface;

    /**
     * @return array
     */
    public function getQueryLogs(): array;

    /**
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder(): QueryBuilderInterface;

    /**
     * @return string[]
     */
    public function getErrors(): array;

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface;

    /**
     * @return QueryBuilderInterface
     */
    public function builder(): QueryBuilderInterface;

    /**
     * @param ConnectionInterface|array $connectionOrCredentials
     * @param QueryBuilderInterface|null $builder
     * @return DatabaseInterface
     */
    public function newInstance(ConnectionInterface|array $connectionOrCredentials, ?QueryBuilderInterface $builder = null): DatabaseInterface;

    /**
     * @param string|null $table
     * @param array|null $selection
     * @param array|null $conditions
     * @return ResultInterface
     * @throws SQLQueryExecuteException
     */
    public function get(?string $table = null, ?array $selection = null, ?array $conditions = null): ResultInterface;

    /**
     * @param string $rawSQL
     * @param array|null $arguments
     * @param array|null $options
     * @return ResultInterface
     * @throws SQLQueryExecuteException
     */
    public function query(string $rawSQL, ?array $arguments = null, ?array $options = null): ResultInterface;

    /**
     * @return int
     * @throws SQLQueryExecuteException
     */
    public function count(): int;

    /**
     * @return int
     * @throws ConnectionException
     */
    public function insertId(): int;

    /**
     * @param Closure $closure
     * @param int $attempt
     * @param bool $testMode
     * @return bool
     * @throws ConnectionException
     */
    public function transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool;

}
