<?php

/**
 * @group regression
 * @covers Engines_MyMemory::_decode
 * User: dinies
 * Date: 28/04/16
 * Time: 17.58
 */
class DecodeMyMemoryTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;
    protected $reflector;
    protected $method;
    protected $others_param;

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


        $this->reflectedClass = new Engines_MyMemory($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_decode");
        $this->method->setAccessible(true);

    }
    /**
     * It tests the behaviour of the decoding of json input.
     * @group regression
     * @covers  Engines_MyMemory::_decode
     */
    public function test__decode_with_json_in_input_deusch_segment(){
        $json_input= <<<LAB
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"responderId":"235","matches":[]}
LAB;

        $input_parameters= array(
            'q' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen." ,
            'langpair' => "en-US|fr-FR",
            'de' => "demo@matecat.com",
            'mt' => NULL,
            'numres' => 100
        );
        $input_function_purpose= "gloss_get_relative_url";

        $actual_return=$this->method->invoke($this->reflectedClass,$json_input,$input_parameters, $input_function_purpose );

        $this->assertEquals(array(),$actual_return->matches);
        $this->assertEquals(200,$actual_return->responseStatus);
        $this->assertEquals("",$actual_return->responseDetails);
        $this->assertCount(2,$actual_return->responseData);
        $this->assertNull($actual_return->error);

        $this->assertTrue($actual_return instanceof Engines_Results_MyMemory_TMS);
        
        $this->reflector= new ReflectionClass($actual_return);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($actual_return));
    }
}