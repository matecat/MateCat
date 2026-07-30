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
     * The tracked source template, plus the built page when a build is present locally:
     * lib/View/index.html is a build artifact and is not in the repository, so this must not
     * require it.
     *
     * @return array<string, array{string}>
     */
    public static function cattoolTemplates(): array
    {
        return [
            'source template' => [__DIR__ . '/../../../../lib/View/templates/_index.html'],
            'built page' => [__DIR__ . '/../../../../lib/View/index.html'],
        ];
    }

    /**
     * The whole fix rests on the template NOT quoting the interpolation, so pin it:
     * re-adding quotes around ${team_name} reopens the injection.
     */
    #[Test]
    #[DataProvider('cattoolTemplates')]
    public function theTemplateDoesNotWrapTheTeamNameInterpolationInQuotes(string $template): void
    {
        if (!file_exists($template)) {
            // build artifact absent: the source template case already covers the rule
            $this->markTestSkipped("$template is not present in this checkout");
        }

        $contents = file_get_contents($template);
        $this->assertIsString($contents, "cannot read $template");

        $this->assertMatchesRegularExpression(
            '/^\s*team_name:\s*\$\{team_name}\s*,\s*$/m',
            $contents,
            'team_name must be interpolated unquoted so PHPTalString can supply the quotes'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/team_name:\s*[\'"]\$\{/',
            $contents,
            'team_name must not be wrapped in template-supplied quotes'
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
