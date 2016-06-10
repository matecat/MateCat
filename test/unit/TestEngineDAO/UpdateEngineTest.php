<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::update
 * User: dinies
 * Date: 20/04/16
 * Time: 15.23
 */
class UpdateEngineTest extends AbstractTest
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
    /**
     * @var EnginesModel_EngineStruct
     */
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
     * It updates the struct of an engine checking the righteousness through the field 'name'.
     * @group regression
     * @covers EnginesModel_EngineDAO::update
     */
    public function test_update_the_struct_of_constructed_engine_check_by_name(){


        $this->engine_struct_param->name = "NONE";
        $this->engine_struct_param->description = "No MT";
        $this->engine_struct_param->type = "NONE";
        $this->engine_struct_param->base_url = "";
        $this->engine_struct_param->translate_relative_url = "";
        $this->engine_struct_param->contribute_relative_url = NULL;
        $this->engine_struct_param->delete_relative_url= NULL;
        $this->engine_struct_param->others = array();
        $this->engine_struct_param->class_load = "NONE";
        $this->engine_struct_param->extra_parameters = NULL;
        $this->engine_struct_param->google_api_compliant_version=NULL;
        $this->engine_struct_param->penalty = "100";
        $this->engine_struct_param->active = "0";

        $sql_engine="SELECT name FROM ".INIT::$DB_DATABASE.".`engines` WHERE id='".$this->engine_id."' and uid='".$this->user_id."'";
        $this->database_instance->query($sql_engine)->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals(array(0 => array('name' => "DeepLingo En/Fr iwslt")), $this->database_instance->query($sql_engine)->fetchAll(PDO::FETCH_ASSOC));
        $this->engine_DAO->update($this->engine_struct_param);
        $this->flusher->flushdb();
        $this->assertEquals(array(0 => array('name' => "NONE")), $this->database_instance->query($sql_engine)->fetchAll(PDO::FETCH_ASSOC));

    }


  /**
   * It doesn't update the struct of an engine because the @param has wrong 'uid'.
   * @group regression
   * @covers EnginesModel_EngineDAO::update
   */
  public function test_update_the_struct_of_engine_with_wrong_uid_avoiding_any_update(){


      $this->engine_struct_param->uid ++;
      $this->assertNull($this->engine_DAO->update($this->engine_struct_param));

  }



}