<?php

namespace unit\Model\Segments;

use Model\Segments\ContextResType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ContextResTypeTest extends AbstractTest
{
    // ── valid cases ──

    #[Test]
    #[DataProvider('validCasesProvider')]
    public function testTryFromReturnsEnumForValidValues(string $value, ContextResType $expected): void
    {
        self::assertSame($expected, ContextResType::tryFrom($value));
    }

    public static function validCasesProvider(): array
    {
        return [
            'x-path'                 => ['x-path', ContextResType::X_PATH],
            'x-client_nodepath'      => ['x-client_nodepath', ContextResType::X_CLIENT_NODEPATH],
            'x-tag-id'               => ['x-tag-id', ContextResType::X_TAG_ID],
            'x-css_class'            => ['x-css_class', ContextResType::X_CSS_CLASS],
            'x-attribute_name_value' => ['x-attribute_name_value', ContextResType::X_ATTRIBUTE_NAME_VALUE],
        ];
    }

    // ── invalid cases ──

    #[Test]
    #[DataProvider('invalidCasesProvider')]
    public function testTryFromReturnsNullForInvalidValues(string $value): void
    {
        self::assertNull(ContextResType::tryFrom($value));
    }

    public static function invalidCasesProvider(): array
    {
        return [
            'x-title'    => ['x-title'],
            'x-li'       => ['x-li'],
            'dialog'     => ['dialog'],
            'empty'      => [''],
            'X-PATH'     => ['X-PATH'],    // case-sensitive
            'x_path'     => ['x_path'],    // wrong separator
            'xpath'      => ['xpath'],     // no prefix
        ];
    }

    // ── backed string values ──

    #[Test]
    public function testEnumCasesHaveExpectedValues(): void
    {
        self::assertSame('x-path', ContextResType::X_PATH->value);
        self::assertSame('x-client_nodepath', ContextResType::X_CLIENT_NODEPATH->value);
        self::assertSame('x-tag-id', ContextResType::X_TAG_ID->value);
        self::assertSame('x-css_class', ContextResType::X_CSS_CLASS->value);
        self::assertSame('x-attribute_name_value', ContextResType::X_ATTRIBUTE_NAME_VALUE->value);
    }

    // ── exhaustive: exactly 5 cases ──

    #[Test]
    public function testEnumHasExactlyFiveCases(): void
    {
        self::assertCount(5, ContextResType::cases());
    }

    #[Test]
    public function testCasesMatchExpectedOrder(): void
    {
        $expected = [
            'x-path',
            'x-client_nodepath',
            'x-tag-id',
            'x-css_class',
            'x-attribute_name_value',
        ];

        $actual = array_map(fn(ContextResType $case) => $case->value, ContextResType::cases());

        self::assertSame($expected, $actual);
    }
}
