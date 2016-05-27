<?php

/**
 * @group regression
 * @covers Engines_MyMemory::createMyMemoryKey
 * User: dinies
 * Date: 18/05/16
 * Time: 18.51
 */
class CreateMyMemoryKeyTest extends AbstractTest
{
    /**
     * @group regression
     * @covers Engines_MyMemory::createMyMemoryKey
     */
    public function test_createMyMemoryKey_mocked_engine_avoiding_uncontrolled_key_spawning()
    {


        $curl_mock_param = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 10
        );


        $url_mock_param = "http://api.mymemory.translated.net/createranduser?";
        $mock_json_return = <<<'T'
{"key":"8dd91ebad29bb0ad0b08","error":"","code":200,"id":"MyMemory_0837f645849e069fd481","pass":"1f8fae3dca"}
T;


        $engineDAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        /**
         * creation of the engine
         */
        $engine_MyMemory = $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($engine_struct_param))->setMethods(array('_call'))->getMock();
        $engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param, $curl_mock_param)->willReturn($mock_json_return);


        $result = $engine_MyMemory->createMyMemoryKey();

        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue($result instanceof Engines_Results_MyMemory_CreateUserResponse);
        $this->assertEquals("8dd91ebad29bb0ad0b08", $result->key);
        $this->assertEquals("MyMemory_0837f645849e069fd481", $result->id);
        $this->assertEquals("1f8fae3dca", $result->pass);
        $this->assertFalse(key_exists('responseStatus',$result ));
        $this->assertFalse(key_exists('responseDetails',$result ));
        $this->assertFalse(key_exists('responseData',$result ));

        $this->assertTrue($result->error instanceof Engines_Results_ErrorMatches);
        $this->assertEquals(0, $result->error->code);
        $this->assertEquals("", $result->error->message);
        /**
         * check of protected property
         */
        $reflector= new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);
        $this->assertEquals("", $property->getValue($result));


    }

    /**
     * @group regression
     * @covers Engines_MyMemory::createMyMemoryKey
     */
    public function test_createMyMemoryKey_with_error_from_mocked__call_for_coverage_purpose()
    {


        $curl_mock_param = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 10
        );


        $url_mock_param = "http://api.mymemory.translated.net/createranduser?";
        $rawValue_error = array(
            'error' => array(
                'code'      => -6,
                'message'   => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                'response'  => "",
            ),
            'responseStatus'    => 0
        );


        $engineDAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[0];

        /**
         * creation of the engine
         * @var Engines_MyMemory
         */
        $engine_MyMemory = $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($engine_struct_param))->setMethods(array('_call'))->getMock();

        $engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param, $curl_mock_param)->willReturn($rawValue_error);


        $result = $engine_MyMemory->createMyMemoryKey();

        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue($result instanceof Engines_Results_MyMemory_CreateUserResponse);
        $this->assertEquals("", $result->key);
        $this->assertEquals("", $result->id);
        $this->assertEquals("", $result->pass);
        $this->assertFalse(key_exists('responseStatus',$result ));
        $this->assertFalse(key_exists('responseDetails',$result ));
        $this->assertFalse(key_exists('responseData',$result ));

        $this->assertTrue($result->error instanceof Engines_Results_ErrorMatches);

        $this->assertEquals(-6, $result->error->code);
        $this->assertEquals("Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result->error->message);

        /**
         * check of protected property
         */
        $reflector= new ReflectionClass($result);
        $property = $reflector->getProperty('_rawResponse');
        $property->setAccessible(true);
        $this->assertEquals("", $property->getValue($result));


    }
}