<?php
/**
 * Database
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

use \InitPHP\Database\Exceptions\{WritableException,
    ReadableException,
    UpdatableException,
    DeletableException,
    SQLQueryExecuteException,
    ConnectionException};
use \InitPHP\Database\Helpers\{Helper, Parameters, Validation};
use \PDO;

class Database extends QueryBuilder
{

    public const ENTITY = 0;
    public const ASSOC = 1;
    public const ARRAY = 2;
    public const OBJECT = 3;
    public const LAZY = 4;

    private PDO $_pdo;

    private static PDO $_globalPDO;

    private array $_credentials = [
        'dsn'                   => '',
        'username'              => 'root',
        'password'              => null,
        'charset'               => 'utf8mb4',
        'collation'             => 'utf8mb4_unicode_ci',
        'tableSchema'           => null,
        'tableSchemaID'         => null,
        'entity'                => Entity::class,
        'createdField'          => null,
        'updatedField'          => null,
        'deletedField'          => null,
        'allowedFields'         => null,
        'timestampFormat'       => 'c',
        'validation'            => [
            'methods'           => [],
            'messages'          => [],
            'labels'            => [],
        ],
        'readable'              => true,
        'writable'              => true,
        'deletable'             => true,
        'updatable'             => true,
        'return'                => null,
    ];

    private Result $_last;

    private array $_transaction = [
        'status'    => false,
        'enable'    => false,
        'testMode'  => false,
    ];

    private bool $_isOnlyDeletes = false;

    private array $_errors = [];

    private Validation $_validation;

    public function __construct(array $credentials = [])
    {
        $this->setCredentials($credentials);
        $this->_validation = new Validation($this->_credentials['validation']['methods'], $this->_credentials['validation']['messages'], $this->_credentials['validation']['labels'], $this);
    }

    final public function newInstance(array $credentials = []): Database
    {
        return new self(empty($credentials) ? $this->_credentials : \array_merge($this->_credentials, $credentials));
    }

    final public function setCredentials(array $credentials): self
    {
        $this->_credentials = \array_merge($this->_credentials, $credentials);
        return $this;
    }

    final public function isError(): bool
    {
        return !empty($this->_errors);
    }

    final public function getError(): array
    {
        return $this->_errors;
    }

    final public function connectionAsGlobal(): void
    {
        self::$_globalPDO = $this->getPDO();
    }

    final public function getPDO(): PDO
    {
        if(isset(self::$_globalPDO)){
            return self::$_globalPDO;
        }
        if(!isset($this->_pdo)){
            try {
                $options = [
                    PDO::ATTR_EMULATE_PREPARES      => false,
                    PDO::ATTR_PERSISTENT            => true,
                    PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                ];
                switch ($this->_credentials['return']) {
                    case null:
                    case self::ARRAY:
                        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_BOTH;
                        break;
                    case self::ASSOC:
                        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
                        break;
                    case self::ENTITY:
                        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_CLASS;
                        break;
                    case self::OBJECT:
                        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_OBJ;
                        break;
                    case self::LAZY:
                        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_LAZY;
                        break;
                    default:
                        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_BOTH;
                }

                $this->_pdo = new PDO($this->_credentials['dsn'], $this->_credentials['username'], $this->_credentials['password'], $options);
                if(!empty($this->_credentials['charset'])){
                    if(!empty($this->_credentials['collation'])) {
                        $this->_pdo->exec("SET NAMES '" . $this->_credentials['charset'] . "' COLLATE '" . $this->_credentials['collation'] . "'");
                    }
                    $this->_pdo->exec("SET CHARACTER SET '" . $this->_credentials['charset'] . "'");
                }
            } catch (\PDOException $e) {
                throw new ConnectionException($e->getMessage(), (int)$e->getCode());
            }
        }

        return $this->_pdo;
    }

    final public function withPDO(PDO $pdo): self
    {
        $with = clone $this;
        $with->_pdo = $pdo;
        return $with;
    }

    final public function getSchemaID(): ?string
    {
        return $this->_credentials['tableSchemaID'];
    }

    final public function setSchemaID(?string $column): self
    {
        $this->_credentials['tableSchemaID'] = $column;
        return $this;
    }

    final public function withSchemaID(?string $column): self
    {
        $with = clone $this;
        return $with->setSchemaID($column);
    }

    final public function getSchema(): ?string
    {
        return $this->_credentials['tableSchema'];
    }

    final public function setSchema(?string $table): self
    {
        $this->_credentials['tableSchema'] = $table;
        return $this;
    }

    final public function withSchema(?string $table): self
    {
        $with = clone $this;
        return $with->setSchema($table);
    }

    final public function beginTransaction(bool $testMode = false): bool
    {
        $this->_transaction = [
            'status'        => true,
            'enable'        => true,
            'testMode'      => $testMode,
        ];
        return (bool)$this->getPDO()->beginTransaction();
    }

    final public function completeTransaction(): bool
    {
        return ($this->_transaction['status'] === FALSE || $this->_transaction['testMode'] === TRUE) ? $this->rollBack() : $this->commit();
    }

    final public function commit(): bool
    {
        $this->_transaction = [
            'status'        => false,
            'enable'        => false,
            'testMode'      => false,
        ];
        return (bool)$this->getPDO()->commit();
    }

    final public function rollBack(): bool
    {
        $this->_transaction = [
            'status'        => false,
            'enable'        => false,
            'testMode'      => false,
        ];
        return (bool)$this->getPDO()->rollBack();
    }

    final public function transaction(\Closure $closure): bool
    {
        try {
            $this->beginTransaction(false);
            \call_user_func_array($closure, [$this]);
            $res = $this->completeTransaction();
        } catch (\Exception $e) {
            $res = $this->rollBack();
        }
        return $res;
    }

    final public function raw(string $rawQuery): Raw
    {
        return new Raw($rawQuery);
    }

    final public function connection(array $credentials = []): self
    {
        return new self($credentials);
    }

    /**
     * @param $value
     * @return false|float|int|string
     */
    final public function escape_str($value)
    {
        if(\is_numeric($value)){
            return $value;
        }
        if(\is_bool($value)){
            return $value === FALSE ? 'FALSE' : 'TRUE';
        }
        if($value === null){
            return 'NULL';
        }
        if(\is_string($value)){
            $value = \str_replace("\\", "", \trim($value, "' \\\t\n\r\0\x0B"));
            return "'" . \str_replace("'", "\\'", $value) . "'";
        }
        if(\is_object($value)){
            return \serialize($value);
        }
        if(\is_array($value)){
            return \serialize($value);
        }
        return false;
    }

    /**
     * @param string $key
     * @param string|int|float|bool|null $value
     * @return $this
     */
    final public function setParameter(string $key, $value): self
    {
        Parameters::set($key, $value);
        return $this;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    final public function setParameters(array $parameters = []): self
    {
        foreach ($parameters as $key => $value) {
            Parameters::set($key, $value);
        }
        return $this;
    }

    /**
     * @param string $sqlQuery
     * @param array $parameters
     * @return Result
     */
    final public function query(string $sqlQuery, array $parameters = []): Result
    {
        $arguments = Parameters::get(true);
        $parameters = empty($parameters) ? $arguments : \array_merge($arguments, $parameters);
        try {
            $stmt = $this->getPDO()->prepare($sqlQuery);
            if($stmt === FALSE){
                throw new \Exception('The SQL query could not be prepared.');
            }
            if(!empty($parameters)){
                foreach ($parameters as $key => $value) {
                    $stmt->bindValue(':' . \ltrim($key, ':'), $value, $this->_bind($value));
                }
            }
            $execute = $stmt->execute();
            if($execute === FALSE){
                throw new \Exception('The SQL query could not be executed.');
            }
            $errorCode = $stmt->errorCode();
            if($errorCode !== null && !empty(\trim($errorCode, "0 \t\n\r\0\x0B"))){
                $errorInfo = $stmt->errorInfo();
                if(isset($errorInfo[2])){
                    $this->_errors[] = $errorCode . ' - ' . $errorInfo[2];
                }
            }
            $this->_last = new Result($stmt);
            if($this->_credentials['return'] === null){
                return $this->_last;
            }
            switch ($this->_credentials['return']) {
                case self::ASSOC:
                    return $this->_last->asAssoc();
                case self::ARRAY:
                    return $this->_last->asArray();
                case self::ENTITY:
                    return $this->_last->asEntity();
                case self::OBJECT:
                    return $this->_last->asObject();
                case self::LAZY:
                    return $this->_last->asLazy();
                default:
                    return $this->_last;
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if(($this->_credentials['debug'] ?? false) === TRUE){
                $message .= ' SQL : '
                    . (empty($parameters) ? $sqlQuery : \strtr($sqlQuery, $parameters));
            }
            throw new SQLQueryExecuteException($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * @return int
     */
    final public function insertId(): int
    {
        $id = $this->getPDO()->lastInsertId();
        return $id === FALSE ? 0 : (int)$id;
    }

    /**
     * @param string|null $table
     * @return Result
     */
    final public function get(?string $table = null): Result
    {
        if(!empty($table)){
            $this->from($table);
        }
        $this->_deleteFieldBuild();
        $res = $this->query($this->_readQuery());
        $this->reset();
        return $res;
    }

    /**
     * @param array $set
     * @return bool
     */
    public function create(array $set)
    {
        if($this->_credentials['writable'] === FALSE){
            throw new WritableException('');
        }
        $isCreatedField = !empty($this->_credentials['createdField']);
        if($isCreatedField){
            $createdFieldParameterName = Parameters::add($this->_credentials['createdField'], \date($this->_credentials['timestampFormat']));
        }
        $data = [];
        if(\count($set) === \count($set, \COUNT_RECURSIVE)){
            $this->_validation->setData($set);
            foreach ($set as $column => $value) {
                if($this->_validation->validation($column, null) === FALSE){
                    $this->_errors[] = $this->_validation->getError();
                    return false;
                }
                $data[$column] = $value;
            }
            if(empty($data)){
                return false;
            }
            if($isCreatedField){
                $data[$this->_credentials['createdField']] = $createdFieldParameterName;
            }
        }else{
            $i = 0;
            foreach ($set as $row) {
                $data[$i] = [];
                $this->_validation->setData($row);
                foreach ($row as $column => $value) {
                    if($this->_validation->validation($column, null) === FALSE){
                        $this->_errors[] = $this->_validation->getError();
                        return false;
                    }
                    $data[$i][$column] = $value;
                }
                if(empty($data[$i])){
                    continue;
                }
                if($isCreatedField){
                    $data[$i][$this->_credentials['createdField']] = $createdFieldParameterName;
                }
                ++$i;
            }
        }
        $res = $this->query($this->_insertQuery($data));
        $this->reset();
        return $res->numRows() > 0;
    }

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return Result
     */
    public function read(array $selector = [], array $conditions = [], array $parameters = [])
    {
        if($this->_credentials['readable'] === FALSE){
            throw new ReadableException('');
        }
        $this->_readQueryHandler($selector, $conditions, $parameters);
        $res = $this->query($this->_readQuery());
        $this->reset();
        return $res;
    }

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return Result
     */
    public function readOne(array $selector = [], array $conditions = [], array $parameters = [])
    {
        if($this->_credentials['readable'] === FALSE){
            throw new ReadableException('');
        }
        $this->limit(1);
        $this->_readQueryHandler($selector, $conditions, $parameters);
        $res = $this->query($this->_readQuery());
        $this->reset();
        return $res;
    }

    /**
     * @param array $set
     * @return bool
     */
    public function update(array $set)
    {
        if($this->_credentials['updatable'] === FALSE){
            throw new UpdatableException('');
        }
        $schemaID = null;
        if(!empty($this->getSchemaID()) && isset($set[$this->getSchemaID()])){
            $schemaID = $set[$this->getSchemaID()];
            $this->where($this->getSchemaID(), $schemaID);
            unset($set[$this->getSchemaID()]);
        }
        if(empty($set)){
            return false;
        }
        $this->_validation->setData($set);
        $data = [];
        foreach($set as $column => $value){
            if($this->_validation->validation($column, $schemaID) === FALSE){
                $this->_errors[] = $this->_validation->getError();
                return false;
            }
            $data[$column] = $value;
        }
        if(!empty($this->_credentials['updatedField'])){
            $data[$this->_credentials['updatedField']] = \date($this->_credentials['timestampFormat']);
        }
        $res = $this->query($this->_updateQuery($data));
        $this->reset();

        return $res->numRows() > 0;
    }

    /**
     * @param array $conditions
     * @return bool
     */
    public function delete(array $conditions = [])
    {
        if($this->_credentials['deletable'] === FALSE){
            throw new DeletableException('');
        }
        foreach ($conditions as $column => $value) {
            $this->where($column, $value);
        }
        if(!empty($this->_credentials['deletedField'])){
            if($this->_isOnlyDeletes !== FALSE){
                $this->isNot($this->_credentials['deletedField'], null);
                $query = $this->_deleteQuery();
                $this->_isOnlyDeletes = false;
            }else{
                $this->is($this->_credentials['deletedField'], null);
                $query = $this->_updateQuery([
                    $this->_credentials['deletedField'] => \date($this->_credentials['timestampFormat'])
                ]);
            }
        }else{
            $query = $this->_deleteQuery();
        }
        $res = $this->query($query);

        $this->reset();
        return $res->numRows() > 0;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return Result
     */
    public function all(int $limit = 100, int $offset = 0)
    {
        return $this->limit($limit)
                ->offset($offset)
                ->read();
    }

    public function onlyDeleted(): self
    {
        $this->_isOnlyDeletes = true;
        return $this;
    }

    public function onlyUndeleted(): self
    {
        $this->_isOnlyDeletes = false;
        return $this;
    }

    public function _readQuery(): string
    {
        if($this->getSchema() !== null){
            $this->table($this->getSchema());
        }

        return 'SELECT '
            . (empty($this->_STRUCTURE['select']) ? '*' : \implode(', ', $this->_STRUCTURE['select']))
            . ' FROM ' . \implode(', ', $this->_STRUCTURE['table'])
            . (!empty($this->_STRUCTURE['join']) ? ' ' . \implode(', ', $this->_STRUCTURE['join']) : '')
            . $this->_whereQuery()
            . $this->_havingQuery()
            . (!empty($this->_STRUCTURE['group_by']) ? ' GROUP BY ' . \implode(', ', $this->_STRUCTURE['group_by']) : '')
            . (!empty($this->_STRUCTURE['order_by']) ? ' ORDER BY ' . \implode(', ', $this->_STRUCTURE['order_by']) : '')
            . $this->_limitQuery();
    }

    public function _insertQuery(array $data): string
    {
        $sql = 'INSERT INTO'
            . ' '
            . (empty($this->_STRUCTURE['table']) ? $this->getSchema() : end($this->_STRUCTURE['table']));
        $columns = [];
        $values = [];

        if(\count($data) === \count($data, \COUNT_RECURSIVE)){
            foreach ($data as $column => $value) {
                $column = \trim($column);
                if($this->_credentials['allowedFields'] !== null && !\in_array($column, $this->_credentials['allowedFields'])){
                    continue;
                }
                $columns[] = $column;
                $values[] = Helper::isSQLParameterOrFunction($value) ? $value : Parameters::add($column, $value);
            }
            if(empty($columns)){
                return '';
            }
            return $sql
                . ' (' . \implode(', ', $columns) . ') VALUES (' . \implode(', ', $values) . ');';
        }

        foreach ($data as &$row) {
            $value = [];
            foreach ($row as $column => $val) {
                $column = \trim($column);
                if($this->_credentials['allowedFields'] !== null && !\in_array($column, $this->_credentials['allowedFields'], true)){
                    continue;
                }
                if(!\in_array($column, $columns, true)){
                    $columns[] = $column;
                }
                $value[$column] = Helper::isSQLParameterOrFunction($val) ? $val : Parameters::add($column, $val);
            }
            $values[] = $value;
        }

        $multiValues = [];
        foreach ($values as $value) {
            $tmpValue = $value;
            $value = [];
            foreach ($columns as $column) {
                $value[$column] = $tmpValue[$column] ?? 'NULL';
            }
            $multiValues[] = '(' . \implode(', ', $value) . ')';
        }
        return $sql . ' (' . \implode(', ', $columns) . ') VALUES '
            . \implode(', ', $multiValues) . ';';
    }

    public function _updateQuery(array $data): string
    {
        $update = [];
        foreach ($data as $column => $value) {
            if($this->getSchemaID() === $column){
                continue;
            }
            if($this->_credentials['allowedFields'] !== null && !\in_array($column, $this->_credentials['allowedFields'])){
                continue;
            }
            $update[] = $column . ' = '
                . (Helper::isSQLParameterOrFunction($value) ? $value : Parameters::add($column, $value));
        }
        if(empty($update)){
            return '';
        }
        $schemaID = $this->getSchemaID();
        if($schemaID !== null && isset($data[$schemaID])){
            $this->where($schemaID, $data[$schemaID]);
        }
        return 'UPDATE '
            . (empty($this->_STRUCTURE['table']) ? $this->getSchema() : end($this->_STRUCTURE['table']))
            . ' SET '
            . \implode(', ', $update)
            . $this->_whereQuery()
            . $this->_havingQuery()
            . $this->_limitQuery();
    }

    public function _deleteQuery(): string
    {
        return 'DELETE FROM'
            . ' '
            . (empty($this->_STRUCTURE['table']) ? $this->getSchema() : end($this->_STRUCTURE['table']))
            . $this->_whereQuery()
            . $this->_havingQuery()
            . $this->_limitQuery();
    }

    private function _whereQuery(): string
    {
        $isAndEmpty = empty($this->_STRUCTURE['where']['AND']);
        $isOrEmpty = empty($this->_STRUCTURE['where']['OR']);
        if($isAndEmpty && $isOrEmpty){
            return ' WHERE 1';
        }
        return ' WHERE '
            . (!$isAndEmpty ? \implode(' AND ', $this->_STRUCTURE['where']['AND']) : '')
            . (!$isAndEmpty && !$isOrEmpty ? ' AND ' : '')
            . (!$isOrEmpty ? \implode(' OR ', $this->_STRUCTURE['where']['OR']) : '');
    }

    private function _havingQuery(): string
    {
        $isAndEmpty = empty($this->_STRUCTURE['having']['AND']);
        $isOrEmpty = empty($this->_STRUCTURE['having']['OR']);
        if($isAndEmpty && $isOrEmpty){
            return '';
        }
        return ' HAVING '
            . (!$isAndEmpty ? \implode(' AND ', $this->_STRUCTURE['having']['AND']) : '')
            . (!$isAndEmpty && !$isOrEmpty ? ' AND ' : '')
            . (!$isOrEmpty ? \implode(' OR ', $this->_STRUCTURE['having']['OR']) : '');
    }

    private function _limitQuery(): string
    {
        if($this->_STRUCTURE['limit'] === null && $this->_STRUCTURE['offset'] === null){
            return '';
        }
        $sql = ' LIMIT ';
        if($this->_STRUCTURE['offset'] !== null){
            $sql .= $this->_STRUCTURE['offset'] . ', ';
        }
        $sql .= $this->_STRUCTURE['limit'] ?? '10000';
        return $sql;
    }

    private function _readQueryHandler(array $selector = [], array $conditions = [], array $parameters = []): void
    {
        if(!empty($selector)){
            $this->select(...$selector);
        }
        if(!empty($conditions)){
            foreach ($conditions as $column => $value) {
                $this->where($column, $value);
            }
        }
        $this->_deleteFieldBuild();
    }

    private function _deleteFieldBuild(bool $reset = true): void
    {
        if(empty($this->_credentials['deletedField'])){
            return;
        }
        if($this->_isOnlyDeletes === FALSE){
            $this->is($this->_credentials['deletedField'], null);
        }else{
            $this->isNot($this->_credentials['deletedField'], null);
        }
        if($reset !== FALSE){
            $this->_isOnlyDeletes = false;
        }
    }

    private function _bind($value)
    {
        if(\is_bool($value) || \is_int($value)){
            return PDO::PARAM_INT;
        }
        if(\is_null($value)){
            return PDO::PARAM_NULL;
        }
        return PDO::PARAM_STR;
    }

}
