<?php

namespace Matecat\Core\Filters\DTO;

use Matecat\TestHelpers\AbstractTest;
use Model\Filters\DTO\Dita;
use PHPUnit\Framework\Attributes\Test;

class DitaTest extends AbstractTest
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
