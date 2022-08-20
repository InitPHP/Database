<?php
/**
 * ConnectionInterface.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.13
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Connection;

interface ConnectionInterface
{

    /**
     * PDO bağlantısını static yaparak global olarak kullanıbilir yapar.
     *
     * @return void
     */
    public function connectionAsGlobal(): void;

    /**
     * Mevcut PDO bağlantısını döndürür.
     *
     * @return \PDO
     */
    public function getPDO(): \PDO;

    /**
     * Mevcut PDO bağlantısını sona erdirir/kapatır.
     *
     * @return void
     */
    public function close(): void;

    /**
     * @param bool $testMode
     * @return bool
     */
    public function beginTransaction(bool $testMode = false): bool;

    /**
     * @return bool
     */
    public function completeTransaction(): bool;

    /**
     * @return bool
     */
    public function statusTransaction(): bool;

    /**
     * @return bool
     */
    public function isTransaction(): bool;

    /**
     * @return void
     */
    public function failedTransaction(): void;

}
