<?php

/**
 * @group regression
 * @covers Engine::createTempInstance
 * User: dinies
 * Date: 20/04/16
 * Time: 18.49
 */
class CreateTempInstanceTest extends AbstractTest
{
    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $sql_insert_user;
    protected $sql_insert_engine;
    protected $sql_delete_user;
    protected $sql_delete_engine;
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    public function setUp()
    {

        parent::setUp();
        $this->database_instance=Database::obtain();
        $this->sql_insert_user = "INSERT INTO ".INIT::$DB_DATABASE.".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES ('44', 'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo', '');";
        $this->sql_insert_engine = "INSERT INTO ".INIT::$DB_DATABASE.".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('10', 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'DeepLingo', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', '44');";
        $this->database_instance->query($this->sql_insert_user);
        $this->database_instance->query($this->sql_insert_engine);
        $this->sql_delete_user ="DELETE FROM users WHERE uid='44';";
        $this->sql_delete_engine ="DELETE FROM engines WHERE id='10';";

        $this->engine_struct_param = new EnginesModel_EngineStruct();

    }

    public function tearDown()
    {

        $this->database_instance->query($this->sql_delete_user);
        $this->database_instance->query($this->sql_delete_engine);
        $flusher= new Predis\Client(INIT::$REDIS_SERVERS);
        $flusher->flushdb();
        parent::tearDown();

    }

    /**
     * It checks if the creation of an istance of engine will be successfully created when it invokes the method.
     * @param EnginesModel_EngineStruct
     * @group regression
     * @covers Engine::createTempInstance
     */
    public function test_createTempInstance_of_constructed_engine(){
        
        
        $this->engine_struct_param->id = 10 ;
        $this->engine_struct_param->name = "DeepLingo En/Fr iwslt";
        $this->engine_struct_param->type = "MT";
        $this->engine_struct_param->description = "DeepLingo Engine";
        $this->engine_struct_param->base_url = "http://mtserver01.deeplingo.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = NULL;
        $this->engine_struct_param->delete_relative_url= NULL;
        $this->engine_struct_param->others='{}';
        $this->engine_struct_param->class_load = "DeepLingo";
        $this->engine_struct_param->extra_parameters = '{"client_secret":"gala15 "}';
        $this->engine_struct_param->google_api_compliant_version="2";
        $this->engine_struct_param->penalty = "14";
        $this->engine_struct_param->active = "1";
        $this->engine_struct_param->uid = 44;

        $engine = Engine::createTempInstance($this->engine_struct_param);
        $this->assertTrue($engine instanceof Engines_DeepLingo);
    }
}