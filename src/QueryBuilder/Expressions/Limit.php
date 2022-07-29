<?php
/**
 * Limit.php
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

use InitPHP\Database\Exceptions\QueryBuilderInvalidArgumentException;

trait Limit
{

    private ?int $limit = null;

    private ?int $offset = null;

    /**
     * @inheritDoc
     */
    public function offset(int $offset = 0): self
    {
        $offset = (int)\abs($offset);
        $this->offset = $offset;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function limit(int $limit): self
    {
        if(($limit = (int)\abs($limit)) < 1){
            throw new QueryBuilderInvalidArgumentException('The limit can be at least 1.');
        }
        $this->limit = $limit;
        return $this;
    }

    protected function limitQuery(): string
    {
        if($this->limit === null && $this->offset === null){
            return '';
        }
        return ' LIMIT '
            . ($this->offset !== null ? $this->offset . ', ' : '')
            . ($this->limit !== null ? $this->limit : '1000');
    }

    protected function limitReset()
    {
        $this->limit = null;
        $this->offset = null;
    }

}
