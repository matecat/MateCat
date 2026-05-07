<?php

namespace TestCases\Filters\DTO;

use DomainException;
use Model\Filters\DTO\Dita;
use Model\Filters\DTO\Json;
use Model\Filters\DTO\MSExcel;
use Model\Filters\DTO\MSPowerpoint;
use Model\Filters\DTO\MSWord;
use Model\Filters\DTO\Xml;
use Model\Filters\DTO\Yaml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DitaTest extends TestCase
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new Dita();
        $this->assertSame(['do_not_translate_elements' => []], $dto->jsonSerialize());
    }

    #[Test]
    public function setDoNotTranslateElementsSetsValue(): void
    {
        $dto = new Dita();
        $dto->setDoNotTranslateElements(['topic', 'note']);
        $this->assertSame(['do_not_translate_elements' => ['topic', 'note']], $dto->jsonSerialize());
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new Dita();
        $dto->fromArray(['do_not_translate_elements' => ['keyword', 'ph']]);
        $this->assertSame(['do_not_translate_elements' => ['keyword', 'ph']], $dto->jsonSerialize());
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new Dita();
        $dto->fromArray(['unknown_key' => 'value']);
        $this->assertSame(['do_not_translate_elements' => []], $dto->jsonSerialize());
    }

    #[Test]
    public function implementsJsonSerializableViaIDto(): void
    {
        $dto = new Dita();
        $this->assertInstanceOf(\JsonSerializable::class, $dto);
    }
}
