<?php

namespace Matecat\Core\Plugins\Uber\Utils;

use Features\Uber\Utils\NoteExtractor;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;

class NoteExtractorTest extends AbstractTest
{
    #[Test]
    public function extractReturnsEmptyArrayForNull(): void
    {
        $this->assertSame([], NoteExtractor::extract(null));
    }

    #[Test]
    public function extractReturnsEmptyArrayForEmptyString(): void
    {
        $this->assertSame([], NoteExtractor::extract(''));
    }

    #[Test]
    public function extractParsesRepo(): void
    {
        $result = NoteExtractor::extract('Repo:my-repo');

        $this->assertArrayHasKey('Repo', $result);
        $this->assertSame('my-repo', $result['Repo']);
    }

    #[Test]
    public function extractParsesMultipleKeys(): void
    {
        $input = 'Repo:my-repo,KeyName:some.key,Description:A test description';
        $result = NoteExtractor::extract($input);

        $this->assertArrayHasKey('Repo', $result);
        $this->assertArrayHasKey('KeyName', $result);
        $this->assertArrayHasKey('Description', $result);
    }

    #[Test]
    public function extractHandlesContentTypeKey(): void
    {
        $result = NoteExtractor::extract('Content Type:email');

        $this->assertArrayHasKey('Content Type', $result);
        $this->assertSame('email', $result['Content Type']);
    }

    #[Test]
    public function extractStripsNewlines(): void
    {
        $result = NoteExtractor::extract("Repo:my-repo\nKeyName:test.key");

        $this->assertArrayHasKey('Repo', $result);
        $this->assertArrayHasKey('KeyName', $result);
    }

    #[Test]
    public function extractTrimsTrailingComma(): void
    {
        $result = NoteExtractor::extract('Repo:my-repo,');

        $this->assertArrayHasKey('Repo', $result);
        $this->assertStringEndsNotWith(',', $result['Repo']);
    }

    #[Test]
    public function extractReturnsNoKeysForUnknownInput(): void
    {
        $result = NoteExtractor::extract('This is just plain text without any keys');

        $this->assertEmpty($result);
    }
}
