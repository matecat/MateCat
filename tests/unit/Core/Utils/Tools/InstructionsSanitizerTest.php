<?php

namespace Matecat\Core\Utils\Tools;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Tools\InstructionsSanitizer;

class InstructionsSanitizerTest extends AbstractTest
{
    /**
     * The payload TOS sends through the Phrase connector: markdown labels plus HTML anchors
     * delivered entity escaped.
     */
    private const TOS_PAYLOAD = '**Project number**: &lt;a href="https://cloud.memsource.com/web/project2/show/34962138" target="_blank"&gt;34962138&lt;/a&gt;

**Project name**: Golden State Warriors 2023/24 Icon Edition Big Kids\' Nike NBA Swingman Jersey FZ0867

**Job number**: &lt;a href="https://cloud.memsource.com/web/job/iMnDnhBVBUHXehBRvgnH71/translate" target="_blank"&gt;iMnDnhBVBUHXehBRvgnH71&lt;/a&gt;

**Buyer owner**: Locplatform Service User | locplatform | a.locplatform@nike.com

**Custom fields**: Consumer channel - NIKE.COM

Reference URL (Nike PM) - https://static.nike.com/images/622dc05b-de18-44ef-9177-4991d2b1a71d/image.jpg

**Project notes**:

Product: content sent to MTPE with Leverage + Translate en-US source';

    // ── Spec rule 1: strip HTML, extract the anchor URL and show it after the label ───────

    #[Test]
    public function anchorBecomesLabelFollowedByTheUrl(): void
    {
        $this->assertEquals(
            'This is a nested link (http://test.com)',
            InstructionsSanitizer::sanitize('This is a nested <a href="http://test.com">link</a>')
        );
    }

    #[Test]
    public function everyOtherTagIsStripped(): void
    {
        $this->assertEquals(
            'This is a simple test. This is nested link (http://test.com)',
            InstructionsSanitizer::sanitize(
                '<p>This is a simple test. <span>This is nested <a href="http://test.com" onclick"XSS_INJECTION">link</a></span></p>'
            )
        );
    }

    #[Test]
    public function tagsNestedInsideTheAnchorAreStrippedFromTheLabel(): void
    {
        $this->assertEquals(
            'bold (http://t.co)',
            InstructionsSanitizer::sanitize('<a href="http://t.co"><b>bo</b>ld</a>')
        );
    }

    #[Test]
    public function everyAnchorIsRewrittenEvenWhenTheHrefIsDuplicated(): void
    {
        $this->assertEquals(
            'a (http://t.co) and b (http://t.co)',
            InstructionsSanitizer::sanitize('<a href="http://t.co">a</a> and <a href="http://t.co">b</a>')
        );
    }

    #[Test]
    public function theUrlIsNotRepeatedWhenTheLabelAlreadyIsTheUrl(): void
    {
        $this->assertEquals(
            'https://x.com',
            InstructionsSanitizer::sanitize('<a href="https://x.com">https://x.com</a>')
        );
    }

    #[Test]
    public function anAnchorWithoutHrefKeepsOnlyItsLabel(): void
    {
        $this->assertEquals('foo', InstructionsSanitizer::sanitize('<a name="x">foo</a>'));
    }

    #[Test]
    public function anAnchorWithoutLabelKeepsOnlyItsUrl(): void
    {
        $this->assertEquals('https://x.com', InstructionsSanitizer::sanitize('<a href="https://x.com"></a>'));
    }

    // ── Spec rule 2: markdown is passed through untouched ────────────────────────────────

    #[Test]
    public function markdownLinksArePreservedVerbatim(): void
    {
        $string = 'This is a nested [link](http://test.com)';

        $this->assertEquals($string, InstructionsSanitizer::sanitize($string));
    }

    #[Test]
    public function markdownStructureIsPreserved(): void
    {
        $markdown = "# Title\n\n- a < b\n- **bold** and *italic* and `code`";

        $this->assertEquals($markdown, InstructionsSanitizer::sanitize($markdown));
    }

    // ── The reported bug: entity escaped anchors were never recognized ────────────────────

    #[Test]
    public function entityEscapedAnchorsAreDecodedAndRewritten(): void
    {
        $this->assertEquals(
            '**Project number**: 34962138 (https://cloud.memsource.com/web/project2/show/34962138)',
            InstructionsSanitizer::sanitize(
                '**Project number**: &lt;a href="https://cloud.memsource.com/web/project2/show/34962138" target="_blank"&gt;34962138&lt;/a&gt;'
            )
        );
    }

    #[Test]
    public function theTosPayloadIsFullySanitized(): void
    {
        $expected = '**Project number**: 34962138 (https://cloud.memsource.com/web/project2/show/34962138)

**Project name**: Golden State Warriors 2023/24 Icon Edition Big Kids\' Nike NBA Swingman Jersey FZ0867

**Job number**: iMnDnhBVBUHXehBRvgnH71 (https://cloud.memsource.com/web/job/iMnDnhBVBUHXehBRvgnH71/translate)

**Buyer owner**: Locplatform Service User | locplatform | a.locplatform@nike.com

**Custom fields**: Consumer channel - NIKE.COM

Reference URL (Nike PM) - https://static.nike.com/images/622dc05b-de18-44ef-9177-4991d2b1a71d/image.jpg

**Project notes**:

Product: content sent to MTPE with Leverage + Translate en-US source';

        $this->assertEquals($expected, InstructionsSanitizer::sanitize(self::TOS_PAYLOAD));
    }

    /**
     * Escaping markup twice must not smuggle it through: the stored value has to be inert text,
     * never something a later pass — or a consumer injecting it as HTML — would act on.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function doublyEscapedMarkupProvider(): array
    {
        return [
            'script' => ['&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;', ''],
            'javascript anchor' => ['&amp;lt;a href="javascript:alert(1)"&amp;gt;x&amp;lt;/a&amp;gt;', 'x'],
            'img onerror' => ['&amp;lt;img src=x onerror=alert(1)&amp;gt;', ''],
        ];
    }

    #[Test]
    #[DataProvider('doublyEscapedMarkupProvider')]
    public function doublyEscapedMarkupIsStrippedNotStored(string $raw, string $expected): void
    {
        $sanitized = InstructionsSanitizer::sanitize($raw);

        $this->assertEquals($expected, $sanitized);
        $this->assertStringNotContainsString('<', $sanitized);
    }

    /**
     * No nesting depth may produce tag shaped output, not even by exhausting the pass budget.
     */
    #[Test]
    public function deeplyEscapedMarkupNeverYieldsTagShapedOutput(): void
    {
        $payload = '<script>alert(1)</script>';

        for ($depth = 0; $depth < 8; $depth++) {
            $payload = htmlentities($payload);
            $sanitized = InstructionsSanitizer::sanitize($payload);

            $this->assertStringNotContainsString('<', $sanitized, "depth $depth");
            $this->assertEquals($sanitized, InstructionsSanitizer::sanitize($sanitized), "depth $depth");
        }
    }

    // ── Plain text stays plain text ──────────────────────────────────────────────────────

    #[Test]
    public function plainTextIsNotEntityEncoded(): void
    {
        $this->assertEquals(
            'Tom & Jerry x 5 < 6 > 2',
            InstructionsSanitizer::sanitize('Tom & Jerry <b>x</b> 5 < 6 > 2')
        );
    }

    #[Test]
    public function plainStringsArePreserved(): void
    {
        $this->assertEquals('This is a simple test.', InstructionsSanitizer::sanitize('This is a simple test.'));
    }

    #[Test]
    public function mdaStringsArePreserved(): void
    {
        $string = 'mda:key|¶|172f7f84-0245-485c-b2c6-aaef19bcf0f9';

        $this->assertEquals($string, InstructionsSanitizer::sanitize($string));
    }

    #[Test]
    public function nonUtf8InputIsConvertedWithoutLosingCharacters(): void
    {
        $this->assertEquals('café nice', InstructionsSanitizer::sanitize("caf\xE9 nice"));
    }

    #[Test]
    public function emptyAndBlankInputReturnAnEmptyString(): void
    {
        $this->assertEquals('', InstructionsSanitizer::sanitize(''));
        $this->assertEquals('', InstructionsSanitizer::sanitize("   \n\t "));
    }

    // ── Dangerous URL schemes ───────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string}>
     */
    public static function notAllowedUrlProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'uppercase javascript' => ['JavaScript:alert(1)'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'data' => ['data:text/html,hello'],
            'relative' => ['/relative/path'],
            'protocol relative' => ['//evil.com'],
        ];
    }

    #[Test]
    #[DataProvider('notAllowedUrlProvider')]
    public function notAllowedSchemesAreDroppedKeepingTheLabel(string $href): void
    {
        $this->assertEquals(
            'click here',
            InstructionsSanitizer::sanitize('click <a href="' . $href . '">here</a>')
        );
    }

    #[Test]
    public function mailtoIsAllowed(): void
    {
        $this->assertEquals(
            'write me (mailto:a@b.com)',
            InstructionsSanitizer::sanitize('write <a href="mailto:a@b.com">me</a>')
        );
    }

    // ── Subtrees whose body must not leak ───────────────────────────────────────────────

    #[Test]
    public function scriptBodyIsRemoved(): void
    {
        $this->assertEquals('Hello', InstructionsSanitizer::sanitize('<script>alert(1)</script>Hello'));
    }

    #[Test]
    public function styleBodyIsRemoved(): void
    {
        $this->assertEquals('hi', InstructionsSanitizer::sanitize('<style>p{color:red}</style>hi'));
    }

    // ── Images ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function imgSrcIsPreserved(): void
    {
        $this->assertEquals(
            'This is a simple test. https://placehold.co/600x400',
            InstructionsSanitizer::sanitize(
                '<p>This is a simple test. <img src="https://placehold.co/600x400" alt="Test"/></p>'
            )
        );
    }

    #[Test]
    public function multipleImgSrcArePreserved(): void
    {
        $this->assertEquals(
            'https://placehold.co/1 test https://placehold.co/2 test https://placehold.co/3',
            InstructionsSanitizer::sanitize(
                '<img src="https://placehold.co/1"/> test <img src="https://placehold.co/2"/> test <img src="https://placehold.co/3"/>'
            )
        );
    }

    #[Test]
    public function imgWithANotAllowedSchemeIsDropped(): void
    {
        $this->assertEquals('after', InstructionsSanitizer::sanitize('<img src="javascript:alert(1)"/>after'));
    }

    // ── Structured payloads ─────────────────────────────────────────────────────────────

    #[Test]
    public function jsonPayloadsKeepTheirStructure(): void
    {
        $json = '{"AdditionalInfo":"{}","DynamicValueExample":"<span>Example</span>","KeyName":"<a href=\"https://text.com\" target=\"_blank\">Test</a><br>","Repo":"<a href =\"https://test2.com\" target=\"_blank\">test2</a>"}';
        $expected = '{"AdditionalInfo":"{}","DynamicValueExample":"Example","KeyName":"Test (https://text.com)","Repo":"test2 (https://test2.com)"}';

        $this->assertEquals($expected, InstructionsSanitizer::sanitize($json));
    }

    // ── Idempotency ─────────────────────────────────────────────────────────────────────

    /**
     * Instructions are re-sanitized whenever they are updated through the v3 endpoint, so a
     * second pass must be a no-op. This is what the final entity decode buys us.
     *
     * @return array<string, array{0: string}>
     */
    public static function idempotencyProvider(): array
    {
        return [
            'tos payload' => [self::TOS_PAYLOAD],
            'html anchor' => ['This is a nested <a href="http://test.com">link</a>'],
            'markdown link' => ['This is a nested [link](http://test.com)'],
            'ampersand' => ['Tom & Jerry <b>x</b> 5 < 6 > 2'],
            'image' => ['<img src="https://placehold.co/600x400" alt="Test"/>'],
            'markdown document' => ["# Title\n\n- a < b\n- **bold** `code`"],
            'javascript href' => ['click <a href="javascript:alert(1)">here</a>'],
            'doubly escaped script' => ['&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;'],
            'json payload' => ['{"KeyName":"<a href=\"https://text.com\">Test</a><br>"}'],
        ];
    }

    #[Test]
    #[DataProvider('idempotencyProvider')]
    public function sanitizingTwiceChangesNothing(string $raw): void
    {
        $once = InstructionsSanitizer::sanitize($raw);

        $this->assertEquals($once, InstructionsSanitizer::sanitize($once));
    }
}
