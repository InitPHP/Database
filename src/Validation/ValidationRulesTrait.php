<?php
/**
 * ValidationRulesTrait.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.9
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Validation;

use InitPHP\Database\Exceptions\ValidationException;
use InitPHP\Database\Helper;

trait ValidationRulesTrait
{

    public function is_unique($data, $column, $schemaID = null): bool
    {
        if($this->db->getSchemaID() === null){
            throw new ValidationException('You need a model with a PRIMARY KEY to use the is_unique validation.');
        }
        $queryBuilder = $this->db->getQueryBuilder();
        $query = clone $queryBuilder;
        $query->reset();

        $query->offset(0)->limit(1);
        if(!empty($schemaID)){
            $query->where($this->db->getSchemaID(), $schemaID, '!=');
        }
        $query->where($column, $data, '=');
        $dataMapper = $this->db->getDataMapper();
        $mapper = clone $dataMapper;
        $mapper->getParameters(); // Prev parameters reset.
        $mapper->persist($query->readQuery(), []);
        return $mapper->numRows() < 1;
    }

    protected function required($data): bool
    {
        if(!\is_iterable($data) && !\is_object($data)){
            $data = \trim((string)$data);
        }
        return !empty($data);
    }

    protected function string($data): bool
    {
        return \is_string($data);
    }

    protected function int($data): bool
    {
        return (bool)\preg_match('/^([+|\-]*)[0-9]+$/', (string)$data);
    }

    protected function float($data): bool
    {
        return (bool)\preg_match('/^[+|\-]*[0-9]+[.]+[0-9]+$/', (string)$data);
    }

    protected function numeric($data):bool
    {
        return (bool)\preg_match('/^[+|\-]*[0-9]+([.]+[0-9]+)*$/', (string)$data);
    }

    protected function alpha($data): bool
    {
        $pattern = '[a-zA-ZğĞŞşÜüİıÖöÇç]';
        return (bool)\preg_match('/^' . $pattern . '$/', (string)$data);
    }

    protected function alphaNumeric($data): bool
    {
        $pattern = '(?:([+|\-]*[0-9]+([.]+[0-9]+)*)|([0-9a-zA-ZğĞŞşÜüİıÖöÇç]))+';
        return (bool)\preg_match('/^' . $pattern . '$/', (string)$data);
    }

    protected function boolean($data): bool
    {
        return (bool)\preg_match('/^(true|false|0|1)]+$/', (string)$data);
    }

    protected function mail($data): bool
    {
        return (bool)\filter_var($data, \FILTER_VALIDATE_EMAIL);
    }

    protected function url($data): bool
    {
        return (bool)\filter_var($data, \FILTER_VALIDATE_URL);
    }

    protected function min($data, $min = 0): bool
    {
        return $data >= $min;
    }

    protected function max($data, $max = 1): bool
    {
        return $data <= $max;
    }

    protected function length($data, $min = 0, $max = null): bool
    {
        $len = \strlen($data);
        $res = $len >= $min;
        if($res !== FALSE && $max !== null){
            return $len <= $max;
        }
        return $res;
    }

    protected function range($data, $min = \PHP_INT_MIN, $max = \PHP_INT_MAX)
    {
        return $this->min($data, $min) && $this->max($data, $max);
    }

    protected function regex($data, $pattern): bool
    {
        return (bool)\preg_match($pattern, (string)$data);
    }

    protected function ip($data): bool
    {
        return (bool)\filter_var($data, \FILTER_VALIDATE_IP);
    }

    protected function ipv4($data): bool
    {
        return (bool)\filter_var($data, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4);
    }

    protected function ipv6($data): bool
    {
        return (bool)\filter_var($data, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6);
    }

    protected function equal($data, $assert): bool
    {
        return $data == $assert;
    }

    protected function startWith($data, $with): bool
    {
        return Helper::str_starts_with($data, $with);
    }

    protected function endWith($data, $with): bool
    {
        return Helper::str_ends_with($data, $with);
    }

    protected function strContains($data, $search): bool
    {
        return Helper::str_contains($data, $search);
    }

    protected function only($data, ...$only): bool
    {
        foreach ($only as $row) {
            if($row == $data){
                return true;
            }
        }
        return false;
    }

}
