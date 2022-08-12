<?php
/**
 * From.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.12
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder\Expressions;

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

    protected function endTableSchema(): ?string
    {
        if(empty($this->tables)){
            return null;
        }
        \end($this->tables);
        return \current($this->tables);
    }

    protected function fromQuery(): string
    {
        if(($schema = $this->db->getSchema()) !== null){
            if(!\in_array($schema, $this->tables, true)){
                \array_unshift($this->tables, $schema);
            }
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
        return \in_array($table, $this->tables, true) || $this->db->getSchema() == $table;
    }

    protected function fromReset()
    {
        $this->tables = [];
    }

}
