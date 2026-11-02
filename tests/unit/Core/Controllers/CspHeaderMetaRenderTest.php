<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\Abstracts\BaseKleinViewController;
use DOMDocument;
use DOMElement;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Registry\AppConfig;
use Utils\Templating\CspDirective;
use Utils\Templating\PHPTALWithAppend;

/**
 * Renders the real `CSP-HeaderMeta.html` macro the way a page does: through PHPTAL, on a view whose plugin
 * placeholders were filled by BaseKleinViewController::applyPluginCspSources() from a real FeatureSet.
 *
 * Both copies of the template are covered: the core one, and the production one in `internal_scripts`,
 * which is skipped when that submodule is not checked out. The guarantee under test is that a plugin can
 * only widen the policy: every directive and every source the template grants survives, including the
 * `*.translated.com` CDN that Translated serves its files from.
 */
class CspHeaderMetaRenderTest extends AbstractTest
{
    private const string TEST_PROVIDER = 'test_csp_sources_provider';
    private const string BAD_PROVIDER = 'test_csp_bad_source_provider';

    /**
     * @return array<string, array{string}>
     */
    public static function templateDirectories(): array
    {
        return [
            'core template'       => ['lib/View/templates'],
            'production template' => ['internal_scripts/tracking_codes'],
        ];
    }

    #[Test]
    #[DataProvider('templateDirectories')]
    public function withoutPluginSourcesNoPlaceholderIsLeftInThePolicy(string $templateDir): void
    {
        $content = $this->renderPolicyContent($templateDir, []);

        self::assertStringNotContainsString('${', $content);
        self::assertStringNotContainsString('x_plugin_csp_', $content);
    }

    #[Test]
    #[DataProvider('templateDirectories')]
    public function pluginSourcesOnlyWidenThePolicy(string $templateDir): void
    {
        $baseline = $this->parsePolicy($this->renderPolicyContent($templateDir, []));
        $widened = $this->parsePolicy($this->renderPolicyContent($templateDir, [self::TEST_PROVIDER]));

        // No directive is added: one the template does not declare would shadow its default-src fallback.
        self::assertSame(array_keys($baseline), array_keys($widened));

        foreach ($baseline as $directive => $sources) {
            // Every source the template grants is still there, in its original order, ahead of the plugin's.
            self::assertSame($sources, array_slice($widened[$directive], 0, count($sources)), $directive);

            $added = array_values(array_slice($widened[$directive], count($sources)));
            self::assertSame(['csp-test.example.com'], $added, $directive);
        }

        self::assertCount(count(CspDirective::cases()), array_intersect(
            array_map(fn(CspDirective $d) => $d->value, CspDirective::cases()),
            array_keys($widened)
        ), 'every CspDirective case must have a placeholder in the template');
    }

    #[Test]
    public function translatedCdnIsStillAllowedWhenPluginsAddSources(): void
    {
        foreach ([[], [self::TEST_PROVIDER], ['airbnb'], ['airbnb', self::TEST_PROVIDER]] as $features) {
            $policy = $this->parsePolicy($this->renderPolicyContent('internal_scripts/tracking_codes', $features));

            $label = 'features: [' . implode(', ', $features) . ']';
            self::assertContains('*.translated.com', $policy['default-src'], $label);
            self::assertContains('*.translated.com', $policy['style-src'], $label);
            self::assertContains('api.laratranslate.com', $policy['default-src'], $label);
        }
    }

    #[Test]
    #[DataProvider('templateDirectories')]
    public function anAirbnbProjectAllowsTheScreenshotCdnOnTopOfTheTemplate(string $templateDir): void
    {
        $policy = $this->parsePolicy($this->renderPolicyContent($templateDir, ['airbnb']));

        self::assertContains('a0.muscache.com', $policy['default-src']);
        foreach (CspDirective::cases() as $directive) {
            if ($directive !== CspDirective::DEFAULT_SRC) {
                self::assertNotContains('a0.muscache.com', $policy[$directive->value], $directive->value);
            }
        }

        if ($templateDir === 'internal_scripts/tracking_codes') {
            self::assertContains('*.translated.com', $policy['default-src']);
        }
    }

    #[Test]
    #[DataProvider('templateDirectories')]
    public function aProjectWithoutTheAirbnbFeatureDoesNotAllowTheScreenshotCdn(string $templateDir): void
    {
        $content = $this->renderPolicyContent($templateDir, []);

        self::assertStringNotContainsString('muscache', $content);
    }

    #[Test]
    #[DataProvider('templateDirectories')]
    public function aPluginWithAMalformedSourceCannotBreakThePageOrThePolicy(string $templateDir): void
    {
        $baseline = $this->parsePolicy($this->renderPolicyContent($templateDir, ['airbnb']));
        $withBadPlugin = $this->parsePolicy($this->renderPolicyContent($templateDir, [self::BAD_PROVIDER, 'airbnb']));

        // The malformed plugin is logged and skipped; the other plugins and the template are unaffected.
        self::assertSame($baseline, $withBadPlugin);
        self::assertNotContains('script-src', array_keys($withBadPlugin));
    }

    /**
     * @param list<string> $featureCodes
     */
    private function renderPolicyContent(string $templateDir, array $featureCodes): string
    {
        $absoluteDir = AppConfig::$ROOT . '/' . $templateDir;
        if (!is_file($absoluteDir . '/CSP-HeaderMeta.html')) {
            self::markTestSkipped("$templateDir/CSP-HeaderMeta.html is not available (submodule not checked out)");
        }

        $featureSet = new FeatureSet(
            $this->createStub(IDatabase::class),
            array_map(fn(string $code) => new BasicFeatureStruct(['feature_code' => $code]), $featureCodes)
        );

        $view = new PHPTALWithAppend();
        $view->setSource(
            '<!DOCTYPE html><html><head><tal:block metal:use-macro="${csp_dir}/CSP-HeaderMeta.html/CSP_NONCE"/></head><body></body></html>'
        );
        $view->set('csp_dir', $absoluteDir);
        $view->set('x_nonce_unique_id', 'test-nonce');
        $view->set('x_self_ajax_location_hosts', null);

        $reflector = new ReflectionClass(CspRenderHarnessController::class);
        $controller = $reflector->newInstanceWithoutConstructor();
        $controller->setFeatureSet($featureSet);
        $reflector->getProperty('view')->setValue($controller, $view);
        $reflector->getProperty('templateName')->setValue($controller, 'context_preview.html');
        $reflector->getMethod('applyPluginCspSources')->invoke($controller);

        $document = new DOMDocument();
        @$document->loadHTML($view->execute());
        foreach ($document->getElementsByTagName('meta') as $meta) {
            if ($meta instanceof DOMElement && $meta->getAttribute('http-equiv') === 'Content-Security-Policy') {
                return $meta->getAttribute('content');
            }
        }

        self::fail('The rendered macro carries no Content-Security-Policy meta');
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
            $name = array_shift($tokens);
            self::assertArrayNotHasKey($name, $policy, "directive $name is declared twice");
            $policy[$name] = $tokens;
        }

        return $policy;
    }
}

class CspRenderHarnessController extends BaseKleinViewController
{
    protected function registerValidators(): void
    {
    }
}

namespace Plugins\Features;

use Model\FeaturesBase\Hook\Event\Filter\ContentSecurityPolicySourcesEvent;
use Utils\Templating\CspDirective;

/**
 * A plugin asking for one host in every directive it may extend.
 */
class TestCspSourcesProvider extends BaseFeature
{
    public const string FEATURE_CODE = 'test_csp_sources_provider';

    public function contentSecurityPolicySources(ContentSecurityPolicySourcesEvent $event): void
    {
        foreach (CspDirective::cases() as $directive) {
            $event->addSources($directive, 'csp-test.example.com');
        }
    }
}

/**
 * A plugin trying to close default-src and open a directive of its own.
 */
class TestCspBadSourceProvider extends BaseFeature
{
    public const string FEATURE_CODE = 'test_csp_bad_source_provider';

    public function contentSecurityPolicySources(ContentSecurityPolicySourcesEvent $event): void
    {
        $event->addSources(CspDirective::DEFAULT_SRC, 'evil.example.com; script-src *');
    }
}
