<?php

use TestHelpers\AbstractTest;


/**
 * @group   regression
 * @covers  Engines_AbstractEngine::_call
 * User: dinies
 * Date: 22/04/16
 * Time: 14.40
 */
class CallprotectedfunctionTest extends AbstractTest {
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var array
     */
    protected $method;
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
     * @var Engines_Moses
     */
    protected $mock_engine;

    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        /**
         * user insertion
         */
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES ('100044', 'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo');";
        $this->database_instance->getConnection()->query( $this->sql_insert_user );
        $this->id_user = $this->database_instance->getConnection()->lastInsertId();

        /**
         * engine insertion
         */
        $this->sql_insert_engine = "INSERT INTO " . INIT::$DB_DATABASE . ".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('10', 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'DeepLingo', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', " . $this->id_user . ");";
        $this->database_instance->getConnection()->query( $this->sql_insert_engine );
        $this->id_database = $this->database_instance->getConnection()->lastInsertId();


        $this->sql_delete_user   = "DELETE FROM users WHERE uid=" . $this->id_user . ";";
        $this->sql_delete_engine = "DELETE FROM engines WHERE id=" . $this->id_database . ";";

        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = $this->id_database;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = @$eng[ 0 ];
        $this->mock_engine         = $this->getMockBuilder( '\Engines_DeepLingo' )->setMethods( [ '_call' ] )->getMock();

    }

    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->database_instance->getConnection()->query( $this->sql_delete_engine );
        $flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $flusher->flushdb();
        parent::tearDown();

    }

    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::_call
     */
    public function test__call_using_mock_object_and_simple_segment() {

        $reflector    = new ReflectionClass( $this->mock_engine );
        $this->method = $reflector->getMethod( "_call" );
        $this->method->setAccessible( true );


        $url = "http://mtserver01.deeplingo.com:8019/translate?q=house+is+red&source=en&target=fr&key=gala15";

        $json_output = <<<LAB
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

        $curl_opt = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => 10
        ];

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
        $url_param_mock   = "http://mtserver01.deeplingo.com:8019/translate?q=house+is+red&source=en&target=fr&key=gala15";

        $this->mock_engine->expects( $this->once() )->method( '_call' )->with( $url_param_mock, $curl_opt )->willReturn( $json_output_mock );


        $this->assertEquals( $json_output, $this->method->invoke( $this->mock_engine, $url, $curl_opt ) );
    }

    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::_call
     */
    public function test__call_using_mock_object_and_long_segment() {

        $reflector    = new ReflectionClass( $this->mock_engine );
        $this->method = $reflector->getMethod( "_call" );
        $this->method->setAccessible( true );


        $json_output = <<<'LAB'
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

        $url = "http://mtserver01.deeplingo.com:8019/translate?q=house+is+red+and+apple+is+green+soup&source=en&target=fr&key=gala15";

        $curl_opt = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => 10
        ];


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


        $this->mock_engine->expects( $this->once() )->method( '_call' )->with( $url_param_mock, $curl_opt )->willReturn( $json_output_mock );


        $this->assertEquals( $json_output, $this->method->invoke( $this->mock_engine, $url, $curl_opt ) );
    }

}