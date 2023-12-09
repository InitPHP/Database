<?php
/**
 * Utils/Datatables
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     3.0
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Utils;

use Closure;
use Throwable;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\QueryBuilderInterface;
use InitORM\ORM\Interfaces\ModelInterface;

/**
 * @mixin QueryBuilderInterface
 */
class Datatables
{

    private DatabaseInterface|ModelInterface $db;

    private array $request;

    private array $response = [
        'draw'              => 0,
        'recordsTotal'      => 0,
        'recordsFiltered'   => 0,
        'data'              => [],
        'post'              => [],
    ];

    private array $columns = [];

    private array $renders = [];

    private array $builder = [];

    private bool $orderByReset = true;

    private array $permanentSelect = [];

    public function __construct(DatabaseInterface|ModelInterface $db)
    {
        $this->request = array_merge($_GET ?? [], $_POST ?? []);

        if ($requestBody = @file_get_contents("php://input")) {
            if (is_array($jsonBody = json_decode($requestBody, true))) {
                $this->request = array_merge($this->request, $jsonBody);
            }
        }
        $this->db = $db;
    }

    public function __call(string $name, array $arguments)
    {
        $this->builder[] = [
            'method'            => $name,
            'arguments'         => $arguments,
        ];

        return $this;
    }

    /**
     * @return string
     * @throws Throwable
     */
    public function __toString(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function toArray(): array
    {
        $this->handle();
        return $this->response;
    }

    /**
     * @return $this
     * @throws Throwable
     */
    public function handle(): self
    {
        $this->filterQuery();

        $totalRow = $this->getCount();

        $res = $this->orderQuery()
            ->limitQuery()
            ->getResults();

        if (!empty($this->renders)) {
            foreach ($res as &$row) {
                foreach ($row as $column => &$value) {
                    if (!isset($this->renders[$column])) {
                        continue;
                    }
                    $value = call_user_func_array($this->renders[$column], [$value, &$row]);
                }
            }
        }

        $this->response = [
            'draw'              => $this->request['draw'] ?? 0,
            'recordsTotal'      => $totalRow,
            'recordsFiltered'   => $totalRow,
            'data'              => $res,
            'post'              => $this->request,
        ];

        return $this;
    }

    /**
     * @param string ...$select
     * @return $this
     */
    public function addPermanentSelect(string ...$select): self
    {
        foreach ($select as $sel) {
            $this->select($sel);
            $this->permanentSelect[] = $sel;
        }

        return $this;
    }

    /**
     * @param string|null ...$columns
     * @return $this
     */
    public function setColumns(?string ...$columns): self
    {
        $dt = count($this->columns) - 1;
        foreach ($columns as $column) {
            $this->columns[] = [
                'db'    => $column,
                'dt'    => ++$dt,
            ];
        }

        return $this;
    }

    /**
     * @param string $column
     * @param Closure $render
     * @return $this
     */
    public function addRender(string $column, Closure $render): self
    {
        $this->renders[$column] = $render;

        return $this;
    }

    /**
     * @return $this
     */
    public function orderBySave(): self
    {
        $this->orderByReset = false;

        return $this;
    }

    /**
     * @return int
     * @throws Throwable
     */
    private function getCount(): int
    {
        if (!empty($this->permanentSelect)) {
            foreach ($this->permanentSelect as $select) {
                $this->db->select($select);
            }
        }
        $isGroupBy = false;
        foreach ($this->builder as $process) {
            if (str_starts_with($process['method'], 'select') || str_starts_with($process['method'], 'orderBy')) {
                continue;
            }
            if ($process['method'] === 'groupBy') {
                if ($isGroupBy === false) {
                    $this->db->selectCountDistinct(current($process['arguments']), 'data_length');
                    $isGroupBy = true;
                }
                continue;
            }
            $this->db->{$process['method']}(...$process['arguments']);
        }
        $isGroupBy === false && $this->db->selectCount('*', 'data_length');
        $res = $this->db->read();

        return $res->numRows() > 0 ? $res->asAssoc()->row()['data_length'] : 0;
    }

    /**
     * @return array
     * @throws Throwable
     */
    private function getResults(): array
    {
        foreach ($this->builder as $process) {
            $this->db->{$process['method']}(...$process['arguments']);
        }
        $res = $this->db->read();

        return $res->numRows() > 0 ? $res->asAssoc()->rows() : [];
    }

    /**
     * @return self
     */
    private function orderQuery(): self
    {
        if (empty($this->request['order']) || !is_array($this->request['order'])) {
            return $this;
        }
        if ($this->orderByReset) {
            foreach ($this->builder as $key => $builder) {
                if (str_starts_with($builder['method'], 'orderBy')) {
                    unset($this->builder[$key]);
                }
            }
        }
        $columns = $this->columns;
        $count = count($this->request['order']);
        for ($i = 0; $i < $count; ++$i) {
            $columnId = intval($this->request['order'][$i]['column']);
            $column = $columns[$columnId];
            if (!isset($column['db'])) {
                continue;
            }
            $dir = strtolower($this->request['order'][$i]['dir']) === 'asc' ? 'ASC' : 'DESC';
            $this->db->orderBy($this->db->raw($column['db']), $dir);
        }

        return $this;
    }

    /**
     * @return void
     * @throws QueryBuilderException
     */
    private function filterQuery(): void
    {
        $search = $this->request['search']['value'] ?? null;
        if (empty($search) || empty($this->columns)) {
            return;
        }
        $this->db->group(function (QueryBuilderInterface $builder) use ($search) {
            foreach ($this->columns as $column) {
                $builder->orLike($this->db->raw($column['db']), $search);
            }
            return $builder;
        });
    }

    private function limitQuery(): self
    {
        if (isset($this->request['start']) && $this->request['length'] != -1) {
            $this->offset($this->request['start'])
                ->limit($this->request['length']);
        }

        return $this;
    }

}
