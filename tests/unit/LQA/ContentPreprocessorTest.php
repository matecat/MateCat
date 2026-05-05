<?php

namespace unit\LQA;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\ContentPreprocessor;

class ContentPreprocessorTest extends AbstractTest
{
    private ContentPreprocessor $preprocessor;

    public function setUp(): void
    {
        parent::setUp();
        $this->preprocessor = new ContentPreprocessor();
    }

    // ========== Static Methods Tests ==========

    #[Test]
    public function getTabPlaceholder(): void
    {
        $placeholder = ContentPreprocessor::getTabPlaceholder();
        $this->assertEquals('##$_09$##', $placeholder);
    }

    #[Test]
    public function getNewlinePlaceholder(): void
    {
        $placeholder = ContentPreprocessor::getNewlinePlaceholder();
        $this->assertEquals('##$_0A$##', $placeholder);
    }

    #[Test]
    public function emptyHtmlTagsPlaceholder(): void
    {
        $this->assertEquals('##$$##______EMPTY_HTML_TAG______##$$##', ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER);
    }

    // ========== Preprocess Tests ==========

    #[Test]
    public function preprocessNull(): void
    {
        $result = $this->preprocessor->preprocess(null);
        $this->assertEquals('', $result);
    }

    #[Test]
    public function preprocessEmptyString(): void
    {
        $result = $this->preprocessor->preprocess('');
        $this->assertEquals('', $result);
    }

    #[Test]
    public function preprocessPlainText(): void
    {
        $result = $this->preprocessor->preprocess('Hello World');
        $this->assertEquals('Hello World', $result);
    }

    #[Test]
    public function preprocessWithTabs(): void
    {
        $result = $this->preprocessor->preprocess("Hello\tWorld");
        $this->assertStringContainsString('##$_09$##', $result);
        $this->assertStringNotContainsString("\t", $result);
    }

    #[Test]
    public function preprocessWithNewlines(): void
    {
        $result = $this->preprocessor->preprocess("Hello\nWorld");
        $this->assertStringContainsString('##$_0A$##', $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    #[Test]
    public function preprocessWithCarriageReturn(): void
    {
        $result = $this->preprocessor->preprocess("Hello\rWorld");
        $this->assertStringContainsString('##$_0D$##', $result);
        $this->assertStringNotContainsString("\r", $result);
    }

    #[Test]
    public function preprocessWithMultipleControlChars(): void
    {
        $result = $this->preprocessor->preprocess("Hello\t\n\rWorld");
        $this->assertStringContainsString('##$_09$##', $result);
        $this->assertStringContainsString('##$_0A$##', $result);
        $this->assertStringContainsString('##$_0D$##', $result);
    }

    // ========== Replace ASCII Tests ==========

    #[Test]
    public function replaceAsciiNullChar(): void
    {
        $result = $this->preprocessor->replaceAscii("Hello\x00World");
        $this->assertStringContainsString('##$_00$##', $result);
    }

    #[Test]
    public function replaceAsciiStartOfHeader(): void
    {
        $result = $this->preprocessor->replaceAscii("Hello\x01World");
        $this->assertStringContainsString('##$_01$##', $result);
    }

    #[Test]
    public function replaceAsciiDeleteChar(): void
    {
        $result = $this->preprocessor->replaceAscii("Hello\x7FWorld");
        $this->assertStringContainsString('##$_7F$##', $result);
    }

    #[Test]
    public function replaceAsciiPreservesNormalText(): void
    {
        $text = 'Hello World 123 !@#$%';
        $result = $this->preprocessor->replaceAscii($text);
        $this->assertEquals($text, $result);
    }

    #[Test]
    public function replaceAsciiMultipleOccurrences(): void
    {
        $result = $this->preprocessor->replaceAscii("\t\t\t");
        $this->assertEquals('##$_09$####$_09$####$_09$##', $result);
    }

    // ========== Replace Hex Entities Tests ==========

    #[Test]
    public function replaceHexEntitiesTab(): void
    {
        $result = $this->preprocessor->replaceHexEntities('Hello&#x09;World');
        $this->assertStringContainsString('##$_09$##', $result);
    }

    #[Test]
    public function replaceHexEntitiesNewline(): void
    {
        $result = $this->preprocessor->replaceHexEntities('Hello&#x0A;World');
        $this->assertStringContainsString('##$_0A$##', $result);
    }

    #[Test]
    public function replaceHexEntitiesCarriageReturn(): void
    {
        $result = $this->preprocessor->replaceHexEntities('Hello&#x0D;World');
        $this->assertStringContainsString('##$_0D$##', $result);
    }

    #[Test]
    public function replaceHexEntitiesShortForm(): void
    {
        // &#x9; is equivalent to &#x09;
        $result = $this->preprocessor->replaceHexEntities('Hello&#x9;World');
        $this->assertStringContainsString('##$_09$##', $result);
    }

    #[Test]
    public function replaceHexEntitiesUpperCase(): void
    {
        // Uppercase hex entity &#x0A; should be replaced
        $result = $this->preprocessor->replaceHexEntities('Hello&#x0A;World');
        $this->assertStringContainsString('##$_0A$##', $result);
    }

    #[Test]
    public function replaceHexEntitiesPreservesNormalEntities(): void
    {
        // Non-control character entities should be preserved
        $text = 'Hello&#x20;World'; // Space is in range but hex 20 might not be in map
        $result = $this->preprocessor->replaceHexEntities($text);
        // Since 0x20 (space) is not in asciiPlaceHoldMap, it should be unchanged
        $this->assertEquals($text, $result);
    }

    #[Test]
    public function replaceHexEntitiesNull(): void
    {
        $result = $this->preprocessor->replaceHexEntities('Hello&#x00;World');
        $this->assertStringContainsString('##$_00$##', $result);
    }

    // ========== Fill Empty HTML Tags Tests ==========

    #[Test]
    public function fillEmptyHTMLTagsWithPlaceholderGTag(): void
    {
        $result = $this->preprocessor->fillEmptyHTMLTagsWithPlaceholder('<g id="1"></g>');
        $this->assertStringContainsString(ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER, $result);
        $this->assertEquals('<g id="1">' . ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER . '</g>', $result);
    }

    #[Test]
    public function fillEmptyHTMLTagsWithPlaceholderMultiple(): void
    {
        $result = $this->preprocessor->fillEmptyHTMLTagsWithPlaceholder('<g id="1"></g><g id="2"></g>');
        $this->assertEquals(
            '<g id="1">' . ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER . '</g>' .
            '<g id="2">' . ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER . '</g>',
            $result
        );
    }

    #[Test]
    public function fillEmptyHTMLTagsPreservesNonEmpty(): void
    {
        $text = '<g id="1">Content</g>';
        $result = $this->preprocessor->fillEmptyHTMLTagsWithPlaceholder($text);
        $this->assertEquals($text, $result);
    }

    #[Test]
    public function fillEmptyHTMLTagsWithNestedTags(): void
    {
        $text = '<g id="1"><x id="2"/></g>';
        $result = $this->preprocessor->fillEmptyHTMLTagsWithPlaceholder($text);
        $this->assertEquals($text, $result);
    }

    #[Test]
    public function fillEmptyHTMLTagsWithSelfClosing(): void
    {
        $text = '<x id="1"/>';
        $result = $this->preprocessor->fillEmptyHTMLTagsWithPlaceholder($text);
        $this->assertEquals($text, $result);
    }

    #[Test]
    public function fillEmptyHTMLTagsDifferentTagTypes(): void
    {
        $result = $this->preprocessor->fillEmptyHTMLTagsWithPlaceholder('<ph id="1"></ph>');
        $this->assertStringContainsString(ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER, $result);
    }

    // ========== Clean Output Content Tests ==========

    #[Test]
    public function cleanOutputContentRemovesPlaceholder(): void
    {
        $content = '<g id="1">' . ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER . '</g>';
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertEquals('<g id="1"></g>', $result);
    }

    #[Test]
    public function cleanOutputContentRestoresNewline(): void
    {
        $content = "Hello##\$_0A\$##World";
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertStringContainsString('&#x0A;', $result);
    }

    #[Test]
    public function cleanOutputContentRestoresTab(): void
    {
        $content = "Hello##\$_09\$##World";
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertStringContainsString('&#x09;', $result);
    }

    #[Test]
    public function cleanOutputContentEncodesNewline(): void
    {
        $content = "Hello\nWorld";
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertStringContainsString('&#10;', $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    #[Test]
    public function cleanOutputContentEncodesCarriageReturn(): void
    {
        $content = "Hello\rWorld";
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertStringContainsString('&#13;', $result);
        $this->assertStringNotContainsString("\r", $result);
    }

    #[Test]
    public function cleanOutputContentEncodesTab(): void
    {
        $content = "Hello\tWorld";
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertStringContainsString('&#09;', $result);
        $this->assertStringNotContainsString("\t", $result);
    }

    #[Test]
    public function cleanOutputContentEncodesNbsp(): void
    {
        $content = "Hello\xc2\xa0World"; // NBSP in UTF-8
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertStringContainsString('&#160;', $result);
    }

    #[Test]
    public function cleanOutputContentPlainText(): void
    {
        $content = 'Hello World';
        $result = $this->preprocessor->cleanOutputContent($content);
        $this->assertEquals('Hello World', $result);
    }

    // ========== Round-trip Tests ==========

    #[Test]
    public function preprocessAndCleanRoundTrip(): void
    {
        $original = "Hello\tWorld";
        $preprocessed = $this->preprocessor->preprocess($original);
        $cleaned = $this->preprocessor->cleanOutputContent($preprocessed);

        // After cleaning, tab placeholder should become &#x09;
        $this->assertStringContainsString('&#x09;', $cleaned);
    }

    #[Test]
    public function preprocessWithEmptyTagAndClean(): void
    {
        $original = '<g id="1"></g>Text';
        $preprocessed = $this->preprocessor->preprocess($original);
        $cleaned = $this->preprocessor->cleanOutputContent($preprocessed);

        $this->assertEquals('<g id="1"></g>Text', $cleaned);
    }

    // ========== Edge Cases ==========

    #[Test]
    public function preprocessWithAllControlChars(): void
    {
        // Test multiple control characters in sequence
        $input = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F";
        $result = $this->preprocessor->preprocess($input);

        $this->assertStringContainsString('##$_00$##', $result);
        $this->assertStringContainsString('##$_01$##', $result);
        $this->assertStringContainsString('##$_09$##', $result);
        $this->assertStringContainsString('##$_0A$##', $result);
        $this->assertStringContainsString('##$_0D$##', $result);
    }

    #[Test]
    public function preprocessUTF8Encoding(): void
    {
        $input = 'Héllo Wörld 日本語';
        $result = $this->preprocessor->preprocess($input);
        $this->assertEquals($input, $result);
    }

    #[Test]
    public function preprocessXmlTags(): void
    {
        $input = '<g id="1">Text</g>';
        $result = $this->preprocessor->preprocess($input);
        $this->assertEquals($input, $result);
    }

    #[Test]
    public function preprocessWithMixedContent(): void
    {
        $input = '<g id="1">Hello' . "\t" . 'World</g><g id="2"></g>';
        $result = $this->preprocessor->preprocess($input);

        $this->assertStringContainsString('##$_09$##', $result);
        $this->assertStringContainsString(ContentPreprocessor::EMPTY_HTML_TAGS_PLACEHOLDER, $result);
    }
}

