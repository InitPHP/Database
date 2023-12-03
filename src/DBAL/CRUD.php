<?php

namespace InitPHP\Database\DBAL;

use \InitPHP\Database\DBAL\Interfaces\{CRUDInterface, DatabaseInterface, ResultInterface};

class CRUD implements CRUDInterface
{

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = &$db;
    }

    public function __call(string $name, array $arguments)
    {
        $res = $this->db->{$name}(...$arguments);

        return ($res instanceof DatabaseInterface) ? $this : $res;
    }

    /**
     * @inheritDoc
     */
    public function create(?string $table = null, ?array $set = null): bool
    {
        $builder = $this->db->getQueryBuilder();

        !empty($set) && $builder->set($set);

        !empty($table) && $builder->from($table);

        $res = $this->db->query($builder->generateInsertQuery());
        $this->db->getQueryBuilder()->getParameter()->reset();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function createBatch(?string $table = null, ?array $set = null): bool
    {
        $builder = $this->db->getQueryBuilder();

        if (!empty($set)) {
            foreach ($set as $row) {
                !empty($row) && is_array($row)
                    && $builder->set($row);
            }
        }

        !empty($table) && $builder->from($table);

        $res = $this->db->query($builder->generateBatchInsertQuery());
        $this->db->getQueryBuilder()->resetStructure();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function read(?string $table = null, array $selector = [], array $conditions = [], array $parameters = []): ResultInterface
    {
        $builder = $this->db->getQueryBuilder();

        !empty($parameters) && $builder->getParameter()->merge($parameters);

        !empty($table) && $builder->from($table);


        $res = $this->db->query($builder->generateSelectQuery($selector, $conditions));
        $this->db->getQueryBuilder()->resetStructure();

        return $res;
    }

    /**
     * @inheritDoc
     */
    public function readOne(?string $table = null, array $selector = [], array $conditions = [], array $parameters = []): ResultInterface
    {
        $builder = $this->db->getQueryBuilder();

        !empty($parameters) && $builder->getParameter()->merge($parameters);

        !empty($table) && $builder->from($table);

        $res = $this->db->query($builder->limit(1)
            ->generateSelectQuery($selector, $conditions));

        $this->db->getQueryBuilder()->resetStructure();

        return $res;
    }

    /**
     * @inheritDoc
     */
    public function update(?string $table = null, ?array $set = null): bool
    {
        $builder = $this->db->getQueryBuilder();

        !empty($set) && $builder->set($set);
        !empty($table) && $builder->from($table);

        $res = $this->db->query($builder
            ->generateUpdateQuery());

        $this->db->getQueryBuilder()->resetStructure();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function updateBatch(?string $table = null, ?array $set = null, ?string $referenceColumn = null): bool
    {
        $builder = $this->db->getQueryBuilder();

        if (!empty($set)) {
            foreach ($set as $row) {
                if (!empty($row) && is_array($row)) {
                    $builder->set($row);
                }
            }
        }
        !empty($table) && $builder->from($table);

        $res = $this->db->query($builder
            ->generateUpdateBatchQuery($referenceColumn));

        $this->db->getQueryBuilder()->resetStructure();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function delete(?string $table = null, ?array $conditions = []): bool
    {
        $builder = $this->db->getQueryBuilder();

        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (is_string($column)) {
                    $builder->where($column, $value);
                } else {
                    $builder->where($value);
                }
            }
        }
        !empty($table) && $builder->from($table);

        $res = $this->db->query($builder->generateDeleteQuery());

        return $res->numRows() > 0;
    }

}
