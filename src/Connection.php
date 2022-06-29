<?php
/**
 * Connection.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.8
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use \PDO;
use \InitPHP\Database\Exception\ConnectionException;
use \InitPHP\Database\Interfaces\ConnectionInterface;

use function is_numeric;
use function is_string;
use function preg_split;
use function trim;
use function preg_replace;
use function is_array;
use function is_iterable;

class Connection implements ConnectionInterface
{

    public const ESCAPE_MIXED = 0;
    public const ESCAPE_NUM = 1;
    public const ESCAPE_STR = 2;
    public const ESCAPE_NUM_ARRAY = 3;
    public const ESCAPE_STR_ARRAY = 4;

    protected string $_DSN;

    protected string $_Username;

    protected string $_Password;

    protected string $_Charset = 'utf8mb4';

    protected string $_Collation = 'utf8mb4_general_ci';

    protected string $_Driver = 'mysql';

    protected ?PDO $_PDO = null;

    protected static ?PDO $_staticPDO = null;

    public function __construct(array $configs = [])
    {
        foreach ($configs as $key => $value) {
            $method = 'set' . ucfirst($key);
            if(method_exists($this, $method) === FALSE){
                continue;
            }
            $this->{$method}($value);
        }
    }

    public function __call($name, $arguments)
    {
        return $this->getPDO()->{$name}(...$arguments);
    }

    /**
     * @inheritDoc
     */
    public function connection(): self
    {
        if($this->_PDO === null){
            try {
                $this->_PDO = new PDO($this->getDSN(), $this->getUsername(), $this->getPassword(), [
                    PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_OBJ,
                    PDO::ATTR_STRINGIFY_FETCHES     => false,
                    PDO::ATTR_EMULATE_PREPARES      => false
                ]);
                $this->_PDO->exec("SET NAMES '" . $this->getCharset() . "' COLLATE '" . $this->getCollation() . "'");
                $this->_PDO->exec("SET CHARACTER SET '" . $this->getCharset() . "'");
            } catch (\PDOException $e) {
                throw new ConnectionException('Connection failed : ' . $e->getMessage());
            }
            $this->_Driver = $this->_PDO->getAttribute(PDO::ATTR_DRIVER_NAME);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function disconnection()
    {
        $this->_PDO = null;
        static::$_staticPDO = null;
    }

    /**
     * @inheritDoc
     */
    public function asConnectionGlobal()
    {
        if($this->_PDO === null){
            $this->connection();
        }
        static::$_staticPDO = $this->_PDO;
    }

    /**
     * @inheritDoc
     */
    public function getPDO(): \PDO
    {
        if(static::$_staticPDO !== null){
            return static::$_staticPDO;
        }
        if($this->_PDO === null){
            $this->connection();
        }
        return $this->_PDO;
    }

    /**
     * @inheritDoc
     */
    public function getDSN(): string
    {
        return $this->_DSN ?? 'mysql:host=localhost';
    }

    /**
     * @inheritDoc
     */
    public function setDSN(string $DSN): self
    {
        $this->_DSN = $DSN;
        return $this;
    }

    public function setDriver(string $driver = 'mysql'): self
    {
        $this->_Driver = $driver;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getUsername(): string
    {
        return $this->_Username ?? 'root';
    }

    /**
     * @inheritDoc
     */
    public function setUsername(string $username): self
    {
        $this->_Username = $username;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPassword(): string
    {
        return $this->_Password ?? '';
    }

    /**
     * @inheritDoc
     */
    public function setPassword(string $password): self
    {
        $this->_Password = $password;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCharset(): string
    {
        return $this->_Charset ?? 'utf8mb4';
    }

    /**
     * @inheritDoc
     */
    public function setCharset(string $charset = 'utf8mb4'): self
    {
        $this->_Charset = $charset;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCollation(): string
    {
        return $this->_Collation ?? 'utf8mb4_general_ci';
    }

    /**
     * @inheritDoc
     */
    public function setCollation(string $collation = 'utf8mb4_general_ci'): self
    {
        $this->_Collation = $collation;
        return $this;
    }

    public function escapeString($value, int $type = self::ESCAPE_MIXED)
    {
        switch ($type) {
            case self::ESCAPE_NUM:
                if(is_numeric($value)){
                    return $value;
                }
                if(is_string($value)){
                    $number = '';
                    $split = preg_split('/(.*)/i', trim($value), -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($split as $char){
                        if(!is_numeric($char)){
                            break;
                        }
                        $number .= $char;
                    }
                    return (int)$number;
                }
                return 0;
            case self::ESCAPE_STR:
                $value = trim((string)$value, "\\ \"'\t\n\r\0\x0B\x00\x0A\x0D\x1A\x22\x27\x5C");
                return preg_replace('/[\x00\x0A\x0D\x1A\x22\x27\x5C]/u', '\\\$0', $value);
            case self::ESCAPE_NUM_ARRAY:
                if(is_array($value)){
                    foreach ($value as &$val) {
                        $val = $this->escapeString($val, self::ESCAPE_NUM);
                    }
                    return $value;
                }
                return [0];
            case self::ESCAPE_STR_ARRAY:
                if(is_array($value)){
                    foreach ($value as &$val) {
                        $val = $this->escapeString($val, self::ESCAPE_STR);
                    }
                    return $value;
                }
                return [''];
            default:
                if(is_numeric($value)){
                    return $value;
                }
                if(is_iterable($value)){
                    return $this->escapeString($value, self::ESCAPE_STR_ARRAY);
                }
                return $this->escapeString($value, self::ESCAPE_STR);
        }
    }

}
