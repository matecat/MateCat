<?php

namespace Matecat\Core\View\API\Commons;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Utils\Registry\AppConfig;
use View\API\Commons\Error;

#[CoversClass(Error::class)]
class ErrorTest extends AbstractTest
{
    public function testRenderReturnsExpectedKeys(): void
    {
        $e      = new Exception('Something went wrong', 500);
        $view   = new Error($e);
        $result = $view->render();

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame([], $result['data']);
    }

    public function testRenderIncludesCodeAndMessage(): void
    {
        $e      = new RuntimeException('Test error', 404);
        $view   = new Error($e);
        $result = $view->render();

        $this->assertSame(404, $result['errors'][0]['code']);
        $this->assertSame('Test error', $result['errors'][0]['message']);
    }

    public function testRenderWithoutForcePrintErrorsOmitsFileAndLine(): void
    {
        $origPrint          = AppConfig::$PRINT_ERRORS;
        AppConfig::$PRINT_ERRORS = false;

        $e      = new Exception('quiet');
        $view   = new Error($e);
        $result = $view->render(false);

        AppConfig::$PRINT_ERRORS = $origPrint;

        $this->assertArrayNotHasKey('file', $result['errors'][0]);
        $this->assertArrayNotHasKey('line', $result['errors'][0]);
    }

    public function testRenderWithForcePrintErrorsIncludesFileAndLine(): void
    {
        $origPrint          = AppConfig::$PRINT_ERRORS;
        AppConfig::$PRINT_ERRORS = false;

        $e      = new Exception('verbose');
        $view   = new Error($e);
        $result = $view->render(true);

        AppConfig::$PRINT_ERRORS = $origPrint;

        $this->assertArrayHasKey('file', $result['errors'][0]);
        $this->assertArrayHasKey('line', $result['errors'][0]);
        $this->assertArrayHasKey('trace', $result['errors'][0]);
    }

    public function testRenderWithCausedByIncludesCausedBySection(): void
    {
        $cause  = new Exception('root cause', 1);
        $e      = new RuntimeException('outer', 2, $cause);
        $view   = new Error($e);
        $result = $view->render(true);

        $this->assertArrayHasKey('caused_by', $result['errors'][0]);
        $this->assertSame('root cause', $result['errors'][0]['caused_by']['message']);
    }

    public function testJsonSerializeReturnsSameAsRender(): void
    {
        $e    = new Exception('json test', 123);
        $view = new Error($e);

        $this->assertSame($view->render(), $view->jsonSerialize());
    }
}
