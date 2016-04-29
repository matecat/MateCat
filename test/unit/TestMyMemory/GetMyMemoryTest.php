<?php

/**
 * @group regression
 * @covers Engines_MyMemory::get
 * User: dinies
 * Date: 28/04/16
 * Time: 16.12
 */
class GetMyMemoryTest extends AbstractTest
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


        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
        

    }
/**
 * @group regression
 * @covers Engines_MyMemory::get
 */
    public function test_get_segment_dutch(){
        $this->config_param= array(
            'segment' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
            'translation' => "",
            'tnote' => NULL,
            'source' => "en-US",
            'target' => "fr-FR",
            'email' => "demo@matecat.com",
            'prop' => NULL,
            'get_mt' => NULL,
            'id_user' => "",
            'num_result' => 100,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => true,
        );

        $result = $this->engine_MyMemory->get($this->config_param);
        $this->assertEquals(200,$result->responseStatus);
        $this->assertEquals("",$result->responseDetails);
        $this->assertCount(2,$result->responseData);
        $this->assertTrue($result instanceof Engines_Results_MyMemory_TMS);
        $this->assertEquals(array(),$result->matches);


        $this->reflector= new ReflectionClass($result);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($result));

    }

    /**
     * @group regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_segment_dutch_with_id_user_initialized(){
        $this->config_param= array(
            'segment' => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
            'translation' => "",
            'tnote' => NULL,
            'source' => "en-US",
            'target' => "fr-FR",
            'email' => "demo@matecat.com",
            'prop' => NULL,
            'get_mt' => NULL,
            'id_user' => 44,
            'num_result' => 100,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => true,
        );

        $result = $this->engine_MyMemory->get($this->config_param);
        $this->assertEquals(200,$result->responseStatus);
        $this->assertEquals("",$result->responseDetails);
        $this->assertCount(2,$result->responseData);
        $this->assertTrue($result instanceof Engines_Results_MyMemory_TMS);
        $this->assertEquals(array(),$result->matches);


        $this->reflector= new ReflectionClass($result);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($result));

    }

    /**
     * @group regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_segment_italian(){
        $this->config_param= array(
            'segment' => "Scelta del Piano di studio parziale per il secondo anno ripetente secondo l’Ordinamento D.M. 270/04",
            'translation' => NULL,
            'tnote' => NULL,
            'source' => "en-US",
            'target' => "fr-FR",
            'email' => "demo@matecat.com",
            'prop' => NULL,
            'get_mt' => true,
            'id_user' => array(),
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
        );



        $result = $this->engine_MyMemory->get($this->config_param);

        $str_1 = "Scelta del Piano di studio parziale per il secondo anno ripetente secondo l’Ordinamento D.M. 270/04";
        $str_2 = "Scelta del Piano di studio de parziale per il secondo anno ripetente secondo l&#39;Ordinamento DM 270/04";
        $str_3 = "MT!";

        $this->assertEquals(200,$result->responseStatus);
        $this->assertEquals("",$result->responseDetails);
        $this->assertCount(2,$result->responseData);
        $this->assertTrue($result instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue($result->matches[0] instanceof Engines_Results_MyMemory_Matches);
        $this->assertEquals(0,$result->matches[0]->id);
        $this->assertEquals($str_1,$result->matches[0]->raw_segment);
        $this->assertEquals($str_1,$result->matches[0]->segment);
        $this->assertEquals($str_2,$result->matches[0]->translation);
        $this->assertEquals("",$result->matches[0]->target_note);
        $this->assertEquals($str_2,$result->matches[0]->raw_translation);
        $this->assertEquals(70,$result->matches[0]->quality);
        $this->assertEquals("Machine Translation provided by Google, Microsoft, Worldlingo or MyMemory customized engine.",$result->matches[0]->reference);
        $this->assertEquals(1,$result->matches[0]->usage_count);
        $this->assertFalse($result->matches[0]->subject);
        $this->assertEquals($str_3,$result->matches[0]->created_by);
        $this->assertEquals($str_3,$result->matches[0]->last_updated_by);
        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/',$result->matches[0]->create_date);
        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$result->matches[0]->last_update_date);
        $this->assertRegExp('/^1?[0-9]{1,2}%$/',$result->matches[0]->match);
        $this->assertEquals(array(),$result->matches[0]->prop);
        $this->assertEquals("",$result->matches[0]->source_note);
        $this->assertEquals("",$result->matches[0]->memory_key);

        $this->reflector= new ReflectionClass($result);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($result));
    }
}