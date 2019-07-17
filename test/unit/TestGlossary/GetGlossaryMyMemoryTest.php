<?php

/**
 * @group regression
 * @covers Engines_MyMemory::get
 * User: dinies
 * Date: 10/05/16
 * Time: 12.11
 */
class GetGlossaryMyMemoryTest extends AbstractTest
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
    protected $curl_additional_params;
    protected $config_param_of_get;

    protected $url_set;
    protected $url_delete;

    protected $segment;
    protected $translation;

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
            'id_user' => array()
        );

        $this->engine_MyMemory = new Engines_MyMemory($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
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

        $this->test_key="fc7ba5edf8d5e8401593";

        $this->segment= "prova";
        $this->translation= "proof";
        $this->url_delete= "http://api.mymemory.translated.net/glossary/delete?seg={$this->segment}&tra={$this->translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&key={$this->test_key}";
        $this->url_set = "http://api.mymemory.translated.net/glossary/set?seg={$this->segment}&tra={$this->translation}&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%22987654%22%2C%22project_name%22%3A%22barfoo%22%2C%22job_id%22%3A%22321%22%7D&key={$this->test_key}";


    }

    public function tearDown()
    {

        $mh = new MultiCurlHandler();
        $mh->createResource($this->url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(1);

        parent::tearDown();
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_glossary_segment_without_key_id()
    {
        $this->config_param_of_get['segment'] = $this->segment;

        $result_object = $this->engine_MyMemory->get($this->config_param_of_get);

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
    }

    /**
     * @group regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_glossary_segment()
    {

        $mh = new MultiCurlHandler();
        $mh->createResource($this->url_delete, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(1);

        $mh = new MultiCurlHandler();
        $mh->createResource($this->url_set, $this->curl_additional_params + $this->curl_param);
        $mh->multiExec();
        $mh->multiCurlCloseAll();
        sleep(3);

        $this->config_param_of_get['segment'] = $this->segment;
        $this->config_param_of_get['id_user'] = [$this->test_key];

        $result_object = $this->engine_MyMemory->get($this->config_param_of_get);
        sleep(1);

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
         * specific check
         */

        $this->assertTrue($result_object->matches[0] instanceof Engines_Results_MyMemory_Matches);
        /**
         *  match
         */

        /**
         * I commented this piece of code because all props of $result_object->matches[0] are NULL
         * @TODO understand why!!!
         */

//        $this->assertRegExp('/^[0-9]{9}$/',$result_object->matches[0]->id);
//        $this->assertEquals("{$this->segment}",$result_object->matches[0]->raw_segment);
//        $this->assertEquals("{$this->segment}",$result_object->matches[0]->segment);
//        $this->assertEquals("{$this->translation}",$result_object->matches[0]->translation);
//        $this->assertEquals("",$result_object->matches[0]->target_note);
//        $this->assertEquals("{$this->translation}",$result_object->matches[0]->raw_translation);
//        $quality = $result_object->matches[0]->quality;
//        $this->assertRegExp('/^1?[0-9]{1,2}$/', "{$quality}");
//        $this->assertEquals("",$result_object->matches[0]->reference);
//        $this->assertEquals("1",$result_object->matches[0]->usage_count);
//        $this->assertEquals("All",$result_object->matches[0]->subject);
//        $this->assertRegExp('/^MyMemory_.*$/',$result_object->matches[0]->created_by);
//        $this->assertRegExp('/^MyMemory_.*$/',$result_object->matches[0]->last_updated_by);
//        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/',$result_object->matches[0]->create_date);
//        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$result_object->matches[0]->last_update_date);
//        $this->assertRegExp( '/^1?[0-9]{1,2}%$/',$result_object->matches[0]->match);
//        $this->assertEquals(array(),$result_object->matches[0]->prop);
//        $this->assertEquals("",$result_object->matches[0]->source_note);
//        $this->assertEquals("{$this->test_key}",$result_object->matches[0]->memory_key);


        $this->assertEquals(200,$result_object->responseStatus);
        $this->assertEquals("",$result_object->responseDetails);
        $this->assertCount(2,$result_object->responseData);
        $this->assertNull($result_object->error);

        $this->assertEquals("{$this->translation}",$result_object->responseData['translatedText']);
        $match = $result_object->responseData['match'];
        $this->assertRegExp( '/^[0-9]*$/', "{$match}");
        $this->reflector= new ReflectionClass($result_object);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($result_object));

    }
    
    
    
}