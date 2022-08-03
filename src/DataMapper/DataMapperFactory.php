<?php
/**
 * DataMapperFactory.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.6
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\DataMapper;

use InitPHP\Database\Connection\Connection;
use InitPHP\Database\Exceptions\DatabaseInvalidArgumentException;

class DataMapperFactory
{

    public function __construct()
    {
    }


    public function create(array $configuration): DataMapperInterface
    {
        if(!isset($configuration['dsn']) || !isset($configuration['username'])){
            throw new DatabaseInvalidArgumentException('Dsn and username are required for connection.');
        }
        $connection = new Connection($configuration['dsn'], $configuration['username'], ($configuration['password'] ?? ''));
        return new DataMapper($connection);
    }

}
