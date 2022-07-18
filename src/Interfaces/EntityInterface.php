<?php
/**
 * EntityInterface.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.13
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Interfaces;

interface EntityInterface
{
    /**
     * Returns the held data as an associative array.
     *
     * @return array
     */
    public function getAttributes(): array;

}
