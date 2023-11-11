<?php
/**
 * Database
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.1
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

use InitPHP\Database\Utils\{Pagination,
    Datatables};
use InitPHP\Database\Exceptions\{QueryBuilderException,
    QueryGeneratorException,
    WritableException,
    ReadableException,
    UpdatableException,
    DeletableException,
    SQLQueryExecuteException,
    ConnectionException};
use \InitPHP\Database\Helpers\{Helper, Parameters, Validation};
use \PDO;
use \RuntimeException;
use \PDOException;
use \Exception;
use \InvalidArgumentException;
use \Closure;

use function microtime;
use function round;
use function count;
use function current;
use function substr;
use function array_merge;
use function is_numeric;
use function is_bool;
use function is_string;
use function is_int;
use function is_null;
use function is_callable;
use function is_object;
use function is_array;
use function in_array;
use function trim;
use function ltrim;
use function stripslashes;
use function serialize;
use function strtr;
use function str_replace;
use function strtoupper;
use function call_user_func_array;
use function file_put_contents;
use function time;
use function date;
use function method_exists;

use const COUNT_RECURSIVE;
use const FILE_APPEND;

class Database extends QueryBuilder
{

    public const ENTITY = 0;
    public const ASSOC = 1;
    public const ARRAY = 2;
    public const OBJECT = 3;
    public const LAZY = 4;

    private PDO $_pdo;

    private bool $isGlobal = false;

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
        'profiler'              => false,
    ];

    private Result $_last;

    private array $_transaction = [
        'status'    => false,
        'enable'    => false,
        'testMode'  => false,
    ];

    private array $_errors = [];

    private array $_queryLogs = [];

    private Validation $_validation;

    public function __construct(array $credentials = [])
    {
        $this->setCredentials($credentials);
        $this->_validation = new Validation($this->_credentials['validation']['methods'], $this->_credentials['validation']['messages'], $this->_credentials['validation']['labels'], $this);
    }

    public function __call($name, $arguments)
    {
        if(Helper::str_starts_with($name, 'findBy') === FALSE){
            throw new RuntimeException('There is no "' . $name . '" method.');
        }
        $this->where(Helper::camelCaseToSnakeCase(substr($name, 6)), current($arguments));

        return $this;
    }

    final public function newInstance(array $credentials = []): Database
    {
        $instance = new Database(empty($credentials) ? $this->_credentials : array_merge($this->_credentials, $credentials));
        $instance->isGlobal = false;

        return $instance;
    }

    final public function clone(): Database
    {
        $clone = new self($this->_credentials);
        $clone->isGlobal = $this->isGlobal;

        return $clone;
    }

    final public function enableQueryProfiler(): void
    {
        $this->_credentials['profiler'] = true;
    }

    final public function disableQueryProfiler(): void
    {
        $this->_credentials['profiler'] = false;
    }

    final public function getProfilerQueries(): array
    {
        return $this->_queryLogs;
    }

    final public function setCredentials(array $credentials): self
    {
        $this->_credentials = array_merge($this->_credentials, $credentials);
        return $this;
    }

    final public function getCredentials(?string $key = null)
    {
        return ($key === null) ? $this->_credentials : ($this->_credentials[$key] ?? null);
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
        $this->isGlobal = true;
    }

    final public function getPDO(): PDO
    {
        if(isset(self::$_globalPDO) && $this->isGlobal){
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
            } catch (PDOException $e) {
                throw new ConnectionException($e->getMessage(), (int)$e->getCode());
            }
        }

        return $this->_pdo;
    }

    final public function withPDO(PDO $pdo): self
    {
        $with = clone $this;
        $with->_pdo = $pdo;
        $with->isGlobal = false;

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

    final public function transaction(Closure $closure): bool
    {
        try {
            $this->beginTransaction(false);
            call_user_func_array($closure, [$this]);
            $res = $this->completeTransaction();
        } catch (Exception $e) {
            $res = $this->rollBack();
        }

        return $res;
    }

    final public function connection(array $credentials = []): self
    {
        return $this->newInstance($credentials);
    }

    /**
     * @param $value
     * @return false|float|int|string
     */
    final public function escape_str($value)
    {
        if(is_numeric($value)){
            return $value;
        }
        if(is_bool($value)){
            return $value === FALSE ? 'FALSE' : 'TRUE';
        }
        if($value === null){
            return 'NULL';
        }
        if(is_string($value)){
            $value = str_replace("\\", "", trim(stripslashes($value), "' \\\t\n\r\0\x0B"));
            return "'" . str_replace("'", "\\'", $value) . "'";
        }
        if(is_object($value)){
            return serialize($value);
        }
        if(is_array($value)){
            return serialize($value);
        }
        return false;
    }

    /**
     * @param string $sqlQuery
     * @param array $parameters
     * @return Result
     */
    final public function query(string $sqlQuery, array $parameters = []): Result
    {
        $arguments = Parameters::get(true);
        $parameters = empty($parameters) ? $arguments : array_merge($arguments, $parameters);
        try {
            $timerStart = $this->_credentials['profiler'] ? microtime(true) : 0;
            $stmt = $this->getPDO()->prepare($sqlQuery);
            if($stmt === FALSE){
                throw new Exception('The SQL query could not be prepared.');
            }
            if(!empty($parameters)){
                foreach ($parameters as $key => $value) {
                    $stmt->bindValue(':' . ltrim($key, ':'), $value, $this->_bind($value));
                }
            }
            $execute = $stmt->execute();
            if ($this->_credentials['profiler']) {
                $timer = round((microtime(true) - $timerStart), 5);
                $this->_queryLogs[] = [
                    'query'     => $sqlQuery,
                    'time'      => $timer,
                    'args'      => $parameters,
                ];
            }
            if($execute === FALSE){
                throw new Exception('The SQL query could not be executed.');
            }
            $errorCode = $stmt->errorCode();
            if($errorCode !== null && !empty(trim($errorCode, "0 \t\n\r\0\x0B"))){
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
                    return $this->_last->asEntity($this->_credentials['entity'] ?? Entity::class);
                case self::OBJECT:
                    return $this->_last->asObject();
                case self::LAZY:
                    return $this->_last->asLazy();
                default:
                    return $this->_last;
            }
        } catch (Exception $e) {
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
     * @throws QueryGeneratorException
     */
    final public function get(?string $table = null): Result
    {
        if(!empty($table)){
            $this->addFrom($table);
        }
        $res = $this->query($this->generateSelectQuery());
        $this->reset();

        return $res;
    }

    /**
     * @param array|null $set
     * @return bool
     * @throws QueryBuilderException
     * @throws QueryGeneratorException
     */
    public function create(?array $set = null)
    {
        if($this->_credentials['writable'] === FALSE){
            throw new WritableException('');
        }

        if($set !== null && count($set) === count($set, COUNT_RECURSIVE)){
            $this->_validation->setData($set);
            foreach ($set as $column => $value) {
                if($this->_validation->validation($column, null) === FALSE){
                    $this->_errors[] = $this->_validation->getError();
                    return false;
                }
                $this->set($column, $value);
            }
        }

        $res = $this->query($this->generateInsertQuery());
        $this->reset();

        return $res->numRows() > 0;
    }

    /**
     * @param array|null $set
     * @return bool
     * @throws QueryBuilderException
     * @throws QueryGeneratorException
     */
    public function insert(?array $set = null)
    {
        return $this->create($set);
    }

    /**
     * @param array|null $set
     * @return bool
     * @throws QueryBuilderException
     * @throws QueryGeneratorException
     */
    public function createBatch(?array $set = null)
    {
        if($this->_credentials['writable'] === FALSE){
            throw new WritableException('');
        }

        if ($set !== null && count($set) !== count($set, COUNT_RECURSIVE)) {
            foreach ($set as $row) {
                $data = [];
                $this->_validation->setData($row);
                foreach ($row as $column => $value) {
                    if ($this->_validation->validation($column, null) === FALSE) {
                        $this->_errors[] = $this->_validation->getError();
                        return false;
                    }
                    $data[$column] = $value;
                }
                if (empty($data)) {
                    continue;
                }
                $this->set($data);
            }
        }

        $res = $this->query($this->generateBatchInsertQuery());
        $this->reset();

        return $res->numRows() > 0;
    }


    /**
     * @param array $set
     * @return bool
     * @throws QueryBuilderException
     * @throws QueryGeneratorException
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
        Parameters::merge($parameters);
        $query = $this->generateSelectQuery($selector, $conditions);

        $res = $this->query($query);
        $this->reset();

        return $res;
    }

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return Result
     * @throws QueryGeneratorException
     */
    public function readOne(array $selector = [], array $conditions = [], array $parameters = [])
    {
        if($this->_credentials['readable'] === FALSE){
            throw new ReadableException('');
        }
        Parameters::merge($parameters);
        $query = $this->limit(1)
            ->generateSelectQuery($selector, $conditions);

        $res = $this->query($query);
        $this->reset();

        return $res;
    }

    /**
     * @param array|null $set
     * @return bool
     * @throws QueryBuilderException
     * @throws QueryGeneratorException
     */
    public function update(?array $set = null)
    {
        if($this->_credentials['updatable'] === FALSE){
            throw new UpdatableException('');
        }
        if ($set !== null) {
            $schemaID = $this->getSchemaID();
            $this->_validation->setData($set);
            foreach ($set as $column => $value) {
                if ($this->_validation->validation($column, $schemaID) === FALSE) {
                    $this->_errors[] = $this->_validation->getError();
                    return false;
                }
            }
            $this->set($set);
        }

        $res = $this->query($this->generateUpdateQuery());
        $this->reset();

        return $res->numRows() > 0;
    }

    /**
     * @param string $referenceColumn
     * @param array|null $set
     * @return bool
     * @throws Exceptions\QueryBuilderException
     * @throws Exceptions\QueryGeneratorException
     */
    public function updateBatch(string $referenceColumn, ?array $set = null)
    {
        if($this->_credentials['updatable'] === FALSE){
            throw new UpdatableException('');
        }

        if ($set !== null) {
            foreach ($set as $data) {
                $this->_validation->setData($data);
                foreach ($data as $column => $value) {
                    if (in_array($column, [$this->getSchemaID(), $referenceColumn])) {
                        continue;
                    }
                    if ($this->_validation->validation($column, $this->getSchemaID()) === FALSE) {
                        $this->_errors[] = $this->_validation->getError();
                        return false;
                    }
                }
                $this->set($data);
            }
        }

        $res = $this->query($this->generateUpdateBatchQuery($referenceColumn));
        $this->reset();

        return $res->numRows() > 0;
    }


    /**
     * @param array $conditions
     * @return bool
     * @throws QueryBuilderException
     * @throws QueryGeneratorException
     */
    public function delete(array $conditions = [])
    {
        if($this->_credentials['deletable'] === FALSE){
            throw new DeletableException('');
        }
        foreach ($conditions as $column => $value) {
            if (is_string($column)) {
                $this->where($column, $value);
            } else {
                $this->where($value);
            }
        }
        if(!empty($this->_credentials['deletedField'])){
            if($this->isOnlyDeletes !== FALSE){
                $this->isNot($this->_credentials['deletedField'], null);
                $query = $this->generateDeleteQuery();
                $this->isOnlyDeletes = false;
            }else{
                $this->is($this->_credentials['deletedField'], null)
                    ->set($this->_credentials['deletedField'], date($this->_credentials['timestampFormat']), false);
                $query = $this->generateUpdateQuery();
            }
        }else{
            $query = $this->generateDeleteQuery();
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
    public function all(int $limit = 100, int $offset = 0): Result
    {
        return $this->limit($limit)
                ->offset($offset)
                ->read();
    }

    /**
     * @return Result
     * @throws QueryGeneratorException
     */
    public function first()
    {
        return $this->readOne();
    }

    public function group(Closure $group, string $logical = 'AND'): self
    {
        $logical = str_replace(['&&', '||'], ['AND', 'OR'], strtoupper($logical));
        if(!in_array($logical, ['AND', 'OR'], true)){
            throw new InvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }

        $clone = clone $this;
        $clone->reset();

        call_user_func_array($group, [$clone]);

        if($where = $clone->__generateWhereQuery()){
            $this->_STRUCTURE['where'][$logical][] = '(' . $where . ')';
        }

        if($having = $clone->__generateHavingQuery()){
            $this->_STRUCTURE['having'][$logical][] = '(' . $having . ')';
        }
        unset($clone);
        return $this;
    }

    public function onlyDeleted(): self
    {
        $this->isOnlyDeletes = true;

        return $this;
    }

    public function onlyUndeleted(): self
    {
        $this->isOnlyDeletes = false;

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
        $this->_STRUCTURE['select'] = ['COUNT(*) AS row_count'];
        $this->__generateSoftDeleteQuery(false);
        $parameters = Parameters::get(false);
        $res = $this->query($this->generateSelectQuery());
        $count = $res->toArray()['row_count'] ?? 0;
        unset($res);
        Parameters::merge($parameters);
        $this->_STRUCTURE['select'] = $select;

        return (int)$count;
    }

    public function pagination(int $page = 1, int $per_page_limit = 10, string $link = '?page={page}'): Pagination
    {
        $total_row = $this->count();
        $this->offset(($page - 1) * $per_page_limit)
            ->limit($per_page_limit);
        $res = $this->query($this->generateSelectQuery());
        $this->reset();

        return new Pagination($res, $page, $per_page_limit, $total_row, $link);
    }

    public function datatables(array $columns, int $method = Datatables::GET_REQUEST): string
    {
        return (new Datatables($this, $columns, $method))->__toString();
    }

    final public function isAllowedFields(string $column): bool
    {
        return empty($this->_credentials['allowedFields']) || in_array($column, $this->_credentials['allowedFields']);
    }

    protected function _logCreate(string $message): void
    {
        if(empty($this->_credentials['log'])){
            return;
        }
        if(is_callable($this->_credentials['log'])){
            call_user_func_array($this->_credentials['log'], [$message]);
            return;
        }
        if(is_string($this->_credentials['log'])){
            $path = strtr($this->_credentials['log'], [
                '{timestamp}'   => time(),
                '{date}'        => date("Y-m-d"),
                '{year}'        => date("Y"),
                '{month}'       => date("m"),
                '{day}'         => date("d"),
                '{hour}'        => date("H"),
                '{minute}'      => date("i"),
                '{second}'      => date("s"),
            ]);
            @file_put_contents($path, $message, FILE_APPEND);
            return;
        }
        if(is_object($this->_credentials['log']) && method_exists($this->_credentials['log'], 'critical')){
            $this->_credentials['log']->critical($message);
        }
    }

    private function _bind($value)
    {
        if(is_bool($value) || is_int($value)){
            return PDO::PARAM_INT;
        }
        if(is_null($value)){
            return PDO::PARAM_NULL;
        }
        return PDO::PARAM_STR;
    }

}
