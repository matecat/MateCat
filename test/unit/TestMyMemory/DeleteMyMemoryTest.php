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
    protected $config_param;

    protected $reflector;
    protected $property;


    public function setUp()
    {
        parent::setUp();

        $this->others_param = array();
        $this->others_param['gloss_get_relative_url'] = "glossary/get";
        $this->others_param['gloss_set_relative_url'] = "glossary/set";
        $this->others_param['gloss_update_relative_url'] = "glossary/update";
        $this->others_param['gloss_delete_relative_url'] = "glossary/delete";
        $this->others_param['tmx_import_relative_url'] = "tmx/import";
        $this->others_param['tmx_status_relative_url'] = "tmx/status";
        $this->others_param['tmx_export_create_url'] = "tmx/export/create";
        $this->others_param['tmx_export_check_url'] = "tmx/export/check";
        $this->others_param['tmx_export_download_url'] = "tmx/export/download";
        $this->others_param['tmx_export_list_url'] = "tmx/export/list";
        $this->others_param['api_key_create_user_url'] = "createranduser";
        $this->others_param['api_key_check_user_url'] = "authkey";
        $this->others_param['analyze_url'] = "analyze";
        $this->others_param['detect_language_url'] = "langdetect.php";


        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->id = 1;
        $this->engine_struct_param->name = "MyMemory (All Pairs)";
        $this->engine_struct_param->type = "TM";
        $this->engine_struct_param->description = "Machine translation from Google Translate and Microsoft Translator.";
        $this->engine_struct_param->base_url = "http://api.mymemory.translated.net";
        $this->engine_struct_param->translate_relative_url = "get";
        $this->engine_struct_param->contribute_relative_url = "set";
        $this->engine_struct_param->delete_relative_url = "delete";
        $this->engine_struct_param->others = $this->others_param;
        $this->engine_struct_param->class_load = "MyMemory";
        $this->engine_struct_param->extra_parameters = array();
        $this->engine_struct_param->google_api_compliant_version = "1";
        $this->engine_struct_param->penalty = "0";
        $this->engine_struct_param->active = "1";
        $this->engine_struct_param->uid = NULL;



        
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_delete_segment_general_check()
    {
        /**
         * initialization of the value to delete
         */

        $prop=<<<'LABEL'
{"project_id":"12","project_name":"ForDeleteTest","job_id":"12"}
LABEL;

        $this->config_param= array(
            'segment' => "Il Sistema registra le informazioni sul nuovo film.",
            'translation' => "The system records the information on the new book.",
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

        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);

        $this->engine_MyMemory->set($this->config_param);

        /**
         * end of initialization
         */
        
        
        
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        //$this->property = $this->reflector->getProperty("curl_additional_params");
        //$this->property->setAccessible(true);
        /**
         * INUTILE
         */
        //$curl_additional_params= array(
        //    '42' => false,
        //    '19913' => true,
        //    '10018' => "Matecat-Cattool/v1.0",
        //    '78' => 10,
        //    '64' => true,
        //    '81' => 2
        //);
//
        //$this->property->setValue($this->engine_MyMemory,$curl_additional_params);

        $this->config_param= array(
            'segment' => "Il Sistema registra le informazioni sul nuovo film.",
            'translation' => "The system records the information on the new book.",
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

        $result = $this->engine_MyMemory->delete($this->config_param);

        $this->assertTrue($result);

        /**
         *  general check of the Engines_Results_MyMemory_TMS object
         */
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


    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_delete_segment_with_mock()
    {

        $this->config_param= array(
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
        $this->engine_MyMemory->expects($this->any())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $actual_result = $this->engine_MyMemory->delete($this->config_param);

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