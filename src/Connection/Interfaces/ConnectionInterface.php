<?php

namespace InitPHP\Database\Connection\Interfaces;

use InitPHP\Database\Connection\Exceptions\ConnectionException;
use PDO;

interface ConnectionInterface
{

    /**
     * @return PDO
     * @throws ConnectionException
     */
    public function getPDO(): PDO;

    /**
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getCredentials(?string $key = null, mixed $default = null): mixed;

    /**
     * @throws ConnectionException
     * @return bool
     */
    public function connect(): bool;

    /**
     * @param bool $testMode
     * @return bool
     * @throws ConnectionException
     */
    public function beginTransaction(bool $testMode = false): bool;

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function completeTransaction(): bool;

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function commit(): bool;

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function rollBack(): bool;

    /**
     * @return bool
     */
    public function disconnect(): bool;

}
