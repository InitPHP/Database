<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitPHP\Database\Utils\Datatables;

use InitPHP\Database\Utils\Datatables\RequestParser;
use PHPUnit\Framework\TestCase;

final class RequestParserTest extends TestCase
{
    public function testEmptyPayloadGivesProtocolDefaults(): void
    {
        $parser = new RequestParser([]);

        self::assertSame(0, $parser->draw());
        self::assertSame(0, $parser->start());
        self::assertSame(-1, $parser->length());
        self::assertFalse($parser->hasPagination());
        self::assertNull($parser->searchValue());
        self::assertSame([], $parser->orders());
    }

    public function testNumericFieldsAreCoercedToIntegers(): void
    {
        $parser = new RequestParser([
            'draw'   => '7',
            'start'  => '20',
            'length' => '10',
        ]);

        self::assertSame(7, $parser->draw());
        self::assertSame(20, $parser->start());
        self::assertSame(10, $parser->length());
        self::assertTrue($parser->hasPagination());
    }

    public function testNegativeStartIsClampedToZero(): void
    {
        $parser = new RequestParser(['start' => '-5']);

        self::assertSame(0, $parser->start());
    }

    public function testLengthMinusOneMeansNoPagination(): void
    {
        $parser = new RequestParser(['start' => '0', 'length' => '-1']);

        self::assertFalse($parser->hasPagination());
        self::assertSame(-1, $parser->length());
    }

    public function testEmptyStringSearchIsTreatedAsAbsent(): void
    {
        $parser = new RequestParser(['search' => ['value' => '']]);

        self::assertNull($parser->searchValue());
    }

    public function testNonScalarSearchValueIsRejected(): void
    {
        $parser = new RequestParser(['search' => ['value' => ['nested']]]);

        self::assertNull($parser->searchValue());
    }

    public function testSearchValueIsReturnedAsString(): void
    {
        $parser = new RequestParser(['search' => ['value' => 42]]);

        self::assertSame('42', $parser->searchValue());
    }

    public function testOrderEntriesAreNormalisedToAscOrDescPairs(): void
    {
        $parser = new RequestParser([
            'order' => [
                ['column' => '2', 'dir' => 'asc'],
                ['column' => '1', 'dir' => 'DESC'],
                ['column' => '3'], // missing dir defaults to DESC
                ['dir' => 'asc'], // missing column is dropped
                'malformed',      // non-array is dropped
            ],
        ]);

        self::assertSame(
            [[2, 'ASC'], [1, 'DESC'], [3, 'DESC']],
            $parser->orders()
        );
    }

    public function testMalformedOrderPayloadGivesEmptyArray(): void
    {
        $parser = new RequestParser(['order' => 'not-an-array']);

        self::assertSame([], $parser->orders());
    }

    public function testAllReturnsTheRawPayloadVerbatim(): void
    {
        $payload = ['draw' => 1, 'custom' => 'value'];
        $parser  = new RequestParser($payload);

        self::assertSame($payload, $parser->all());
    }
}
