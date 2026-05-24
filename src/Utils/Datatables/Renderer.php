<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitPHP\Database\Utils\Datatables;

use Closure;

/**
 * Registry of per-column render callbacks applied to a result set just before
 * it is handed to the client. Pulled out of {@see Datatables} so the
 * transformation step can be unit-tested in isolation.
 *
 * Each callback receives {@code ($value, array &$row)} — the current cell
 * value and a reference to the full row (so a renderer can inspect or mutate
 * sibling columns when it returns the new cell value).
 */
final class Renderer
{
    /**
     * @var array<string, Closure>
     */
    private array $renders = [];

    /**
     * Register a render callback for {@code $column}. Overwrites any previous
     * binding for the same column.
     */
    public function add(string $column, Closure $render): void
    {
        $this->renders[$column] = $render;
    }

    /**
     * True when at least one column has a registered renderer. Lets the
     * caller skip the apply() loop when there is nothing to do.
     */
    public function hasAny(): bool
    {
        return $this->renders !== [];
    }

    /**
     * Apply every registered renderer to {@code $rows} in place. Rows that
     * are not associative arrays (e.g. objects produced by fetch-class mode)
     * are passed through untouched.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $rows): array
    {
        if (!$this->hasAny() || $rows === []) {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $column => $value) {
                if (!isset($this->renders[$column])) {
                    continue;
                }
                $row[$column] = ($this->renders[$column])($value, $row);
            }
            $rows[$index] = $row;
        }

        return $rows;
    }
}
