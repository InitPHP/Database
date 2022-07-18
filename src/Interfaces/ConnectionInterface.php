<?php
/**
 * ConnectionInterface.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.13
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Interfaces;

use \InitPHP\Database\Exception\ConnectionException;

interface ConnectionInterface
{

    /**
     * @used-by ConnectionInterface::getPDO()
     * @return $this
     * @throws ConnectionException
     */
    public function connection(): self;

    /**
     * @return void
     */
    public function asConnectionGlobal();

    /**
     * @return void
     */
    public function disconnection();

    /**
     * @return \PDO
     */
    public function getPDO(): \PDO;

    /**
     * @return string
     */
    public function getDSN(): string;

    /**
     * @param string $DSN
     * @return $this
     */
    public function setDSN(string $DSN): self;

    /**
     * @return string
     */
    public function getUsername(): string;

    /**
     * @param string $username
     * @return $this
     */
    public function setUsername(string $username): self;

    /**
     * @return string
     */
    public function getPassword(): string;

    /**
     * @param string $password
     * @return $this
     */
    public function setPassword(string $password): self;

    /**
     * @return string
     */
    public function getCharset(): string;

    /**
     * @param string $charset
     * @return $this
     */
    public function setCharset(string $charset = 'utf8mb4'): self;

    /**
     * @return string
     */
    public function getCollation(): string;

    /**
     * @param string $collation
     * @return $this
     */
    public function setCollation(string $collation = 'utf8mb4_general_ci'): self;

}
