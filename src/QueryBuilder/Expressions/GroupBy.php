<?php
/**
 * GroupBy.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.2
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder\Expressions;

trait GroupBy
{

    private array $groupBy = [];

    /**
     * @inheritDoc
     */
    public function groupBy(string ...$column): self
    {
        $this->groupBy = \array_unique(\array_merge($this->groupBy, $column));
        return $this;
    }

    protected function groupByQuery(): string
    {
        if(empty($this->groupBy)){
            return '';
        }
        return 'GROUP BY ' . \implode(', ', $this->groupBy);
    }

    protected function groupByReset()
    {
        $this->groupBy = [];
    }

}
