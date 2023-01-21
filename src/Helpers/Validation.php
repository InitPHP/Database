<?php
/**
 * Helpers/Validation
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.7
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Helpers;

use InitPHP\Database\Database;
use InitPHP\Database\Exceptions\ValidationException;

final class Validation
{

    protected const AVAILABLE_VALIDATION_METHODS = [
        'required', 'string', 'int', 'float', 'numeric',
        'alpha', 'alphaNumeric', 'boolean', 'mail', 'url',
        'min', 'max', 'length', 'range', 'regex',
        'ip', 'ipv4', 'ipv6', 'equal', 'startWith', 'endWith',
        'strContains', 'only',
    ];

    protected const DEFAULT_MESSAGES = [
        'required'          => '{field} cannot be empty.',
        'string'            => 'Must be {field} string.',
        'int'               => 'Must be {field} integer.',
        'float'             => 'Must be {field} float.',
        'unsigned'          => 'Must be {field} unsigned.',
        'numeric'           => 'Must be {field} numeric.',
        'alpha'             => 'Must be {field} alphabetical.',
        'alphaNumeric'      => 'Must be {field} alphanumeric.',
        'boolean'           => 'Must be {field} boolean.',
        'mail'              => 'Must be {field} mail.',
        'url'               => 'Must be {field} url.',
        'min'               => '{field} must be {1} or greater.',
        'max'               => '{field} must be {1} or less.',
        'length'            => '{field} can be minimum of {1} characters and a maximum of {2} characters.',
        'range'             => '{field} can be at least {1} and at most {2}.',
        'regex'             => '{field} is not in proper format/pattern.',
        'ip'                => 'It should be {field} IP Address.',
        'ipv4'              => '{field} should be an IPv4 Address.',
        'ipv6'              => '{field} should be an IPv6 Address.',
        'equal'             => '{field} can only be {1}',
        'startWith'         => '{field} must start with {1}',
        'endWith'           => '{field} must end with {1}',
        'strContains'       => 'Must contain {field}, {1}',
        'only'              => '{field} can only be; {arguments}',
        //'is_unique'         => '{field} must be unique.',
    ];

    private array $data = [];

    private array $methods = [];
    private array $labels = [];
    private array $messages = [];
    private string $error;

    private Database $db;

    public function __construct(array $methods, array $messages, array $labels, Database &$db)
    {
        $this->methods = $methods;
        $this->messages = $messages;
        $this->labels = $labels;
        $this->db = &$db;
    }

    public function setData($data, array $parameters = []): self
    {
        $this->data = $data;
        foreach ($parameters as $key => $value) {
            $id = \array_search($key, $this->data);
            if($id === FALSE){
                continue;
            }
            $this->data[$id] = $value;
        }
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error ?? null;
    }

    public function validation(string $column, $schemaID = null): bool
    {
        unset($this->error);
        if(!isset($this->methods[$column])){
            return true;
        }
        $methods = $this->methods[$column];
        if(\is_string($methods)){
            $methods = \explode('|', $methods);
        }

        if(($key = \array_search('optional', $methods, true)) !== FALSE){
            if(!isset($this->data[$column])){
                return true;
            }
            unset($methods[$key]);
        }

        $data = $this->data[$column] ?? null;

        if(Helper::isSQLParameter($data)){
            $data = Parameters::getParam($data);
        }
        if(($key = \array_search('is_unique', $methods, true)) !== FALSE){
            if($this->is_unique($data, $column, $schemaID) === FALSE){
                $this->error = $this->get_message($column, 'is_unique');
                return false;
            }
            unset($methods[$key]);
        }

        foreach ($methods as $method) {
            $method = $this->method_parse($method);
            if(!\in_array($method['method'], self::AVAILABLE_VALIDATION_METHODS)){
                throw new ValidationException('The validation method "' . $method['method'] . '" defined for column "' . $column . '" is not available.');
            }
            $arguments = $method['arguments'];
            \array_unshift($arguments, $data);
            $method = $method['method'];
            $res = $this->{$method}(...$arguments);
            if($res === FALSE){
                $this->error = $this->get_message($column, $method, $arguments);
                return false;
            }
        }
        return true;
    }

    protected function is_unique($data, $column, $schemaID = null): bool
    {
        if($this->db->getSchemaID() === null){
            throw new ValidationException('You need a model with a PRIMARY KEY to use the is_unique validation.');
        }
        $db = $this->db->newInstance();
        $db->reset();
        $parameters = Parameters::get(true);
        $db->select($db->getSchemaID())->offset(0)
            ->limit(1);
        if(!empty($schemaID)){
            $db->where($db->getSchemaID(), $schemaID, '!=');
        }
        $db->where($column, $data, '=');
        $res = $db->get($db->getSchema());
        unset($db);
        Parameters::merge($parameters);
        return $res->numRows() < 1;
    }

    protected function required($data): bool
    {
        if(!\is_iterable($data) && !\is_object($data)){
            $data = \trim((string)$data);
            return $data !== '';
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

    protected function unsigned($data): bool
    {
        return !((bool)\preg_match('/^[+|\-]+/', (string)$data));
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

    private function method_parse(string $str): array
    {
        $arguments = [];
        $method = $str;
        if(\preg_match('/([\w]+)\((.+)\)/', $str, $params)){
            $method = $params[1];
            if(isset($params[2])){
                $split = \explode(',', $params[2]);
                foreach ($split as $argument) {
                    $arguments[] = \trim(\trim($argument), '"\'');
                }
            }
        }

        return [
            'method'        => $method,
            'arguments'     => $arguments,
        ];
    }

    private function get_message(string $column, string $method, $arguments = []): string
    {
        $msg = $this->messages[$column][$method] ?? self::DEFAULT_MESSAGES[$method];
        $label = $this->labels[$column] ?? $column;

        $replace = [
            '{field}' => $label
        ];
        \array_shift($arguments);
        if(!empty($arguments)){
            $replace['{arguments}'] = \implode(', ', $arguments);
            $i = 1;
            foreach ($arguments as $argument) {
                $replace['{' . $i . '}'] = $argument;
                ++$i;
            }
        }
        return \strtr($msg, $replace);
    }


}
