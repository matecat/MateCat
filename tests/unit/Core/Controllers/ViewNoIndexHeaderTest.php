<?php

namespace Matecat\Core\Controllers;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Exceptions\RenderTerminatedException;
use Utils\Registry\AppConfig;
use Controller\Views\ActivityLogController;
use Controller\Views\AnalyzeController;
use Controller\Views\CattoolController;
use Controller\Views\ContextReviewController;
use Controller\Views\ManageController;
use Controller\Views\QualityReportController;
use Controller\Views\SignInController;
use Controller\Views\UploadPageController;
use Controller\Views\XliffToTargetController;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Throwable;

/**
 * A view addressing one job carries its password in the URL, so the link is the credential and one
 * published on a crawlable page puts the address in a search index. These assert that such a view
 * answers with `X-Robots-Tag: noindex`, and that the three pages which exist to be found still do
 * not.
 *
 * @see BaseKleinViewController::$isIndexable
 */
class ViewNoIndexHeaderTest extends AbstractTest
{

    /**
     * @return array<string, array{class-string<BaseKleinViewController>}>
     */
    public static function nonIndexableViewProvider(): array
    {
        return [
            '/translate, /revise, /revise2' => [CattoolController::class],
            '/analyze, /jobanalysis'        => [AnalyzeController::class],
            '/revise-summary'               => [QualityReportController::class],
            '/activityLog'                  => [ActivityLogController::class],
            '/context-preview'              => [ContextReviewController::class],
            '/manage'                       => [ManageController::class],
        ];
    }

    /**
     * @return array<string, array{class-string<BaseKleinViewController>}>
     */
    public static function indexableViewProvider(): array
    {
        return [
            '/'                      => [UploadPageController::class],
            '/signin'                => [SignInController::class],
            '/utils/xliff-to-target' => [XliffToTargetController::class],
        ];
    }

    /**
     * @param class-string<BaseKleinViewController> $controllerClass
     */
    #[DataProvider('nonIndexableViewProvider')]
    public function testViewIsNotIndexable(string $controllerClass): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('header')
            ->with('X-Robots-Tag', 'noindex, nofollow');

        $this->renderWithoutView($controllerClass, $response);
    }

    /**
     * @param class-string<BaseKleinViewController> $controllerClass
     */
    #[DataProvider('indexableViewProvider')]
    public function testViewMeantToBeFoundSendsNoRobotsHeader(string $controllerClass): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->never())->method('header');

        $this->renderWithoutView($controllerClass, $response);
    }

    /**
     * The response a crawler actually gets for a job URL is the sign-in redirect, because it holds
     * no session and never reaches render(). That redirect has to carry the header too.
     *
     * @param class-string<BaseKleinViewController> $controllerClass
     */
    #[DataProvider('nonIndexableViewProvider')]
    public function testRedirectFromNonIndexableViewCarriesTheHeader(string $controllerClass): void
    {
        self::assertSame(['X-Robots-Tag: noindex, nofollow'], $this->headersOnRedirectFor($controllerClass));
    }

    /**
     * @param class-string<BaseKleinViewController> $controllerClass
     */
    #[DataProvider('indexableViewProvider')]
    public function testRedirectFromIndexableViewCarriesNoHeader(string $controllerClass): void
    {
        self::assertSame([], $this->headersOnRedirectFor($controllerClass));
    }

    /**
     * The sign-in redirect is the response a crawler actually receives, so the whole method has to
     * run with the header on it: the emission comes before the destination is recorded and before
     * the Location header goes out.
     */
    public function testTheSigninRedirectEmitsTheHeaderAndRecordsTheDestination(): void
    {
        $spy   = (new ReflectionClass(HeaderRecordingView::class))->newInstanceWithoutConstructor();
        $store = $this->injectSessionStore($spy);

        $_SERVER['REQUEST_URI'] = '/translate/project/it-IT-abc/1-abcdef123456';

        try {
            $spy->redirectToSignin();
        } catch (RenderTerminatedException) {
            // redirectToSignin() is declared `never`; under the testing environment it ends here.
        }

        self::assertSame(
            ['X-Robots-Tag: noindex, nofollow', 'Location: ' . AppConfig::$HTTPHOST . AppConfig::$BASEURL . 'signin'],
            $spy->sent,
            'The header has to precede the redirect it labels.'
        );
        self::assertSame('translate/project/it-IT-abc/1-abcdef123456', $store->get('wanted_url'));
    }

    /**
     * The post-login hop carries the header too: the address it hands back is the same job URL.
     */
    public function testTheWantedUrlRedirectEmitsTheHeaderAndConsumesTheDestination(): void
    {
        $spy   = (new ReflectionClass(HeaderRecordingView::class))->newInstanceWithoutConstructor();
        $store = $this->injectSessionStore($spy);
        $store->set('wanted_url', 'translate/project/it-IT-abc/1-abcdef123456');

        try {
            $spy->redirectToWantedUrl();
        } catch (RenderTerminatedException) {
            // redirectToWantedUrl() is declared `never`; under the testing environment it ends here.
        }

        $wanted = 'translate/project/it-IT-abc/1-abcdef123456';
        self::assertSame(
            ['X-Robots-Tag: noindex, nofollow', 'Location: ' . AppConfig::$HTTPHOST . AppConfig::$BASEURL . $wanted],
            $spy->sent,
            'The header has to precede the redirect it labels.'
        );
        self::assertFalse($store->has('wanted_url'), 'A consumed destination must not be replayed.');
    }

    /**
     * A header emitted under CLI cannot be read back, so the emission point is observed instead.
     *
     * @param class-string<BaseKleinViewController> $controllerClass
     *
     * @return list<string>
     */
    private function headersOnRedirectFor(string $controllerClass): array
    {
        $spy = (new ReflectionClass(HeaderRecordingView::class))->newInstanceWithoutConstructor();

        $isIndexableProperty = (new ReflectionClass(BaseKleinViewController::class))
            ->getProperty('isIndexable');
        $isIndexableProperty->setAccessible(true);
        $isIndexableProperty->setValue(
            $spy,
            (new ReflectionClass($controllerClass))->getDefaultProperties()['isIndexable']
        );

        $spy->redirectHeaders();

        return $spy->sent;
    }

    /**
     * A view added later is not indexable by omission.
     */
    public function testDefaultIsNotIndexable(): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('header')
            ->with('X-Robots-Tag', 'noindex, nofollow');

        $this->renderWithoutView(ViewDeclaringNothing::class, $response);
    }

    /**
     * Drives `render()` with no view assigned, so the header block runs while the PHPTAL execution
     * below it is skipped. The constructor is bypassed because it opens a database connection and
     * a session, neither of which the header decision reads.
     *
     * `render()` never returns: under the testing environment it ends in a
     * `RenderTerminatedException` after the header has already been written to the response.
     *
     * @param class-string<BaseKleinViewController> $controllerClass
     */
    private function renderWithoutView(string $controllerClass, Response $response): void
    {
        $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();

        $responseProperty = (new ReflectionClass(BaseKleinViewController::class))
            ->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($controller, $response);

        try {
            $controller->render(200);
        } catch (Throwable) {
            // render() is declared `never`; the mock expectations above are the assertion.
        }
    }

}

/**
 * Stands in for a view controller written later that says nothing about being indexable.
 */
class ViewDeclaringNothing extends BaseKleinViewController
{

    public function renderView(): void
    {
    }

}

/**
 * Records what the redirect path would have emitted, which a header sent under CLI cannot report.
 */
class HeaderRecordingView extends BaseKleinViewController
{

    /** @var list<string> */
    public array $sent = [];

    public function renderView(): void
    {
    }

    protected function emitRawHeader(string $header, bool $replace = true): void
    {
        $this->sent[] = $header;
    }

    public function redirectHeaders(): void
    {
        $this->sendNoIndexHeaderOnRedirect();
    }

}
