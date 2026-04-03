<?php

namespace unit\Model\Segments;

use Model\Segments\SegmentMetadataMarshaller;
use Model\Segments\SegmentMetadataStruct;
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
            'resname'         => ['resname'],
            'restype'         => ['restype'],
            'context-url'     => ['context-url'],
            'x-client-name'   => ['x-client-name'],
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
            'resname',
            'restype',
            'context-url',
            'x-client-name',
        ];

        $actual = array_map(fn(SegmentMetadataMarshaller $case) => $case->value, SegmentMetadataMarshaller::cases());

        self::assertSame($expected, $actual);
    }

    // ── unMarshall ──

    #[Test]
    public function testUnmarshallSizeRestrictionReturnsInt(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::SIZE_RESTRICTION->value;
        $struct->meta_value = '42';

        self::assertSame(42, SegmentMetadataMarshaller::unMarshall($struct));
    }

    #[Test]
    public function testUnmarshallRegularKeyReturnsString(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::ID_REQUEST->value;
        $struct->meta_value = 'REQ-123';

        self::assertSame('REQ-123', SegmentMetadataMarshaller::unMarshall($struct));
    }

    #[Test]
    public function testUnmarshallIdOrderReturnsString(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::ID_ORDER->value;
        $struct->meta_value = '5';

        self::assertSame('5', SegmentMetadataMarshaller::unMarshall($struct));
    }

    // ── resname ──

    #[Test]
    public function testMarshallResnameReturnsString(): void
    {
        self::assertSame(
            '//html/body/div[2]/p[1]',
            SegmentMetadataMarshaller::RESNAME->marshall('//html/body/div[2]/p[1]')
        );
    }

    #[Test]
    public function testUnmarshallResnameReturnsString(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::RESNAME->value;
        $struct->meta_value = 'product-title';

        self::assertSame('product-title', SegmentMetadataMarshaller::unMarshall($struct));
    }

    // ── restype ──

    #[Test]
    #[DataProvider('validRestypeProvider')]
    public function testMarshallRestypeReturnsStringForValidContextResType(string $value): void
    {
        self::assertSame($value, SegmentMetadataMarshaller::RESTYPE->marshall($value));
    }

    public static function validRestypeProvider(): array
    {
        return [
            'x-path'                 => ['x-path'],
            'x-client_nodepath'      => ['x-client_nodepath'],
            'x-tag-id'               => ['x-tag-id'],
            'x-css_class'            => ['x-css_class'],
            'x-attribute_name_value' => ['x-attribute_name_value'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRestypeProvider')]
    public function testMarshallRestypeReturnsNullForInvalidContextResType(string $value): void
    {
        self::assertNull(SegmentMetadataMarshaller::RESTYPE->marshall($value));
    }

    public static function invalidRestypeProvider(): array
    {
        return [
            'x-title' => ['x-title'],
            'x-li'    => ['x-li'],
            'dialog'  => ['dialog'],
            'empty'   => [''],
        ];
    }

    #[Test]
    public function testUnmarshallRestypeReturnsString(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::RESTYPE->value;
        $struct->meta_value = 'x-path';

        self::assertSame('x-path', SegmentMetadataMarshaller::unMarshall($struct));
    }

    // ── context-url ──

    #[Test]
    public function testMarshallContextUrlReturnsUrlString(): void
    {
        self::assertSame(
            'https://example.com/page/1',
            SegmentMetadataMarshaller::CONTEXT_URL->marshall('https://example.com/page/1')
        );
    }

    #[Test]
    public function testMarshallContextUrlReturnsEmptyStringAsIs(): void
    {
        self::assertSame('', SegmentMetadataMarshaller::CONTEXT_URL->marshall(''));
    }

    #[Test]
    public function testUnmarshallContextUrlReturnsString(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::CONTEXT_URL->value;
        $struct->meta_value = 'https://example.com/review';

        self::assertSame('https://example.com/review', SegmentMetadataMarshaller::unMarshall($struct));
    }

    // ── x-client-name ──

    #[Test]
    public function testMarshallXClientNameReturnsString(): void
    {
        self::assertSame(
            'dot-notation',
            SegmentMetadataMarshaller::X_CLIENT_NAME->marshall('dot-notation')
        );
    }

    #[Test]
    public function testMarshallXClientNameReturnsEmptyStringAsIs(): void
    {
        self::assertSame('', SegmentMetadataMarshaller::X_CLIENT_NAME->marshall(''));
    }

    #[Test]
    public function testUnmarshallXClientNameReturnsString(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::X_CLIENT_NAME->value;
        $struct->meta_value = 'acme-crm';

        self::assertSame('acme-crm', SegmentMetadataMarshaller::unMarshall($struct));
    }
}
