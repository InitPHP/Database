<?php
/**
 * Raw
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.5
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

final class Raw
{

    private string $raw;

    public function __construct(string $rawQuery)
    {
        $this->raw = \trim($rawQuery);
    }

    public function __toString(): string
    {
        return $this->get();
    }

    public function get(): string
    {
        return $this->raw;
    }

    public static function raw(string $sqlQuery): self
    {
        return new self($sqlQuery);
    }

}
