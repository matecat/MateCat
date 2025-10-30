<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\AuthKeyResponse;
use Utils\Registry\AppConfig;
use Utils\Tools\Matches;


/**
 * @group  regression
 * @covers MyMemory::checkCorrectKey
 * User: dinies
 * Date: 19/05/16
 * Time: 16.09
 */
class CheckCorrectKeyMyMemoryTest extends AbstractTest {


    /**
     * @group  regression
     * @covers MyMemory::checkCorrectKey
     */
    public function test_checkCorrectKey_with_success() {
        $key_param         = "bfb9bd80a43253670c8d";
        $engineDAO         = new EngineDAO( Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE ) );
        $engine_struct     = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        $engine_MyMemory = new MyMemory( $engine_struct_param );

        $engine_MyMemory->checkCorrectKey( $key_param );


        $reflector = new ReflectionClass( $engine_MyMemory );
        $property  = $reflector->getProperty( 'result' );
        
        $object_result = $property->getValue( $engine_MyMemory );


        $this->assertTrue( $object_result instanceof AuthKeyResponse );
        $this->assertTrue( property_exists( $object_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $object_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $object_result, 'responseData' ) );
        $this->assertTrue( property_exists( $object_result, 'error' ) );
        $this->assertTrue( property_exists( $object_result, '_rawResponse' ) );

    }

    /**
     * @group  regression
     * @covers MyMemory::checkCorrectKey
     */
    public function test_checkCorrectKey_with_failure_with_fake_tmKey() {
        $key_param         = "b2invalid2d";
        $engineDAO         = new EngineDAO( Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE ) );
        $engine_struct     = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        $engine_MyMemory = new MyMemory( $engine_struct_param );

        $bool_result = $engine_MyMemory->checkCorrectKey( $key_param );

        $this->assertFalse( $bool_result );
        $reflector = new ReflectionClass( $engine_MyMemory );
        $property  = $reflector->getProperty( 'result' );

        $object_result = $property->getValue( $engine_MyMemory );


        $this->assertTrue( $object_result instanceof AuthKeyResponse );
        $this->assertEquals( 200, $object_result->responseStatus );
        $this->assertEquals( "", $object_result->responseDetails );
        $this->assertEquals( 0, $object_result->responseData );
        $this->assertNull( $object_result->error );

        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $object_result );
        $property  = $reflector->getProperty( '_rawResponse' );
        
        $this->assertEquals( "", $property->getValue( $object_result ) );


    }


    /**
     * @group  regression
     * @covers MyMemory::checkCorrectKey
     */
    public function test_checkCorrectKey_mocked() {
        $key_param       = "bfb9bd80a43253670c8d";
        $url_mock_param  = "https://api.mymemory.translated.net/authkey?key=bfb9bd80a43253670c8d";
        $mock_raw_value  = "1";
        $curl_mock_param = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => 10
        ];


        $engineDAO         = new EngineDAO( Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE ) );
        $engine_struct     = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        /**
         * creation of the engine
         * @var Matches
         */
        $engine_MyMemory = @$this->getMockBuilder( '\Utils\Engines\MyMemory' )->setConstructorArgs( [ $engine_struct_param ] )->onlyMethods( [ '_call' ] )->getMock();
        $engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $mock_raw_value );


        $bool_result = $engine_MyMemory->checkCorrectKey( $key_param );

        $reflector = new ReflectionClass( $engine_MyMemory );
        $property  = $reflector->getProperty( 'result' );
        $object_result = $property->getValue( $engine_MyMemory );


        $this->assertTrue( $bool_result );
        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue( $object_result instanceof AuthKeyResponse );
        $this->assertEquals( 200, $object_result->responseStatus );
        $this->assertEquals( "", $object_result->responseDetails );
        $this->assertEquals( 1, $object_result->responseData );

        $this->assertNull( $object_result->error );
        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $object_result );
        $property  = $reflector->getProperty( '_rawResponse' );
        $this->assertEquals( "", $property->getValue( $object_result ) );

    }

    /**
     * @group  regression
     * @covers MyMemory::checkCorrectKey
     */
    public function test_checkCorrectKey_with_error_from_mocked__call_for_coverage_purpose() {

        $key_param       = "bfb9bd80a43253670c8d";
        $url_mock_param  = "https://api.mymemory.translated.net/authkey?key=bfb9bd80a43253670c8d";
        $curl_mock_param = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => 10
        ];

        $rawValue_error = [
                'error'          => [
                        'code'     => -6,
                        'message'  => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                        'response' => "",
                ],
                'responseStatus' => 0
        ];


        $engineDAO         = new EngineDAO( Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE ) );
        $engine_struct     = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        /**
         * creation of the engine
         * @var Matches
         */
        $engine_MyMemory = $this->getMockBuilder( '\Utils\Engines\MyMemory' )->setConstructorArgs( [ $engine_struct_param ] )->onlyMethods( [ '_call' ] )->getMock();
        $engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $rawValue_error );

        $this->expectException( 'Exception' );
        $engine_MyMemory->checkCorrectKey( $key_param );


    }
}