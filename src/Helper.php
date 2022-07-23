<?php
/**
 * Helper.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

final class Helper
{

    public static function str_ends_with(string $haystack, string $needle): bool
    {
        if (\function_exists('str_ends_with')) {
            return \str_ends_with($haystack, $needle);
        }
        if($needle == ''){
            return true;
        }
        if($haystack == ''){
            return false;
        }
        $needle_len = \strlen($needle);
        $haystack_len = \strlen($haystack);
        if($haystack_len <= $needle_len){
            return $haystack == $needle;
        }
        return \substr($haystack, (0 - $needle_len)) == $needle;
    }

    public static function str_starts_with(string $haystack, string $needle): bool
    {
        if(\function_exists('str_starts_with')){
            return \str_starts_with($haystack, $needle);
        }
        if($needle == ''){
            return true;
        }
        if($haystack == ''){
            return false;
        }
        $needle_len = \strlen($needle);
        $haystack_len = \strlen($haystack);
        if($haystack_len <= $needle_len){
            return $haystack == $needle;
        }
        return \substr($haystack, 0, $needle_len) == $needle;
    }

    public static function str_contains(string $haystack, string $needle): bool
    {
        if(\function_exists('str_contains')){
            return \str_contains($haystack, $needle);
        }
        if($needle == ''){
            return true;
        }
        if($haystack == ''){
            return false;
        }
        return \strpos($haystack, $needle) !== FALSE;
    }

    public static function attributeNameCamelCaseDecode(string $camelCase): string
    {
        $camelCase = \lcfirst($camelCase); // İlk karakterden önce _ oluşmasını önlemek için.
        $parse = \preg_split('/(?=[A-Z])/', $camelCase, -1, \PREG_SPLIT_NO_EMPTY);
        $key = '';
        $first = true;
        foreach ($parse as $value) {
            if(!$first){
                $key .= '_';
            }else{
                $first = false;
            }
            $key .= \strtolower($value);
        }
        return \lcfirst($key);
    }

    public static function attributeNameCamelCaseEncode(string $attributeName): string
    {
        $attrName = '';
        $parse = \explode('_', \strtolower($attributeName));
        foreach ($parse as $col) {
            $attrName .= \ucfirst($col);
        }
        return $attrName;
    }

}
