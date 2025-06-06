<?php

use Constants\ConversionHandlerStatus;
use Conversion\ConvertedFileModel;
use TestHelpers\AbstractTest;

class ConvertFileModelTest extends AbstractTest {

    /**
     * @throws Exception
     */
    function test_model() {
        $model = new ConvertedFileModel();
        $this->assertEquals( ConversionHandlerStatus::NOT_CONVERTED, $model->getCode() );
        $this->assertFalse( $model->hasErrors() );

        $model->changeCode( ConversionHandlerStatus::OK );
        $this->assertEquals( ConversionHandlerStatus::OK, $model->getCode() );

        try {
            $model->changeCode( 43434343 );
        } catch ( Exception $exception ) {
            $this->assertEquals( '43434343 is not a valid code', $exception->getMessage() );
        }

        $model->changeCode( ConversionHandlerStatus::SOURCE_ERROR );
        $model->addError( 'Source not valid' );

        $this->assertEquals( 'Source not valid', $model->getMessage() );
        $this->assertTrue( $model->hasErrors() );

        $json = json_encode( $model );

        $this->assertEquals( '{"code":-3,"message":"Source not valid","warning":null}', $json );
    }
}