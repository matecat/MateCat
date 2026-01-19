<?php

use Model\Conversion\ConvertedFileModel;
use TestHelpers\AbstractTest;
use Utils\Constants\ConversionHandlerStatus;

class ConvertFileModelTest extends AbstractTest
{

    /**
     * @throws Exception
     */
    function test_model()
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
}