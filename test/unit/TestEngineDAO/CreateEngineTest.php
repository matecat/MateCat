<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::create
 * User: dinies
 * Date: 14/04/16
 * Time: 20.27
 */
class CreateEngineTest extends AbstractTest
{

    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_Dao;
    protected $engine_struct_param;
    protected $sql_delete_engine;
    protected $sql_select_engine;
    /**
     * @var Database
     */
    protected $database_instance;
protected $actual;

    public function setUp()
    {
        parent::setUp();
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->engine_Dao = new EnginesModel_EngineDAO($this->database_instance);
        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->name = "Moses_bar_and_foo";
        $this->engine_struct_param->description = "Machine translation from bar and foo.";
        $this->engine_struct_param->type = "TM";
        $this->engine_struct_param->base_url = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = NULL;
        $this->engine_struct_param->delete_relative_url = NULL;
        $this->engine_struct_param->others = "{}";
        $this->engine_struct_param->class_load = "foo_bar";
        $this->engine_struct_param->extra_parameters ="{}";
        $this->engine_struct_param->penalty = 1;
        $this->engine_struct_param->active = 0;
        $this->engine_struct_param->uid = 1;

        $this->actual = $this->engine_Dao->create($this->engine_struct_param);
        $id=$this->database_instance->last_insert();
        $this->sql_select_engine = "SELECT base_url FROM engines WHERE id='" . $id . "';";
        $this->sql_delete_engine = "DELETE FROM engines WHERE id='" . $id . "';";
    }


    public function tearDown()
    {

        $this->database_instance->query($this->sql_delete_engine);
        $this->flusher = new Predis\Client(INIT::$REDIS_SERVERS);
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * This test builds an engine object from an array that describes the properties
     * @group regression
     * @covers EnginesModel_EngineDAO::create
     */
    public function test_create_with_success()
    {


        $this->assertEquals($this->engine_struct_param, $this->actual);

        $result=$this->database_instance->query($this->sql_select_engine)->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals(array(0 => array("base_url" => "http://mtserver01.deepfoobar.com:8019")), $result);
    }


 //   /**
 //    * @group regression
 //    * @covers EnginesModel_EngineDAO::create
 //    */
 //   public function test_create_with_no_success()
 //   {
 //       //   TODO:    fare fallire la creazione nel database per coprire il ramo dell'if
 //       $snapshot_DB = INIT::$DB_DATABASE;
 //       INIT::$DB_DATABASE = NULL;
 //       $this->assertNull($this->engine_Dao->create($this->engine_struct_param));
 //       INIT::$DB_DATABASE = $snapshot_DB;
 //   }

}