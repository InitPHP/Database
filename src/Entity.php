<?php
/**
 * Entity.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use InitPHP\Database\Interfaces\EntityInterface;

use function substr;
use function str_ends_with;
use function method_exists;
use function explode;
use function ucfirst;
use function array_search;
use function preg_split;
use function strtolower;
use function lcfirst;

class Entity implements EntityInterface
{

    protected array $DBAttributes = [];

    protected array $DBCamelCaseAttributeNames = [];

    protected array $DBOriginalAttributes = [];

    public function __construct(?array $data = null)
    {
        $this->setUp($data);
    }

    public function __call($name, $arguments)
    {
        if(str_ends_with($name, 'Attribute') === FALSE){
            throw new \RuntimeException('There is no ' . $name . ' method.');
        }
        $startWith = substr($name, 0, 3);
        if($startWith === 'get' && empty($arguments)){
            $key = $this->camelCaseAttributeNameNormalize(substr($name, 3, -9));
            return $this->DBAttributes[$key] ?? null;
        }
        if($startWith === 'set'){
            $key = $this->camelCaseAttributeNameNormalize(substr($name, 3, -9));
            $this->DBAttributes[$key] = $arguments[0];
            return $this;
        }
        throw new \RuntimeException('There is no ' . $name . ' method.');
    }

    public function __set($key, $value)
    {
        $attrName = $this->attributeName($key);
        $method = 'set'.$attrName.'Attribute';
        return $this->DBAttributes[$key] = method_exists($this, $method) ? $this->{$method}($value) : $value;
    }

    public function __get($key)
    {
        $attrName = $this->attributeName($key);
        $method = 'get'.$attrName.'Attribute';
        $value = $this->DBAttributes[$key] ?? ($this->DBAttributes[$attrName] ?? null);
        if(method_exists($this, $method)){
            return $this->{$method}($value);
        }
        return $value;
    }

    public function __isset($key)
    {
        return isset($this->DBAttributes[$key]);
    }

    public function __unset($key)
    {
        if(isset($this->DBAttributes[$key])){
            unset($this->DBAttributes[$key]);
        }
    }

    public function __debugInfo()
    {
        return $this->DBAttributes;
    }

    public final function getAttributes(): array
    {
        return $this->DBAttributes;
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
        $this->DBOriginalAttributes = $this->DBAttributes;
        return $this;
    }

    private function attributeName(string $key): string
    {
        if(isset($this->DBCamelCaseAttributeNames[$key])){
            return $this->DBCamelCaseAttributeNames[$key];
        }
        $attrName = '';
        $parse = explode('_', $key);
        foreach ($parse as $col) {
            $attrName .= ucfirst($col);
        }
        return $this->DBCamelCaseAttributeNames[$key] = $attrName;
    }

    private function camelCaseAttributeNameNormalize(string $name): string
    {
        if(($key = array_search($name, $this->DBCamelCaseAttributeNames, true)) !== FALSE){
            return $key;
        }
        $parse = preg_split('/(?=[A-Z])/', $name, -1, \PREG_SPLIT_NO_EMPTY);
        $key = '';
        foreach ($parse as $value) {
            $key .= '_' . strtolower($value);
        }
        return isset($this->DBAttributes[$key]) ? $key : lcfirst($name);
    }

}
