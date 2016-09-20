<?php

/**
 * @group regression
 * @covers Engines_MyMemory::updateGlossary
 * @covers Engines_MyMemory::set
 * @covers Engines_MyMemory::get
 * User: dinies
 * Date: 24/05/16
 * Time: 15.30
 */
class GetSetUpdateWithNoteTest extends AbstractTest
{

    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;

    protected $test_key;
    protected $config_param_of_update;
    protected $config_param_of_set;
    protected $config_param_of_get;
    protected $first_url_delete;
    protected $second_url_delete;
    protected $segment;
    protected $old_translation;
    protected $new_translation;
    protected $string;
    protected $curl_additional_params;
    protected $curl_param;


    public function setUp()
    {
        parent::setUp();

        $engineDAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $engine_struct = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = $eng[0];


        /**
         * creation of the engine
         */
        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
        $this->test_key = "fc7ba5edf8d5e8401593";


        $this->curl_additional_params = array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );
        $this->curl_param = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 10
        );

        $this->config_param_of_update = array(
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'prop' => NULL,
            'get_mt' => NULL,
            'num_result' => 100,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => true,
            'id_user' => array($this->test_key)
        );

        $prop = <<<'LABEL'
{"project_id":"987654","project_name":"barfoo","job_id":"321"}
LABEL;

        $this->config_param_of_set = array(
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'email' => "demo@matecat.com",
            'prop' => $prop,
            'get_mt' => NULL,
            'num_result' => 100,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => true,
            'id_user' => array($this->test_key)
        );

        $this->config_param_of_get = array(
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'email' => "demo@matecat.com",
            'prop' => NULL,
            'get_mt' => NULL,
            'num_result' => 100,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => true,
            'id_user' => array($this->test_key)
        );

        $this->segment = "LEO";
        $this->old_translation = "BEA";
        $this->new_translation = "ZOE";

        $this->first_url_delete = "http://api.mymemory.translated.net/glossary/delete?seg={$this->segment}&tra={$this->old_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";
        $this->second_url_delete = "http://api.mymemory.translated.net/glossary/delete?seg={$this->segment}&tra={$this->new_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";
        $this->string= "bar and foo";


        $mh = new MultiCurlHandler();
        $mh->createResource($this->first_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->createResource($this->second_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(1);

    }

    public function tearDown()
    {


        $mh = new MultiCurlHandler();
        $mh->createResource($this->first_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->createResource($this->second_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(1);


        parent::tearDown();
    }


    /**
     * This test is focused on the behaviour of the words with a correlated note
     * @group regression
     * @covers Engines_MyMemory::updateGlossary
     * @covers Engines_MyMemory::set
     * @covers Engines_MyMemory::get
     */
    public function test_set_update_get_word_with_note_glossary(){

        /**
         * Setting
         */
        $this->config_param_of_set['segment'] = $this->segment;
        $this->config_param_of_set['translation'] = $this->old_translation;
        $this->config_param_of_set['tnote'] = $this->string;

        $this->engine_MyMemory->set($this->config_param_of_set);
        sleep(2);
        /**
         * Updating
         */
        $substitute_string="food or barckley";

        $this->config_param_of_update['segment'] = $this->segment;
        $this->config_param_of_update['translation'] = $this->old_translation;
        $this->config_param_of_update['newsegment'] = $this->segment;
        $this->config_param_of_update['newtranslation'] = $this->new_translation;
        $this->config_param_of_update['tnote'] = $substitute_string;

        $this->engine_MyMemory->updateGlossary($this->config_param_of_update);
        sleep(2);
        /**
         * Getting
         */
        $this->config_param_of_get['segment'] = $this->segment;

        $result= $this->engine_MyMemory->get($this->config_param_of_get);

        $this->assertTrue($result->matches[0] instanceof Engines_Results_MyMemory_Matches);

        $source_note = $result->matches[0]->source_note;
        $this->assertEquals($substitute_string, $source_note, "It WILL fail UNTIL it won't be fixed");







    }
}