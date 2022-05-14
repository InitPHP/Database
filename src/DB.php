<?php
/**
 * DB.php
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

use \InitPHP\Database\Exception\QueryExecuteException;
use \InitPHP\Database\Interfaces\{DBInterface, EntityInterface};

use function is_string;
use function is_object;
use function trim;
use function substr;
use function str_starts_with;

class DB extends Connection implements DBInterface
{

    use QueryBuilder;

    protected ?string $_DBExecuteLastQueryStatement;

    protected ?int $_DB_NumRows = null, $_DB_LastInsertId = null;

    /** @var string|EntityInterface */
    protected $entity;

    protected ?int $_DBAsReturnType;
    protected \PDOStatement $_DBLastStatement;
    private array $_DBArguments = [];

    private bool $_DBTransactionStatus = false;
    private bool $_DBTransactionTestMode = false;
    private bool $_DBTransaction = false;

    public function __construct(array $configs = [])
    {
        if(isset(static::$_QB_StaticPrefix)){
            $this->_QB_Prefix = static::$_QB_StaticPrefix;
        }
        if(isset($configs['prefix'])){
            $this->_QB_Prefix = $configs['prefix'];
            unset($configs['prefix']);
        }
        parent::__construct($configs);
    }

    public function __call($name, $arguments)
    {
        if(str_starts_with($name, 'findBy')){
            $attrCamelCase = substr($name, 6);
            $attributeName = Helper::attributeNameCamelCaseDecode($attrCamelCase);
            $this->where($attributeName, ...$arguments);
            return $this;
        }
        return parent::__call($name, $arguments);
    }

    public function asConnectionGlobal()
    {
        static::$_QB_StaticPrefix = $this->_QB_Prefix;
        parent::asConnectionGlobal();
    }

    /**
     * @inheritDoc
     */
    public function numRows($dbOrPDOStatement = null): int
    {
        if($dbOrPDOStatement === null){
            return $this->_DB_NumRows ?? 0;
        }
        if($dbOrPDOStatement instanceof \PDOStatement){
            return $dbOrPDOStatement->rowCount();
        }
        if($dbOrPDOStatement instanceof DBInterface){
            return $dbOrPDOStatement->numRows(null);
        }
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function lastSQL(): ?string
    {
        return $this->_DBExecuteLastQueryStatement ?? null;
    }

    /**
     * @inheritDoc
     */
    public function insertId(): ?int
    {
        return $this->_DB_LastInsertId ?? null;
    }

    /**
     * @inheritDoc
     */
    public function asAssoc(): self
    {
        $this->_DBAsReturnType = \PDO::FETCH_ASSOC;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asArray(): self
    {
        $this->_DBAsReturnType = \PDO::FETCH_BOTH;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function asObject(): self
    {
        $this->_DBAsReturnType = \PDO::FETCH_OBJ;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function transactionStatus(): bool
    {
        return $this->_DBTransactionStatus;
    }

    /**
     * @inheritDoc
     */
    public function transactionStart(bool $testMode = false): self
    {
        $this->_DBTransaction = true;
        $this->_DBTransactionTestMode = $testMode;
        $this->_DBTransactionStatus = true;
        $this->getPDO()->beginTransaction();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function transactionComplete(): self
    {
        $this->_DBTransaction = false;
        if($this->_DBTransactionTestMode === FALSE && $this->_DBTransactionStatus !== FALSE){
            $this->getPDO()->commit();
        }else{
            $this->getPDO()->rollBack();
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function rows()
    {
        return $this->dbGetFetchMode('fetchAll');
    }

    /**
     * @inheritDoc
     */
    public function row()
    {
        return $this->dbGetFetchMode('fetch');
    }

    /**
     * @inheritDoc
     */
    public function column(int $column = 0)
    {
        if(!isset($this->_DBLastStatement)){
            throw new \RuntimeException('The query must be executed with the DB::get() method before the column is retrieved.');
        }
        return $this->_DBLastStatement->fetchColumn($column);
    }

    /**
     * @inheritDoc
     */
    public function setParameter(string $name, $value): self
    {
        $this->_DBArguments[$name] = $value;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setParams(array $arguments): self
    {
        $this->_DBArguments = $arguments;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(?string $table = null)
    {
        if(!empty($table)){
            $this->from($table);
        }
        $arguments = !empty($this->_DBArguments) ? $this->_DBArguments : null;
        $this->_DBArguments = [];
        if(($stmt = $this->query($this->selectStatementBuild(), $arguments)) !== FALSE){
            $this->clear();
            return $this->_DBLastStatement = $stmt;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fromGet($dbOrPDOStatement): DBInterface
    {
        $clone = clone $this;
        $clone->clear();
        if($dbOrPDOStatement instanceof \PDOStatement){
            $clone->_DBLastStatement = $dbOrPDOStatement;
        }elseif($dbOrPDOStatement instanceof DBInterface){
            $clone->_DBLastStatement = $dbOrPDOStatement->_DBLastStatement;
        }else{
            throw new \InvalidArgumentException('Get must be a \\PDOStatement or \\SimpleDB\\DB object.');
        }
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->exec($this->selectStatementBuild());
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql, ?array $parameters = null)
    {
        if(trim($sql) === ''){
            return false;
        }
        $this->_DBExecuteLastQueryStatement = $sql;
        try {
            if(($query = $this->getPDO()->prepare($sql)) === FALSE){
                if($this->_DBTransaction !== FALSE){
                    $this->_DBTransactionStatus = false;
                }
                return false;
            }
            $res = $query->execute((empty($parameters) ? null : $parameters));
            if($res === FALSE && $this->_DBTransaction !== FALSE){
                $this->_DBTransactionStatus = false;
            }
        }catch (\PDOException $e) {
            $this->_DBTransactionStatus = false;
            $err = $e->getMessage() . ' ' . PHP_EOL . 'SQL Statement : ' . $sql;
            throw new QueryExecuteException($err);
        }
        if($query instanceof \PDOStatement){
            $this->_DB_NumRows = $query->rowCount();
            if(($insertId = $this->getPDO()->lastInsertId()) !== FALSE){
                $this->_DB_LastInsertId = (int)$insertId;
            }
        }
        return $query;
    }

    /**
     * Veritabanına bir ya da daha fazla veri ekler.
     *
     * @param array $data
     * @return bool
     */
    public function insert(array $data)
    {
        $stmt = $this->query($this->insertStatementBuild($data));
        $this->clear();
        return $stmt !== FALSE;
    }

    /**
     * Güncelleme sorgusu çalıştırır.
     *
     * @param array $data
     * @return bool
     */
    public function update(array $data)
    {
        $stmt = $this->query($this->updateStatementBuild($data));
        $this->clear();
        return $stmt !== FALSE;
    }

    /**
     * Silme sorgusu çalıştırır.
     *
     * @return bool
     */
    public function delete()
    {
        $stmt = $this->query($this->deleteStatementBuild());
        $this->clear();
        return $stmt !== FALSE;
    }

    /**
     * @inheritDoc
     */
    public function exec(string $sql): int
    {
        if(trim($sql) === ''){
            return 0;
        }
        $this->_DBExecuteLastQueryStatement = $sql;
        try {
            $arguments = !empty($this->_DBArguments) ? $this->_DBArguments : null;
            if($arguments !== null){
                if(($stmt = $this->getPDO()->prepare($sql)) === FALSE){
                    if($this->_DBTransaction !== FALSE){
                        $this->_DBTransactionStatus = false;
                    }
                    return 0;
                }
                if($stmt->execute($arguments) === FALSE && $this->_DBTransaction !== FALSE){
                    $this->_DBTransactionStatus = false;
                }
                return $stmt->rowCount();
            }
            if(($stmt = $this->getPDO()->exec($sql)) === FALSE){
                if($this->_DBTransaction !== FALSE){
                    $this->_DBTransactionStatus = false;
                }
                return 0;
            }
            return (int)$stmt;
        }catch (\PDOException $e) {
            $this->_DBTransactionStatus = false;
            $err = $e->getMessage()
                . ' SQL Statement : ' . $sql;
            throw new QueryExecuteException($err);
        }
    }

    /**
     * Bu yöntem DB::get() ile yürütülmüş sorgunun yanıtlarını PDOStatement nesnesinden istenen yöntem ile alır.
     *
     * @used-by DB::rows()
     * @used-by DB::row()
     * @param string $pdoMethod <p>[fetch|fetchAll]</p>
     * @return array|EntityInterface|EntityInterface[]|object|object[]|null
     */
    private function dbGetFetchMode(string $pdoMethod)
    {
        if(!isset($this->_DBLastStatement)){
            return null;
        }
        if(isset($this->_DBAsReturnType)){
            $asType = $this->_DBAsReturnType;
            unset($this->_DBAsReturnType);
            return $this->_DBLastStatement->{$pdoMethod}($asType);
        }
        if(!isset($this->entity)){
            return $this->_DBLastStatement->{$pdoMethod}();
        }
        if(is_object($this->entity)){
            return $this->_DBLastStatement->{$pdoMethod}(\PDO::FETCH_INTO, $this->entity);
        }
        if(is_string($this->entity)){
            return $this->_DBLastStatement->{$pdoMethod}(\PDO::FETCH_CLASS, $this->entity);
        }
        return $this->_DBLastStatement->{$pdoMethod}(\PDO::FETCH_BOTH);
    }

}
