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

    // ── Spec rule 1: strip HTML, the anchor excepted ─────────────────────────────────────
    //
    // The anchor survives as an anchor. Showing the URL next to the label is a *rendering*
    // requirement, and the front end owns it: it is the only layer that knows which links the
    // current deployment lets a translator follow. Flattening here would take the decision away
    // from it, and would diverge from the shape every stored row already has.

    #[Test]
    public function anchorSurvivesAsAnAnchor(): void
    {
        $this->assertEquals(
            'This is a nested <a href="http://test.com">link</a>',
            InstructionsSanitizer::sanitize('This is a nested <a href="http://test.com">link</a>')
        );
    }

    #[Test]
    public function everyOtherTagIsStripped(): void
    {
        $this->assertEquals(
            'This is a simple test. This is nested <a href="http://test.com">link</a>',
            InstructionsSanitizer::sanitize(
                '<p>This is a simple test. <span>This is nested <a href="http://test.com" onclick"XSS_INJECTION">link</a></span></p>'
            )
        );
    }

    #[Test]
    public function tagsNestedInsideTheAnchorAreStrippedFromTheLabel(): void
    {
        $this->assertEquals(
            '<a href="http://t.co">bold</a>',
            InstructionsSanitizer::sanitize('<a href="http://t.co"><b>bo</b>ld</a>')
        );
    }

    #[Test]
    public function everyAnchorIsKeptEvenWhenTheHrefIsDuplicated(): void
    {
        $this->assertEquals(
            '<a href="http://t.co">a</a> and <a href="http://t.co">b</a>',
            InstructionsSanitizer::sanitize('<a href="http://t.co">a</a> and <a href="http://t.co">b</a>')
        );
    }

    /**
     * The parser has already hoisted a nested anchor into a sibling, which is what keeps the output
     * re-parseable to the same tree.
     */
    #[Test]
    public function aNestedAnchorIsNotNestedInTheOutput(): void
    {
        $this->assertEquals(
            '<a href="https://a.com">out </a><a href="https://b.com">in</a>',
            InstructionsSanitizer::sanitize('<a href="https://a.com">out <a href="https://b.com">in</a></a>')
        );
    }

    #[Test]
    public function anAnchorWithoutHrefKeepsOnlyItsLabel(): void
    {
        $this->assertEquals('foo', InstructionsSanitizer::sanitize('<a name="x">foo</a>'));
    }

    #[Test]
    public function anAnchorWithoutLabelIsLabelledWithItsUrl(): void
    {
        $this->assertEquals(
            '<a href="https://x.com">https://x.com</a>',
            InstructionsSanitizer::sanitize('<a href="https://x.com"></a>')
        );
    }

    // ── The anchor attribute allowlist ──────────────────────────────────────────────────

    #[Test]
    public function targetIsKeptOnlyForItsValidTokens(): void
    {
        $this->assertEquals(
            '<a href="https://x.com" target="_blank">a</a> <a href="https://x.com">b</a>',
            InstructionsSanitizer::sanitize(
                '<a href="https://x.com" target="_BLANK">a</a> <a href="https://x.com" target="popup">b</a>'
            )
        );
    }

    #[Test]
    public function titleIsKeptAndEscaped(): void
    {
        $this->assertEquals(
            '<a href="https://x.com" title="Tom &amp; Jerry">y</a>',
            InstructionsSanitizer::sanitize('<a href="https://x.com" title="Tom & Jerry">y</a>')
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function droppedAttributeProvider(): array
    {
        return [
            'event handler' => ['<a href="https://x.com" onclick="alert(1)">y</a>'],
            'style' => ['<a href="https://x.com" style="position:fixed;inset:0">y</a>'],
            'name' => ['<a href="https://x.com" name="anchor">y</a>'],
            'data attribute' => ['<a href="https://x.com" data-payload="alert(1)">y</a>'],
            // The quote closes the attribute, so the parser reads the rest as further attributes.
            'target breakout' => ['<a href="https://x.com" target="_blank\" onclick=\"alert(1)">y</a>'],
        ];
    }

    #[Test]
    #[DataProvider('droppedAttributeProvider')]
    public function everyAttributeOutsideTheAllowlistIsDropped(string $raw): void
    {
        $this->assertEquals('<a href="https://x.com">y</a>', InstructionsSanitizer::sanitize($raw));
    }

    #[Test]
    public function anEscapedQuoteCannotBreakOutOfTheHref(): void
    {
        $this->assertEquals(
            '<a href="https://x.com/a">y</a>',
            InstructionsSanitizer::sanitize('<a href="https://x.com/a\"onmouseover=alert(1)">y</a>')
        );
    }

    #[Test]
    public function anAmpersandInTheQueryStringIsEscapedOnce(): void
    {
        $this->assertEquals(
            '<a href="https://x.com/?a=1&amp;b=2">y</a>',
            InstructionsSanitizer::sanitize('<a href="https://x.com/?a=1&amp;b=2">y</a>')
        );
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
    public function entityEscapedAnchorsAreDecodedAndKept(): void
    {
        $this->assertEquals(
            '**Project number**: <a href="https://cloud.memsource.com/web/project2/show/34962138" target="_blank">34962138</a>',
            InstructionsSanitizer::sanitize(
                '**Project number**: &lt;a href="https://cloud.memsource.com/web/project2/show/34962138" target="_blank"&gt;34962138&lt;/a&gt;'
            )
        );
    }

    /**
     * The stored value, end to end. This is the shape the integrations have always written and the
     * one the front end needs in order to decide anything: an anchor it can keep or flatten.
     */
    #[Test]
    public function theTosPayloadIsFullySanitized(): void
    {
        $expected = '**Project number**: <a href="https://cloud.memsource.com/web/project2/show/34962138" target="_blank">34962138</a>

**Project name**: Golden State Warriors 2023/24 Icon Edition Big Kids\' Nike NBA Swingman Jersey FZ0867

**Job number**: <a href="https://cloud.memsource.com/web/job/iMnDnhBVBUHXehBRvgnH71/translate" target="_blank">iMnDnhBVBUHXehBRvgnH71</a>

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
            'write <a href="mailto:a@b.com">me</a>',
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

    /**
     * Some integrations put a JSON blob in the instructions field. The keys and the text survive,
     * but an anchor inside one leaves the blob no longer parseable as JSON: the href quotes are
     * HTML-escaped, not JSON-escaped. Accepted deliberately — the value is stored to be injected as
     * HTML, nothing reads it back as JSON, and the alternative is losing the anchor entirely.
     */
    #[Test]
    public function jsonPayloadsKeepTheirStructure(): void
    {
        $json = '{"AdditionalInfo":"{}","DynamicValueExample":"<span>Example</span>","KeyName":"<a href=\"https://text.com\" target=\"_blank\">Test</a><br>","Repo":"<a href =\"https://test2.com\" target=\"_blank\">test2</a>"}';
        $expected = '{"AdditionalInfo":"{}","DynamicValueExample":"Example","KeyName":"<a href="https://text.com">Test</a>","Repo":"<a href="https://test2.com">test2</a>"}';

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
            'anchor with target' => ['<a href="https://x.com" target="_blank" title="Tom & Jerry">y</a>'],
            'nested anchor' => ['<a href="https://a.com">out <a href="https://b.com">in</a></a>'],
            'ampersand in query' => ['<a href="https://x.com/?a=1&b=2">y</a>'],
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

    /**
     * The two write paths hand the sanitizer a differently prepared string and must still store the
     * same value. `/api/v1/new` sanitizes first and Uber's htmlentities/html_entity_decode pair
     * wraps that as a net identity; the v3 endpoint dispatches the decode hook *before* sanitizing,
     * so the sanitizer sees an already decoded payload.
     *
     * They agree because sanitize() decodes to a fixed point on its own, which absorbs the extra
     * decode. The equality holds only while that pair stays an identity: a plugin implementing just
     * `encodeInstructions` would break it.
     */
    #[Test]
    #[DataProvider('idempotencyProvider')]
    public function theTwoWritePathsStoreTheSameValue(string $raw): void
    {
        // POST /api/v1|v2/new — NewController sanitizes, then the encode/decode pair round trips.
        $viaProjectCreation = html_entity_decode(htmlentities(InstructionsSanitizer::sanitize($raw)));

        // POST /api/v3/jobs/{id}/{password}/file/{id_file}/instructions — decode hook, then sanitize.
        $viaV3Endpoint = InstructionsSanitizer::sanitize(html_entity_decode($raw));

        $this->assertEquals($viaProjectCreation, $viaV3Endpoint);
    }
}
