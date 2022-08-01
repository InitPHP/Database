<?php
/**
 * Join.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.5
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder\Expressions;

use InitPHP\Database\Exceptions\QueryBuilderInvalidArgumentException;
use InitPHP\Database\Helper;

trait Join
{

    private array $join = [];

    /**
     * @inheritDoc
     */
    public function join(string $table, string $onStmt, string $type = 'INNER'): self
    {
        $type = \trim(\strtoupper($type));
        if(!\in_array($type, self::SUPPORTED_JOIN_TYPES, true)){
            throw new QueryBuilderInvalidArgumentException($type . ' Join type is not supported.');
        }
        $table = Helper::queryBuilderFromCheck($table);
        if(\is_array($table)){
            throw new QueryBuilderInvalidArgumentException('Join method cannot join more than one table at the same time.');
        }
        if(isset($this->join[$table]) || $table == $this->getSchema() || $this->hasFrom($table)){
            return $this;
        }
        if($type == 'NATURAL'){
            $this->join[$table] = 'NATURAL JOIN ' . $table;
            return $this;
        }
        $onStmt = \str_replace(' = ', '=', $onStmt);
        if((bool)\preg_match('/([\w\_\-]+)\.([\w\_\-]+)=([\w\_\-]+)\.([\w\_\-]+)/u', $onStmt, $matches) === FALSE){
            throw new QueryBuilderInvalidArgumentException('Join syntax is not in the correct format. Example : "post.author=user.id". Give : "' . $onStmt . '"');
        }
        $onStmt = $matches[1] . '.' . $matches[2] . '=' . $matches[3] . '.' . $matches[4];
        if($type == 'SELF'){
            $this->fromAppend($table);
            $this->whereAppend($onStmt);
        }else{
            $this->join[$table] = $type . ' JOIN ' . $table . ' ON ' . $onStmt;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selfJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'SELF');
    }

    /**
     * @inheritDoc
     */
    public function innerJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'INNER');
    }

    /**
     * @inheritDoc
     */
    public function leftJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT');
    }

    /**
     * @inheritDoc
     */
    public function rightJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT');
    }

    /**
     * @inheritDoc
     */
    public function leftOuterJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'LEFT OUTER');
    }

    /**
     * @inheritDoc
     */
    public function rightOuterJoin(string $table, string $onStmt): self
    {
        return $this->join($table, $onStmt, 'RIGHT OUTER');
    }

    /**
     * @inheritDoc
     */
    public function naturalJoin(string $table): self
    {
        return $this->join($table, '', 'NATURAL');
    }

    protected function joinQuery(): string
    {
        return !empty($this->join) ? ' ' . \implode(' ', $this->join) : '';
    }

    protected function joinReset()
    {
        $this->join = [];
    }

}
