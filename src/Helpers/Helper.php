<?php
/**
 * Helpers/Helper
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.1
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Helpers;

use InitPHP\Database\Exceptions\ModelRelationsException;
use InitPHP\Database\Model;
use InitPHP\Database\Raw;

final class Helper
{

    private static array $modelInstance = [];

    public static function str_starts_with(string $haystack, string $needle): bool
    {
        if(\function_exists('str_starts_with')){
            return \str_starts_with($haystack, $needle);
        }
        return 0 === \strncmp($haystack, $needle, \strlen($needle));
    }

    public static function str_contains(string $haystack, string $needle): bool
    {
        if(\function_exists('str_contains')){
            return \str_contains($haystack, $needle);
        }
        return $needle === '' || FALSE !== \strpos($haystack, $needle);
    }

    public static function str_ends_with(string $haystack, string $needle): bool
    {
        if(\function_exists('str_ends_with')){
            return \str_ends_with($haystack, $needle);
        }
        if($needle === '' || $needle === $haystack){
            return true;
        }
        if($haystack === ''){
            return false;
        }
        $need_len = \strlen($needle);

        return $need_len <= \strlen($haystack) && 0 === \substr_compare($haystack, $needle, (0 - $need_len));
    }

    public static function isSQLParameterOrFunction($value): bool
    {
        return ((\is_string($value)) && (
                $value === '?'
                || (bool)\preg_match('/^:[\w]+$/', $value)
                || (bool)\preg_match('/^[a-zA-Z_]+[\.]+[a-zA-Z_]+$/', $value)
                || (bool)\preg_match('/^[a-zA-Z_]+\(\)$/', $value)
            )) || ($value instanceof Raw) || \is_int($value);
    }

    public static function isSQLParameter($value): bool
    {
        return (\is_string($value)) && ($value === '?' || (bool)\preg_match('/^:[\w]+$/', $value));
    }

    public static function camelCaseToSnakeCase($camelCase): string
    {
        $camelCase = \lcfirst($camelCase);
        $split = \preg_split('', $camelCase, -1, \PREG_SPLIT_NO_EMPTY);
        $snake_case = '';
        $i = 0;
        foreach ($split as $row) {
            $snake_case .= ($i === 0 ? '_' : '')
                . \strtolower($row);
            ++$i;
        }
        return \lcfirst($snake_case);
    }

    public static function snakeCaseToCamelCase($snake_case): string
    {
        return \lcfirst(self::snakeCaseToPascalCase($snake_case));
    }

    public static function snakeCaseToPascalCase($snake_case): string
    {
        $split = \explode('_', \strtolower($snake_case));
        $camelCase = '';
        foreach ($split as $row) {
            $camelCase .= \ucfirst($row);
        }
        return $camelCase;
    }

    public static function getModelInstance($model): Model
    {
        if ($model instanceof Model) {
            $class = \get_class($model);
            return self::$modelInstance[$class] = $model;
        } elseif (\is_string($model) && \class_exists($model)) {
            if (isset(self::$modelInstance[$model])) {
                return self::$modelInstance[$model];
            }
        } else {
            throw new \InvalidArgumentException();
        }

        $reflection = new \ReflectionClass($model);
        if ($reflection->isSubclassOf(Model::class) === FALSE) {
            throw new ModelRelationsException('The target class must be a subclass of \\InitPHP\\Database\\Model.');
        }
        $class = $reflection->getName();

        return self::$modelInstance[$class] = $reflection->newInstance();
    }

}
