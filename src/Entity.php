<?php
/**
 * Entity.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.13
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use InitPHP\Database\Interfaces\EntityInterface;

use function substr;
use function method_exists;

class Entity implements EntityInterface
{

    protected array $DBAttributes = [];

    protected array $DBOriginalAttributes = [];

    public function __construct(?array $data = null)
    {
        $this->setUp($data);
    }

    public function __call($name, $arguments)
    {
        if(Helper::strEndsWith($name, 'Attribute') === FALSE){
            throw new \RuntimeException('There is no ' . $name . ' method.');
        }
        $startWith = substr($name, 0, 3);
        if($startWith === 'get'){
            $attrCamelCase = substr($name, 3, -9);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            return $this->DBAttributes[$attributeName] ?? null;
        }
        if($startWith === 'set'){
            $attrCamelCase = substr($name, 3, -9);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->DBAttributes[$attributeName] = ($arguments[0] ?? '');
            return $this;
        }
        throw new \RuntimeException('There is no ' . $name . ' method.');
    }

    public function __set($key, $value)
    {
        $attrName = Helper::attributeNameCamelCaseEncode($key);
        $method = 'set' . $attrName . 'Attribute';
        if(method_exists($this, $method)){
            $this->{$method}($value);
            return $value;
        }
        return $this->DBAttributes[$key] = $value;
    }

    public function __get($key)
    {
        $attrName = Helper::attributeNameCamelCaseEncode($key);
        $method = 'get' . $attrName . 'Attribute';
        if(method_exists($this, $method)){
            return $this->{$method}();
        }
        return $this->DBAttributes[$key] ?? null;
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

}
