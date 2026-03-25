<?php

namespace unit\Model\Segments;

use Model\Segments\SegmentMetadataMarshaller;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SegmentMetadataMarshallerTest extends TestCase
{
    // ── isAllowed ──

    #[Test]
    #[DataProvider('allowedKeysProvider')]
    public function testIsAllowedReturnsTrueForValidKeys(string $key): void
    {
        self::assertTrue(SegmentMetadataMarshaller::isAllowed($key));
    }

    public static function allowedKeysProvider(): array
    {
        return [
            'id_request'      => ['id_request'],
            'id_content'      => ['id_content'],
            'id_order'        => ['id_order'],
            'id_order_group'  => ['id_order_group'],
            'screenshot'      => ['screenshot'],
            'sizeRestriction' => ['sizeRestriction'],
        ];
    }

    #[Test]
    #[DataProvider('disallowedKeysProvider')]
    public function testIsAllowedReturnsFalseForInvalidKeys(string $key): void
    {
        self::assertFalse(SegmentMetadataMarshaller::isAllowed($key));
    }

    public static function disallowedKeysProvider(): array
    {
        return [
            'unknown'         => ['unknown'],
            'comment'         => ['comment'],
            'empty'           => [''],
            'SizeRestriction' => ['SizeRestriction'],  // case-sensitive
        ];
    }

    // ── marshall ──

    #[Test]
    public function testMarshallReturnsStringForRegularKeys(): void
    {
        self::assertSame('REQ-123', SegmentMetadataMarshaller::ID_REQUEST->marshall('REQ-123'));
    }

    #[Test]
    public function testMarshallSizeRestrictionReturnsStringForPositiveInt(): void
    {
        self::assertSame('42', SegmentMetadataMarshaller::SIZE_RESTRICTION->marshall(42));
    }

    #[Test]
    public function testMarshallSizeRestrictionReturnsStringForPositiveNumericString(): void
    {
        self::assertSame('42', SegmentMetadataMarshaller::SIZE_RESTRICTION->marshall('42'));
    }

    #[Test]
    public function testMarshallSizeRestrictionReturnsNullForZero(): void
    {
        self::assertNull(SegmentMetadataMarshaller::SIZE_RESTRICTION->marshall(0));
    }

    #[Test]
    public function testMarshallSizeRestrictionReturnsNullForNegative(): void
    {
        self::assertNull(SegmentMetadataMarshaller::SIZE_RESTRICTION->marshall(-5));
    }

    #[Test]
    public function testMarshallSizeRestrictionReturnsNullForZeroString(): void
    {
        self::assertNull(SegmentMetadataMarshaller::SIZE_RESTRICTION->marshall('0'));
    }

    #[Test]
    public function testMarshallReturnsStringValueForNonStringInput(): void
    {
        self::assertSame('123', SegmentMetadataMarshaller::ID_ORDER->marshall(123));
    }

    #[Test]
    public function testMarshallReturnsEmptyStringAsIs(): void
    {
        self::assertSame('', SegmentMetadataMarshaller::ID_CONTENT->marshall(''));
    }

    // ── enum values ──

    #[Test]
    public function testEnumCasesMatchExpectedKeys(): void
    {
        $expected = [
            'id_request',
            'id_content',
            'id_order',
            'id_order_group',
            'screenshot',
            'sizeRestriction',
        ];

        $actual = array_map(fn(SegmentMetadataMarshaller $case) => $case->value, SegmentMetadataMarshaller::cases());

        self::assertSame($expected, $actual);
    }

    // ── unmarshall ──

    #[Test]
    public function testUnmarshallSizeRestrictionReturnsInt(): void
    {
        self::assertSame(42, SegmentMetadataMarshaller::SIZE_RESTRICTION->unmarshall('42'));
    }

    #[Test]
    public function testUnmarshallRegularKeyReturnsString(): void
    {
        self::assertSame('REQ-123', SegmentMetadataMarshaller::ID_REQUEST->unmarshall('REQ-123'));
    }

    #[Test]
    public function testUnmarshallIdOrderReturnsString(): void
    {
        self::assertSame('5', SegmentMetadataMarshaller::ID_ORDER->unmarshall('5'));
    }
}
