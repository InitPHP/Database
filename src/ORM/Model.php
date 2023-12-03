<?php

namespace InitPHP\Database\ORM;

use \InitPHP\Database\DBAL\Interfaces\{CRUDInterface, ResultInterface};
use \InitPHP\Database\ORM\Exceptions\{DeletableException, ModelException, ReadableException, UpdatableException, WritableException};
use \InitPHP\Database\ORM\Interfaces\{EntityInterface, ModelInterface};
use InitPHP\Database\Facade\DB;
use InitPHP\Database\Utils\Helper;
use ReflectionClass;
use ReflectionException;

abstract class Model implements ModelInterface
{

    protected CRUDInterface $crud;

    protected ?array $credentials = null;

    protected string $entity = Entity::class;

    protected string $schema;

    protected ?string $schemaId = null;

    protected bool $readable = true;

    protected bool $writable = true;

    protected bool $deletable = true;

    protected bool $updatable = true;

    protected ?string $createdField = 'created_at';

    protected ?string $updatedField = 'updated_at';

    protected ?string $deletedField = 'deleted_at';

    protected bool $useSoftDeletes = false;

    private bool $isOnlyDelete = false;

    protected string $timestampFormat = 'Y-m-d H:i:s';

    /**
     * @throws ModelException
     * @throws ReflectionException
     */
    public function __construct()
    {
        if (!isset($this->schema)) {
            $modelClass = get_called_class();
            $modelReflection = new ReflectionClass($modelClass);
            $this->schema = Helper::camelCaseToSnakeCase($modelReflection->getShortName());
            unset($modelClass, $modelReflection);
        }
        if ($this->useSoftDeletes !== false && empty($this->deletedField)) {
            throw new ModelException('There must be a delete column to use soft delete.');
        }

        $this->crud = empty($this->credentials) ? DB::getDatabase() : DB::connect($this->credentials);
    }

    public function __call(string $name, array $arguments)
    {
        $res = $this->crud->{$name}(...$arguments);

        return ($res instanceof CRUDInterface) ? $this : $res;
    }

    /**
     * @inheritDoc
     */
    public function create(array $set = []): bool
    {
        if (!$this->writable) {
            throw new WritableException();
        }

        !empty($this->createdField) && $set[$this->createdField] = date($this->timestampFormat);

        return $this->crud->create($this->schema, $set);
    }

    /**
     * @inheritDoc
     */
    public function createBatch(array $set = []): bool
    {
        if (!$this->writable) {
            throw new WritableException();
        }
        $createdField = $this->createdField;
        if (!empty($createdField) && !empty($set)) {
            foreach ($set as &$row) {
                $row[$createdField] = date($this->timestampFormat);
            }
        }

        return $this->crud->createBatch($this->schema, $set);
    }

    /**
     * @inheritDoc
     */
    public function read(array $selector = [], array $conditions = [], array $parameters = []): ResultInterface
    {
        if (!$this->readable) {
            throw new ReadableException();
        }
        if ($this->useSoftDeletes) {
            if ($this->isOnlyDelete) {
                $this->onlyDeleted();
            } else {
                $this->ignoreDeleted();
            }
            $this->isOnlyDelete = false;
        }

        return $this->crud
            ->read($this->schema, $selector, $conditions, $parameters)
            ->asClass($this->entity);
    }

    /**
     * @inheritDoc
     */
    public function readOne(array $selector = [], array $conditions = [], array $parameters = []): ResultInterface
    {
        if (!$this->readable) {
            throw new ReadableException();
        }
        if ($this->useSoftDeletes) {
            if ($this->isOnlyDelete) {
                $this->onlyDeleted();
            } else {
                $this->ignoreDeleted();
            }
            $this->isOnlyDelete = false;
        }

        return $this->crud
            ->readOne($this->schema, $selector, $conditions, $parameters)
            ->asClass($this->entity);
    }

    /**
     * @inheritDoc
     */
    public function update(array $set = []): bool
    {
        if (!$this->updatable) {
            throw new UpdatableException();
        }

        if (!empty($this->schemaId) && isset($set[$this->schemaId])) {
            $this->where($this->schemaId, $set[$this->schemaId]);
            unset($set[$this->schemaId]);
        }

        !empty($this->updatedField) && $set[$this->updatedField] = date($this->timestampFormat);

        $this->ignoreDeleted();

        return $this->crud->update($this->schema, $set);
    }

    /**
     * @inheritDoc
     */
    public function updateBatch(array $set = [], ?string $referenceColumn = null): bool
    {
        if (!$this->updatable) {
            throw new UpdatableException();
        }
        $updatedField = $this->updatedField;
        if (!empty($updatedField) && !empty($set)) {
            foreach ($set as &$row) {
                $row[$updatedField] = date($this->timestampFormat);
            }
        }
        $this->ignoreDeleted();

        return $this->crud->updateBatch($this->schema, $set, $referenceColumn ?? $this->schemaId);
    }

    /**
     * @inheritDoc
     */
    public function delete(?array $conditions = null, bool $purge = false): bool
    {
        if (!$this->deletable) {
            throw new DeletableException();
        }
        if ($this->useSoftDeletes && $purge === false) {
            $this->ignoreDeleted()
                ->set($this->deletedField, date($this->timestampFormat));

            if (!empty($conditions)) {
                foreach ($conditions as $column => $value) {
                    if (is_string($column)) {
                        $this->crud->getQueryBuilder()->where($column, $value);
                    } else {
                        $this->crud->getQueryBuilder()->where($value);
                    }
                }
            }

            return $this->crud->update($this->schema);
        }

        return $this->crud->delete($this->schema, $conditions);
    }

    /**
     * @inheritDoc
     */
    public function save(EntityInterface $entity): bool
    {
        $data = $entity->toArray();

        return !empty($this->schemaId) && isset($data[$this->schemaId]) ? $this->update($data) : $this->create($data);
    }

    /**
     * @inheritDoc
     */
    public function ignoreDeleted(): self
    {
        $this->useSoftDeletes && $this->crud->getQueryBuilder()
            ->whereIsNull($this->deletedField);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function onlyDeleted(): self
    {
        $this->useSoftDeletes && $this->crud->getQueryBuilder()
            ->whereIsNotNull($this->deletedField);

        return $this;
    }

}
