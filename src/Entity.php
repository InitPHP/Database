<?php
/**
 * Entity
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.7
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

use InitPHP\Database\Helpers\Helper;

class Entity
{

    protected array $__attributes = [];

    protected array $__attributesOriginal = [];

    public function __construct(?array $data = [])
    {
        $this->setUp($data);
    }

    public function __call($name, $arguments)
    {
        if(Helper::str_ends_with($name, 'Attribute') === FALSE){
            throw new \RuntimeException('There is no "' . $name . '" method.');
        }
        switch (\substr($name, 0, 3)) {
            case 'get':
                $attr = Helper::camelCaseToSnakeCase(\substr($name, 3, -9));
                return $this->__attributes[$attr] ?? null;
            case 'set':
                $attr = Helper::camelCaseToSnakeCase(\substr($name, 3, -9));
                return $this->__attributes[$attr] = $arguments[0];
            default:
                throw new \RuntimeException('There is no "' . $name . '" method.');
        }
    }

    public function __set($name, $value)
    {
        $methodName = 'set' . Helper::snakeCaseToPascalCase($name) . 'Attribute';
        if(\method_exists($this, $methodName)){
            $this->{$methodName}($value);
            return $value;
        }
        return $this->__attributes[$name] = $value;
    }

    public function __get($name)
    {
        $methodName = 'get' . Helper::snakeCaseToPascalCase($name) . 'Attribute';
        if(\method_exists($this, $methodName)){
            return $this->{$methodName}();
        }
        return $this->__attributes[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->__attributes[$name]);
    }

    public function __unset($name)
    {
        if(isset($this->__attributes[$name])){
            unset($this->__attributes[$name]);
        }
    }

    public function __debugInfo()
    {
        return $this->__attributes;
    }

    public function toArray(): array
    {
        return $this->__attributes;
    }

    public function getAttributes(): array
    {
        return $this->toArray();
    }

    protected function setUp(?array $data = null): self
    {
        $this->syncOriginal()
            ->fill($data);
        return $this;
    }

    protected function fill(?array $data = null): self
    {
        if($data !== null){
            foreach ($data as $key => $value) {
                $this->__set($key, $value);
            }
        }
        return $this;
    }

    protected function syncOriginal(): self
    {
        $this->__attributesOriginal = $this->__attributes;
        return $this;
    }

}
