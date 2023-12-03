<?php

namespace InitPHP\Database\DBAL;

use Exception;
use Closure;
use Throwable;
use PDO;
use \InitPHP\Database\Connection\{Connection, Interfaces\ConnectionInterface};
use InitPHP\Database\DBAL\Exceptions\SQLQueryExecuteException;
use \InitPHP\Database\DBAL\Interfaces\{DatabaseInterface, ResultInterface};
use InitPHP\Database\QueryBuilder\Interfaces\QueryBuilderInterface;

class Database implements DatabaseInterface
{

    private ConnectionInterface $connection;

    private QueryBuilderInterface $builder;
    
    private array $queryOptions = [
        'parameterReset'        => true,
        'queryLog'              => false,
    ];

    private ResultInterface $lastResult;

    private array $queryLogs = [];

    private array $errors = [];

    public function __construct(ConnectionInterface $connection, QueryBuilderInterface $builder)
    {
        $this->connection = $connection;
        $this->builder = $builder;
        $this->queryOptions = array_merge($this->queryOptions, $connection->getCredentials());
    }

    /**
     * @throws Exception
     */
    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->connection, $name)) {
            $res = $this->connection->{$name}(...$arguments);

            return ($res instanceof ConnectionInterface) ? $this : $res;
        }

        if (method_exists($this->builder, $name)) {
            $res = $this->connection->{$name}(...$arguments);

            return ($res instanceof QueryBuilderInterface) ? $this : $res;
        }

        throw new Exception("There is no method called \"" . $name . "\"");
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): array
    {
        return $this->errors ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->builder;
    }

    /**
     * @inheritDoc
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @inheritDoc
     */
    public function builder(): QueryBuilderInterface
    {
        return $this->getQueryBuilder()->newBuilder();
    }

    /**
     * @inheritDoc
     */
    public function newInstance(ConnectionInterface|array $connectionOrCredentials, ?QueryBuilderInterface $builder = null): self
    {
        return new self(($connectionOrCredentials instanceof ConnectionInterface) ? $connectionOrCredentials : new Connection($connectionOrCredentials), $builder ?? $this->builder);
    }

    /**
     * @inheritDoc
     */
    public function get(?string $table = null, ?array $selection = null, ?array $conditions = null): ResultInterface
    {
        !empty($table) && $this->getQueryBuilder()->addFrom($table);
        $res = $this->query($this->getQueryBuilder()->generateSelectQuery($selection ?? [], $conditions ?? []));
        $this->getQueryBuilder()
            ->resetStructure();

        return $res;
    }

    /**
     * @inheritDoc
     */
    public function query(string $rawSQL, ?array $arguments = null, ?array $options = null): ResultInterface
    {
        $arguments = array_merge($this->getQueryBuilder()->getParameter()->all(), ($arguments ?? []));
        $options = array_merge($this->queryOptions, ($options ?? []));

        try {
            $timerStart = $options['profiler'] ? microtime(true) : 0;
            $stmt = $this->getConnection()->getPDO()->prepare($rawSQL);
            if ($stmt === false) {
                throw new SQLQueryExecuteException('The SQL query could not be prepared.');
            }
            if (!empty($arguments)) {
                foreach ($arguments as $key => $value) {
                    $stmt->bindValue($key, $value, $this->__getValueBindType($value));
                }
            }
            if ($options['parameterReset']) {
                $this->getQueryBuilder()
                    ->getParameter()
                    ->reset();
            }
            $execute = $stmt->execute();
            if ($options['queryLog']) {
                $timer = round((microtime(true) - $timerStart), 5);
                $this->queryLogs[] = [
                    'query'     => $rawSQL,
                    'time'      => $timer,
                    'args'      => $arguments,
                ];
            }
            if ($execute === false) {
                throw new SQLQueryExecuteException('The SQL query could not be executed.');
            }
            $errorCode = $stmt->errorCode();
            if($errorCode !== null && !empty(trim($errorCode, "0 \t\n\r\0\x0B"))){
                $errorInfo = $stmt->errorInfo();
                if(isset($errorInfo[2])){
                    $msg = $errorCode . ' - ' . $errorInfo[2];
                    $this->errors[] = $msg;
                    !empty($options['log']) && $this->createLog(($msg . ' SQL : ' . $rawSQL), $options['log']);
                }
            }
            $this->lastResult = new Result($stmt);

            return !empty($options['fetch_mode'])
                ? $this->lastResult->setFetchMode($options['fetch_mode'])
                : $this->lastResult;

        } catch (Exception $e) {
            $message = $e->getMessage();
            $sqlQuery = empty($arguments) ? $rawSQL : strtr($rawSQL, $arguments);
            !empty($options['log']) && $this->createLog(($message . ' SQL : ' . $sqlQuery), $options['log']);
            if ($options['debug'] === true) {
                $message .= ' SQL : ' . $sqlQuery;
            }
            if ($options['parameterReset']) {
                $this->getQueryBuilder()
                    ->getParameter()
                    ->reset();
            }
            throw new SQLQueryExecuteException($message, (int)$e->getCode());
        }
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        $builder = $this->getQueryBuilder()->clone()
            ->resetStructure('select', false);

        $builder->selectCount('*', 'row_count');

        $res = $this->query($builder->generateSelectQuery(), null, [
            'parameterReset'        => false,
        ]);

        return $res->asAssoc()->row()['row_count'] ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function insertId(): int
    {
        $id = $this->getConnection()->getPDO()->lastInsertId();

        return $id === false ? 0 : (int)$id;
    }


    /**
     * @inheritDoc
     */
    public function enableQueryLog(): DatabaseInterface
    {
        $this->queryOptions['queryLog'] = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function disableQueryLog(): DatabaseInterface
    {
        $this->queryOptions['queryLog'] = false;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getQueryLogs(): array
    {
        return $this->queryLogs;
    }

    /**
     * @inheritDoc
     */
    public function transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool
    {
        $attempt < 1 && $attempt = 1;
        $res = false;
        for ($i = 0; $i < $attempt; ++$i) {
            try {
                $this->getConnection()->beginTransaction($testMode);
                call_user_func_array($closure, [$this]);
                $res = $testMode ? $this->getConnection()->rollBack() : $this->getConnection()->commit();
                break;
            } catch (Throwable $e) {
                $res = $this->getConnection()->rollBack();
                $this->createLog('Transaction Rollback : ' . $e->getMessage());
            }
        }

        return $res;
    }

    protected function __getValueBindType($value): int
    {
        return match (true) {
            is_bool($value), is_int($value) => PDO::PARAM_INT,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    private function createLog(string $message, mixed $handler = null): void
    {
        $handler === null && $handler = $this->getConnection()->getCredentials('log');
        if (empty($handler)) {
            return;
        }
        if (is_callable($handler)) {
            call_user_func_array($handler, [$message]);
        } else if (is_object($handler) && method_exists($handler, 'critical')) {
            $handler->critical($message);
        } else if (is_string($handler)) {
            $path = strtr($handler, [
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
        }

    }

}
