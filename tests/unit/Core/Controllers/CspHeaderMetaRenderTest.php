<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use DOMDocument;
use DOMElement;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;
use Utils\Templating\PHPTALWithAppend;

/**
 * Renders the real `CSP-HeaderMeta.html` macro the way a page does: through PHPTAL, from a template in
 * `lib/View`, with the relative macro path every page uses and the `x_plugin_csp` map that
 * BaseKleinViewController::render() sets from the plugins.
 *
 * The guarantee under test is that a plugin can only widen the policy: the directives and the sources the
 * template grants stay exactly as they are, and a plugin's sources are appended to the directive it names.
 */
class CspHeaderMetaRenderTest extends AbstractTest
{
    private const array DIRECTIVES = ['default-src', 'script-src-elem', 'child-src', 'worker-src', 'font-src', 'style-src'];

    #[Test]
    public function withoutPluginSourcesThePolicyIsTheTemplatesOwn(): void
    {
        foreach (['empty map' => [], 'unset variable' => null] as $case => $map) {
            $content = $this->renderPolicyContent($map);
            $policy = $this->parsePolicy($content);

            self::assertSame(self::DIRECTIVES, array_keys($policy), $case);
            self::assertSame($this->templatePolicy(), $policy, $case);
            self::assertStringNotContainsString('${', $content, $case);
            self::assertStringNotContainsString("'unsafe-eval'", $content, $case);
        }
    }

    #[Test]
    public function withoutPluginSourcesThePolicyIsTodaysPolicy(): void
    {
        // Literals, not the template file: a host deleted from the template by mistake must fail here.
        $policy = $this->parsePolicy($this->renderPolicyContent(null));

        self::assertSame(["'self'", "'nonce-test-nonce'", '*.googletagmanager.com'], $policy['script-src-elem']);
        self::assertSame(["'self'", '*.google.com', '*.hubspot.com'], $policy['child-src']);
        self::assertSame(["'self'"], $policy['worker-src']);
        self::assertSame(["'self'", 'fonts.gstatic.com', 'data:'], $policy['font-src']);
        self::assertSame(["'self'", "'unsafe-inline'", '*.googleapis.com'], $policy['style-src']);

        // default-src lists about 200 Google hosts; a few anchors are enough.
        foreach (["'self'", "'nonce-test-nonce'", '*.lexiqa.net', '*.hubspot.com', 'data:', '*.fbcdn.net'] as $source) {
            self::assertContains($source, $policy['default-src'], $source);
        }
    }

    #[Test]
    public function aPluginSourceIsAppendedToItsDirectiveOnly(): void
    {
        $baseline = $this->templatePolicy();
        $policy = $this->parsePolicy($this->renderPolicyContent(['default-src' => 'a.example.com wss:']));

        self::assertSame(self::DIRECTIVES, array_keys($policy));
        self::assertSame([...$baseline['default-src'], 'a.example.com', 'wss:'], $policy['default-src']);
        foreach (array_diff(self::DIRECTIVES, ['default-src']) as $directive) {
            self::assertSame($baseline[$directive], $policy[$directive], $directive);
        }
    }

    #[Test]
    public function aDirectiveTheTemplateDoesNotDeclareIsIgnored(): void
    {
        // A new img-src would shadow default-src for images and drop every host it grants.
        $content = $this->renderPolicyContent(['img-src' => 'a.example.com', 'style-src' => 'b.example.com']);
        $policy = $this->parsePolicy($content);

        self::assertSame(self::DIRECTIVES, array_keys($policy));
        self::assertStringNotContainsString('a.example.com', $content);
        self::assertContains('b.example.com', $policy['style-src']);
    }

    /**
     * Every page must load the macro from the core template. A page still loading it through
     * TRACKING_CODES_VIEW_PATH would break as soon as the platform's copy in internal_scripts is deleted.
     */
    #[Test]
    public function everyPageLoadsTheMacroFromTheCoreTemplate(): void
    {
        $pages = glob(AppConfig::$TEMPLATE_ROOT . '/templates/_*.html') ?: [];
        $including = array_filter($pages, fn(string $page) => str_contains((string)file_get_contents($page), 'CSP-HeaderMeta.html'));

        self::assertNotEmpty($including);
        foreach ($including as $page) {
            self::assertStringContainsString(
                'metal:use-macro="templates/CSP-HeaderMeta.html/CSP_NONCE"',
                (string)file_get_contents($page),
                basename($page)
            );
            self::assertStringNotContainsString('TRACKING_CODES_VIEW_PATH}/CSP-HeaderMeta', (string)file_get_contents($page), basename($page));
        }
    }

    /**
     * @param array<string, string>|null $pluginCsp null leaves the variable unset
     */
    private function renderPolicyContent(?array $pluginCsp): string
    {
        $view = new PHPTALWithAppend();
        // A source path inside lib/View, so the relative macro path resolves the way it does for a real page.
        $view->setSource(
            '<!DOCTYPE html><html><head><tal:block metal:use-macro="templates/CSP-HeaderMeta.html/CSP_NONCE"/></head><body></body></html>',
            AppConfig::$TEMPLATE_ROOT . '/csp_render_test.html'
        );
        $view->set('x_nonce_unique_id', 'test-nonce');
        $view->set('x_self_ajax_location_hosts', null);
        if ($pluginCsp !== null) {
            $view->set('x_plugin_csp', $pluginCsp);
        }

        return $this->metaContent($view->execute());
    }

    /**
     * The policy the template file declares, with the plugin placeholders left out.
     *
     * @return array<string, list<string>>
     */
    private function templatePolicy(): array
    {
        $template = (string)file_get_contents(AppConfig::$TEMPLATE_ROOT . '/templates/CSP-HeaderMeta.html');
        $template = (string)preg_replace('#\$\{x_plugin_csp/[a-z-]+ \| nothing\}#', '', $template);
        $template = str_replace(['${x_nonce_unique_id}', '${x_self_ajax_location_hosts}'], ['test-nonce', ''], $template);

        return $this->parsePolicy($this->metaContent($template));
    }

    private function metaContent(string $html): string
    {
        $document = new DOMDocument();
        @$document->loadHTML($html);
        foreach ($document->getElementsByTagName('meta') as $meta) {
            if ($meta instanceof DOMElement && $meta->getAttribute('http-equiv') === 'Content-Security-Policy') {
                return $meta->getAttribute('content');
            }
        }

        self::fail('No Content-Security-Policy meta found');
    }

    /**
     * @return array<string, list<string>> directive name => sources, in order
     */
    private function parsePolicy(string $content): array
    {
        $policy = [];
        foreach (explode(';', $content) as $directive) {
            $tokens = preg_split('/\s+/', trim($directive), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens === []) {
                continue;
            }
            $name = (string)array_shift($tokens);
            self::assertArrayNotHasKey($name, $policy, "directive $name is declared twice");
            $policy[$name] = $tokens;
        }

        return $policy;
    }
}
