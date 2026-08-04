<?php

namespace Matecat\Core\Filters\DTO;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Filters\DTO\Json;
use PHPUnit\Framework\Attributes\Test;

class JsonTest extends AbstractTest
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
        $this->assertNull($result['inner_content_type']);
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
            'inner_content_type'    => 'text/html',
        ]);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_arrays']);
        $this->assertTrue($result['escape_forward_slashes']);
        $this->assertSame(['id'], $result['do_not_translate_keys']);
        $this->assertSame(['ctx'], $result['context_keys']);
        $this->assertSame(['limit1'], $result['character_limit']);
        $this->assertSame('text/html', $result['inner_content_type']);
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new Json();
        $dto->fromArray(['unknown' => 'value']);
        $this->assertFalse($dto->jsonSerialize()['extract_arrays']);
    }

    #[Test]
    public function setInnerContentTypeAcceptsValidMimeType(): void
    {
        $dto = new Json();
        $dto->setInnerContentType('text/html');
        $this->assertSame('text/html', $dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function setInnerContentTypeAcceptsNull(): void
    {
        $dto = new Json();
        $dto->setInnerContentType('text/html');
        $dto->setInnerContentType(null);
        $this->assertNull($dto->jsonSerialize()['inner_content_type']);
    }

    #[Test]
    public function setInnerContentTypeThrowsOnInvalidMimeType(): void
    {
        $this->expectException(DomainException::class);
        $dto = new Json();
        $dto->setInnerContentType('text/plain');
    }

    /**
     * text/html is the only mime type allowed for JSON; the wider YAML allow-list must not leak in.
     */
    #[Test]
    public function setInnerContentTypeRejectsYamlOnlyMimeTypes(): void
    {
        $yamlOnlyTypes = [
            'text/xml',
            'application/xml',
            'text/csv',
            'application/json',
            'text/markdown',
            'text/x-markdown',
        ];

        foreach ($yamlOnlyTypes as $type) {
            $dto = new Json();

            try {
                $dto->setInnerContentType($type);
                $this->fail("Expected DomainException for inner_content_type '$type'");
            } catch (DomainException $e) {
                $this->assertStringContainsString('text/html', $e->getMessage());
            }
        }
    }

    #[Test]
    public function fromArrayThrowsOnInvalidInnerContentType(): void
    {
        $this->expectException(DomainException::class);
        $dto = new Json();
        $dto->fromArray(['inner_content_type' => 'invalid/type']);
    }
}
