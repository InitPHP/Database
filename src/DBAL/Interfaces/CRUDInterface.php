<?php

namespace InitPHP\Database\DBAL\Interfaces;

use InitPHP\Database\DBAL\Exceptions\SQLQueryExecuteException;
use InitPHP\Database\QueryBuilder\Exceptions\QueryGeneratorException;

/**
 * @mixin DatabaseInterface
 */
interface CRUDInterface
{

    /**
     * @param string|null $table
     * @param array|null $set
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     */
    public function create(?string $table = null, ?array $set = null): bool;

    /**
     * @param string|null $table
     * @param array|null $set
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     */
    public function createBatch(?string $table = null, ?array $set = null): bool;

    /**
     * @param string|null $table
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return ResultInterface
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     */
    public function read(?string $table = null, array $selector = [], array $conditions = [], array $parameters = []): ResultInterface;

    /**
     * @param string|null $table
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return ResultInterface
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     */
    public function readOne(?string $table = null, array $selector = [], array $conditions = [], array $parameters = []): ResultInterface;

    /**
     * @param string|null $table
     * @param array|null $set
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     */
    public function update(?string $table = null, ?array $set = null): bool;

    /**
     * @param string|null $table
     * @param array|null $set
     * @param string|null $referenceColumn
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     */
    public function updateBatch(?string $table = null, ?array $set = null, ?string $referenceColumn = null): bool;

    /**
     * @param string|null $table
     * @param array|null $conditions
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     */
    public function delete(?string $table = null, ?array $conditions = []): bool;

}
