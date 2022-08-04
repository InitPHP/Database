<?php
/**
 * Where.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.7
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder\Expressions;

use InitPHP\Database\Exceptions\QueryBuilderInvalidArgumentException;
use InitPHP\Database\Helper;

trait WhereAndHaving
{

    private array $wheres = [
        'AND'   => [],
        'OR'    => [],
    ];

    private array $having = [
        'AND'   => [],
        'OR'    => [],
    ];

    /**
     * @inheritDoc
     */
    public function where(string $column, $value, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = \str_replace(['&&', '||'], ['AND', 'OR'], \strtoupper($logical));
        if(\in_array($logical, ['AND', 'OR'], true) === FALSE){
            throw new QueryBuilderInvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->wheres[$logical][] = $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function andWhere(string $column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'AND');
    }

    /**
     * @inheritDoc
     */
    public function orWhere(string $column, $value, string $mark = '='): self
    {
        return $this->where($column, $value, $mark, 'OR');
    }

    /**
     * @inheritDoc
     */
    public function having(string $column, $value, string $mark = '=', string $logical = 'AND'): self
    {
        $logical = str_replace(['&&', '||'], ['AND', 'OR'], strtoupper($logical));
        if(in_array($logical, ['AND', 'OR'], true) === FALSE){
            throw new QueryBuilderInvalidArgumentException('Logical operator OR, AND, && or || it could be.');
        }
        $this->having[$logical][] = $this->whereOrHavingStatementPrepare($column, $value, $mark);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function between(string $column, array $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'BETWEEN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'BETWEEN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notBetween(string $column, array $values, string $logical = 'AND'): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotBetween(string $column, array $values): self
    {
        return $this->where($column, $values, 'NOTBETWEEN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function findInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'FINDINSET', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'FINDINSET', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notFindInSet(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', $logical);
    }

    /**
     * @inheritDoc
     */
    public function andNotFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function orNotFindInSet(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTFINDINSET', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function in(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orIn(string $column, $value): self
    {
        return $this->where($column, $value, 'IN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andIn(string $column, $value): self
    {
        return $this->where($column, $value, 'IN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notIn(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTIN', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotIn(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTIN', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function regexp(string $column, string $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'REGEXP', $logical);
    }

    /**
     * @inheritDoc
     */
    public function andRegexp(string $column, string $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function orRegexp(string $column, string $value): self
    {
        return $this->where($column, $value, 'REGEXP', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function like(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'LIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orLike(string $column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andLike(string $column, $value): self
    {
        return $this->where($column, $value, 'LIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function startLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function endLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function notLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'NOTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'NOTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function startNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andStartNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'STARTNOTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function endNotLike(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andEndNotLike(string $column, $value): self
    {
        return $this->where($column, $value, 'ENDNOTLIKE', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function soundex(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'SOUNDEX', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orSoundex(string $column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andSoundex(string $column, $value): self
    {
        return $this->where($column, $value, 'SOUNDEX', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function is(string $column, $value, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'IS', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orIs(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andIs(string $column, $value = null): self
    {
        return $this->where($column, $value, 'IS', 'AND');
    }

    /**
     * @inheritDoc
     */
    public function isNot(string $column, $value = null, string $logical = 'AND'): self
    {
        return $this->where($column, $value, 'ISNOT', $logical);
    }

    /**
     * @inheritDoc
     */
    public function orIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'OR');
    }

    /**
     * @inheritDoc
     */
    public function andIsNot(string $column, $value = null): self
    {
        return $this->where($column, $value, 'ISNOT', 'AND');
    }

    protected function whereAppend($wheres)
    {
        if(empty($wheres)){
            return;
        }
        if(\is_string($wheres)){
            $this->wheres['AND'][] = $wheres;
            return;
        }
        if(\is_array($wheres)){
            $this->wheres['AND'] = \array_merge($this->wheres['AND'], $wheres);
            return;
        }
    }

    protected function whereQuery(): string
    {
        $sql = ' WHERE ';
        $isAndEmpty = empty($this->wheres['AND']);
        $isOrEmpty = empty($this->wheres['OR']);

        if($isAndEmpty && $isOrEmpty){
            return $sql . '1';
        }

        $sql .= (!$isAndEmpty ? \implode(' AND ', $this->wheres['AND']) : '')
            . ((!$isAndEmpty && !$isOrEmpty) ? ' AND ' : '')
            . (!$isOrEmpty ? \implode(' OR ', $this->wheres['OR']) : '');

        return $sql;
    }

    protected function havingQuery(): string
    {
        $isAndEmpty = empty($this->having['AND']);
        $isOrEmpty = empty($this->having['OR']);
        if($isAndEmpty && $isOrEmpty){
            return '';
        }
        return ' HAVING '
            . (!$isAndEmpty ? \implode(' AND ', $this->having['AND']) : '')
            . ((!$isAndEmpty && !$isOrEmpty) ? ' AND ' : '')
            . (!$isOrEmpty ? \implode(' OR ', $this->having['OR']) : '');
    }

    protected function whereReset(): void
    {
        $this->wheres = [
            'AND'   => [],
            'OR'    => [],
        ];
    }

    protected function havingReset(): void
    {
        $this->having = [
            'AND'   => [],
            'OR'    => [],
        ];
    }

    private function whereOrHavingStatementPrepare(string $column, $value, string $mark = '='): string
    {
        $mark = \trim($mark);
        if(\in_array($mark, ['=', '!=', '>=', '<=', '<', '>'], true)){
            return $column . ' ' . $mark . ' ' . Helper::queryBindParameter($value);
        }
        $markUpperCase = \strtoupper($mark);
        switch (\str_replace([' ', '_'], '', $markUpperCase)) {
            case 'IS':
                return $column . ' IS ' . Helper::queryBindParameter($value, '{value}');
            case 'ISNOT':
                return $column . ' IS NOT ' . Helper::queryBindParameter($value, '{value}');
            case 'LIKE':
                return $column . ' LIKE ' . Helper::queryBindParameter($value, '%{value}%');
            case 'STARTLIKE':
                return $column . ' LIKE ' . Helper::queryBindParameter($value, '%{value}');
            case 'ENDLIKE':
                return $column . ' LIKE ' . Helper::queryBindParameter($value, '{value}%');
            case 'NOTLIKE':
                return $column . ' NOT LIKE ' . Helper::queryBindParameter($value, '%{value}%');
            case 'STARTNOTLIKE':
                return $column . ' NOT LIKE ' . Helper::queryBindParameter($value, '%{value}');
            case 'ENDNOTLIKE':
                return $column . ' NOT LIKE ' . Helper::queryBindParameter($value, '{value}%');
            case 'REGEXP':
                return $column . ' REGEXP ' . Helper::queryBindParameter($value, '{value}');
            case 'BETWEEN':
                return $column . ' BETWEEN '
                    . Helper::queryBindParameter($value[0], '{value}')
                    . ' AND ' . Helper::queryBindParameter($value[1], '{value}');
            case 'NOTBETWEEN':
                return $column . ' NOT BETWEEN '
                    . Helper::queryBindParameter($value[0], '{value}')
                    . ' AND ' . Helper::queryBindParameter($value[1], '{value}');
            case 'IN':
                return $column . ' IN '
                    . (\is_array($value) ? '(' . \implode(', ', $value) . ')' : Helper::queryBindParameter($value, '({value})'));
            case 'NOTIN':
                return $column . ' NOT IN '
                    . (\is_array($value) ? '(' . \implode(', ', $value) . ')' : Helper::queryBindParameter($value, '({value})'));
            case 'FINDINSET':
                return 'FIND_IN_SET('
                . \is_array($value) ? "'" . \implode(', ', $value) . "'" : Helper::queryBindParameter($value, '{value}')
                    . ', ' . $column . ')';
            case 'NOTFINDINSET':
                return 'NOT FIND_IN_SET('
                . \is_array($value) ? "'" . \implode(', ', $value) . "'" : Helper::queryBindParameter($value, '{value}')
                    . ', ' . $column . ')';
            case 'SOUNDEX':
                return "SOUNDEX(" . $column . ") LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(" . Helper::queryBindParameter($value, '{value}') . ")), '%')";
        }

        if(((bool)\preg_match('/([\w\_]+)\((.+)\)$/iu', $column, $matches)) !== FALSE){
            return \strtoupper($matches[1]) . '(' . $matches[2] . ')';
        }

        return $column . ' ' . $mark . ' ' . Helper::queryBindParameter($value, '{value}');
    }

}