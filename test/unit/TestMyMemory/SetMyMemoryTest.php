<?php

/**
 * @group regression
 * @covers Engines_MyMemory::set
 * User: dinies
 * Date: 02/05/16
 * Time: 18.22
 */
class SetMyMemoryTest extends AbstractTest
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
    public function test_set_segment_1_general_check()
    {
        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);

        $prop=<<<'LABEL'
{"project_id":"10","project_name":"tyuio","job_id":"10"}
LABEL;

        $this->config_param= array(
            'segment' => "Il Sistema registra le informazioni sul nuovo film.",
            'translation' => "The system records the information on the new movie.",
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
            'id_user' => "a6043e606ac9b5d7ff24"
        );

        $result = $this->engine_MyMemory->set($this->config_param);

        $this->assertTrue($result);

        /**
         *  general check of the Engines_Results_MyMemory_SetContributionResponse object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertFalse(property_exists($result_object,'matches'));
        $this->assertTrue(property_exists($result_object,'responseStatus'));
        $this->assertTrue(property_exists($result_object,'responseDetails'));
        $this->assertTrue(property_exists($result_object,'responseData'));
        $this->assertTrue(property_exists($result_object,'error'));
        $this->assertTrue(property_exists($result_object,'_rawResponse'));


    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_1_with_mock()
    {

        $prop=<<<'LABEL'
{"project_id":"10","project_name":"tyuio","job_id":"10"}
LABEL;

        $this->config_param= array(
            'segment' => "Il Sistema registra le informazioni sul nuovo film.",
            'translation' => "The system records the information on the new movie.",
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
            'id_user' => "a6043e606ac9b5d7ff24"
    );
        $curl_mock_param=array(
            '80' => true,
            '13' => 10
        );
        $url_mock_param= "http://api.mymemory.translated.net/set?seg=Il+Sistema+registra+le+informazioni+sul+nuovo+film.&tra=The+system+records+the+information+on+the+new+movie.&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%2210%22%2C%22project_name%22%3A%22tyuio%22%2C%22job_id%22%3A%2210%22%7D&key=a6043e606ac9b5d7ff24";

        $mock_json_return=<<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484525156]}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->any())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $actual_result = $this->engine_MyMemory->set($this->config_param);

        $this->assertTrue($actual_result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);
        
        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals(array('0' => 484525156), $result_object->responseDetails);
        $this->assertEquals("OK", $result_object->responseData);
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
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_2_general_check()
    {
        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);


        $prop = <<<'LABEL'
{"project_id":"9","project_name":"eryt","job_id":"9"}
LABEL;

        $segment = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;

        $translation = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;


        $this->config_param = array(
            'segment' => $segment,
            'translation' => $translation,
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
            'id_user' => "a6043e606ac9b5d7ff24"
        );
        
        
        $result = $this->engine_MyMemory->set($this->config_param);

        $this->assertTrue($result);

        /**
         *  general check of the Engines_Results_MyMemory_SetContributionResponse object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertFalse(property_exists($result_object,'matches'));
        $this->assertTrue(property_exists($result_object,'responseStatus'));
        $this->assertTrue(property_exists($result_object,'responseDetails'));
        $this->assertTrue(property_exists($result_object,'responseData'));
        $this->assertTrue(property_exists($result_object,'error'));
        $this->assertTrue(property_exists($result_object,'_rawResponse'));


    }
    public function test_set_segment_2_with_mock(){
        
        $prop = <<<'LABEL'
{"project_id":"9","project_name":"eryt","job_id":"9"}
LABEL;

        $segment = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;

        $translation = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;


        $this->config_param = array(
            'segment' => $segment,
            'translation' => $translation,
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
            'id_user' => "a6043e606ac9b5d7ff24"
        );
        $curl_mock_param=array(
            '80' => true,
            '13' => 10
        );
        $url_mock_param= "http://api.mymemory.translated.net/set?seg=Ad+esempio%2C+una+copia+del+film+%3Cg+id%3D%2210%22%3EBlade+Runner%3C%2Fg%3E+in+formato+DVD%2C+con+numero+di+serie+6457.&tra=For+example%2C+a+copy+of+the+film+%3Cg+id%3D%2210%22%3EFlade+Bunner%3C%2Fg%3E+in+DVD+format%2C+with+numbers+of+6457+series.&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%229%22%2C%22project_name%22%3A%22eryt%22%2C%22job_id%22%3A%229%22%7D&key=a6043e606ac9b5d7ff24";

        $mock_json_return=<<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484540480]}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $this->engine_MyMemory->expects($this->any())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $actual_result = $this->engine_MyMemory->set($this->config_param);

        $this->assertTrue($actual_result);


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         */
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object=$this->property->getValue($this->engine_MyMemory);

        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals(array('0' => 484540480), $result_object->responseDetails);
        $this->assertEquals("OK", $result_object->responseData);
        $this->assertNull($result_object->error);
        /**
         * check of protected property
         */
        $this->reflector= new ReflectionClass($result_object);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($result_object));




    }

}