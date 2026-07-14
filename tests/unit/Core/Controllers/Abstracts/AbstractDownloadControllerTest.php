<?php

namespace Matecat\Core\Controllers\Abstracts;

use Controller\Abstracts\AbstractDownloadController;
use Controller\Exceptions\RenderTerminatedException;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Projects\ProjectStruct;
use Utils\Registry\AppConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use View\API\Commons\ZipContentObject;

#[CoversClass(AbstractDownloadController::class)]
class AbstractDownloadControllerTest extends AbstractTest
{
    private AbstractDownloadController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->createController();
    }

    private function createController(): AbstractDownloadController
    {
        $request = Request::createFromGlobals();
        $response = new Response();
        $app = new App();
        $app->register('getDatabase', static fn() => obtainTestDatabase());

        return new class ($request, $response, null, $app) extends AbstractDownloadController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                $this->userIsLogged = false;
            }
        };
    }

    #[Test]
    public function setMimeTypeSetsXliffForXlfExtension(): void
    {
        $this->setProperty('_filename', 'document.xlf');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/xliff+xml', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsXliffForSdlxliff(): void
    {
        $this->setProperty('_filename', 'document.sdlxliff');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/xliff+xml', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsXliffForXliffExtension(): void
    {
        $this->setProperty('_filename', 'document.xliff');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/xliff+xml', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsZipForZipExtension(): void
    {
        $this->setProperty('_filename', 'archive.zip');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/zip', $this->getProperty('mimeType'));
    }

    #[Test]
    public function setMimeTypeSetsOctetStreamForUnknown(): void
    {
        $this->setProperty('_filename', 'readme.txt');
        $this->invokeProtected('setMimeType');

        $this->assertSame('application/octet-stream', $this->getProperty('mimeType'));
    }

    #[Test]
    public function finalizeThrowsRenderTerminatedExceptionInTestingEnvironment(): void
    {
        // The render path used to be wrapped in a try/catch that print_r'd the exception and exit()'d,
        // which leaked stack traces to clients and made finalize() untestable. It now emits the file and,
        // under the 'testing' env, throws RenderTerminatedException instead of exit() so the path
        // (unlockToken + header emission + body output) can be exercised here.
        $project     = new ProjectStruct();
        $project->id = 1;
        $this->setProperty('project', $project);
        $this->setProperty('_filename', 'document.xlf');
        $this->setProperty('mimeType', 'application/xliff+xml');
        $this->setProperty('outputContent', 'translated-file-body');

        $previousEnv     = AppConfig::$ENV;
        $originalObLevel = ob_get_level();
        // Shield PHPUnit's own output buffer: finalize() calls ob_get_clean() on the top buffer.
        ob_start();
        AppConfig::$ENV = 'testing';

        // finalize() emits HTTP headers; under the CLI/PHPUnit SAPI output is already sent, so
        // header() raises "headers already sent" warnings that are irrelevant to what we assert.
        set_error_handler(
            static fn (int $errno, string $errstr): bool => str_contains($errstr, 'Cannot modify header information'),
            E_WARNING
        );

        try {
            $this->controller->finalize(true);
            $this->fail('finalize() must throw RenderTerminatedException under the testing env');
        } catch (RenderTerminatedException $e) {
            $this->assertInstanceOf(RenderTerminatedException::class, $e);
        } finally {
            restore_error_handler();
            AppConfig::$ENV = $previousEnv;
            while (ob_get_level() > $originalObLevel) {
                @ob_end_clean();
            }
        }
    }

    #[Test]
    #[DataProvider('ocrExtensionProvider')]
    public function forceOcrExtensionConvertsImageExtensions(string $input, string $expected): void
    {
        $result = AbstractDownloadController::forceOcrExtension($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ocrExtensionProvider(): array
    {
        return [
            'pdf becomes docx' => ['report.pdf', 'report.pdf.docx'],
            'png becomes docx' => ['image.png', 'image.png.docx'],
            'jpg becomes docx' => ['photo.jpg', 'photo.jpg.docx'],
            'jpeg becomes docx' => ['scan.jpeg', 'scan.jpeg.docx'],
            'tiff becomes docx' => ['fax.tiff', 'fax.tiff.docx'],
            'tif becomes docx' => ['doc.tif', 'doc.tif.docx'],
            'bmp becomes docx' => ['bitmap.bmp', 'bitmap.bmp.docx'],
            'gif becomes docx' => ['anim.gif', 'anim.gif.docx'],
            'docx stays docx' => ['file.docx', 'file.docx'],
            'txt stays txt' => ['notes.txt', 'notes.txt'],
            'xlf stays xlf' => ['trans.xlf', 'trans.xlf'],
        ];
    }

    #[Test]
    #[DataProvider('contentDispositionFilenameProvider')]
    public function sanitizeContentDispositionFilenameStripsHeaderBreakingChars(string $input, string $expected): void
    {
        $result = AbstractDownloadController::sanitizeContentDispositionFilename($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function contentDispositionFilenameProvider(): array
    {
        return [
            'plain name untouched'     => ['report.docx', 'report.docx'],
            'double quote stripped'    => ['a".xliff', 'a.xliff'],
            'backslash stripped'       => ['a\\b.xliff', 'ab.xliff'],
            'CR stripped'              => ["a\rb.xliff", 'ab.xliff'],
            'LF stripped'              => ["a\nb.xliff", 'ab.xliff'],
            'NUL stripped'             => ["a\0b.xliff", 'ab.xliff'],
            'header injection attempt' => ["f.xliff\"\r\nSet-Cookie: x=1", 'f.xliffSet-Cookie: x=1'],
            'unicode name preserved'   => ['résumé.docx', 'résumé.docx'],
        ];
    }

    #[Test]
    public function composeZipReturnsValidZipContent(): void
    {
        $content1 = new ZipContentObject([
            'output_filename' => 'file1.txt',
            'document_content' => 'Hello World',
            'input_filename' => 'file1.txt',
        ]);
        $content2 = new ZipContentObject([
            'output_filename' => 'file2.txt',
            'document_content' => 'Second file',
            'input_filename' => 'file2.txt',
        ]);

        $method = new ReflectionMethod(AbstractDownloadController::class, 'composeZip');
        $result = $method->invoke(null, [$content1, $content2]);

        $this->assertIsString($result);
        $this->assertStringStartsWith("PK", $result);
    }

    #[Test]
    public function composeZipHandlesDuplicateFilenames(): void
    {
        $content1 = new ZipContentObject([
            'output_filename' => 'same.txt',
            'document_content' => 'First',
            'input_filename' => 'same.txt',
        ]);
        $content2 = new ZipContentObject([
            'output_filename' => 'same.txt',
            'document_content' => 'Second',
            'input_filename' => 'same.txt',
        ]);

        $method = new ReflectionMethod(AbstractDownloadController::class, 'composeZip');
        $result = $method->invoke(null, [$content1, $content2]);

        $this->assertIsString($result);
        $this->assertStringStartsWith("PK", $result);

        $tmpFile = tempnam('/tmp', 'ziptest');
        file_put_contents($tmpFile, $result);
        $zip = new \ZipArchive();
        $zip->open($tmpFile);
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
        unlink($tmpFile);
    }

    #[Test]
    public function composeZipSkipsEmptyContent(): void
    {
        $content1 = new ZipContentObject([
            'output_filename' => 'filled.txt',
            'document_content' => 'Has content',
            'input_filename' => 'filled.txt',
        ]);

        $emptyFile = tempnam('/tmp', 'empty');
        file_put_contents($emptyFile, '');
        $content2 = new ZipContentObject([
            'output_filename' => 'empty.txt',
            'document_content' => null,
            'input_filename' => $emptyFile,
        ]);

        $method = new ReflectionMethod(AbstractDownloadController::class, 'composeZip');
        $result = $method->invoke(null, [$content1, $content2]);

        $tmpFile = tempnam('/tmp', 'ziptest');
        file_put_contents($tmpFile, $result);
        $zip = new \ZipArchive();
        $zip->open($tmpFile);
        $this->assertSame(1, $zip->numFiles);
        $zip->close();
        unlink($tmpFile);
        unlink($emptyFile);
    }

    #[Test]
    public function composeZipConvertsOcrExtensions(): void
    {
        $content = new ZipContentObject([
            'output_filename' => 'scan.pdf',
            'document_content' => 'PDF content',
            'input_filename' => 'scan.pdf',
        ]);

        $method = new ReflectionMethod(AbstractDownloadController::class, 'composeZip');
        $result = $method->invoke(null, [$content], null, false);

        $tmpFile = tempnam('/tmp', 'ziptest');
        file_put_contents($tmpFile, $result);
        $zip = new \ZipArchive();
        $zip->open($tmpFile);
        $this->assertSame('scan.pdf.docx', $zip->getNameIndex(0));
        $zip->close();
        unlink($tmpFile);
    }

    #[Test]
    public function composeZipPreservesOriginalExtensionWhenIsOriginalFile(): void
    {
        $content = new ZipContentObject([
            'output_filename' => 'scan.pdf',
            'document_content' => 'PDF content',
            'input_filename' => 'scan.pdf',
        ]);

        $method = new ReflectionMethod(AbstractDownloadController::class, 'composeZip');
        $result = $method->invoke(null, [$content], null, true);

        $tmpFile = tempnam('/tmp', 'ziptest');
        file_put_contents($tmpFile, $result);
        $zip = new \ZipArchive();
        $zip->open($tmpFile);
        $this->assertSame('scan.pdf', $zip->getNameIndex(0));
        $zip->close();
        unlink($tmpFile);
    }

    #[Test]
    public function setOutputContentStoresContentString(): void
    {
        $zipContent = new ZipContentObject([
            'output_filename' => 'test.txt',
            'document_content' => 'Test content here',
            'input_filename' => 'test.txt',
        ]);

        $result = $this->controller->setOutputContent($zipContent);

        $this->assertSame($this->controller, $result);
        $this->assertSame('Test content here', $this->getProperty('outputContent'));
    }

    #[Test]
    public function setFilenameAndGetFilenameRoundTrip(): void
    {
        $this->controller->setFilename('my-file.xlf');
        $this->assertSame('my-file.xlf', $this->controller->getFilename());
    }

    #[Test]
    public function getProjectReturnsProjectStruct(): void
    {
        $project = new ProjectStruct();
        $project->name = 'Test Project';
        $this->setProperty('project', $project);

        $this->assertSame($project, $this->controller->getProject());
    }

    #[Test]
    public function unlockTokenDoesNothingWhenNoToken(): void
    {
        $this->invokeProtected('unlockToken');

        $this->assertTrue(true);
    }

    private function invokeProtected(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($this->controller, $method);

        return $ref->invoke($this->controller, ...$args);
    }

    private function setProperty(string $name, mixed $value): void
    {
        $ref = new ReflectionProperty($this->controller, $name);
        $ref->setValue($this->controller, $value);
    }

    private function getProperty(string $name): mixed
    {
        $ref = new ReflectionProperty($this->controller, $name);

        return $ref->getValue($this->controller);
    }
}
