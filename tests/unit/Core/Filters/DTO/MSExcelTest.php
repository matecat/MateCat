<?php

namespace Matecat\Core\Filters\DTO;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Filters\DTO\MSExcel;
use PHPUnit\Framework\Attributes\Test;

class MSExcelTest extends AbstractTest
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new MSExcel();
        $result = $dto->jsonSerialize();

        $this->assertFalse($result['extract_doc_properties']);
        $this->assertFalse($result['extract_hidden_cells']);
        $this->assertFalse($result['extract_diagrams']);
        $this->assertFalse($result['extract_drawings']);
        $this->assertFalse($result['extract_sheet_names']);
        $this->assertSame([], $result['exclude_columns']);
        $this->assertArrayHasKey('inner_content_type', $result);
        $this->assertNull($result['inner_content_type']);
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $dto = new MSExcel();
        $dto->setExtractDocProperties(true);
        $dto->setExtractHiddenCells(true);
        $dto->setExtractDiagrams(true);
        $dto->setExtractDrawings(true);
        $dto->setExtractSheetNames(true);
        $dto->setExcludeColumns(['A', 'B']);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_doc_properties']);
        $this->assertTrue($result['extract_hidden_cells']);
        $this->assertTrue($result['extract_diagrams']);
        $this->assertTrue($result['extract_drawings']);
        $this->assertTrue($result['extract_sheet_names']);
        $this->assertSame(['A', 'B'], $result['exclude_columns']);
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new MSExcel();
        $dto->fromArray([
            'extract_doc_properties' => true,
            'extract_hidden_cells'   => true,
            'extract_diagrams'       => true,
            'extract_drawings'       => true,
            'extract_sheet_names'    => true,
            'exclude_columns'        => ['C'],
        ]);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_doc_properties']);
        $this->assertTrue($result['extract_hidden_cells']);
        $this->assertTrue($result['extract_diagrams']);
        $this->assertTrue($result['extract_drawings']);
        $this->assertTrue($result['extract_sheet_names']);
        $this->assertSame(['C'], $result['exclude_columns']);
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new MSExcel();
        $dto->fromArray(['unknown' => true]);
        $this->assertFalse($dto->jsonSerialize()['extract_doc_properties']);
    }

    #[Test]
    public function setterTrimsColumnsAndDropsTheEmptyOnes(): void
    {
        $dto = new MSExcel();
        $dto->setExcludeColumns([' A ', "B\t", ' ', '']);

        $this->assertSame(['A', 'B'], $dto->jsonSerialize()['exclude_columns']);
    }

    #[Test]
    public function fromArrayTrimsColumns(): void
    {
        $dto = new MSExcel();
        $dto->fromArray(['exclude_columns' => [' AA ']]);

        $this->assertSame(['AA'], $dto->jsonSerialize()['exclude_columns']);
    }

    #[Test]
    public function setInnerContentTypeAcceptsHtml(): void
    {
        $dto = new MSExcel();
        $dto->setInnerContentType('text/html');
        $this->assertSame('text/html', $dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function setInnerContentTypeAcceptsPlainText(): void
    {
        $dto = new MSExcel();
        $dto->setInnerContentType('text/plain');
        $this->assertSame('text/plain', $dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function setInnerContentTypeAcceptsNull(): void
    {
        $dto = new MSExcel();
        $dto->setInnerContentType('text/html');
        $dto->setInnerContentType(null);
        $this->assertNull($dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function setInnerContentTypeThrowsOnInvalidMimeType(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Excel inner content type not valid. Allowed values: ['text/plain', 'text/html']");
        (new MSExcel())->setInnerContentType('application/pdf');
    }

    /**
     * Only text/plain and text/html are allowed for Excel; the wider YAML allow-list must not leak in.
     */
    #[Test]
    public function setInnerContentTypeRejectsYamlOnlyMimeTypes(): void
    {
        foreach (['text/xml', 'application/xml', 'text/csv', 'application/json', 'text/markdown', 'text/x-markdown'] as $type) {
            try {
                (new MSExcel())->setInnerContentType($type);
                $this->fail("Expected DomainException for inner_content_type '$type'");
            } catch (DomainException $e) {
                $this->assertStringContainsString('text/html', $e->getMessage());
            }
        }
    }

    #[Test]
    public function setInnerContentTypeIsCaseSensitive(): void
    {
        $this->expectException(DomainException::class);
        (new MSExcel())->setInnerContentType('TEXT/HTML');
    }

    #[Test]
    public function fromArrayHydratesInnerContentType(): void
    {
        $dto = new MSExcel();
        $dto->fromArray(['inner_content_type' => 'text/html']);
        $this->assertSame('text/html', $dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function fromArrayLeavesInnerContentTypeNullWhenMissingOrNull(): void
    {
        $dto = new MSExcel();
        $dto->fromArray(['extract_sheet_names' => true]);
        $this->assertNull($dto->jsonSerialize()['inner_content_type']);

        $dto->fromArray(['inner_content_type' => null]);
        $this->assertNull($dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function fromArrayThrowsOnInvalidInnerContentType(): void
    {
        $this->expectException(DomainException::class);
        (new MSExcel())->fromArray(['inner_content_type' => 'invalid/type']);
    }

    #[Test]
    public function jsonSerializeRoundTripsThroughFromArray(): void
    {
        $dto = new MSExcel();
        $dto->setExtractHiddenCells(true);
        $dto->setExcludeColumns(['1C']);
        $dto->setInnerContentType('text/html');

        $copy = new MSExcel();
        $copy->fromArray($dto->jsonSerialize());

        $this->assertSame($dto->jsonSerialize(), $copy->jsonSerialize());
    }
}
