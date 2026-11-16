<?php

namespace Matecat\Core\Filters\DTO;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Filters\DTO\Yaml;
use PHPUnit\Framework\Attributes\Test;

class YamlTest extends AbstractTest
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new Yaml();
        $result = $dto->jsonSerialize();

        $this->assertFalse($result['force_double_quoting']);
        $this->assertSame([], $result['translate_keys']);
        $this->assertNull($result['inner_content_type']);
        $this->assertSame([], $result['context_keys']);
        $this->assertSame([], $result['character_limit']);
        $this->assertArrayNotHasKey('do_not_translate_keys', $result);
    }

    #[Test]
    public function setForceDoubleQuotingSetsValue(): void
    {
        $dto = new Yaml();
        $dto->setForceDoubleQuoting(true);
        $this->assertTrue($dto->jsonSerialize()['force_double_quoting']);
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
            'force_double_quoting'  => true,
            'translate_keys'        => ['name'],
            'do_not_translate_keys' => ['id'],
            'inner_content_type'    => 'application/json',
            'context_keys'          => ['ctx'],
            'character_limit'       => ['lim'],
        ]);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['force_double_quoting']);
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

    /**
     * The parameter is optional: a payload that omits it keeps the source quoting style.
     */
    #[Test]
    public function fromArrayLeavesForceDoubleQuotingOffWhenOmitted(): void
    {
        $dto = new Yaml();
        $dto->fromArray(['translate_keys' => ['name']]);

        $this->assertFalse($dto->jsonSerialize()['force_double_quoting']);
    }

    /**
     * An explicit null is allowed by the JSON schema and is skipped by the isset() guard in
     * Yaml::fromArray(), leaving the default in place.
     */
    #[Test]
    public function fromArrayAcceptsExplicitNullForceDoubleQuoting(): void
    {
        $dto = new Yaml();
        $dto->fromArray(['force_double_quoting' => null]);

        $this->assertFalse($dto->jsonSerialize()['force_double_quoting']);
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

    #[Test]
    public function keysKeepTheirLeadingAndTrailingSpaces(): void
    {
        // a YAML mapping key is an arbitrary string, so " label " is a legitimate key
        // distinct from "label" and must survive untouched
        $dto = new Yaml();
        $dto->setTranslateKeys([' label ']);
        $dto->setContextKeys(['  note']);
        $dto->setCharacterLimit(['limit  ']);

        $result = $dto->jsonSerialize();
        $this->assertSame([' label '], $result['translate_keys']);
        $this->assertSame(['  note'], $result['context_keys']);
        $this->assertSame(['limit  '], $result['character_limit']);
    }

    #[Test]
    public function fromArrayKeepsKeyPadding(): void
    {
        $dto = new Yaml();
        $dto->fromArray(['do_not_translate_keys' => [' label ', '   ']]);

        $this->assertSame([' label ', '   '], $dto->jsonSerialize()['do_not_translate_keys']);
    }
}
