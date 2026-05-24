<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitPHP\Database\Utils\Datatables;

use InitPHP\Database\Utils\Datatables\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    public function testEmptyRendererIsANoOp(): void
    {
        $renderer = new Renderer();
        $rows     = [['id' => 1, 'name' => 'Alice']];

        self::assertFalse($renderer->hasAny());
        self::assertSame($rows, $renderer->apply($rows));
    }

    public function testRegisteredCallbackTransformsTheTargetColumn(): void
    {
        $renderer = new Renderer();
        $renderer->add('name', fn (string $value): string => strtoupper($value));

        $output = $renderer->apply([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        self::assertSame('ALICE', $output[0]['name']);
        self::assertSame('BOB', $output[1]['name']);
        self::assertSame(1, $output[0]['id']);
    }

    public function testCallbackReceivesTheFullRow(): void
    {
        $renderer = new Renderer();
        $renderer->add('full_name', static fn (?string $value, array $row): string =>
            ($row['first'] ?? '') . ' ' . ($row['last'] ?? ''));

        $output = $renderer->apply([
            ['first' => 'Ada', 'last' => 'Lovelace', 'full_name' => ''],
        ]);

        self::assertSame('Ada Lovelace', $output[0]['full_name']);
    }

    public function testColumnsWithoutAnyRendererPassThrough(): void
    {
        $renderer = new Renderer();
        $renderer->add('only_this', fn ($v) => 'X');

        $output = $renderer->apply([['only_this' => 'a', 'untouched' => 'b']]);

        self::assertSame('X', $output[0]['only_this']);
        self::assertSame('b', $output[0]['untouched']);
    }

    public function testNonAssociativeRowsArePassedThroughUnchanged(): void
    {
        $renderer = new Renderer();
        $renderer->add('name', fn ($v) => 'X');

        $object = (object) ['name' => 'Alice'];
        $output = $renderer->apply([$object]);

        self::assertSame($object, $output[0]);
    }

    public function testReAddingAColumnOverwritesThePreviousRenderer(): void
    {
        $renderer = new Renderer();
        $renderer->add('name', fn ($v) => 'first');
        $renderer->add('name', fn ($v) => 'second');

        $output = $renderer->apply([['name' => 'whatever']]);

        self::assertSame('second', $output[0]['name']);
    }
}
