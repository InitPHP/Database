<?php
/**
 * OrderBy.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.9
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\QueryBuilder\Expressions;

use InitPHP\Database\Exceptions\QueryBuilderInvalidArgumentException;

trait OrderBy
{

    private array $orderBy = [];

    /**
     * @inheritDoc
     */
    public function orderBy(string $column, string $soft = 'ASC'): self
    {
        $soft = \trim(\strtoupper($soft));
        if(!\in_array($soft, ['ASC', 'DESC'], true)){
            throw new QueryBuilderInvalidArgumentException('It can only sort as ASC or DESC.');
        }
        $orderBy = \trim($column) . ' ' . $soft;
        if(!\in_array($orderBy, $this->orderBy, true)){
            $this->orderBy[] = $orderBy;
        }
        return $this;
    }

    protected function orderByQuery(): string
    {
        if(empty($this->orderBy)){
            return '';
        }
        return ' ORDER BY ' . \implode(', ', $this->orderBy);
    }

    protected function orderByReset()
    {
        $this->orderBy = [];
    }

}
