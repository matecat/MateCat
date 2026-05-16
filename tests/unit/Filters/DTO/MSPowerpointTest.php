<?php

namespace TestCases\Filters\DTO;

use Model\Filters\DTO\MSPowerpoint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MSPowerpointTest extends TestCase
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new MSPowerpoint();
        $result = $dto->jsonSerialize();

        $this->assertFalse($result['extract_doc_properties']);
        $this->assertSame([], $result['translate_slides']);
        $this->assertTrue($result['extract_notes']);
        $this->assertArrayNotHasKey('extract_hidden_slides', $result);
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $dto = new MSPowerpoint();
        $dto->setExtractDocProperties(true);
        $dto->setExtractNotes(false);
        $dto->setTranslateSlides(['1', '3', '5']);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_doc_properties']);
        $this->assertFalse($result['extract_notes']);
        $this->assertSame(['1', '3', '5'], $result['translate_slides']);
    }

    #[Test]
    public function extractHiddenSlidesRemovesTranslateSlides(): void
    {
        $dto = new MSPowerpoint();
        $dto->setTranslateSlides(['1']);
        $dto->setExtractHiddenSlides(true);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_hidden_slides']);
        $this->assertArrayNotHasKey('translate_slides', $result);
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new MSPowerpoint();
        $dto->fromArray([
            'extract_doc_properties' => true,
            'extract_hidden_slides'  => true,
            'translate_slides'       => ['2'],
            'extract_notes'          => false,
        ]);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_doc_properties']);
        $this->assertTrue($result['extract_hidden_slides']);
        $this->assertFalse($result['extract_notes']);
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new MSPowerpoint();
        $dto->fromArray(['unknown' => true]);
        $this->assertFalse($dto->jsonSerialize()['extract_doc_properties']);
    }
}
