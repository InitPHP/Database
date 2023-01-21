<?php
/**
 * Database
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.6
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

use InitPHP\Database\Utils\{Pagination,
    Datatables};
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
        'debug'                 => false,
        'log'                   => null,
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

    public function __call($name, $arguments)
    {
        if(Helper::str_starts_with($name, 'findBy') === FALSE){
            throw new \RuntimeException('There is no "' . $name . '" method.');
        }
        $this->where(Helper::camelCaseToSnakeCase(\substr($name, 6)), \current($arguments));
        return $this;
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
            $sqlMessage = 'SQL : '
                . (empty($parameters) ? $sqlQuery : strtr($sqlQuery, $parameters));
            if(!empty($this->_credentials['log'])){
                $this->_logCreate($message . ' ' . $sqlMessage);
            }
            if($this->_credentials['debug'] === TRUE){
                $message .= $message . ' ' . $sqlMessage;
            }
            throw new SQLQueryExecuteException($message, (int)$e->getCode());
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
        }

        $res = $this->query($this->_insertQuery($data));
        $this->reset();
        return $res->numRows() > 0;
    }

    /**
     * @param array $set
     * @return bool
     */
    public function insert(array $set)
    {
        return $this->create($set);
    }

    /**
     * @param array $set
     * @return bool
     */
    public function createBatch(array $set)
    {
        if($this->_credentials['writable'] === FALSE){
            throw new WritableException('');
        }
        $isCreatedField = !empty($this->_credentials['createdField']);
        if($isCreatedField){
            $createdFieldParameterName = Parameters::add($this->_credentials['createdField'], \date($this->_credentials['timestampFormat']));
        }
        $data = [];
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
        $res = $this->query($this->_insertBatchQuery($data));
        $this->reset();

        return $res->numRows() > 0;
    }


    /**
     * @param array $set
     * @return bool
     */
    public function insertBatch(array $set)
    {
        return $this->createBatch($set);
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
     * @param array $set
     * @param string $referenceColumn
     * @return bool
     */
    public function updateBatch(array $set, string $referenceColumn)
    {
        if($this->_credentials['updatable'] === FALSE){
            throw new UpdatableException('');
        }
        $schemaID = $this->getSchemaID();

        $results = [];
        $setUpdate = [];

        foreach ($set as $data) {
            if(!\is_array($data) || \array_key_exists($referenceColumn, $data)){
                continue;
            }

            $setUpdate[] = $data;
            $this->_validation->setData($data);
            foreach ($data as $column => $value) {
                if($column == $referenceColumn){
                    continue;
                }
                if($this->_validation->validation($column, $schemaID) === FALSE){
                    $this->_errors[] = $this->_validation->getError();
                    return false;
                }
            }

            if(!empty($this->_credentials['updatedField'])){
                $setUpdate[$this->_credentials['updatedField']] = \date($this->_credentials['timestampFormat']);
            }
        }

        $res = $this->query($this->_updateBatchQuery($setUpdate, $referenceColumn));
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

    public function group(\Closure $group, string $logical = 'AND'): self
    {
        $logical = \str_replace(['&&', '||'], ['AND', 'OR'], \strtoupper($logical));
        if(!\in_array($logical, ['AND', 'OR'], true)){
            throw new \InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }

        $clone = clone $this;
        $clone->reset();

        \call_user_func_array($group, [$clone]);

        $where = $clone->_whereQuery();
        if($where !== ''){
            $this->_STRUCTURE['where'][$logical][] = '(' . $where . ')';
        }

        $having = $clone->_havingQuery();
        if($having !== ''){
            $this->_STRUCTURE['having'][$logical][] = '(' . $having . ')';
        }
        unset($clone);
        return $this;
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

    /**
     * QueryBuilder resetlemeden SELECT cümlesi kurar ve satır sayısını döndürür.
     *
     * @return int
     */
    public function count(): int
    {
        $select = $this->_STRUCTURE['select'];
        $this->_STRUCTURE['select'][] = 'COUNT(*) AS row_count';
        $this->_deleteFieldBuild(false);
        $parameters = Parameters::get(false);
        $res = $this->query($this->_readQuery());
        $count = $res->toArray()['row_count'] ?? 0;
        unset($res);
        Parameters::merge($parameters);
        $this->_STRUCTURE['select'] = $select;
        return $count;
    }

    public function pagination(int $page = 1, int $per_page_limit = 10, string $link = '?page={page}'): Pagination
    {
        $total_row = $this->count();
        $this->offset(($page - 1) * $per_page_limit)
            ->limit($per_page_limit);
        $res = $this->query($this->_readQuery());
        $this->reset();

        return new Pagination($res, $page, $per_page_limit, $total_row, $link);
    }

    public function datatables(array $columns, int $method = Datatables::GET_REQUEST): string
    {
        return (new Datatables($this, $columns, $method))->__toString();
    }

    public function _readQuery(): string
    {
        if($this->getSchema() !== null){
            $this->table($this->getSchema());
        }
        $where = $this->_whereQuery();
        if($where !== ''){
            $where = ' WHERE ' . $where;
        }else{
            $where = ' WHERE 1';
        }
        return 'SELECT '
            . (empty($this->_STRUCTURE['select']) ? '*' : \implode(', ', $this->_STRUCTURE['select']))
            . ' FROM ' . \implode(', ', $this->_STRUCTURE['table'])
            . (!empty($this->_STRUCTURE['join']) ? ' ' . \implode(', ', $this->_STRUCTURE['join']) : '')
            . $where
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

    public function _insertBatchQuery($data): string
    {
        $sql = 'INSERT INTO'
            . ' '
            . (empty($this->_STRUCTURE['table']) ? $this->getSchema() : end($this->_STRUCTURE['table']));
        $columns = [];
        $values = [];

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
        $where = $this->_whereQuery();
        if($where !== ''){
            $where = ' WHERE ' . $where;
        }else{
            $where = ' WHERE 1';
        }
        return 'UPDATE '
            . (empty($this->_STRUCTURE['table']) ? $this->getSchema() : end($this->_STRUCTURE['table']))
            . ' SET '
            . \implode(', ', $update)
            . $where
            . $this->_havingQuery()
            . $this->_limitQuery();
    }

    public function _updateBatchQuery(array $data, $referenceColumn): string
    {
        $updateData = []; $columns = []; $where = [];
        $schemaID = $this->getSchemaID();
        foreach ($data as $set) {
            if(!\is_array($set) || !isset($set[$referenceColumn])){
                continue;
            }
            $setData = [];
            foreach ($set as $key => $value) {
                if($key === $schemaID){
                    continue;
                }
                if($this->_credentials['allowedFields'] !== null && $key != $referenceColumn && !\in_array($key, $this->_credentials['allowedFields'])){
                    continue;
                }
                if($key == $referenceColumn){
                    $where[] = $value;
                    continue;
                }
                $setData[$key] = Helper::isSQLParameterOrFunction($value) ? $value : Parameters::add($key, $value);
                if(!\in_array($key, $columns)){
                    $columns[] = $key;
                }
            }
            $updateData[] = $setData;
        }

        $update = [];
        foreach ($columns as $column) {
            $syntax = $column . ' = CASE';
            foreach ($updateData as $key => $values) {
                if(!\array_key_exists($column, $values)){
                    continue;
                }
                $syntax .= ' WHEN ' . $referenceColumn . ' = '
                    . (Helper::isSQLParameterOrFunction($where[$key]) ? $where[$key] : Parameters::add($referenceColumn, $where[$key]))
                    . ' THEN '
                    . $values[$column];
            }
            $syntax .= ' ELSE ' . $column . ' END';
            $update[] = $syntax;
        }
        $this->in($referenceColumn, $where);
        $where = $this->_whereQuery();
        if($where !== ''){
            $where = ' WHERE ' . $where;
        }else{
            $where = ' WHERE 1';
        }
        return 'UPDATE '
            . (empty($this->_STRUCTURE['table']) ? $this->getSchema() : end($this->_STRUCTURE['table']))
            . ' SET '
            . \implode(', ', $update)
            . $where
            . $this->_havingQuery()
            . $this->_limitQuery();
    }

    public function _deleteQuery(): string
    {
        $where = $this->_whereQuery();
        if($where !== ''){
            $where = ' WHERE ' . $where;
        }else{
            $where = ' WHERE 1';
        }
        return 'DELETE FROM'
            . ' '
            . (empty($this->_STRUCTURE['table']) ? $this->getSchema() : end($this->_STRUCTURE['table']))
            . $where
            . $this->_havingQuery()
            . $this->_limitQuery();
    }

    protected function _logCreate(string $message): void
    {
        if(empty($this->_credentials['log'])){
            return;
        }
        if(\is_callable($this->_credentials['log'])){
            \call_user_func_array($this->_credentials['log'], [$message]);
            return;
        }
        if(\is_string($this->_credentials['log'])){
            $path = \strtr($this->_credentials['log'], [
                '{timestamp}'   => \time(),
                '{date}'        => \date("Y-m-d"),
                '{year}'        => \date("Y"),
                '{month}'       => \date("m"),
                '{day}'         => \date("d"),
                '{hour}'        => \date("H"),
                '{minute}'      => \date("i"),
                '{second}'      => \date("s"),
            ]);
            @\file_put_contents($path, $message, \FILE_APPEND);
            return;
        }
        if(\is_object($this->_credentials['log']) && \method_exists($this->_credentials['log'], 'critical')){
            $this->_credentials['log']->critical($message);
        }
    }

    private function _whereQuery(): string
    {
        $isAndEmpty = empty($this->_STRUCTURE['where']['AND']);
        $isOrEmpty = empty($this->_STRUCTURE['where']['OR']);
        if($isAndEmpty && $isOrEmpty){
            return '';
        }
        return (!$isAndEmpty ? \implode(' AND ', $this->_STRUCTURE['where']['AND']) : '')
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
