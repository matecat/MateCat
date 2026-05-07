<?php

namespace TestCases\Filters\DTO;

use Model\Filters\DTO\Json;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new Json();
        $result = $dto->jsonSerialize();

        $this->assertFalse($result['extract_arrays']);
        $this->assertFalse($result['escape_forward_slashes']);
        $this->assertSame([], $result['translate_keys']);
        $this->assertSame([], $result['context_keys']);
        $this->assertSame([], $result['character_limit']);
        $this->assertArrayNotHasKey('do_not_translate_keys', $result);
    }

    #[Test]
    public function setExtractArraysSetsValue(): void
    {
        $dto = new Json();
        $dto->setExtractArrays(true);
        $this->assertTrue($dto->jsonSerialize()['extract_arrays']);
    }

    #[Test]
    public function setEscapeForwardSlashesSetsValue(): void
    {
        $dto = new Json();
        $dto->setEscapeForwardSlashes(true);
        $this->assertTrue($dto->jsonSerialize()['escape_forward_slashes']);
    }

    #[Test]
    public function setTranslateKeysSetsValue(): void
    {
        $dto = new Json();
        $dto->setTranslateKeys(['title', 'description']);
        $this->assertSame(['title', 'description'], $dto->jsonSerialize()['translate_keys']);
    }

    #[Test]
    public function doNotTranslateKeysRemovesTranslateKeys(): void
    {
        $dto = new Json();
        $dto->setTranslateKeys(['title']);
        $dto->setDoNotTranslateKeys(['id', 'code']);

        $result = $dto->jsonSerialize();
        $this->assertSame(['id', 'code'], $result['do_not_translate_keys']);
        $this->assertArrayNotHasKey('translate_keys', $result);
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new Json();
        $dto->fromArray([
            'extract_arrays'        => true,
            'escape_forward_slashes' => true,
            'translate_keys'        => ['name'],
            'do_not_translate_keys' => ['id'],
            'context_keys'          => ['ctx'],
            'character_limit'       => ['limit1'],
        ]);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_arrays']);
        $this->assertTrue($result['escape_forward_slashes']);
        $this->assertSame(['id'], $result['do_not_translate_keys']);
        $this->assertSame(['ctx'], $result['context_keys']);
        $this->assertSame(['limit1'], $result['character_limit']);
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new Json();
        $dto->fromArray(['unknown' => 'value']);
        $this->assertFalse($dto->jsonSerialize()['extract_arrays']);
    }
}
