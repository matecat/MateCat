<?php

namespace Matecat\Core\View;

use Matecat\TestHelpers\AbstractTest;
use PHPTAL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Templating\PHPTalString;

/**
 * Regression guard for the team name reaching the cattool page inside an inline
 * <script>.
 *
 * PHPTAL emits interpolations verbatim inside a CDATA-marked <script> block, so a
 * template that supplies its own quotes — `team_name: '${team_name}'` — lets an
 * apostrophe in the team name close the literal and append JavaScript. The team name
 * is attacker controlled: `TeamsController` sanitizes it with
 * FILTER_SANITIZE_FULL_SPECIAL_CHARS and FILTER_FLAG_NO_ENCODE_QUOTES, which encodes
 * tags but deliberately keeps quotes.
 *
 * These tests pin both halves of the fix: the templates must not add quotes, and the
 * value must arrive wrapped so it carries its own.
 */
class CattoolTeamNameScriptContextTest extends AbstractTest
{

    private const string BREAKOUT = "x'-alert(1)-'";

    /**
     * Every source template under lib/View/templates. Built pages under lib/View are generated
     * from these by the frontend build and are deliberately out of scope: they are artifacts, and
     * a stale one would report a rule violation that no longer exists in any tracked file.
     *
     * @return array<string, array{string}>
     */
    public static function sourceTemplates(): array
    {
        $templates = glob(__DIR__ . '/../../../../lib/View/templates/*.html') ?: [];

        $cases = [];
        foreach ($templates as $template) {
            $cases[basename($template)] = [$template];
        }

        return $cases;
    }

    /**
     * The general form of the rule this class was opened for. PHPTAL supplies no quotes of its
     * own inside a script block, so a template that writes `key: \'${value}\'` is asking the value
     * to behave, and no page configuration should have to. Every value now reaches the page inside
     * one serialised document instead, which is why this can be asserted over the whole directory
     * rather than over one variable.
     */
    #[Test]
    #[DataProvider('sourceTemplates')]
    public function noTemplateWrapsAnInterpolationInItsOwnQuotesInsideAScriptBlock(string $template): void
    {
        $contents = file_get_contents($template);
        $this->assertIsString($contents, "cannot read $template");

        preg_match_all('#<script\b.*?</script>#s', $contents, $scripts);

        foreach ($scripts[0] as $script) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\'"]\$\{/',
                $script,
                basename($template) . ' quotes an interpolation inside a script block; pass the value'
                . ' through the serialised configuration document instead'
            );
        }
    }

    /**
     * The stronger form of the rule above, and the one that survives a careless edit. Removing the
     * quotes is not enough on its own: an unquoted `${value}` inside a script block still writes
     * whatever the value holds straight into executable text, and PHPTAL supplies no escaping there
     * beyond `</`. Only a value that arrives already serialised as JSON with tags, ampersands and
     * both quote characters hex-escaped can be dropped into that context safely, which is exactly
     * what `config_json` is. Anything else in a script block fails here rather than in production.
     *
     * @see \Utils\Templating\BootstrapConfig::JSON_FLAGS
     */
    #[Test]
    #[DataProvider('sourceTemplates')]
    public function everyInterpolationInsideAScriptBlockIsASerialisedJsonDocument(string $template): void
    {
        $contents = file_get_contents($template);
        $this->assertIsString($contents, "cannot read $template");

        preg_match_all('#<script\b[^>]*>.*?</script>#si', $contents, $scripts);

        foreach ($scripts[0] as $script) {
            preg_match_all('/\$\{[^}]*}/', $script, $interpolations);

            foreach ($interpolations[0] as $interpolation) {
                $this->assertTrue(
                    self::isASerialisedJsonDocument($interpolation),
                    basename($template) . " interpolates $interpolation inside a script block."
                    . ' Only a JSON document hex-escaping tags, ampersands and quotes may be written'
                    . ' there: add the value to the configuration document and read it from there'
                );
            }
        }
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function interpolationSamples(): array
    {
        return [
            'the configuration document' => ['${structure config_json}', true],
            'an inline encode carrying every hex flag' => [
                '${structure php: json_encode(flashMessages, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)}',
                true,
            ],
            'a bare value' => ['${team_name}', false],
            'a bare value marked structure' => ['${structure team_name}', false],
            'an encode that forgets the quote flags' => [
                '${structure php: json_encode(flashMessages, JSON_HEX_TAG|JSON_HEX_AMP)}',
                false,
            ],
            'an encode with no flags at all' => ['${structure php: json_encode(flashMessages)}', false],
            'a php expression that is not an encode' => ['${structure php: user/email}', false],
        ];
    }

    /**
     * Proves the sweep above can fail. Without this it could pass because the rule accepts
     * everything rather than because the templates comply.
     */
    #[Test]
    #[DataProvider('interpolationSamples')]
    public function theRuleAcceptsOnlySerialisedDocuments(string $interpolation, bool $expected): void
    {
        $this->assertSame($expected, self::isASerialisedJsonDocument($interpolation));
    }

    private static function isASerialisedJsonDocument(string $interpolation): bool
    {
        $expression = trim(substr($interpolation, 2, -1));

        // without `structure` PHPTAL escapes for HTML, which is the wrong escaping for a script
        // block and not a reason to allow the value through
        if (!str_starts_with($expression, 'structure ')) {
            return false;
        }

        $value = trim(substr($expression, strlen('structure ')));

        if ($value === 'config_json') {
            return true;
        }

        if (!str_starts_with($value, 'php: json_encode(')) {
            return false;
        }

        foreach (['JSON_HEX_TAG', 'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT'] as $flag) {
            if (!str_contains($value, $flag)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The other half: the cattool page must actually be reading that document. Without this the
     * test above passes for a template that simply stopped configuring anything.
     */
    #[Test]
    public function theCattoolTemplateBuildsItsConfigurationFromTheSerialisedDocument(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../../../lib/View/templates/_index.html');
        $this->assertIsString($contents, 'cannot read _index.html');

        $this->assertStringContainsString(
            'var config = ${structure config_json};',
            $contents,
            'the cattool page must take its configuration from the serialised document'
        );
        $this->assertStringNotContainsString(
            '${team_name}',
            $contents,
            'the team name must reach the page through the document, not through its own interpolation'
        );
    }

    /**
     * End to end through PHPTAL itself, in the same output mode and CDATA-marked block
     * shape the cattool page uses.
     */
    #[Test]
    public function aBreakoutPayloadStaysInsideTheLiteralWhenRenderedByPhptal(): void
    {
        $rendered = $this->renderScriptBlock(new PHPTalString(self::BREAKOUT));

        // the payload arrives as one JavaScript string that decodes back to itself,
        // and not a single apostrophe survives to close that string early
        preg_match('/team_name: (".*"),/', $rendered, $matches);
        $this->assertCount(2, $matches, 'team_name must render as a quoted literal');
        $this->assertSame(self::BREAKOUT, json_decode($matches[1]));
        $this->assertStringNotContainsString(chr(39), $rendered);
    }

    /**
     * Proves the guard above can fail: the previous, quote-supplying template shape does
     * let the payload out. Without this the test above could pass for the wrong reason.
     */
    #[Test]
    public function theSupersededQuotedShapeIsShownToBeExploitable(): void
    {
        $rendered = $this->renderScriptBlock(self::BREAKOUT, "team_name: '\${team_name}',");

        $this->assertStringContainsString("team_name: 'x'-alert(1)-''", $rendered);
    }

    #[Test]
    public function aLegitimateNameSurvivesUnchangedForTheBrowser(): void
    {
        $rendered = $this->renderScriptBlock(new PHPTalString("O'Brien & Sons <Ltd>"));

        $this->assertMatchesRegularExpression('/team_name: (".*")/', $rendered);
        preg_match('/team_name: (".*"),/', $rendered, $matches);
        $this->assertSame("O'Brien & Sons <Ltd>", json_decode($matches[1]));
    }

    /**
     * Renders a minimal copy of the cattool inline-config block.
     */
    private function renderScriptBlock(mixed $teamName, string $line = 'team_name: ${team_name},'): string
    {
        $templatePath = tempnam(sys_get_temp_dir(), 'phptal_') . '.html';
        file_put_contents(
            $templatePath,
            "<html><body>\n"
            . "<script type=\"text/javascript\">\n"
            . "/*<![CDATA[*/\n"
            . "var config = {\n"
            . "    " . $line . "\n"
            . "    done: true\n"
            . "}\n"
            . "/*]]>*/\n"
            . "</script>\n"
            . "</body></html>"
        );

        try {
            $view = new PHPTAL($templatePath);
            $view->setOutputMode(PHPTAL::HTML5);
            $view->team_name = $teamName;

            return $view->execute();
        } finally {
            unlink($templatePath);
        }
    }

}
