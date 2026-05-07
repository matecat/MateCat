<?php

namespace TestCases\Filters\DTO;

use Model\Filters\DTO\Xml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class XmlTest extends TestCase
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new Xml();
        $result = $dto->jsonSerialize();

        $this->assertFalse($result['preserve_whitespace']);
        $this->assertSame([], $result['translate_elements']);
        $this->assertSame([], $result['translate_attributes']);
        $this->assertArrayNotHasKey('do_not_translate_elements', $result);
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $dto = new Xml();
        $dto->setPreserveWhitespace(true);
        $dto->setTranslateElements(['p', 'span']);
        $dto->setTranslateAttributes(['alt', 'title']);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['preserve_whitespace']);
        $this->assertSame(['p', 'span'], $result['translate_elements']);
        $this->assertSame(['alt', 'title'], $result['translate_attributes']);
    }

    #[Test]
    public function doNotTranslateElementsRemovesTranslateElements(): void
    {
        $dto = new Xml();
        $dto->setTranslateElements(['p']);
        $dto->setDoNotTranslateElements(['code', 'pre']);

        $result = $dto->jsonSerialize();
        $this->assertSame(['code', 'pre'], $result['do_not_translate_elements']);
        $this->assertArrayNotHasKey('translate_elements', $result);
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new Xml();
        $dto->fromArray([
            'preserve_whitespace'        => true,
            'translate_elements'         => ['div'],
            'do_not_translate_elements'  => ['script'],
            'translate_attributes'       => ['href'],
        ]);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['preserve_whitespace']);
        $this->assertSame(['script'], $result['do_not_translate_elements']);
        $this->assertSame(['href'], $result['translate_attributes']);
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new Xml();
        $dto->fromArray(['unknown' => true]);
        $this->assertFalse($dto->jsonSerialize()['preserve_whitespace']);
    }
}
