<?php
/**
 * Validation.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.8
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Validation;

use InitPHP\Database\DB;
use InitPHP\Database\Exceptions\ValidationException;
use InitPHP\Database\Helper;

class Validation
{

    use ValidationRulesTrait;

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
        'is_unique'         => '{field} must be unique.'
    ];

    private array $data = [];

    private DB $db;

    private array $methods = [];

    private array $messages = [];

    private array $labels = [];

    private string $error;

    public function __construct(array $methods, array $messages, array $labels, DB $db)
    {
        $this->methods = $methods;
        $this->messages = $messages;
        $this->labels = $labels;
        $this->db = $db;
    }

    public function setData(array $fields, array $parameters = [])
    {
        $this->data = $fields;
        foreach ($parameters as $key => $value) {
            $id = \array_search($key, $this->data);
            if($id === FALSE){
                continue;
            }
            $this->data[$id] = $value;
        }
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

        $data = $this->data[$column] ?? null;
        if(Helper::isSQLParameterOrFunction($data)){
            $parameters = $this->db->getDataMapper()->getParameters(false);
            $data = $parameters[$data];
        }
        if(($key = \array_search('is_unique', $methods, true)) !== FALSE){
            if($this->is_unique($data, $column, $schemaID) === FALSE){
                $this->error = $this->get_message($column, 'is_unique');
                return false;
            }
            unset($methods[$key]);
        }
        if(($key = \array_search('optional', $methods, true)) !== FALSE){
            if($data === null){
                return true;
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
