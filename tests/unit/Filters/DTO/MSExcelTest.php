<?php

namespace TestCases\Filters\DTO;

use Model\Filters\DTO\MSExcel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MSExcelTest extends TestCase
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
}
