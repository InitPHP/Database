<?php

namespace InitPHP\Database\QueryBuilder;

use Closure;

class RawQuery
{

    private string $raw;

    public function __construct(mixed $rawQuery)
    {
        $this->set($rawQuery);
    }

    public function __toString(): string
    {
        return $this->get();
    }

    public function set(mixed $rawQuery): self
    {
        if (is_string($rawQuery)) {
            $this->raw = $rawQuery;
        } else if ($rawQuery instanceof Closure) {
            $builder = new QueryBuilder();
            $res = call_user_func_array($rawQuery, [&$builder]);
            if (is_string($res)) {
                $this->raw = $res;
            } else if (is_object($res) && method_exists($res, '__toString')) {
                $this->raw = $res->__toString();
            } else {
                $this->raw = $builder->__toString();
            }
        } else {
            $this->raw = (string)$rawQuery;
        }

        return $this;
    }

    public function get(): string
    {
        return $this->raw ?? '';
    }

    public static function raw($rawQuery): RawQuery
    {
        return new self($rawQuery);
    }

}
