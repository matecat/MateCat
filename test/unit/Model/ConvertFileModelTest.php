<?php

use Constants\ConversionHandlerStatus;
use Conversion\ConvertedFileModel;

class ConvertFileModelTest extends PHPUnit_Framework_TestCase {

    function test_model() {
        $model = new ConvertedFileModel();
        $this->assertEquals($model->getCode(), ConversionHandlerStatus::NOT_CONVERTED);
        $this->assertFalse($model->hasErrors());

        $model->changeCode(ConversionHandlerStatus::OK);
        $this->assertEquals($model->getCode(), ConversionHandlerStatus::OK);

        try {
            $model->changeCode(43434343);
        } catch (\Exception $exception){
            $this->assertEquals($exception->getMessage(), '43434343 is not a valid code');
        }

        $model->changeCode(ConversionHandlerStatus::SOURCE_ERROR);
        $model->addError('Source not valid');

        $this->assertCount(1, $model->getErrors());
        $this->assertTrue($model->hasErrors());

        $json = json_encode($model);

        $this->assertEquals('{"code":-3,"errors":[{"code":-3,"message":"Source not valid"}]}', $json);
    }
}