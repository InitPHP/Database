<?php
/**
 * Helper.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.3
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

    public static function queryBindParameter($value, string $syntax = '{value}'): string
    {
        if(
            \is_string($value) && (
                $value == '?' ||
                (bool)\preg_match('/^:[\w]+$/', $value) ||
                (bool)\preg_match('/^[a-zA-Z\_]+\(\)$/', $value)
            )
        ){
            return $value;
        }
        if($value === null){
            return \str_replace('{value}', 'NULL', $syntax);
        }
        if($value === FALSE){
            $value = 0;
        }
        if(\is_bool($value) || \is_numeric($value)){
            return \str_replace('{value}', (string)$value, $syntax);
        }
        $value = \str_replace(['\\\"', '\\\\\"'], '\\"', \trim((string)$value, '\\"'));
        return '"' . \str_replace('{value}', $value, $syntax) . '"';
    }

    /**
     * @return false|array
     */
    public static function queryBuilderAliasParse(string $str, array $separators = [' as ', ' '])
    {
        foreach ($separators as $separator) {
            if(\stripos($str, $separator) === FALSE){
                continue;
            }
            $lowercase = \strtolower($separator);
            $str = \str_replace(\strtoupper($separator), $lowercase, $str);
            return \explode($lowercase, $str, 2);
        }
        return false;
    }

    /**
     * @param string $table
     * @return string|string[]
     */
    public static function queryBuilderFromCheck(string $table)
    {
        $table = \trim($table);
        if(self::str_contains($table, ',')){
            $split = \explode(',', $table);
            $tables = [];
            foreach ($split as $table) {
                $tables[] = self::queryBuilderFromCheck($table);
            }
            return $tables;
        }
        $alias_parse = self::queryBuilderAliasParse($table, [' as ', ' ']);
        if($alias_parse === FALSE){
            return $table;
        }
        return $alias_parse[0] . ' AS ' . $alias_parse[1];
    }

}
