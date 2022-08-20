<?php
/**
 * Select.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.13
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder\Expressions;

use InitPHP\Database\Helper;

trait Select
{

    private array $selector = [];

    /**
     * @inheritDoc
     */
    public function select(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->selectorsPush($column);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCount(string $column): self
    {
        $this->selectorsPush($column, null, 'COUNT');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMax(string $column): self
    {
        $this->selectorsPush($column, null, 'MAX');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMin(string $column): self
    {
        $this->selectorsPush($column, null, 'MIN');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAvg(string $column): self
    {
        $this->selectorsPush($column, null, 'AVG');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectAs(string $column, string $alias): self
    {
        $this->selectorsPush($column, $alias);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectUpper(string $column): self
    {
        $this->selectorsPush($column, null, 'UPPER', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLower(string $column): self
    {
        $this->selectorsPush($column, null, 'LOWER', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLength(string $column): self
    {
        $this->selectorsPush($column, null, 'LENGTH', '{function}({column})');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectMid(string $column, int $offset, int $length): self
    {
        $this->selectorsPush($column, null, 'MID', '{function}({column}, ' . $offset . ', ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectLeft(string $column, int $length): self
    {
        $this->selectorsPush($column, null, 'LEFT', '{function}({column}, ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectRight(string $column, int $length): self
    {
        $this->selectorsPush($column, null, 'RIGHT', '{function}({column}, ' . $length . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectDistinct(string $column): self
    {
        $this->selectorsPush($column, null, 'DISTINCT');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectCoalesce(string $column, $default = '0'): self
    {
        if(!\is_numeric($default)){
            if((bool)\preg_match('/^[a-zA-Z\d]+\.[a-zA-Z\d]+$/', (string)$default) === FALSE){
                $default = "'" . \str_replace("'", "\'", \trim($default, "\\'\" \r\n")) . "'";
            }
        }
        $this->selectorsPush($column, null, 'COALESCE', '{function}({column}, ' . $default . ')');
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function selectSum(string $column): self
    {
        $this->selectorsPush($column, null, 'SUM');
        return $this;
    }

    private function selectorsPush($column, ?string $alias = null, ?string $fn = null, ?string $pattern = null): void
    {
        if(\is_array($column)){
            foreach ($column as $item) {
                $this->selectorsPush($item, $alias, $fn, $pattern);
            }
            return;
        }
        $column = \trim((string)$column);
        if($column == ''){
            return;
        }
        if(Helper::str_contains($column, ',')){
            $columns = \explode(',', $column);
            $this->selectorsPush($columns, $alias, $fn, $pattern);
            return;
        }
        if(($split = Helper::queryBuilderAliasParse($column, [' as '])) !== FALSE){
            $this->selectorsPush($split[0], $split[1], $fn, $pattern);
            return;
        }
        if(((bool)\preg_match('/([\w_]+)\((.+)\)$/iu', $column, $matches)) !== FALSE){
            $this->selectorsPush($matches[2], $alias, $matches[1], $pattern);
            return;
        }
        if($alias !== null){
            $alias = \trim($alias);
        }
        $select = $column;
        if(!empty($fn)){
            if(!empty($pattern)){
                $select = \str_replace(['{column}', '{function}'], [$select, \strtoupper($fn)], $pattern);
            }else{
                $select = \strtoupper($fn) . '(' . $select . ')';
            }
        }
        if(!empty($alias)){
            $select .= ' AS ' . $alias;
        }
        if(\in_array($select, $this->selector, true)){
            return;
        }
        $this->selector[] = $select;
    }


    protected function selectorQuery(): string
    {
        $sql = 'SELECT ';
        if(empty($this->selector)){
            return $sql . '*';
        }
        return $sql . \implode(', ', $this->selector);
    }

    protected function selectorReset(): void
    {
        $this->selector = [];
    }

    protected function selectorAppend($selector): void
    {
        if(empty($selector)){
            return;
        }
        if(\is_string($selector)){
            $this->selector[] = $selector;
            return;
        }
        if(\is_array($selector)){
            $this->selector = \array_merge($this->selector, $selector);
            return;
        }
    }

}
