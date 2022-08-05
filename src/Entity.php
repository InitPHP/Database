<?php
/**
 * Entity.php
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

namespace InitPHP\Database;

class Entity
{

    protected array $_Attributes = [];

    protected array $_OriginalAttributes = [];

    public function __construct(?array $data = null)
    {
        $this->setUp($data);
    }

    public function __call($name, $arguments)
    {
        if(Helper::str_ends_with($name, 'Attribute') === FALSE){
            throw new \RuntimeException('There is no ' . $name . ' method.');
        }
        $startWith = \substr($name, 0, 3);
        if($startWith === 'get'){
            $attrCamelCase = \substr($name, 3, -9);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            return $this->_Attributes[$attributeName] ?? null;
        }
        if($startWith === 'set'){
            $attrCamelCase = \substr($name, 3, -9);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->_Attributes[$attributeName] = ($arguments[0] ?? '');
            return $this;
        }
        throw new \RuntimeException('There is no ' . $name . ' method.');
    }

    public function __set($key, $value)
    {
        $attrName = Helper::attributeNameCamelCaseEncode($key);
        $method = 'set' . $attrName . 'Attribute';
        if(\method_exists($this, $method)){
            $this->{$method}($value);
            return $value;
        }
        return $this->_Attributes[$key] = $value;
    }

    public function __get($key)
    {
        $attrName = Helper::attributeNameCamelCaseEncode($key);
        $method = 'get' . $attrName . 'Attribute';
        if(\method_exists($this, $method)){
            return $this->{$method}();
        }
        return $this->_Attributes[$key] ?? null;
    }

    public function __isset($key)
    {
        return isset($this->_Attributes[$key]);
    }

    public function __unset($key)
    {
        if(isset($this->_Attributes[$key])){
            unset($this->_Attributes[$key]);
        }
    }

    public function __debugInfo()
    {
        return $this->_Attributes;
    }

    public final function getAttributes(): array
    {
        return $this->_Attributes;
    }

    protected function setUp(?array $data = null): void
    {
        $this->syncOriginal();
        $this->fill($data);
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
        $this->_OriginalAttributes = $this->_Attributes;
        return $this;
    }

}
