<?php
/**
 * Helper.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.10
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use function trim;
use function preg_split;
use function strtolower;
use function lcfirst;
use function ucfirst;
use function explode;
use function function_exists;
use function implode;
use function ctype_lower;

final class Helper
{

    public static function strContains(string $haystack, string $needle): bool
    {
        if(function_exists('str_contains')){
            return (bool) \str_contains($haystack, $needle);
        }
        return \stripos($haystack, $needle) !== FALSE;
    }

    public static function strStartsWith(string $haystack, string $needle): bool
    {
        if(function_exists('str_starts_with')){
            return (bool) \str_starts_with($haystack, $needle);
        }
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    public static function strEndsWith(string $haystack, string $needle): bool
    {
        if(function_exists('str_ends_with')){
            return (bool) \str_ends_with($haystack, $needle);
        }
        return substr($haystack, (0 - strlen($needle))) === $needle;
    }

    public static function attributeNameCamelCaseDecode(string $camelCase): string
    {
        $camelCase = lcfirst($camelCase); // İlk karakterden önce _ oluşmasını önlemek için.
        $parse = preg_split('/(?=[A-Z])/', $camelCase, -1, \PREG_SPLIT_NO_EMPTY);
        $key = '';
        $first = true;
        foreach ($parse as $value) {
            if(!$first){
                $key .= '_';
                $first = false;
            }
            $key .= strtolower($value);
        }
        return lcfirst($key);
    }

    public static function attributeNameCamelCaseEncode(string $attributeName): string
    {
        $attrName = '';
        $parse = explode('_', strtolower($attributeName));
        foreach ($parse as $col) {
            $attrName .= ucfirst($col);
        }
        return $attrName;
    }

    public static function sqlDriverQuotesStructure(string $value, string $driver): string
    {
        $value = trim($value);
        if(self::strContains($value, ' ')){
            $parse = explode(' ', $value, 2);
            $res = [];
            foreach ($parse as $val) {
                $res[] = self::sqlDriverQuotesStructure($val, $driver);
            }
            return implode(' ', $res);
        }
        if(self::strContains($value, '.')){
            $parse = explode('.', $value, 2);
            $res = [];
            foreach ($parse as $val) {
                $res[] = self::sqlDriverQuotesStructure($val, $driver);
            }
            return implode('.', $res);
        }
        switch (strtolower($driver)) {
            case 'mysql':
                return '`' . $value . '`';
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return !ctype_lower($value) ? '"' . $value . '"' : $value;
            case 'sqlite':
                return '"' . $value . '"';
            default:
                return $value;
        }
    }

}
