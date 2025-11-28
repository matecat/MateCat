<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\ErrorResponse;
use Utils\Engines\Results\MyMemory\CreateUserResponse;
use Utils\Registry\AppConfig;
use Utils\Tools\Matches;


/**
 * @group  regression
 * @covers MyMemory::createMyMemoryKey
 * User: dinies
 * Date: 18/05/16
 * Time: 18.51
 */
class CreateMyMemoryKeyTest extends AbstractTest {
    /**
     * @group  regression
     * @covers MyMemory::createMyMemoryKey
     */
    public function test_createMyMemoryKey_mocked_engine_avoiding_uncontrolled_key_spawning() {


        $curl_mock_param = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => 10
        ];


        $url_mock_param   = "https://api.mymemory.translated.net/createranduser?";
        $mock_json_return = <<<'T'
{"key":"8dd91ebad29bb0ad0b08","error":"","code":200,"id":"MyMemory_0837f645849e069fd481","pass":"1f8fae3dca"}
T;


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
         */
        $engine_MyMemory = @$this->getMockBuilder( MyMemory::class )->setConstructorArgs( [ $engine_struct_param ] )->onlyMethods( [ '_call' ] )->getMock();
        $engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $mock_json_return );


        $result = $engine_MyMemory->createMyMemoryKey();

        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue( $result instanceof CreateUserResponse );
        $this->assertEquals( "8dd91ebad29bb0ad0b08", $result->key );
        $this->assertEquals( "MyMemory_0837f645849e069fd481", $result->id );
        $this->assertEquals( "1f8fae3dca", $result->pass );
        $this->assertFalse( isset( $result->responseStatus ) );
        $this->assertFalse( isset( $result->responseDetails ) );
        $this->assertFalse( isset( $result->responseData ) );

        $this->assertNull( $result->error  );

        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $result );
        $property  = $reflector->getProperty( '_rawResponse' );
        
        $this->assertEquals( "", $property->getValue( $result ) );


    }

    /**
     * @group  regression
     * @covers MyMemory::createMyMemoryKey
     */
    public function test_createMyMemoryKey_with_error_from_mocked__call_for_coverage_purpose() {


        $curl_mock_param = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => 10
        ];


        $url_mock_param = "https://api.mymemory.translated.net/createranduser?";
        $rawValue_error = json_encode([
                'error'          => [
                        'code'     => -6,
                        'message'  => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                        'response' => "",
                ],
                'responseStatus' => 401
            ]
        );


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

        $engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $rawValue_error );


        $result = $engine_MyMemory->createMyMemoryKey();

        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue( $result instanceof CreateUserResponse );
        $this->assertEquals( "", $result->key );
        $this->assertEquals( "", $result->id );
        $this->assertEquals( "", $result->pass );
        $this->assertFalse( isset( $result->responseStatus ) );
        $this->assertFalse( isset( $result->responseDetails ) );
        $this->assertFalse( isset( $result->responseData ) );

        $this->assertTrue( $result->error instanceof ErrorResponse );

        $this->assertEquals( -6, $result->error->code );
        $this->assertEquals( "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result->error->message );

        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $result );
        $property  = $reflector->getProperty( '_rawResponse' );
        
        $this->assertEquals( "", $property->getValue( $result ) );


    }
}