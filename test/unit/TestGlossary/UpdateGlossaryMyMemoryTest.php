<?php

/**
 * @group regression
 * @covers Engines_MyMemory::updateGlossary
 * User: dinies
 * Date: 17/05/16
 * Time: 16.27
 */
class UpdateGlossaryMyMemoryTest extends AbstractTest
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
    protected $reflector_of_result_object;
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
    protected $config_param_of_update;

    protected $old_old_url_set;
    protected $old_old_url_delete;
    protected $old_new_url_delete;
    protected $new_old_url_delete;
    protected $new_new_url_delete;
    protected $old_url_get;
    protected $new_url_get;

    protected $old_segment;
    protected $old_translation;
    protected $new_segment;
    protected $new_translation;

    protected $test_key;
    protected $old_decode_parameters;
    protected $new_decode_parameters;


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


        $this->reflector_of_engine = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector_of_engine->getProperty("result");
        $this->property->setAccessible(true);


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
            'id_user' => array()
        );


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

        $this->old_decode_parameters = array(
            'q' => "{$this->old_segment}",
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'mt' => true,
            'numres' => "100",
            'key' => "{$this->test_key}"
        );

        $this->new_decode_parameters = array(
            'q' => "{$this->new_segment}",
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'mt' => true,
            'numres' => "100",
            'key' => "{$this->test_key}"
        );

        $this->test_key = "fc7ba5edf8d5e8401593";

        $this->old_segment = "BOB";
        $this->old_translation = "MARY";
        $this->new_segment = "LUKE";
        $this->new_translation = "MARY";

        $this->old_old_url_set = "http://api.mymemory.translated.net/glossary/set?seg={$this->old_segment}&tra={$this->old_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%22987654%22%2C%22project_name%22%3A%22barfoo%22%2C%22job_id%22%3A%22321%22%7D&key={$this->test_key}";

        $this->old_old_url_delete = "http://api.mymemory.translated.net/glossary/delete?seg={$this->old_segment}&tra={$this->old_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";
        $this->old_new_url_delete = "http://api.mymemory.translated.net/glossary/delete?seg={$this->old_segment}&tra={$this->new_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";
        $this->new_old_url_delete = "http://api.mymemory.translated.net/glossary/delete?seg={$this->new_segment}&tra={$this->old_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";
        $this->new_new_url_delete = "http://api.mymemory.translated.net/glossary/delete?seg={$this->new_segment}&tra={$this->new_translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";


        $this->new_url_get = "http://api.mymemory.translated.net/glossary/get?q={$this->new_segment}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&numres=100&key={$this->test_key}";
        $this->old_url_get = "http://api.mymemory.translated.net/glossary/get?q={$this->old_segment}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&numres=100&key={$this->test_key}";


        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_old_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_new_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->new_old_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->new_new_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(1);

    }

    public function tearDown()
    {

        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_old_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_new_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->new_old_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        $mh = new MultiCurlHandler();
        $mh->createResource($this->new_new_url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(1);


        parent::tearDown();
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::updateGlossary
     */
    public function test_update_glossary_segment_without_key_id()
    {
        $this->config_param_of_update['segment'] = $this->old_segment;
        $this->config_param_of_update['translation'] = $this->old_translation;
        $this->config_param_of_update['newsegment'] = $this->new_segment;
        $this->config_param_of_update['newtranslation'] = $this->new_translation;


        $result = $this->engine_MyMemory->updateGlossary($this->config_param_of_update);

//        $this->assertFalse($result);
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

//        $this->assertEquals(403, $result_object->responseStatus);
//        $this->assertEquals("GLOSSARY REQUIRES AUTHENTICATION, PROVIDE THE 'KEY' PARAMETER", $result_object->responseDetails);$this->assertEquals(403, $result_object->responseStatus);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals("", $result_object->responseDetails);
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::updateGlossary
     */

    public function test_OLD_NEW_update_with_success_glossary_word_checking_through_get_verification()
    {


        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_old_url_set, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(2);

        $this->config_param_of_update['segment'] = $this->old_segment;
        $this->config_param_of_update['translation'] = $this->old_translation;
        $this->config_param_of_update['newsegment'] = $this->old_segment;
        $this->config_param_of_update['newtranslation'] = $this->new_translation;
        $this->config_param_of_update['id_user'] = array('0' => "{$this->test_key}");

        $this->engine_MyMemory->updateGlossary($this->config_param_of_update);
        sleep(2);


        /**
         * verification through glossary get method
         */
        $this->reflector_of_engine = new ReflectionClass($this->engine_MyMemory);


        $method_call = $this->reflector_of_engine->getMethod('_call');
        $method_call->setAccessible(true);

        $raw_value = $method_call->invoke($this->engine_MyMemory, $this->old_url_get, $this->curl_param);
        sleep(1);
        $method_decode = $this->reflector_of_engine->getMethod('_decode');
        $method_decode->setAccessible(true);

        $result_from_glossary = $method_decode->invoke($this->engine_MyMemory, $raw_value, "gloss_get_relative_url", $this->old_decode_parameters);
        $translatedText = $result_from_glossary->responseData['translatedText'];
        $this->assertEquals("{$this->new_translation}", $translatedText);
    }


    /**
     * SOMETIMES IT FAILS IF LAUNCHED WITH ALL THE OTHER UNIT TESTS
     * @group regression
     * @covers Engines_MyMemory::updateGlossary
     * @see    UpdateGlossaryMyMemoryTest::test_OLD_NEW_update_with_success_glossary_word_checking_through_get_verification
     */

    public function test_OLD_NEW_update_with_success_glossary_word()
    {


        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_old_url_set, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(2);

        $this->config_param_of_update['segment'] = $this->old_segment;
        $this->config_param_of_update['translation'] = $this->old_translation;
        $this->config_param_of_update['newsegment'] = $this->old_segment;
        $this->config_param_of_update['newtranslation'] = $this->new_translation;
        $this->config_param_of_update['id_user'] = array('0' => "{$this->test_key}");

        $result = $this->engine_MyMemory->updateGlossary($this->config_param_of_update);

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

        /**
         * check on the values of TMS object returned
         */
        $this->assertEquals(array(), $result_object->matches);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals("", $result_object->responseDetails);
        $this->assertEquals("OK", $result_object->responseData);
        $this->assertNull($result_object->error);
        /**
         * check of protected property
         */
        $this->reflector_of_result_object = new ReflectionClass($result_object);
        $property = $this->reflector_of_result_object->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result_object));


    }

    /**
     * @group regression
     * @covers Engines_MyMemory::updateGlossary
     */

    public function test_NEW_OLD_update_with_success_of_glossary_word_with_id_not_in_array_coverage_purpose_checking_through_get_verification()
    {
        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_old_url_set, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(2);

        $this->config_param_of_update['segment'] = $this->old_segment;
        $this->config_param_of_update['translation'] = $this->old_translation;
        $this->config_param_of_update['newsegment'] = $this->new_segment;
        $this->config_param_of_update['newtranslation'] = $this->old_translation;
        $this->config_param_of_update['id_user'] = "{$this->test_key}";

        $this->engine_MyMemory->updateGlossary($this->config_param_of_update);
        sleep(2);

        /**
         * verification through glossary get method
         */
        $this->reflector_of_engine = new ReflectionClass($this->engine_MyMemory);


        $method_call = $this->reflector_of_engine->getMethod('_call');
        $method_call->setAccessible(true);

        $raw_value = $method_call->invoke($this->engine_MyMemory, $this->new_url_get, $this->curl_param);

        $method_decode = $this->reflector_of_engine->getMethod('_decode');
        $method_decode->setAccessible(true);

        $result_from_glossary = $method_decode->invoke($this->engine_MyMemory, $raw_value, "gloss_get_relative_url", $this->new_decode_parameters);
        $translatedText = $result_from_glossary->responseData['translatedText'];

        $this->assertEquals("{$this->old_translation}", $translatedText);
    }


    /**
     * @group regression
     * @covers Engines_MyMemory::updateGlossary
     * @see    UpdateGlossaryMyMemoryTest::test_NEW_OLD_update_with_success_of_glossary_word_with_id_not_in_array_coverage_purpose_checking_through_get_verification
     */

    public function test_NEW_OLD_update_with_success_of_glossary_word_with_id_not_in_array_coverage_purpose()
    {

        $mh = new MultiCurlHandler();
        $mh->createResource($this->old_old_url_set, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(2);

        $this->config_param_of_update['segment'] = $this->old_segment;
        $this->config_param_of_update['translation'] = $this->old_translation;
        $this->config_param_of_update['newsegment'] = $this->new_segment;
        $this->config_param_of_update['newtranslation'] = $this->old_translation;
        $this->config_param_of_update['id_user'] = "{$this->test_key}";

        $result = $this->engine_MyMemory->updateGlossary($this->config_param_of_update);
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

        /**
         * check on the values of TMS object returned
         */
        $this->assertEquals(array(), $result_object->matches);
        $this->assertEquals(200, $result_object->responseStatus);
        $this->assertEquals("", $result_object->responseDetails);
        $this->assertEquals("OK", $result_object->responseData);
        $this->assertNull($result_object->error);
        /**
         * check of protected property
         */
        $this->reflector_of_result_object = new ReflectionClass($result_object);
        $property = $this->reflector_of_result_object->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("", $property->getValue($result_object));

    }


}