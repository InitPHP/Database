<?php
/**
 * Utils/Datatables
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.6
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Utils;

use InitPHP\Database\Database;

final class Datatables
{

    public const GET_REQUEST = 0;

    public const POST_REQUEST = 1;

    private Database $db;

    private array $request = [];

    private array $columns = [];

    private int $total_row = 0;

    private int $total_filtered_row = 0;

    private array $results;

    private int $draw = 0;

    public function __construct(Database $db, $columns, $method = self::GET_REQUEST)
    {
        $this->db = $db;

        switch ($method) {
            case self::GET_REQUEST:
                $this->request = $_GET ?? [];
                break;
            case self::POST_REQUEST:
                $this->request = $_POST ?? [];
                break;
        }
        $this->columns = $columns;

        $this->total_row = $this->db->count();

        $this->filterQuery();
        $this->orderQuery();
        $this->total_filtered_row = $this->db->count();
        $this->limitQuery();
        $this->results = $this->db->get()->toArray();

        if(isset($this->request['draw'])){
            $this->draw = (int)$this->request['draw'];
        }
    }

    public function __toString(): string
    {
        return \json_encode($this->getResults());
    }

    public function getResults(): array
    {
        return [
            'draw'              => $this->draw,
            'recordsTotal'      => $this->total_row,
            'recordsFiltered'   => $this->total_filtered_row,
            'data'              => $this->output_prepare()
        ];
    }


    private function orderQuery()
    {
        $columns = $this->columns;
        if(!isset($this->request['order'])){
            return;
        }
        $count = \count($this->request['order']);
        $dtColumns = $this->pluck($columns, 'dt');
        for($i = 0; $i < $count; ++$i){
            $columnId = \intval($this->request['order'][$i]['column']);
            $reqColumn = $this->request['columns'][$columnId];
            $columnId = \array_search($reqColumn['data'], $dtColumns);
            $column = $columns[$columnId];
            if(($reqColumn['orderable'] ?? 'false') != 'true' || !isset($column['db'])){
                continue;
            }
            $dir = ($this->request['order'][$i]['dir'] ?? 'asc') === 'asc' ? 'ASC' : 'DESC';
            $this->db->orderBy($column['db'], $dir);
        }
    }

    private function filterQuery()
    {
        $columns = $this->columns;
        $dtColumns = $this->pluck($columns, 'dt');
        $str = $this->request['search']['value'] ?? '';
        if($str === '' || !isset($this->request['columns'])){
            return;
        }
        $columnsCount = \count($this->request['columns']);
        $this->db->group(function (Database $db) use ($str, $dtColumns, $columns, $columnsCount) {
            for ($i = 0; $i < $columnsCount; ++$i) {
                $reqColumn = $this->request['columns'][$i];
                $columnId = \array_search($reqColumn['data'], $dtColumns);
                $column = $columns[$columnId];
                if(empty($column['db'])){
                    continue;
                }
                $db->orLike($column['db'], $str);
            }
        });
        if(isset($this->request['columns'])){
            for ($i = 0; $i < $columnsCount; ++$i) {
                $reqColumn = $this->request['columns'][$i];
                $columnId = \array_search($reqColumn['data'], $dtColumns);
                $column = $columns[$columnId];
                $str = $reqColumn['search']['value'] ?? '';
                if(($reqColumn['searchable'] ?? 'false') != 'true' || $str == '' || empty($column['db'])){
                    continue;
                }
                $this->db->like($column['db'], $str);
            }
        }
    }

    private function limitQuery()
    {
        if(isset($this->request['start']) && $this->request['length'] != -1){
            $this->db->offset((int)$this->request['start'])
                ->limit((int)$this->request['length']);
        }
    }

    private function output_prepare(): array
    {
        $out = [];
        $columns = $this->columns;
        $data = $this->results;
        $dataCount = \count($data);
        $columnCount = \count($columns);

        for ($i = 0; $i < $dataCount; ++$i) {
            $row = [];

            for ($y = 0; $y < $columnCount; ++$y) {
                $column = $columns[$y];
                if(isset($column['formatter'])){
                    $row[$column['dt']] = \call_user_func_array($column['formatter'], (empty($column['db']) ? [$data[$i]] : [$data[$i][$column['db']], $data[$i]]));
                }else{
                    $row[$column['dt']] = !empty($column['db']) ? $data[$i][$column['db']] : '';
                }
            }

            $out[] = $row;
        }


        return $out;
    }

    private function pluck(array $array, string $prop): array
    {
        $out = [];
        $len = \count($array);
        for ($i = 0; $i < $len; ++$i) {
            if(empty($array[$i][$prop])){
                continue;
            }
            $out[$i] = $array[$i][$prop];
        }
        return $out;
    }

}
