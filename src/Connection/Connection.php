<?php
/**
 * Connection.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.11
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Connection;

use InitPHP\Database\Exceptions\DatabaseConnectionException;
use \PDO;

class Connection implements ConnectionInterface
{

    private ?PDO $pdo;

    private static ?PDO $global = null;

    private array $transaction = [
        'enable'        => false,
        'testMode'      => false,
        'status'        => false,
    ];

    private array $credentials = [
        'dsn'       => '',
        'username'  => '',
        'password'  => '',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ];

    public function __construct(array $credentials = [])
    {
        if(!empty($credentials)){
            $this->credentials = \array_merge($this->credentials, \array_change_key_case($credentials, \CASE_LOWER));
        }
    }

    /**
     * @inheritDoc
     */
    public function connectionAsGlobal(): void
    {
        self::$global = $this->getPDO();
    }

    /**
     * @inheritDoc
     */
    public function getPDO(): PDO
    {
        if(self::$global !== null){
            return self::$global;
        }
        if(!isset($this->pdo)){
            try {
                $this->pdo = new PDO(
                    $this->credentials['dsn'],
                    $this->credentials['username'],
                    $this->credentials['password'],
                    [
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => true,
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH
                    ]
                );

                if(!empty($this->credentials['charset'])){
                    if(!empty($this->credentials['collation'])){
                        $this->pdo->exec("SET NAMES '" . $this->credentials['charset'] . "' COLLATE '" . $this->credentials['collation'] . "'");
                    }
                    $this->pdo->exec("SET CHARACTER SET '" . $this->credentials['charset'] . "'");
                }
            }catch (\PDOException $e) {
                throw new DatabaseConnectionException($e->getMessage(), (int)$e->getCode());
            }
        }
        return $this->pdo;
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->pdo = null;
        unset($this->pdo);
        self::$global = null;
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(bool $testMode = false): bool
    {
        $this->transaction = [
            'enable'        => true,
            'testMode'      => $testMode,
            'status'        => true,
        ];
        return $this->getPDO()->beginTransaction();
    }

    /**
     * @inheritDoc
     */
    public function completeTransaction(): bool
    {

        if($this->transaction['status'] !== FALSE && $this->transaction['testMode'] === FALSE){
            $res = $this->getPDO()->commit();
        }else{
            $res = $this->getPDO()->rollBack();
        }
        $this->transaction = [
            'enable'        => false,
            'testMode'      => false,
            'status'        => false,
        ];
        return (bool)$res;
    }

    /**
     * @inheritDoc
     */
    public function statusTransaction(): bool
    {
        return $this->transaction['status'];
    }

    /**
     * @inheritDoc
     */
    public function isTransaction(): bool
    {
        return $this->transaction['enable'];
    }

    /**
     * @inheritDoc
     */
    public function failedTransaction(): void
    {
        $this->transaction['status'] = false;
    }

}
