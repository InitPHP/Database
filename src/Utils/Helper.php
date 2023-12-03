<?php

namespace InitPHP\Database\Utils;

use const PREG_SPLIT_NO_EMPTY;

final class Helper
{
    public static function camelCaseToSnakeCase($camelCase): string
    {
        $camelCase = lcfirst($camelCase);
        $split = preg_split('', $camelCase, -1, PREG_SPLIT_NO_EMPTY);
        $snake_case = '';
        $i = 0;
        foreach ($split as $row) {
            $snake_case .= ($i === 0 ? '_' : '')
                . strtolower($row);
            ++$i;
        }
        return lcfirst($snake_case);
    }

    public static function snakeCaseToCamelCase($snake_case): string
    {
        return lcfirst(self::snakeCaseToPascalCase($snake_case));
    }

    public static function snakeCaseToPascalCase($snake_case): string
    {
        $split = explode('_', strtolower($snake_case));
        $camelCase = '';
        foreach ($split as $row) {
            $camelCase .= ucfirst($row);
        }
        return $camelCase;
    }
}
