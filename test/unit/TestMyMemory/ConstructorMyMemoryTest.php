<?php

/**
 * @group regression
 * @covers Engines_MyMemory::__construct
 * * User: dinies
 * Date: 28/04/16
 * Time: 15.45
 */
class ConstructorMyMemoryTest extends AbstractTest
{


    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var array
     */
    protected $others_param;
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
     * It construct an engine and it initialises some globals from the abstract constructor
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_of_sub_engine_of_moses()
    {
        $this->reflectedClass = new Engines_MyMemory($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->property = $this->reflector->getProperty("engineRecord");
        $this->property->setAccessible(true);

        $this->assertEquals($this->engine_struct_param, $this->property->getValue($this->reflectedClass));

        $this->property = $this->reflector->getProperty("className");
        $this->property->setAccessible(true);

        $this->assertEquals("Engines_MyMemory", $this->property->getValue($this->reflectedClass));

        $this->property = $this->reflector->getProperty("curl_additional_params");
        $this->property->setAccessible(true);

        $this->assertEquals(6, count($this->property->getValue($this->reflectedClass)));

    }


    /**
     * It will raise an exception constructing an engine because of he wrong property of the struct.
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_failure()
    {
        $this->engine_struct_param->type = "fooo";
        $this->setExpectedException("Exception");
        new Engines_MyMemory($this->engine_struct_param);
    }
}