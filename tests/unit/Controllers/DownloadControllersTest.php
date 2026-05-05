<?php

namespace unit\Controllers;

use Controller\API\App\DownloadAnalysisReportController;
use Controller\API\V2\DownloadController;
use Controller\API\V2\DownloadJobTMXController;
use Controller\API\V2\DownloadOriginalController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(DownloadController::class)]
#[CoversClass(DownloadOriginalController::class)]
#[CoversClass(DownloadJobTMXController::class)]
#[CoversClass(DownloadAnalysisReportController::class)]
class DownloadControllersTest extends TestCase
{
    // --- DownloadController::pathinfoString ---

    #[Test]
    public function pathinfoStringReturnsBasename(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/file.xlf', PATHINFO_BASENAME);
        $this->assertSame('file.xlf', $result);
    }

    #[Test]
    public function pathinfoStringReturnsExtension(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/file.sdlxliff', PATHINFO_EXTENSION);
        $this->assertSame('sdlxliff', $result);
    }

    #[Test]
    public function pathinfoStringReturnsFilename(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/document.docx', PATHINFO_FILENAME);
        $this->assertSame('document', $result);
    }

    #[Test]
    public function pathinfoStringReturnsDirname(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/to/document.docx', PATHINFO_DIRNAME);
        $this->assertSame('/path/to', $result);
    }

    #[Test]
    public function pathinfoStringHandlesUnicodeFilenames(): void
    {
        $controller = $this->createDownloadController();
        $method = new ReflectionMethod($controller, 'pathinfoString');

        $result = $method->invoke($controller, '/path/日本語ファイル.txt', PATHINFO_EXTENSION);
        $this->assertSame('txt', $result);
    }

    // --- DownloadAnalysisReportController::validateTheRequest ---

    #[Test]
    public function validateTheRequestThrowsInvalidArgumentExceptionWithCorrectMessageAndCode(): void
    {
        $controller = $this->createAnalysisReportController(['id_project' => '999999']);
        $method = new ReflectionMethod($controller, 'validateTheRequest');

        try {
            $method->invoke($controller);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Verify the bug fix: message is string, code is int
            $this->assertSame("Wrong Id project provided", $e->getMessage());
            $this->assertSame(-10, $e->getCode());
        }
    }

    #[Test]
    public function validateTheRequestThrowsWhenIdProjectEmpty(): void
    {
        $controller = $this->createAnalysisReportController(['id_project' => '0']);
        $method = new ReflectionMethod($controller, 'validateTheRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Id project not provided");

        $method->invoke($controller);
    }

    #[Test]
    public function validateTheRequestThrowsWhenIdProjectZero(): void
    {
        // id_project = '0' is treated as empty
        $controller = $this->createAnalysisReportController(['id_project' => '0']);
        $method = new ReflectionMethod($controller, 'validateTheRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Id project not provided");

        $method->invoke($controller);
    }

    // --- DownloadOriginalController filter_var handling ---

    #[Test]
    public function downloadOriginalControllerIdJobPropertyIsTypedInt(): void
    {
        $controller = $this->createOriginalController();
        $prop = new ReflectionProperty($controller, 'id_job');
        $prop->setValue($controller, 42);

        $this->assertIsInt($prop->getValue($controller));
    }

    // --- DownloadJobTMXController::$errors property type ---

    #[Test]
    public function downloadJobTMXControllerErrorsPropertyIsArray(): void
    {
        $controller = $this->createTMXController();
        $prop = new ReflectionProperty($controller, 'errors');
        $prop->setValue($controller, []);

        $this->assertIsArray($prop->getValue($controller));
    }

    // --- Helper factories ---

    private function createDownloadController(): DownloadController
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        return new class ($request, $response) extends DownloadController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };
    }

    private function createAnalysisReportController(array $params = []): DownloadAnalysisReportController
    {
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }
        $request = Request::createFromGlobals();
        $response = new Response();

        $controller = new class ($request, $response) extends DownloadAnalysisReportController {
            protected bool $useSession = false;

            protected function afterConstruct(): void
            {
                // Skip validators for unit testing
            }

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };

        foreach ($params as $key => $value) {
            unset($_GET[$key]);
        }

        return $controller;
    }

    private function createOriginalController(): DownloadOriginalController
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        return new class ($request, $response) extends DownloadOriginalController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };
    }

    private function createTMXController(): DownloadJobTMXController
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        return new class ($request, $response) extends DownloadJobTMXController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };
    }
}
