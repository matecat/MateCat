<?php

namespace unit\Model\Files;

use Model\Files\FilesMetadataMarshaller;
use Model\Files\MetadataStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilesMetadataMarshallerTest extends TestCase
{

    #[Test]
    #[DataProvider('allowedKeysProvider')]
    public function testIsAllowedReturnsTrueForValidKeys(string $key): void
    {
        self::assertTrue(FilesMetadataMarshaller::isAllowed($key));
    }

    public static function allowedKeysProvider(): array
    {
        return [
            'instructions' => ['instructions'],
            'pdfAnalysis'  => ['pdfAnalysis'],
            'context-url'  => ['context-url'],
        ];
    }

    #[Test]
    #[DataProvider('disallowedKeysProvider')]
    public function testIsAllowedReturnsFalseForInvalidKeys(string $key): void
    {
        self::assertFalse(FilesMetadataMarshaller::isAllowed($key));
    }

    public static function disallowedKeysProvider(): array
    {
        return [
            'unknown'          => ['unknown'],
            'legacy_mtc_key'   => ['mtc:instructions'],
            'empty'            => [''],
        ];
    }

    #[Test]
    public function testEnumCasesMatchExpectedKeys(): void
    {
        $expected = [
            'instructions',
            'pdfAnalysis',
            'context-url',
        ];

        $actual = array_map(fn(FilesMetadataMarshaller $case) => $case->value, FilesMetadataMarshaller::cases());

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function testMarshallInstructionsReturnsSameString(): void
    {
        self::assertSame('some text', FilesMetadataMarshaller::INSTRUCTIONS->marshall('some text'));
    }

    #[Test]
    public function testMarshallPdfAnalysisPassesThroughString(): void
    {
        self::assertSame('{"key":"val"}', FilesMetadataMarshaller::PDF_ANALYSIS->marshall('{"key":"val"}'));
    }

    #[Test]
    public function testMarshallPdfAnalysisEncodesArrayAsJson(): void
    {
        self::assertSame('{"key":"val"}', FilesMetadataMarshaller::PDF_ANALYSIS->marshall(['key' => 'val']));
    }

    #[Test]
    public function testMarshallContextUrlReturnsSameString(): void
    {
        self::assertSame(
            'https://example.com/page.html',
            FilesMetadataMarshaller::CONTEXT_URL->marshall('https://example.com/page.html')
        );
    }

    #[Test]
    public function testMarshallInstructionsCastsNumericToString(): void
    {
        self::assertSame('123', FilesMetadataMarshaller::INSTRUCTIONS->marshall(123));
    }

    #[Test]
    public function testUnMarshallPdfAnalysisReturnsDecodedArrayForValidJson(): void
    {
        $struct = $this->makeStruct(FilesMetadataMarshaller::PDF_ANALYSIS->value, '{"key":"val"}');

        self::assertSame(['key' => 'val'], FilesMetadataMarshaller::unMarshall($struct));
    }

    #[Test]
    public function testUnMarshallPdfAnalysisReturnsRawStringForInvalidJson(): void
    {
        $struct = $this->makeStruct(FilesMetadataMarshaller::PDF_ANALYSIS->value, 'not-json');

        self::assertSame('not-json', FilesMetadataMarshaller::unMarshall($struct));
    }

    #[Test]
    public function testUnMarshallInstructionsReturnsRawString(): void
    {
        $struct = $this->makeStruct(FilesMetadataMarshaller::INSTRUCTIONS->value, 'some text');

        self::assertSame('some text', FilesMetadataMarshaller::unMarshall($struct));
    }

    #[Test]
    public function testUnMarshallContextUrlReturnsRawString(): void
    {
        $struct = $this->makeStruct(FilesMetadataMarshaller::CONTEXT_URL->value, 'https://example.com/page.html');

        self::assertSame('https://example.com/page.html', FilesMetadataMarshaller::unMarshall($struct));
    }

    private function makeStruct(string $key, string $value): MetadataStruct
    {
        $struct = new MetadataStruct();
        $struct->id_project = 1;
        $struct->id_file = 1;
        $struct->key = $key;
        $struct->value = $value;

        return $struct;
    }
}
