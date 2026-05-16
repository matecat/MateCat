<?php

namespace unit\Controllers\Abstracts;

use Controller\Abstracts\AbstractDownloadController;
use Exception;
use Klein\Request;
use Klein\Response;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use View\API\Commons\ZipContentObject;

#[CoversClass(AbstractDownloadController::class)]
class AbstractDownloadControllerTest extends TestCase
{
    private AbstractDownloadController $controller;

    protected function setUp(): void
    {
        $this->controller = $this->createController();
    }

    private function createController(): AbstractDownloadController
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        return new class ($request, $response) extends AbstractDownloadController {
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
