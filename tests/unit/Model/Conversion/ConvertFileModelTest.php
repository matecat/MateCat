<?php

namespace unit\Model\Conversion;

use Exception;
use Model\Conversion\ConvertedFileModel;
use Model\Conversion\InternalHashPaths;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\ConversionHandlerStatus;

class ConvertFileModelTest extends AbstractTest
{

    /**
     * @throws Exception
     */
    #[Test]
    public function model(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('set file name is mandatory');

        $this->assertEquals(ConversionHandlerStatus::NOT_CONVERTED, $model->getCode());
        $this->assertFalse($model->isError());

        $model->setErrorCode(ConversionHandlerStatus::OK);
        $this->assertEquals(ConversionHandlerStatus::OK, $model->getCode());

        try {
            $model->setErrorCode(43434343);
            throw new Exception(); // to be sure that the exception is raised internally and to not skip the assertion.
        } catch (Exception $exception) {
            $this->assertEquals('43434343 is not a valid code', $exception->getMessage());
        }

        $model->setErrorCode(ConversionHandlerStatus::SOURCE_ERROR);
        $model->setErrorMessage('Source not valid');

        $this->assertEquals('Source not valid', $model->getMessage());
        $this->assertTrue($model->isError());

        $json = json_encode($model->getResult());

        $this->assertEquals('{"name":"set file name is mandatory","size":0,"pdfAnalysis":[]}', $json);

        $this->assertEquals('{"code":-3,"message":"Source not valid","name":"set file name is mandatory"}', json_encode($model->asError()));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function constructorWithValidCode(): void
    {
        $model = new ConvertedFileModel(ConversionHandlerStatus::GENERIC_ERROR);
        $this->assertEquals(ConversionHandlerStatus::GENERIC_ERROR, $model->getCode());
    }

    #[Test]
    public function constructorWithInvalidCodeThrows(): void
    {
        $this->expectException(Exception::class);
        new ConvertedFileModel(99999);
    }

    #[Test]
    public function constructorWithNullCodeDefaultsToNotConverted(): void
    {
        $model = new ConvertedFileModel();
        $this->assertEquals(ConversionHandlerStatus::NOT_CONVERTED, $model->getCode());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function isWarningReturnsTrueForWarningCode(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('test.pdf');
        $model->setErrorCode(ConversionHandlerStatus::OCR_WARNING);

        $this->assertTrue($model->isWarning());
        $this->assertFalse($model->isError());
    }

    #[Test]
    public function setSizeAndGetSize(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('test.txt');

        $this->assertEquals(0, $model->getSize());

        $model->setSize(12345);
        $this->assertEquals(12345, $model->getSize());
    }

    #[Test]
    public function zipContentDefaultFalseAndToggle(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('file.txt');

        $this->assertFalse($model->isZipContent());

        $model->zipContent(true);
        $this->assertTrue($model->isZipContent());

        $model->zipContent(false);
        $this->assertFalse($model->isZipContent());
    }

    #[Test]
    public function setFileNameWithIsZipContent(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('inner.txt', true);

        $this->assertEquals('inner.txt', $model->getName());
        $this->assertTrue($model->isZipContent());
    }

    #[Test]
    public function pdfAnalysisRoundtrip(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('doc.pdf');

        $this->assertEmpty($model->getPdfAnalysis());

        $analysis = ['pages' => 10, 'images' => 3];
        $model->setPdfAnalysis($analysis);

        $this->assertEquals($analysis, $model->getPdfAnalysis());
    }

    #[Test]
    public function conversionHashesRoundtrip(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('file.docx');

        $this->assertFalse($model->hasConversionHashes());

        $hashes = new InternalHashPaths(['cacheHash' => 'c1', 'diskHash' => 'd1']);
        $model->addConversionHashes($hashes);

        $this->assertTrue($model->hasConversionHashes());
        $this->assertEquals('c1', $model->getConversionHashes()->getCacheHash());
        $this->assertEquals('d1', $model->getConversionHashes()->getDiskHash());
    }

    #[Test]
    public function getConversionHashesReturnsEmptyWhenNoneSet(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('file.txt');

        $hashes = $model->getConversionHashes();
        $this->assertTrue($hashes->isEmpty());
    }

    #[Test]
    public function messageDefaultsToNull(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('file.txt');

        $this->assertNull($model->getMessage());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getResultIncludesSizeAndPdfAnalysis(): void
    {
        $model = new ConvertedFileModel();
        $model->setFileName('report.pdf');
        $model->setSize(999);
        $model->setPdfAnalysis(['pages' => 5]);

        $result = $model->getResult();

        $this->assertEquals('report.pdf', $result['name']);
        $this->assertEquals(999, $result['size']);
        $this->assertEquals(['pages' => 5], $result['pdfAnalysis']);
    }
}