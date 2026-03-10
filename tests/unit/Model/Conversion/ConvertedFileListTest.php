<?php

namespace unit\Model\Conversion;

use Exception;
use Model\Conversion\ConvertedFileList;
use Model\Conversion\ConvertedFileModel;
use Model\Conversion\InternalHashPaths;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\ConversionHandlerStatus;

class ConvertedFileListTest extends AbstractTest
{

    /**
     * Helper: creates a ConvertedFileModel with the given name and optional error/warning code.
     *
     * @throws Exception
     */
    private function makeModel(string $name, ?int $code = null, bool $isZip = false): ConvertedFileModel
    {
        $m = new ConvertedFileModel();
        $m->setFileName($name, $isZip);
        if ($code !== null) {
            $m->setErrorCode($code);
            if ($code < 0) {
                $m->setErrorMessage("Error for $name");
            }
        }

        return $m;
    }

    #[Test]
    public function emptyListHasNoErrorsOrWarnings(): void
    {
        $list = new ConvertedFileList();

        $this->assertFalse($list->hasErrors());
        $this->assertFalse($list->hasWarnings());
        $this->assertEmpty($list->getErrors());
        $this->assertEmpty($list->getWarnings());
        $this->assertEmpty($list->getHashes());
        $this->assertEmpty($list->getData());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addSingleFileAppearsInData(): void
    {
        $list = new ConvertedFileList();
        $list->add($this->makeModel('file.docx'));

        $data = $list->getData();
        $this->assertArrayHasKey('simpleFileName', $data);
        $this->assertCount(1, $data['simpleFileName']);
        $this->assertEquals('file.docx', $data['simpleFileName'][0]['name']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addZipFileAppearsUnderZipFiles(): void
    {
        $list = new ConvertedFileList();
        $list->add($this->makeModel('inner.txt', null, true));

        $data = $list->getData();
        $this->assertArrayHasKey('zipFiles', $data);
        $this->assertCount(1, $data['zipFiles']);
        $this->assertArrayNotHasKey('simpleFileName', $data);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function mixedZipAndSimpleFiles(): void
    {
        $list = new ConvertedFileList();
        $list->add($this->makeModel('simple.txt'));
        $list->add($this->makeModel('zipped.txt', null, true));

        $data = $list->getData();
        $this->assertCount(1, $data['simpleFileName']);
        $this->assertCount(1, $data['zipFiles']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setErroredFilePopulatesErrors(): void
    {
        $list = new ConvertedFileList();
        $model = $this->makeModel('bad.docx', ConversionHandlerStatus::GENERIC_ERROR);
        $list->add($model);
        $list->setErroredFile($model);

        $this->assertTrue($list->hasErrors());
        $this->assertCount(1, $list->getErrors());
        $this->assertEquals('bad.docx', $list->getErrors()[0]['name']);
        $this->assertEquals(ConversionHandlerStatus::GENERIC_ERROR, $list->getErrors()[0]['code']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function setWarnedFilePopulatesWarnings(): void
    {
        $list = new ConvertedFileList();
        $model = $this->makeModel('warn.pdf', ConversionHandlerStatus::OCR_WARNING);
        $model->setErrorMessage('OCR warning');
        $list->add($model);
        $list->setWarnedFile($model);

        $this->assertTrue($list->hasWarnings());
        $this->assertCount(1, $list->getWarnings());
        $this->assertEquals('warn.pdf', $list->getWarnings()[0]['name']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getHashesOnlyFromModelsWithHashes(): void
    {
        $list = new ConvertedFileList();

        // Model without hashes
        $list->add($this->makeModel('no-hash.txt'));

        // Model with hashes
        $withHash = $this->makeModel('with-hash.txt');
        $withHash->addConversionHashes(new InternalHashPaths([
            'cacheHash' => 'cache123',
            'diskHash' => 'disk456',
        ]));
        $list->add($withHash);

        $hashes = $list->getHashes();
        $this->assertCount(1, $hashes);
        $this->assertEquals('cache123', $hashes[0]->getCacheHash());
        $this->assertEquals('disk456', $hashes[0]->getDiskHash());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function multipleErrorsAccumulate(): void
    {
        $list = new ConvertedFileList();

        $m1 = $this->makeModel('err1.txt', ConversionHandlerStatus::INVALID_FILE);
        $m2 = $this->makeModel('err2.txt', ConversionHandlerStatus::SOURCE_ERROR);

        $list->add($m1);
        $list->add($m2);
        $list->setErroredFile($m1);
        $list->setErroredFile($m2);

        $this->assertCount(2, $list->getErrors());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function noErrorsAfterAddingOnlySuccessful(): void
    {
        $list = new ConvertedFileList();
        $list->add($this->makeModel('ok.txt', ConversionHandlerStatus::OK));

        $this->assertFalse($list->hasErrors());
        $this->assertFalse($list->hasWarnings());
    }
}

