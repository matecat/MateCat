<?php

/**
 * @group regression
 * @covers  Engines_AbstractEngine::call
 * User: dinies
 * Date: 06/05/16
 * Time: 16.39
 */
class CallAbstractMosesTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param_DeepLingo;
    /**
     * @var Engines_DeepLingo
     * DeepLingo is a subclass of Moses
     */
    protected $engine_Deep_Lingo;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $sql_insert_user;
    protected $sql_insert_engine;
    protected $sql_delete_user;
    protected $sql_delete_engine;
    protected $id_user;
    protected $id_database;

    /**
     * @var array
     */
    protected $array_param;
    protected $curl_param;

    public function setUp()
    {
        parent::setUp();

        $engine_DAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->curl_param = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 10
        );

        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        /**
         * user insertion
         */
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES (NULL, 'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo', '');";
        $this->database_instance->query($this->sql_insert_user);
        $this->id_user = $this->database_instance->getConnection()->lastInsertId();

        /**
         * engine insertion
         */
        $this->sql_insert_engine = "INSERT INTO " . INIT::$DB_DATABASE . ".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('10', 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'DeepLingo', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', " . $this->id_user . ");";
        $this->database_instance->query($this->sql_insert_engine);
        $this->id_database = $this->database_instance->getConnection()->lastInsertId();


        /**
         * obtaining DeepLingo Engine
         */
        $engine_struct_DeepLingo = EnginesModel_EngineStruct::getStruct();
        $engine_struct_DeepLingo->id = $this->id_database;
        $eng_Deep_Lingo = $engine_DAO->read($engine_struct_DeepLingo);

        $this->engine_struct_param_DeepLingo = @$eng_Deep_Lingo[0];


    }

    public function tearDown()
    {
        $this->sql_delete_user = "DELETE FROM users WHERE uid=" . $this->id_user . ";";
        $this->sql_delete_engine = "DELETE FROM engines WHERE id=" . $this->id_database . ";";
        $this->database_instance->query($this->sql_delete_user);
        $this->database_instance->query($this->sql_delete_engine);
        $flusher = new Predis\Client(INIT::$REDIS_SERVERS);
        $flusher->flushdb();
        parent::tearDown();
    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_with_simple_segment_triggered_by_get()
    {

        $mock_engine = $this->getMockBuilder('\Engines_DeepLingo')->setConstructorArgs(array($this->engine_struct_param_DeepLingo))->setMethods(array('_call'))->getMock();
        $reflector = new ReflectionClass($mock_engine);
        $property = $reflector->getProperty("result");
        $property->setAccessible(true);

        $this->array_param = array('q' => "house is red", 'source' => "en", 'target' => "fr", 'key' => "gala15");
        $function_name = "translate_relative_url";

        $json_output_mock = <<<LAB
{
  "data": {
    "translations": [
      {
        "translatedText": "maison est rouge.", 
        "translatedTextRaw": "maison est rouge .", 
        "annotatedSource": "house is red", 
        "tokenization": {
          "src": [
            [
              0, 
              4
            ], 
            [
              6, 
              7
            ], 
            [
              9, 
              11
            ]
          ], 
          "tgt": [
            [
              0, 
              5
            ], 
            [
              7, 
              9
            ], 
            [
              11, 
              15
            ], 
            [
              16, 
              16
            ]
          ]
        }
      }
    ]
  }
}
LAB;
        $url_param_mock = "http://mtserver01.deeplingo.com:8019/translate?q=house+is+red&source=en&target=fr&key=gala15";

        $mock_engine->expects($this->once())->method('_call')->with($url_param_mock)->willReturn($json_output_mock);


        $mock_engine->call($function_name, $this->array_param);
        $this->assertEquals("maison est rouge.", $property->getValue($mock_engine)["translation"]);
    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_with_more_complex_segment_triggered_by_get()
    {

        $mock_engine = $this->getMockBuilder('\Engines_DeepLingo')->setConstructorArgs(array($this->engine_struct_param_DeepLingo))->setMethods(array('_call'))->getMock();

        $reflector = new ReflectionClass($mock_engine);
        $property = $reflector->getProperty("result");
        $property->setAccessible(true);

        $this->array_param = array('q' => "house is red and apple is green soup", 'source' => "en", 'target' => "fr", 'key' => "gala15");
        $function_name = "translate_relative_url";

        $json_output_mock = <<<'LAB'
{
  "data": {
    "translations": [
      {
        "translatedText": "maison est rouge et la soupe Apple est vert.", 
        "translatedTextRaw": "maison est rouge et la soupe Apple est vert .", 
        "annotatedSource": "house is red and apple is green soup", 
        "tokenization": {
          "src": [
            [
              0, 
              4
            ], 
            [
              6, 
              7
            ], 
            [
              9, 
              11
            ], 
            [
              13, 
              15
            ], 
            [
              17, 
              21
            ], 
            [
              23, 
              24
            ], 
            [
              26, 
              30
            ], 
            [
              32, 
              35
            ]
          ], 
          "tgt": [
            [
              0, 
              5
            ], 
            [
              7, 
              9
            ], 
            [
              11, 
              15
            ], 
            [
              17, 
              18
            ], 
            [
              20, 
              21
            ], 
            [
              23, 
              27
            ], 
            [
              29, 
              33
            ], 
            [
              35, 
              37
            ], 
            [
              39, 
              42
            ], 
            [
              43, 
              43
            ]
          ]
        }
      }
    ]
  }
}
LAB;

        $url_param_mock = "http://mtserver01.deeplingo.com:8019/translate?q=house+is+red+and+apple+is+green+soup&source=en&target=fr&key=gala15";

        $mock_engine->expects($this->once())->method('_call')->with($url_param_mock)->willReturn($json_output_mock);

        $mock_engine->call($function_name, $this->array_param);
        $this->assertEquals("maison est rouge et la soupe Apple est vert.", $property->getValue($mock_engine)["translation"]);
    }

}