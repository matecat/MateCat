<?php

namespace TestCases\Filters\DTO;

use DomainException;
use Model\Filters\DTO\Yaml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YamlTest extends TestCase
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new Yaml();
        $result = $dto->jsonSerialize();

        $this->assertSame([], $result['translate_keys']);
        $this->assertNull($result['inner_content_type']);
        $this->assertSame([], $result['context_keys']);
        $this->assertSame([], $result['character_limit']);
        $this->assertArrayNotHasKey('do_not_translate_keys', $result);
    }

    #[Test]
    public function setTranslateKeysSetsValue(): void
    {
        $dto = new Yaml();
        $dto->setTranslateKeys(['title', 'body']);
        $this->assertSame(['title', 'body'], $dto->jsonSerialize()['translate_keys']);
    }

    #[Test]
    public function doNotTranslateKeysRemovesTranslateKeys(): void
    {
        $dto = new Yaml();
        $dto->setTranslateKeys(['title']);
        $dto->setDoNotTranslateKeys(['id']);

        $result = $dto->jsonSerialize();
        $this->assertSame(['id'], $result['do_not_translate_keys']);
        $this->assertArrayNotHasKey('translate_keys', $result);
    }

    #[Test]
    public function setInnerContentTypeAcceptsValidMimeType(): void
    {
        $dto = new Yaml();
        $dto->setInnerContentType('text/html');
        $this->assertSame('text/html', $dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function setInnerContentTypeThrowsOnInvalidMimeType(): void
    {
        $this->expectException(DomainException::class);
        $dto = new Yaml();
        $dto->setInnerContentType('text/plain');
    }

    #[Test]
    public function setContextKeysSetsValue(): void
    {
        $dto = new Yaml();
        $dto->setContextKeys(['ctx1']);
        $this->assertSame(['ctx1'], $dto->jsonSerialize()['context_keys']);
    }

    #[Test]
    public function setCharacterLimitSetsValue(): void
    {
        $dto = new Yaml();
        $dto->setCharacterLimit(['limit']);
        $this->assertSame(['limit'], $dto->jsonSerialize()['character_limit']);
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new Yaml();
        $dto->fromArray([
            'translate_keys'        => ['name'],
            'do_not_translate_keys' => ['id'],
            'inner_content_type'    => 'application/json',
            'context_keys'          => ['ctx'],
            'character_limit'       => ['lim'],
        ]);

        $result = $dto->jsonSerialize();
        $this->assertSame(['id'], $result['do_not_translate_keys']);
        $this->assertSame('application/json', $result['inner_content_type']);
        $this->assertSame(['ctx'], $result['context_keys']);
        $this->assertSame(['lim'], $result['character_limit']);
    }

    #[Test]
    public function fromArrayThrowsOnInvalidInnerContentType(): void
    {
        $this->expectException(DomainException::class);
        $dto = new Yaml();
        $dto->fromArray(['inner_content_type' => 'invalid/type']);
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new Yaml();
        $dto->fromArray(['unknown' => 'value']);
        $this->assertSame([], $dto->jsonSerialize()['translate_keys']);
    }

    #[Test]
    public function allValidMimeTypesAccepted(): void
    {
        $validTypes = [
            'text/html',
            'text/xml',
            'application/xml',
            'text/csv',
            'application/json',
            'text/markdown',
            'text/x-markdown',
        ];

        foreach ($validTypes as $type) {
            $dto = new Yaml();
            $dto->setInnerContentType($type);
            $this->assertSame($type, $dto->jsonSerialize()['inner_content_type']);
        }
    }
}
