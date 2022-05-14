<?php
/**
 * Helper.php
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

use function preg_split;
use function strtolower;
use function lcfirst;
use function ucfirst;
use function explode;

final class Helper
{

    public static function attributeNameCamelCaseDecode(string $camelCase): string
    {
        $camelCase = lcfirst($camelCase); // İlk karakterden önce _ oluşmasını önlemek için.
        $parse = preg_split('/(?=[A-Z])/', $camelCase, -1, \PREG_SPLIT_NO_EMPTY);
        $key = '';
        foreach ($parse as $value) {
            $key .= '_' . strtolower($value);
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


}
