<?php
/**
 * Helper.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.8
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
use function str_contains;
use function implode;
use function ctype_lower;

final class Helper
{

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
        if(str_contains($value, ' ')){
            $parse = explode(' ', $value, 2);
            $res = [];
            foreach ($parse as $val) {
                $res[] = self::sqlDriverQuotesStructure($val, $driver);
            }
            return implode(' ', $res);
        }
        if(str_contains($value, '.')){
            $parse = explode('.', $value, 2);
            $res = [];
            foreach ($parse as $val) {
                $res[] = self::sqlDriverQuotesStructure($val, $driver);
            }
            return implode('.', $res);
        }
        return match ($driver) {
            'mysql' => '`' . $value . '`',
            'pgsql', 'postgres', 'postgresql' => !ctype_lower($value) ? '"' . $value . '"' : $value,
            'sqlite' => '"' . $value . '"',
            default => $value,
        };
    }



}
