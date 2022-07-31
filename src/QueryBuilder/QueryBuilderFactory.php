<?php
/**
 * QueryBuilderFactory.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.4
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder;

use InitPHP\Database\Exceptions\QueryBuilderException;

class QueryBuilderFactory
{

    public function __construct()
    {
    }

    public static function create(string $queryBuilderClass): QueryBuilderInterface
    {
        $queryBuilder = new $queryBuilderClass();
        if (!($queryBuilder instanceof QueryBuilderInterface)) {
            throw new QueryBuilderException($queryBuilderClass . ' is not a valid Query builder object.');
        }
        return $queryBuilder;
    }


}
