<?php

/**
 * @group regression
 * @covers Engines_MyMemory::set
 * User: dinies
 * Date: 06/05/16
 * Time: 18.20
 */
class SetGlossaryMyMemoryTest extends AbstractTest
{


    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;

    protected $reflector;
    protected $property;
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_DAO;
    /**
     * @var json
     */
    protected $prop;
    /**
     * @var array
     */
    protected $curl_param;
    protected $config_param_of_set;


    public function setUp()
    {
        parent::setUp();

        $engineDAO = new EnginesModel_EngineDAO(Database::obtain());
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = @$eng[0];


        $this->prop = <<<'LABEL'
{"project_id":"987654","project_name":"barfoo","job_id":"321"}
LABEL;
        $param_de = "demo@matecat.com";

        $this->config_param_of_set = array(
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'email' => $param_de,
            'prop' => $this->prop,
            'get_mt' => NULL,
            'num_result' => 100,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => true,
            'id_user' => array()
        );

        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
    }
    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_glossary_segment_without_key_id(){
        $this->config_param_of_set['segment']="testone";
        $this->config_param_of_set['translation']="testaccio";
        
        $result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertNotTrue($result);

        $result_object= $this->property->getValue($this->engine_MyMemory);
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

        $this->assertEquals(403, $result_object->responseStatus);

        $this->assertEquals("GLOSSARY REQUIRES AUTHENTICATION, PROVIDE THE 'KEY' PARAMETER", $result_object->responseDetails);
    }

    /**
 * @group regression
 * @covers Engines_MyMemory::set
 */
    public function test_set_glossary_segment(){
        $this->config_param_of_set['segment']="REQUISITI";
        $this->config_param_of_set['translation']="Requisitions";
        $this->config_param_of_set['id_user']="fc7ba5edf8d5e8401593";

        $result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertTrue($result);

        $result_object= $this->property->getValue($this->engine_MyMemory);
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

    }
}