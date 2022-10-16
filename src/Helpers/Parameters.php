<?php
/**
 * Helpers/Parameters
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Helpers;

final class Parameters
{

    private static array $parameters = [];

    public static function get(bool $reset = true): array
    {
        $parameters = self::$parameters;
        if($reset === TRUE){
            self::reset();
        }
        return $parameters;
    }

    public static function set(string $key, $value): void
    {
        $key = ':' . \ltrim(\str_replace('.', '', $key), ':');
        self::$parameters[$key] = $value;
    }

    public static function add(string $key, $value): string
    {
        $originKey = \ltrim(\str_replace('.', '', $key), ':');
        $i = 0;
        do{
            $key = ':' . ($i === 0 ? $originKey : $originKey . '_' . $i);
            ++$i;
            $hasParameter = isset(self::$parameters[$key]);
        }while($hasParameter);
        self::$parameters[$key] = $value;
        return $key;
    }

    public static function merge(...$array): void
    {
        self::$parameters = \array_merge(self::$parameters, ...$array);
    }

    public static function getParam(string $key)
    {
        return self::$parameters[$key] ?? $key;
    }

    public static function reset()
    {
        self::$parameters = [];
    }

}
