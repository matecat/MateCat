<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\XliffToTargetConverterController;
use Klein\DataCollection\DataCollection;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Conversion\Filters;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

/**
 * Testable subclass: empty ctor bypassed via newInstanceWithoutConstructor,
 * upload mechanics + conversion engine substituted through the protected seams.
 */
class TestableXliffToTargetConverterController extends XliffToTargetConverterController
{
    public string $xliffPath = '';
    public ?Filters $filtersStub = null;

    protected function prepareUploadedXliff(): string
    {
        return $this->xliffPath;
    }

    protected function createFilters(): Filters
    {
        return $this->filtersStub ?? parent::createFilters();
    }
}

#[Group('unit')]
class XliffToTargetConverterControllerTest extends AbstractTest
{
    private string $tmpFile = '';

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        $this->tmpFile = '';
        parent::tearDown();
    }

    // ─── convert(): success path ──────────────────────────────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function convertWritesEncodedPayloadOnSuccessfulConversion(): void
    {
        $path = $this->writeTempXliff('<xliff>dummy</xliff>');
        $filters = $this->makeFiltersStub([
            'successful'       => true,
            'fileName'         => 'result.docx',
            'document_content' => 'BINARY-CONTENT',
        ]);

        $captured = null;
        $controller = $this->makeController($path, $filters, $captured);

        $controller->convert();

        $this->assertIsString($captured);
        $decoded = json_decode($captured, true);
        $this->assertSame('result.docx', $decoded['fileName']);
        $this->assertSame(base64_encode('BINARY-CONTENT'), $decoded['fileContent']);
    }

    // ─── convert(): error path with message ───────────────────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function convertReturnsErrorMessageWhenConversionFails(): void
    {
        $path = $this->writeTempXliff('<xliff>dummy</xliff>');
        $filters = $this->makeFiltersStub([
            'successful'   => false,
            'errorMessage' => 'broken xliff',
        ]);

        $captured = null;
        $controller = $this->makeController($path, $filters, $captured);

        $controller->convert();

        $this->assertSame('broken xliff', $captured);
    }

    // ─── convert(): error path with empty message ─────────────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function convertReturnsFallbackMessageWhenErrorMessageEmpty(): void
    {
        $path = $this->writeTempXliff('<xliff>dummy</xliff>');
        $filters = $this->makeFiltersStub([
            'successful'   => false,
            'errorMessage' => '',
        ]);

        $captured = null;
        $controller = $this->makeController($path, $filters, $captured);

        $controller->convert();

        $this->assertSame('(No error message provided)', $captured);
    }

    // ─── convert(): unreadable file => RuntimeException ───────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function convertThrowsWhenUploadedFileCannotBeRead(): void
    {
        $captured = null;
        $controller = $this->makeController('/nonexistent/path/to/file.xlf', $this->makeFiltersStub([]), $captured);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read uploaded xliff file');

        $controller->convert();
    }

    // ─── seam: createFilters() returns a real Filters ─────────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function createFiltersReturnsFiltersInstance(): void
    {
        $reflection = new ReflectionClass(XliffToTargetConverterController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('createFilters');
        $this->assertInstanceOf(Filters::class, $method->invoke($controller));
    }

    // ─── seam: prepareUploadedXliff() builds the .xlf path ────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function prepareUploadedXliffAppendsXlfExtensionToTmpName(): void
    {
        $files = $this->createStub(DataCollection::class);
        $files->method('get')->willReturn(['tmp_name' => '/tmp/php_upload_abc']);

        $request = $this->createStub(Request::class);
        $request->method('files')->willReturn($files);

        $reflection = new ReflectionClass(XliffToTargetConverterController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        (new ReflectionProperty($controller, 'request'))->setValue($controller, $request);

        $result = $reflection->getMethod('prepareUploadedXliff')->invoke($controller);

        $this->assertSame('/tmp/php_upload_abc.xlf', $result);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $conversionRow
     * @throws ReflectionException
     */
    private function makeController(string $xliffPath, Filters $filters, mixed &$capturedBody): TestableXliffToTargetConverterController
    {
        $reflection = new ReflectionClass(TestableXliffToTargetConverterController::class);
        /** @var TestableXliffToTargetConverterController $controller */
        $controller = $reflection->newInstanceWithoutConstructor();
        $controller->xliffPath = $xliffPath;
        $controller->filtersStub = $filters;

        $response = $this->createStub(Response::class);
        $response->method('body')->willReturnCallback(function ($body) use (&$capturedBody, $response) {
            $capturedBody = $body;
            return $response;
        });

        (new ReflectionProperty($controller, 'request'))->setValue($controller, $this->createStub(Request::class));
        (new ReflectionProperty($controller, 'response'))->setValue($controller, $response);

        return $controller;
    }

    /**
     * @param array<string, mixed> $conversionRow
     */
    private function makeFiltersStub(array $conversionRow): Filters
    {
        $filters = $this->createStub(Filters::class);
        $filters->method('xliffToTarget')->willReturn([$conversionRow]);

        return $filters;
    }

    private function writeTempXliff(string $content): string
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'xliff_test_') . '.xlf';
        file_put_contents($this->tmpFile, $content);

        return $this->tmpFile;
    }
}
