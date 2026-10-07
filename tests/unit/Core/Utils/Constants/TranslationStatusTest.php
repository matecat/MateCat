<?php

namespace Matecat\Core\Utils\Constants;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\TranslationStatus;

class TranslationStatusTest extends AbstractTest
{
    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function preTranslateStatusProvider(): array
    {
        return [
            'translated'  => ['TRANSLATED', 'TRANSLATED'],
            'approved'    => ['APPROVED', 'APPROVED'],
            'approved2'   => ['APPROVED2', 'APPROVED2'],
            'lower case'  => ['approved2', 'APPROVED2'],
            'mixed case'  => ['Translated', 'TRANSLATED'],
            'new'         => ['NEW', null],
            'draft'       => ['draft', null],
            'rejected'    => ['REJECTED', null],
            'empty'       => ['', null],
            'not string'  => [['APPROVED'], null],
            'null'        => [null, null],
        ];
    }

    #[Test]
    #[DataProvider('preTranslateStatusProvider')]
    public function preTranslateStatusReturnsTheUpperCaseConstant(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, TranslationStatus::preTranslateStatus($value));
    }
}
