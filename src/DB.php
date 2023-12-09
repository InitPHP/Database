<?php

declare(strict_types=1);
namespace InitPHP\Database;

use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;

/**
 * @mixin \InitORM\Database\Facade\DB
 */
class DB
{

    private static DatabaseInterface $db;

    public function __call($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    public static function createImmutable(array|ConnectionInterface $connection): void
    {
        self::$db = self::connect($connection);
    }

    /**
     * @param array|ConnectionInterface $connection
     * @return DatabaseInterface
     */
    public static function connect(array|ConnectionInterface $connection): DatabaseInterface
    {
        return new Database($connection);
    }

    public static function getDatabase(): DatabaseInterface
    {
        if (!isset(self::$db)) {
            throw new DatabaseException('To create an immutable, first use the "createImmutable()" method.');
        }

        return self::$db;
    }


}
