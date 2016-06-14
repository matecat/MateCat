<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::disable
 * User: dinies
 * Date: 20/04/16
 * Time: 18.38
 */
class DisableEngineTest extends  AbstractTest
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

    protected $user_id;
    protected $engine_id;
    protected $engine_struct_param;
    protected $flusher;
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_DAO;

    public function setUp()
    {
        parent::setUp();
        $this->database_instance=Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->sql_insert_user = "INSERT INTO ".INIT::$DB_DATABASE.".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES (NULL,'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo', '');";
        $this->database_instance->query($this->sql_insert_user);
        $this->user_id= $this->database_instance->last_insert();
        $this->sql_delete_user ="DELETE FROM users WHERE uid='".$this->user_id."';";

        $this->sql_insert_engine = "INSERT INTO ".INIT::$DB_DATABASE.".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES (NULL, 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'DeepLingo', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', '".$this->user_id."');";
        $this->database_instance->query($this->sql_insert_engine);
        $this->engine_id= $this->database_instance->last_insert();
        $this->sql_delete_engine ="DELETE FROM engines WHERE id='".$this->engine_id."';";

        $this->engine_DAO= new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));

        $this->flusher= new Predis\Client(INIT::$REDIS_SERVERS);
        $this->engine_struct_param = new EnginesModel_EngineStruct();
        $this->engine_struct_param->id = $this->engine_id ;
        $this->engine_struct_param->uid = $this->user_id;

    }

    public function tearDown()
    {

        $this->database_instance->query($this->sql_delete_user);
        $this->database_instance->query($this->sql_delete_engine);
        $this->flusher->flushdb();
        parent::tearDown();

    }


    /**
     * @param EnginesModel_EngineStruct
     * It disables the struct of the engine passed as @param
     * @group regression
     * @covers EnginesModel_EngineDAO::disable
     */
    public function test_disable_the_struct_of_constructed_engine(){


        $sql_engine="SELECT active FROM ".INIT::$DB_DATABASE.".`engines` WHERE id='".$this->engine_id."' and uid='".$this->user_id."'";
        $this->database_instance->query($sql_engine)->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals(array(0 => array('active' => 1)), $this->database_instance->query($sql_engine)->fetchAll(PDO::FETCH_ASSOC));
        $this->engine_DAO->disable($this->engine_struct_param);
        $this->flusher->flushdb();
        $this->assertEquals(array(0 => array('active' => 0)), $this->database_instance->query($sql_engine)->fetchAll(PDO::FETCH_ASSOC));

    }

    /**
     * @param EnginesModel_EngineStruct
     * It fails in disabling the struct of the engine because the engine passed as @param has wrong uid
     * @group regression
     * @covers EnginesModel_EngineDAO::disable
     */
    public function test_disable_the_struct_of_engine_with_wrong_uid_avoiding_the_disable(){

        $this->engine_struct_param->uid ++;
        $this->assertNull($this->engine_DAO->disable($this->engine_struct_param));

    }
}