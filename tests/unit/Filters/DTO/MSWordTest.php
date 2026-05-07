<?php

namespace TestCases\Filters\DTO;

use Model\Filters\DTO\MSWord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MSWordTest extends TestCase
{
    #[Test]
    public function jsonSerializeReturnsDefaultValues(): void
    {
        $dto = new MSWord();
        $result = $dto->jsonSerialize();

        $this->assertFalse($result['extract_doc_properties']);
        $this->assertFalse($result['extract_comments']);
        $this->assertFalse($result['extract_headers_footers']);
        $this->assertFalse($result['extract_hidden_text']);
        $this->assertFalse($result['accept_revisions']);
        $this->assertSame([], $result['exclude_styles']);
        $this->assertSame([], $result['exclude_highlight_colors']);
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $dto = new MSWord();
        $dto->setExtractDocProperties(true);
        $dto->setExtractComments(true);
        $dto->setExtractHeadersFooters(true);
        $dto->setExtractHiddenText(true);
        $dto->setAcceptRevisions(true);
        $dto->setExcludeStyles(['Heading1']);
        $dto->setExcludeHighlightColors(['yellow', 'red']);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_doc_properties']);
        $this->assertTrue($result['extract_comments']);
        $this->assertTrue($result['extract_headers_footers']);
        $this->assertTrue($result['extract_hidden_text']);
        $this->assertTrue($result['accept_revisions']);
        $this->assertSame(['Heading1'], $result['exclude_styles']);
        $this->assertSame(['yellow', 'red'], $result['exclude_highlight_colors']);
    }

    #[Test]
    public function fromArrayHydratesAllFields(): void
    {
        $dto = new MSWord();
        $dto->fromArray([
            'extract_doc_properties'   => true,
            'extract_comments'         => true,
            'accept_revisions'         => true,
            'exclude_highlight_colors' => ['green'],
            'extract_headers_footers'  => true,
            'exclude_styles'           => ['Normal'],
            'extract_hidden_text'      => true,
        ]);

        $result = $dto->jsonSerialize();
        $this->assertTrue($result['extract_doc_properties']);
        $this->assertTrue($result['extract_comments']);
        $this->assertTrue($result['extract_headers_footers']);
        $this->assertTrue($result['extract_hidden_text']);
        $this->assertTrue($result['accept_revisions']);
        $this->assertSame(['Normal'], $result['exclude_styles']);
        $this->assertSame(['green'], $result['exclude_highlight_colors']);
    }

    #[Test]
    public function fromArrayIgnoresUnknownKeys(): void
    {
        $dto = new MSWord();
        $dto->fromArray(['unknown' => true]);
        $this->assertFalse($dto->jsonSerialize()['extract_doc_properties']);
    }
}
