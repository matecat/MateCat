<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\Abstracts\BaseKleinViewController;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Filter\ContentSecurityPolicySourcesEvent;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Throwable;
use Utils\Registry\AppConfig;
use Utils\Templating\PHPTALWithAppend;

/**
 * Runs the real BaseKleinViewController::render(), so the test fails if render() stops handing the plugins'
 * sources to the template: the other CSP tests set `x_plugin_csp` themselves or override render().
 */
class RenderPassesPluginCspTest extends AbstractTest
{
    #[Test]
    public function renderPutsThePluginSourcesInTheCspMetaTag(): void
    {
        $controller = (new ReflectionClass(CspRenderView::class))->newInstanceWithoutConstructor();

        // A plugin that adds one host, without loading a real plugin.
        $featureSet = self::createStub(FeatureSet::class);
        $featureSet->method('dispatch')->willReturnCallback(function (ContentSecurityPolicySourcesEvent $event) {
            $event->addSources('default-src', 'a.example.com');

            return $event;
        });
        $controller->setFeatureSet($featureSet);

        // A page that only prints the CSP meta tag.
        $view = new PHPTALWithAppend();
        $view->setSource(
            '<!DOCTYPE html><html><head><tal:block metal:use-macro="templates/CSP-HeaderMeta.html/CSP_NONCE"/></head><body></body></html>',
            AppConfig::$TEMPLATE_ROOT . '/csp_render_site_test.html'
        );
        $view->set('x_nonce_unique_id', 'test-nonce');
        $view->set('x_self_ajax_location_hosts', null);

        $body = '';
        $response = self::createStub(Response::class);
        $response->method('body')->willReturnCallback(function (string $html) use (&$body) {
            $body = $html;
        });

        $class = new ReflectionClass(BaseKleinViewController::class);
        $class->getProperty('view')->setValue($controller, $view);
        $class->getProperty('response')->setValue($controller, $response);

        try {
            $controller->render(200);
        } catch (Throwable) {
            // render() is declared `never`; what it sent to the response is the assertion.
        }

        self::assertStringContainsString('a.example.com', $body);
    }
}

class CspRenderView extends BaseKleinViewController
{
    public function renderView(): void
    {
    }
}
