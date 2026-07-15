<?php

namespace Matecat\Core\Controllers\Views\TemplateDecorator;

use Controller\Abstracts\AbstractDownloadController;
use Controller\Views\TemplateDecorator\DownloadOmegaTOutputDecorator;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplTempFileObject;
use Utils\TMS\TMSService;

/**
 * Testable subclass exposing the TMSService seam so decorate() can run without
 * touching a real TM/DB export.
 */
class TestableDownloadOmegaTOutputDecorator extends DownloadOmegaTOutputDecorator
{
    public TMSService $stubTms;

    protected function getTMSService(IDatabase $database): TMSService
    {
        return $this->stubTms;
    }
}

class DownloadOmegaTOutputDecoratorTest extends TestCase
{
    private function makeTmx(string $content): SplTempFileObject
    {
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->rewind();

        return $file;
    }

    /**
     * @return AbstractDownloadController&\PHPUnit\Framework\MockObject\Stub
     */
    private function makeController(): AbstractDownloadController
    {
        $controller = $this->createStub(AbstractDownloadController::class);

        $job         = new JobStruct();
        $job->id     = 4242;
        $job->source  = 'en-US';
        $job->target  = 'it-IT';

        $user      = new UserStruct();
        $user->uid = 55;

        $controller->method('getProject')->willReturn($this->createStub(ProjectStruct::class));
        $controller->method('getDefaultFileName')->willReturn('mydoc.docx');
        $controller->method('getFilename')->willReturn('mydoc_it-IT.docx');
        $controller->method('getJob')->willReturn($job);
        $controller->method('getUser')->willReturn($user);
        $controller->method('getDatabase')->willReturn($this->createStub(IDatabase::class));

        $controller->id_job   = 4242;
        $controller->password = 'abcpassword';

        return $controller;
    }

    private function makeDecorator(AbstractDownloadController $controller): TestableDownloadOmegaTOutputDecorator
    {
        $decorator = new TestableDownloadOmegaTOutputDecorator($controller);

        $tms = $this->createStub(TMSService::class);
        $tms->method('exportJobAsTMX')->willReturnCallback(
            fn() => $this->makeTmx("<tmx>line-a</tmx>\n<tmx>line-b</tmx>")
        );
        $decorator->stubTms = $tms;

        return $decorator;
    }

    public function testDecorateBuildsTmAndMtEntries(): void
    {
        $decorator = $this->makeDecorator($this->makeController());

        $result = $decorator->decorate();

        $this->assertCount(2, $result);

        $keys = array_keys($result);
        $this->assertStringStartsWith('tm', $keys[0]);
        $this->assertStringStartsWith('mt', $keys[1]);

        foreach ($result as $entry) {
            $this->assertArrayHasKey('document_content', $entry);
            $this->assertArrayHasKey('output_filename', $entry);
            $this->assertStringContainsString('line-a', $entry['document_content']);
            $this->assertStringContainsString('line-b', $entry['document_content']);
            $this->assertStringContainsString('mydoc_it-IT', $entry['output_filename']);
        }
    }

    public function testCreateOmegaTZipWritesArchiveAndSetsOutputContent(): void
    {
        $controller = $this->makeController();

        $captured = null;
        $controller->method('setOutputContent')->willReturnCallback(
            function ($content) use ($controller, &$captured) {
                $captured = $content;

                return $controller;
            }
        );

        $decorator = $this->makeDecorator($controller);

        $output_content = [
            // tm/mt keys -> tm/ directory branch
            'tm123' => ['document_content' => 'tm-body', 'output_filename' => 'export_TM.tmx'],
            'mt456' => ['document_content' => 'mt-body', 'output_filename' => 'export_MT.tmx'],
            // non tm/mt key -> inbox directory branch
            'file0' => ['document_content' => 'file-body', 'output_filename' => 'document.txt'],
            // duplicate resolved name -> uniqid prefix branch
            'file1' => ['document_content' => 'dup-body', 'output_filename' => 'document.txt'],
            // short (<3 chars) name -> uniqid prefix branch
            'file2' => ['document_content' => 'short-body', 'output_filename' => 'ab'],
            // missing output_filename -> null coalesce branch
            'file3' => ['document_content' => 'noname-body'],
        ];

        $decorator->createOmegaTZip($output_content);

        $this->assertNotNull($captured);
        $this->assertNotEmpty($captured->input_filename);
        $this->assertFileExists($captured->input_filename);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($captured->input_filename) === true);
        // omegat.project + directory structure present
        $this->assertNotFalse($zip->locateName('4242/omegat.project'));
        $projectContent = $zip->getFromName('4242/omegat.project');
        $this->assertStringContainsString('<source_lang>EN-US</source_lang>', $projectContent);
        $zip->close();

        @unlink($captured->input_filename);
    }

    public function testGetOmegatProjectFileMapsKnownAndDefaultTokenizers(): void
    {
        $decorator = new TestableDownloadOmegaTOutputDecorator($this->makeController());

        $method = new ReflectionMethod(DownloadOmegaTOutputDecorator::class, 'getOmegatProjectFile');
        $method->setAccessible(true);

        // Known languages -> mapped tokenizers
        $known = $method->invoke($decorator, 'it-IT', 'fr-FR');
        $this->assertStringContainsString('<source_lang>IT-IT</source_lang>', $known);
        $this->assertStringContainsString('<target_lang>FR-FR</target_lang>', $known);
        $this->assertStringContainsString('org.omegat.tokenizer.LuceneItalianTokenizer', $known);
        $this->assertStringContainsString('org.omegat.tokenizer.LuceneFrenchTokenizer', $known);

        // Unknown languages -> default tokenizer on both arms
        $unknown = $method->invoke($decorator, 'xx-XX', 'zz-ZZ');
        $this->assertStringContainsString('<source_lang>XX-XX</source_lang>', $unknown);
        $this->assertStringContainsString('org.omegat.tokenizer.LuceneEnglishTokenizer', $unknown);
    }
}
