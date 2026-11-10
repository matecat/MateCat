<?php

namespace Matecat\Core\Filters\DTO;

use Matecat\TestHelpers\AbstractTest;
use Model\Filters\DTO\Markdown;
use PHPUnit\Framework\Attributes\Test;

class MarkdownTest extends AbstractTest
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new Markdown();

        $this->assertSame(
            [
                'extract_frontmatter'        => false,
                'translate_frontmatter_keys' => [],
            ],
            $dto->jsonSerialize()
        );
    }

    #[Test]
    public function setExtractFrontmatterSetsValue(): void
    {
        $dto = new Markdown();
        $dto->setExtractFrontmatter(true);
        $this->assertTrue($dto->jsonSerialize()['extract_frontmatter']);
    }

    #[Test]
    public function setTranslateFrontmatterKeysSetsValue(): void
    {
        $dto = new Markdown();
        $dto->setTranslateFrontmatterKeys(['title', 'description']);
        $this->assertSame(['title', 'description'], $dto->jsonSerialize()['translate_frontmatter_keys']);
    }

    #[Test]
    public function doNotTranslateFrontmatterKeysRemovesTranslateFrontmatterKeys(): void
    {
        $dto = new Markdown();
        $dto->setTranslateFrontmatterKeys(['title']);
        $dto->setDoNotTranslateFrontmatterKeys(['slug']);

        $result = $dto->jsonSerialize();
        $this->assertSame(['slug'], $result['do_not_translate_frontmatter_keys']);
        $this->assertArrayNotHasKey('translate_frontmatter_keys', $result);
    }

    #[Test]
    public function emptyDoNotTranslateFrontmatterKeysKeepsTranslateFrontmatterKeys(): void
    {
        $dto = new Markdown();
        $dto->setTranslateFrontmatterKeys(['title']);
        $dto->setDoNotTranslateFrontmatterKeys([]);

        $result = $dto->jsonSerialize();
        $this->assertSame(['title'], $result['translate_frontmatter_keys']);
        $this->assertArrayNotHasKey('do_not_translate_frontmatter_keys', $result);
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new Markdown();
        $dto->fromArray([
            'extract_frontmatter'               => true,
            'translate_frontmatter_keys'        => ['title'],
            'do_not_translate_frontmatter_keys' => ['slug'],
        ]);

        $this->assertSame(
            [
                'extract_frontmatter'               => true,
                'do_not_translate_frontmatter_keys' => ['slug'],
            ],
            $dto->jsonSerialize()
        );
    }

    /**
     * The key lists are kept while extraction is off, so turning it back on restores them.
     */
    #[Test]
    public function keysAreKeptWhenExtractionIsOff(): void
    {
        $dto = new Markdown();
        $dto->fromArray([
            'extract_frontmatter'        => false,
            'translate_frontmatter_keys' => ['title'],
        ]);

        $this->assertSame(
            [
                'extract_frontmatter'        => false,
                'translate_frontmatter_keys' => ['title'],
            ],
            $dto->jsonSerialize()
        );
    }

    /**
     * An explicit null is allowed by the JSON schema and is skipped by the isset() guard,
     * leaving the default in place.
     */
    #[Test]
    public function fromArrayAcceptsExplicitNulls(): void
    {
        $dto = new Markdown();
        $dto->fromArray([
            'extract_frontmatter'               => null,
            'translate_frontmatter_keys'        => null,
            'do_not_translate_frontmatter_keys' => null,
        ]);

        $this->assertSame(
            [
                'extract_frontmatter'        => false,
                'translate_frontmatter_keys' => [],
            ],
            $dto->jsonSerialize()
        );
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new Markdown();
        $dto->fromArray(['translate_keys' => ['title'], 'unknown' => 'value']);

        $this->assertSame(
            [
                'extract_frontmatter'        => false,
                'translate_frontmatter_keys' => [],
            ],
            $dto->jsonSerialize()
        );
    }

    #[Test]
    public function keysKeepTheirLeadingAndTrailingSpaces(): void
    {
        // a frontmatter key is a YAML mapping key, an arbitrary string, so " label " is a
        // legitimate key distinct from "label" and must survive untouched
        $dto = new Markdown();
        $dto->fromArray(['do_not_translate_frontmatter_keys' => [' label ', '   ']]);

        $this->assertSame([' label ', '   '], $dto->jsonSerialize()['do_not_translate_frontmatter_keys']);
    }
}
