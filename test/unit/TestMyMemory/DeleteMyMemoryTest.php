<?php

/**
 * @group regression
 * @covers Engines_MyMemory::set
 * User: dinies
 * Date: 03/05/16
 * Time: 16.57
 */
class DeleteMyMemoryTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var array
     */
    protected $others_param;
    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;
    /**
     * @var array
     */
    protected $config_param_delete;
    protected $config_param_set;

    protected $reflector;
    protected $property;
protected $str_segment;
    protected $str_translation;

    public function setUp()
    {
        parent::setUp();
        
        
        $prop=<<<'LABEL'
{"project_id":"12","project_name":"ForDeleteTest","job_id":"12"}
LABEL;
        $this->str_segment = "Il Sistema film.";
        $this->str_translation = "The bystek pie foo.";
        
        $this->config_param_set= array(
            'segment' => $this->str_segment,
            'translation' => $this->str_translation,
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'email' => "demo@matecat.com",
            'prop' => $prop,
            'get_mt' => 1,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
            'id_user' => array()
        );

        $this->config_param_delete= array(
            'segment' => $this->str_segment,
            'translation' => $this->str_translation,
            'tnote' => NULL,
            'source' => "IT",
            'target' => "EN",
            'email' => "demo@matecat.com",
            'prop' => NULL,
            'get_mt' => 1,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
            'id_user' => array()
        );

     
        
        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct= EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = @$eng[0];
        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
        $this->engine_MyMemory->delete($this->config_param_delete);


    }
    public function tearDown(){
        parent::tearDown();
        $this->engine_MyMemory->delete($this->config_param_delete);

    }

    /**
     * @group regression
     * @covers Engines_MyMemory::delete
     */
    public function test_delete_segment_general_check_without_time_for_MyMemory_to_make_the_transation_and_id_user_not_in_array_purpose_()
    {
        /**
         * initialization of the value to delete
         */
        sleep(2);
        $res = $this->engine_MyMemory->set($this->config_param_set);

        $this->assertTrue($res);

        /**
         * end of initialization
         */
        $this->config_param_delete['id_user'] = "fc7ba5edf8d5e8401593";

        //"MIXTURE OF PARAMETERS OF DIFFERENT MODES IS NOT ALLOWED. CHOOSE BETWEEN "DELETE?ID=1,2,3,5&LANGPAIR=EN|IT" OR "DELETE?SEG=CIAO&TRA=HELLO&LANGPAIR=EN|IT
        $result = $this->engine_MyMemory->delete($this->config_param_delete);

        $this->assertTrue($result);

        /**
         *  general check of the Engines_Results_MyMemory_TMS object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($result_object,'matches'));
        $this->assertTrue(property_exists($result_object,'responseStatus'));
        $this->assertTrue(property_exists($result_object,'responseDetails'));
        $this->assertTrue(property_exists($result_object,'responseData'));
        $this->assertTrue(property_exists($result_object,'error'));
        $this->assertTrue(property_exists($result_object,'_rawResponse'));
        $this->assertEquals("NO ID FOUND", $result_object->responseDetails);


    }

    /**
     * @group regression
     * @covers Engines_MyMemory::delete
     */
    public function test_delete_segment_general_check_with_sleep_time_for_MyMemory_to_make_the_transation()
    {

        /**
         * initialization of the value to delete
         */

        $this->engine_MyMemory->set($this->config_param_set);
        /**
         * end of initialization
         */
        sleep(3);
        $result = $this->engine_MyMemory->delete($this->config_param_delete);

        $this->assertTrue($result);

        /**
         *  general check of the Engines_Results_MyMemory_TMS object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($result_object,'matches'));
        $this->assertTrue(property_exists($result_object,'responseStatus'));
        $this->assertTrue(property_exists($result_object,'responseDetails'));
        $this->assertTrue(property_exists($result_object,'responseData'));
        $this->assertTrue(property_exists($result_object,'error'));
        $this->assertTrue(property_exists($result_object,'_rawResponse'));
        $this->assertEmpty('', $result_object->responseDetails);
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::delete
     */
    public function test_delete_segment_with_mock()
    {

        $config_param= array(
            'segment' => "Il Sistema registra le informazioni sul nuovo film.",
            'translation' => "The system records the information on the new movie.",
            'tnote' => NULL,
            'source' => "IT",
            'target' => "EN",
            'email' => "demo@matecat.com",
            'prop' => NULL,
            'get_mt' => 1,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
            'id_user' => array()
        );

        $curl_mock_param = [
                CURLOPT_POSTFIELDS => [
                        'seg' => 'Il Sistema registra le informazioni sul nuovo film.',
                        'tra'   => 'The system records the information on the new movie.',
                        'langpair' => 'IT|EN',
                        'de' => 'demo@matecat.com',
                ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT => 120,
        ];

//        $url_mock_param= "http://api.mymemory.translated.net/delete?seg=Il+Sistema+registra+le+informazioni+sul+nuovo+film.&tra=The+system+records+the+information+on+the+new+movie.&langpair=IT%7CEN&de=demo%40matecat.com";
        $url_mock_param= "http://api.mymemory.translated.net/delete";

        $mock_json_return=<<<'TAB'
{"responseStatus":200,"responseData":"Found and deleted 1 segments"}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $result = $this->engine_MyMemory->delete($config_param);

        $this->assertTrue($result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);
        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_TMS);
        $this->assertEquals(array(),$result_object->matches);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals("", $result_object->responseDetails);
        $this->assertEquals("Found and deleted 1 segments", $result_object->responseData);
        $this->assertNull($result_object->error);
        /**
         * check of protected property
         */
        $this->reflector= new ReflectionClass($result_object);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($result_object));



    }


    /**
     * @group regression
     * @covers Engines_MyMemory::delete
     */
    public function test_delete_with_error_from_mocked__call_for_coverage_purpose()
    {

        $config_param= array(
            'segment' => "Il Sistema registra le informazioni sul nuovo film.",
            'translation' => "The system records the information on the new movie.",
            'tnote' => NULL,
            'source' => "IT",
            'target' => "EN",
            'email' => "demo@matecat.com",
            'prop' => NULL,
            'get_mt' => 1,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
            'id_user' => array()
        );

        $curl_mock_param = [
                CURLOPT_POSTFIELDS => [
                        'seg' => 'Il Sistema registra le informazioni sul nuovo film.',
                        'tra'   => 'The system records the information on the new movie.',
                        'langpair' => 'IT|EN',
                        'de' => 'demo@matecat.com',
                ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT => 120,
        ];

//        $url_mock_param= "http://api.mymemory.translated.net/delete?seg=Il+Sistema+registra+le+informazioni+sul+nuovo+film.&tra=The+system+records+the+information+on+the+new+movie.&langpair=IT%7CEN&de=demo%40matecat.com";
        $url_mock_param= "http://api.mymemory.translated.net/delete";

        $rawValue_error = array(
            'error' => array(
                'code'      => -6,
                'message'   => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                'response'  => "",
            ),
            'responseStatus'    => 0
        );


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($rawValue_error);

        $result = $this->engine_MyMemory->delete($config_param);

        $this->assertFalse($result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);
        /**
         * check on the values of TMS object returned
         */

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_TMS);
        $this->assertEquals(0, $result_object->responseStatus);
        $this->assertEquals("", $result_object->responseDetails);
        $this->assertEquals("", $result_object->responseData);
        $this->assertTrue($result_object->error instanceof Engines_Results_ErrorMatches);

        $this->assertEquals(-6, $result_object->error->code);
        $this->assertEquals("Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result_object->error->message);

        /**
         * check of protected property
         */
        $this->reflector= new ReflectionClass($result_object);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($result_object));



    }
}