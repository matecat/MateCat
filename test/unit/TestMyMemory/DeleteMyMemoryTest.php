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

     
        
        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain() );
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
    public function test_delete_segment_general_check_without_time_for_MyMemory_to_make_the_transation()
    {

        /**
         * initialization of the value to delete
         */
        sleep(2);
        $this->engine_MyMemory->set($this->config_param_set);
        /**
         * end of initialization
         */
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
        $actual_result=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($actual_result,'matches'));
        $this->assertTrue(property_exists($actual_result,'responseStatus'));
        $this->assertTrue(property_exists($actual_result,'responseDetails'));
        $this->assertTrue(property_exists($actual_result,'responseData'));
        $this->assertTrue(property_exists($actual_result,'error'));
        $this->assertTrue(property_exists($actual_result,'_rawResponse'));
        $this->assertEquals("NO ID FOUND", $actual_result->responseDetails);


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
        $actual_result=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($actual_result,'matches'));
        $this->assertTrue(property_exists($actual_result,'responseStatus'));
        $this->assertTrue(property_exists($actual_result,'responseDetails'));
        $this->assertTrue(property_exists($actual_result,'responseData'));
        $this->assertTrue(property_exists($actual_result,'error'));
        $this->assertTrue(property_exists($actual_result,'_rawResponse'));
        $this->assertEquals("", $actual_result->responseDetails);

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

        $curl_mock_param=array(
            '80' => true,
            '13' => 10
        );
        $url_mock_param= "http://api.mymemory.translated.net/delete?seg=Il+Sistema+registra+le+informazioni+sul+nuovo+film.&tra=The+system+records+the+information+on+the+new+movie.&langpair=IT%7CEN&de=demo%40matecat.com";

        $mock_json_return=<<<'TAB'
{"responseStatus":200,"responseData":"Found and deleted 1 segments"}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->once())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $actual_result = $this->engine_MyMemory->delete($config_param);

        $this->assertTrue($actual_result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $actual_result=$this->property->getValue($this->engine_MyMemory);
        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_TMS);
        $this->assertEquals(array(),$actual_result->matches);
        $this->assertEquals(200, $actual_result->responseStatus);
        $this->assertEquals("", $actual_result->responseDetails);
        $this->assertEquals("Found and deleted 1 segments", $actual_result->responseData);
        $this->assertNull($actual_result->error);
        /**
         * check of protected property
         */
        $this->reflector= new ReflectionClass($actual_result);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($actual_result));



    }
}