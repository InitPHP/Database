<?php

namespace InitPHP\Database\ORM\Interfaces;

use InitPHP\Database\DBAL\Exceptions\SQLQueryExecuteException;
use InitPHP\Database\DBAL\Interfaces\CRUDInterface;
use InitPHP\Database\DBAL\Interfaces\ResultInterface;
use InitPHP\Database\ORM\Exceptions\DeletableException;
use InitPHP\Database\ORM\Exceptions\ReadableException;
use InitPHP\Database\ORM\Exceptions\UpdatableException;
use InitPHP\Database\ORM\Exceptions\WritableException;
use InitPHP\Database\QueryBuilder\Exceptions\QueryGeneratorException;

/**
 * @mixin CRUDInterface
 */
interface ModelInterface
{
    /**
     * @param array $set
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws WritableException
     */
    public function create(array $set = []): bool;

    /**
     * @param array $set
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws WritableException
     */
    public function createBatch(array $set = []): bool;

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return ResultInterface
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws ReadableException
     */
    public function read(array $selector = [], array $conditions = [], array $parameters = []): ResultInterface;

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return ResultInterface
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws ReadableException
     */
    public function readOne(array $selector = [], array $conditions = [], array $parameters = []): ResultInterface;

    /**
     * @param array $set
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws UpdatableException
     */
    public function update(array $set = []): bool;

    /**
     * @param array $set
     * @param string|null $referenceColumn
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws UpdatableException
     */
    public function updateBatch(array $set = [], ?string $referenceColumn = null): bool;

    /**
     * @param array|null $conditions
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws DeletableException
     */
    public function delete(?array $conditions = null): bool;

    /**
     * @param EntityInterface $entity
     * @return bool
     * @throws SQLQueryExecuteException
     * @throws QueryGeneratorException
     * @throws WritableException
     * @throws UpdatableException
     */
    public function save(EntityInterface $entity): bool;

    /**
     * @return self
     */
    public function onlyDeleted(): self;

    /**
     * @return self
     */
    public function ignoreDeleted(): self;

}
