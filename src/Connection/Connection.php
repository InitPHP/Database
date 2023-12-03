<?php

namespace InitPHP\Database\Connection;

use InitPHP\Database\Connection\Exceptions\ConnectionException;
use PDO;
use Exception;

class Connection implements Interfaces\ConnectionInterface
{

    private array $credentials = [
        'dsn'               => '',
        'username'          => 'root',
        'password'          => '',
        'charset'           => 'utf8mb4',
        'collation'         => 'utf8mb4_unicode_ci',

        'debug'             => false,
        'log'               => null,
    ];

    private ?PDO $pdo = null;

    private array $transaction = [
        'status'            => false,
        'enable'            => false,
        'testMode'          => false,
    ];

    public function __construct(?array $credentials = null)
    {
        !empty($credentials) && $this->credentials = array_merge($this->credentials, $credentials);
    }

    /**
     * @inheritDoc
     */
    public function getCredentials(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->credentials;
        }

        return $this->credentials[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function getPDO(): PDO
    {
        !isset($this->pdo) && $this->connect();

        return $this->pdo;
    }

    /**
     * @inheritDoc
     */
    public function connect(): bool
    {
        try {
            $options = [
                PDO::ATTR_EMULATE_PREPARES      => false,
                PDO::ATTR_PERSISTENT            => true,
                PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_CLASS,
            ];

            $this->pdo = new PDO($this->getCredentials('dsn'), $this->getCredentials('username'), $this->getCredentials('password'), $options);

            if ($charset = $this->getCredentials('charset')) {
                if ($collation = $this->getCredentials('collation')) {
                    $this->pdo->exec("SET NAMES '" . $charset . "' COLLATE '" . $collation . "'");
                }
                $this->pdo->exec("SET CHARACTER SET '" . $charset . "'");
            }

            return true;
        } catch (Exception $e) {
            throw new ConnectionException($e->getMessage(), (int)$e->getCode(), $e->getPrevious());
        }
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(bool $testMode = false): bool
    {
        $this->transaction = [
            'status'        => true,
            'enable'        => true,
            'testMode'      => $testMode,
        ];

        return $this->getPDO()->beginTransaction();
    }

    /**
     * @inheritDoc
     */
    public function completeTransaction(): bool
    {
        return $this->transaction['status'] === false || $this->transaction['testMode'] === true ? $this->rollBack() : $this->commit();
    }

    /**
     * @inheritDoc
     */
    public function commit(): bool
    {
        $this->transaction = [
            'status'        => false,
            'enable'        => false,
            'testMode'      => false,
        ];

        return $this->getPDO()->commit();
    }

    /**
     * @inheritDoc
     */
    public function rollBack(): bool
    {
        $this->transaction = [
            'status'        => false,
            'enable'        => false,
            'testMode'      => false,
        ];

        return $this->getPDO()->rollBack();
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): bool
    {
        $this->pdo = null;

        return true;
    }

}
