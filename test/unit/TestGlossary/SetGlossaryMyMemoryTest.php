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

    protected $reflector_of_engine;
    protected $reflector_result;
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
    protected $curl_additional_params;
    protected $config_param_of_set;

    protected $first_url_delete;
    protected $second_url_delete;
    protected $segment;
    protected $old_translation;
    protected $new_translation;
    protected $unset_seg;
    protected $test_key;


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


        $this->prop = <<<'LABEL'
{"project_id":"987654","project_name":"barfoo","job_id":"321"}
LABEL;

        $this->config_param_of_set = array(
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'email' => "demo@matecat.com",
            'prop' => $this->prop,
            'get_mt' => NULL,
            'num_result' => 100,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => true,
            'id_user' => array()
        );

        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
        $this->reflector_of_engine = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector_of_engine->getProperty("result");
        $this->property->setAccessible(true);


        $this->curl_param = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 10
        );

        $this->curl_additional_params = array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );

        $this->test_key = "fc7ba5edf8d5e8401593";

        $this->segment = "tic";
        $this->old_translation = "tac";

        $this->new_translation="tac";
        $this->unset_seg="tac";

        $this->first_url_delete = "http://api.mymemory.translated.net/glossary/delete?seg={$this->segment}&tra={$this->old_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";
        $this->second_url_delete="http://api.mymemory.translated.net/glossary/delete?seg={$this->segment}&tra={$this->new_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";

        $mh = new MultiCurlHandler();
        $mh->createResource($this->first_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->second_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();

        /**
         * Unsetting
         */
        $this->config_param_of_set['segment'] = $this->segment;
        $this->config_param_of_set['translation'] = $this->unset_seg;
        $this->config_param_of_set['id_user'] = array('0' => "{$this->test_key}");

        $this->engine_MyMemory->set($this->config_param_of_set);
        sleep(2);

    }

    public function tearDown()
    {
        /**
         * Unsetting
         */
        $this->config_param_of_set['segment'] = $this->segment;
        $this->config_param_of_set['translation'] = $this->unset_seg;
        $this->config_param_of_set['id_user'] = array('0' => "{$this->test_key}");

        $this->engine_MyMemory->set($this->config_param_of_set);



        $mh = new MultiCurlHandler();
        $mh->createResource($this->first_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->second_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(2);

        parent::tearDown();
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_glossary_segment_without_key_id()
    {
        $this->config_param_of_set['segment'] = $this->segment;
        $this->config_param_of_set['translation'] = $this->old_translation;
        $this->config_param_of_set['id_user'] = array();
        $result = $this->engine_MyMemory->set($this->config_param_of_set);

        $this->assertNotTrue($result);

        $result_object = $this->property->getValue($this->engine_MyMemory);
        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($result_object, 'matches'));
        $this->assertTrue(property_exists($result_object, 'responseStatus'));
        $this->assertTrue(property_exists($result_object, 'responseDetails'));
        $this->assertTrue(property_exists($result_object, 'responseData'));
        $this->assertTrue(property_exists($result_object, 'error'));
        $this->assertTrue(property_exists($result_object, '_rawResponse'));

        $this->assertEquals(403, $result_object->responseStatus);

        $this->assertEquals("GLOSSARY REQUIRES AUTHENTICATION, PROVIDE THE 'KEY' PARAMETER", $result_object->responseDetails);
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_glossary_segment()
    {

        $this->config_param_of_set['segment'] = $this->segment;
        $this->config_param_of_set['translation'] = $this->old_translation;
        $this->config_param_of_set['id_user'] = array('0' => "{$this->test_key}");

        $result = $this->engine_MyMemory->set($this->config_param_of_set);
        sleep(2);

        $this->assertTrue($result);

        $result_object = $this->property->getValue($this->engine_MyMemory);
        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($result_object instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($result_object, 'matches'));
        $this->assertTrue(property_exists($result_object, 'responseStatus'));
        $this->assertTrue(property_exists($result_object, 'responseDetails'));
        $this->assertTrue(property_exists($result_object, 'responseData'));
        $this->assertTrue(property_exists($result_object, 'error'));
        $this->assertTrue(property_exists($result_object, '_rawResponse'));

        $this->assertEquals(200, $result_object->responseStatus);
        $details = $result_object->responseDetails[0];
        $this->assertRegExp('/^[0-9]{9}$/', "{$details}");
        $this->assertEquals("OK", $result_object->responseData);
        $this->assertNull($result_object->error);
        /**
         * check of protected property
         */
        $this->reflector_result = new ReflectionClass($result_object);
        $property = $this->reflector_result->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result_object));
    }


    /**
     * The actual behaviour is that if there are multiple set call for glossary with the same segment the last inserted will be returned
     * @group regression
     * @covers Engines_MyMemory::updateGlossary
     */

    public function test_set_two_matches_for_the_same_source_word_of_glossary_word_and_verify_that_return_the_last_inserted()
    {

        $this->config_param_of_set['segment'] = $this->segment;
        $this->config_param_of_set['translation'] = $this->old_translation;
        $this->config_param_of_set['id_user'] = array('0' => "{$this->test_key}");

        /**
         * first set
         */
        $this->engine_MyMemory->set($this->config_param_of_set);
        sleep(2);

        $this->config_param_of_set['translation'] = $this->new_translation;

        /**
         * second set
         */
        $this->engine_MyMemory->set($this->config_param_of_set);
        sleep(2);


        /**
         * verification through glossary get method
         */
        $this->reflector_of_engine= new ReflectionClass($this->engine_MyMemory);


        $method_call=$this->reflector_of_engine->getMethod('_call');
        $method_call->setAccessible(true);

        $url_get= "http://api.mymemory.translated.net/glossary/get?q={$this->segment}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&numres=100&key={$this->test_key}";


        $raw_value=$method_call->invoke($this->engine_MyMemory,$url_get,$this->curl_param);

        $method_decode=$this->reflector_of_engine->getMethod('_decode');
        $method_decode->setAccessible(true);

        $decode_parameters= array(
            'q' => "{$this->segment}",
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'mt' => true,
            'numres' => "100",
            'key' => "{$this->test_key}"
        );


        $result_from_glossary=$method_decode->invoke($this->engine_MyMemory,$raw_value,"gloss_get_relative_url",$decode_parameters);
        $translatedText = $result_from_glossary->responseData['translatedText'];
        $this->assertEquals("{$this->new_translation}", $translatedText);
    }


}