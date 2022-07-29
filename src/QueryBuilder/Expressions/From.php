<?php
/**
 * From.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.3
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder\Expressions;

use InitPHP\Database\Exceptions\QueryBuilderException;
use InitPHP\Database\Helper;

trait From
{

    private array $tables = [];

    /**
     * @inheritDoc
     */
    public function from(string $table): self
    {
        $table = Helper::queryBuilderFromCheck($table);
        $this->fromAppend($table);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function table(string $table): self
    {
        return $this->from($table);
    }

    protected function endTableSchema(): string
    {
        if(empty($this->tables)){
            throw new QueryBuilderException('The table name could not be found to build the operation.');
        }
        return \end($this->tables);
    }

    protected function fromQuery(): string
    {
        if(($schema = $this->getSchema()) !== null){
            \array_unshift($this->tables, $schema);
        }
        return ' FROM ' . \implode(', ', $this->tables);
    }

    protected function fromAppend($table)
    {
        if(empty($table)){
            return;
        }
        if(\is_string($table)){
            $table = \trim($table);
            if(!$this->hasFrom($table)){
                $this->tables[] = $table;
            }
            return;
        }
        if(\is_array($table)){
            foreach ($table as $row) {
                $this->fromAppend((string)$row);
            }
            return;
        }
    }

    protected function hasFrom(string $table): bool
    {
        return \in_array($table, $this->tables, true) || $this->getSchema() == $table;
    }

    protected function fromReset()
    {
        $this->tables = [];
    }

}
