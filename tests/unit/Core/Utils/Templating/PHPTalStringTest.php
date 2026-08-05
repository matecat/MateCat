<?php

namespace Matecat\Core\Utils\Templating;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Templating\PHPTalString;

class PHPTalStringTest extends AbstractTest
{

    #[Test]
    public function rendersTheValueAsAQuotedLiteralSoTheTemplateNeedsNoQuotes(): void
    {
        $this->assertSame('"Acme Team"', (string)new PHPTalString('Acme Team'));
    }

    #[Test]
    public function nullRendersAsAnEmptyLiteral(): void
    {
        $this->assertSame('""', (string)new PHPTalString(null));
    }

    #[Test]
    public function emptyStringRendersAsAnEmptyLiteral(): void
    {
        $this->assertSame('""', (string)new PHPTalString(''));
    }

    /**
     * The characters that could end the literal or the surrounding <script> element
     * must never reach the output unescaped.
     */
    #[Test]
    #[DataProvider('breakoutCharacters')]
    public function escapesEveryCharacterThatCouldEndTheLiteralOrTheScriptElement(string $char): void
    {
        $rendered = (string)new PHPTalString('a' . $char . 'b');

        $this->assertStringNotContainsString($char, substr($rendered, 1, -1));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function breakoutCharacters(): array
    {
        return [
            'apostrophe' => ["'"],
            'quote' => ['"'],
            'less than' => ['<'],
            'greater than' => ['>'],
            'ampersand' => ['&'],
        ];
    }

    #[Test]
    public function neutralisesAnApostropheBreakoutPayload(): void
    {
        $rendered = (string)new PHPTalString("x'-alert(1)-'");

        $this->assertSame('"x\u0027-alert(1)-\u0027"', $rendered);
        $this->assertStringNotContainsString("'", $rendered);
    }

    #[Test]
    public function neutralisesAScriptClosingPayload(): void
    {
        $rendered = (string)new PHPTalString('</script><img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<', $rendered);
        $this->assertStringNotContainsString('>', $rendered);
    }

    /**
     * U+2028 and U+2029 are legal JSON but were literal line terminators inside a
     * JavaScript string before ES2019, so they must come out escaped too.
     */
    #[Test]
    public function escapesLineAndParagraphSeparators(): void
    {
        $rendered = (string)new PHPTalString("a\u{2028}b\u{2029}c");

        $this->assertStringNotContainsString("\u{2028}", $rendered);
        $this->assertStringNotContainsString("\u{2029}", $rendered);
    }

    /**
     * Escaping must not change what the browser ends up with: the rendered literal has
     * to decode back to the original value.
     */
    #[Test]
    #[DataProvider('roundTripValues')]
    public function theRenderedLiteralDecodesBackToTheOriginalValue(string $value): void
    {
        $this->assertSame($value, json_decode((string)new PHPTalString($value)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roundTripValues(): array
    {
        return [
            'plain' => ['Acme Team'],
            'apostrophe in a real name' => ["O'Brien & Sons"],
            'angle brackets' => ['<Ltd>'],
            'non ascii' => ['Équipe Ünicode 日本語'],
            'emoji' => ['Team 🚀'],
            'backslash' => ['C:\\path'],
        ];
    }

}
