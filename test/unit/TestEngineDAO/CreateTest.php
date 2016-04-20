<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::create
 * User: dinies
 * Date: 14/04/16
 * Time: 20.27
 */
class CreateTest extends AbstractTest
{

    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_Dao;
    protected $engine_struct_param;
    protected $sql_delete_engine;
    protected $database_instance;

    public function setUp()
    {
        $this->engine_Dao = new EnginesModel_EngineDAO(Database::obtain());

        $this->engine_struct_param = new EnginesModel_EngineStruct();
        $this->database_instance = Database::obtain();
    }


    public function tearDown()
    {
        $this->sql_delete_engine = "DELETE FROM engines WHERE id='" . $this->engine_struct_param->id . "';";
        $this->database_instance->query($this->sql_delete_engine);
        require_once 'Predis/autoload.php';
        $flusher = new Predis\Client(INIT::$REDIS_SERVERS);
        $flusher->flushdb();
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::create
     */
    public function test_create_with_success()
    {

        $this->engine_struct_param->id = <<<LABEL
2
LABEL;
        $this->engine_struct_param->name = <<<LABEL
Moses_bar_and_foo
LABEL;
        $this->engine_struct_param->description = <<<LABEL
Machine translation from bar and foo.
LABEL;
        $this->engine_struct_param->type = <<<LABEL
TM
LABEL;
        $this->engine_struct_param->base_url = <<<LABEL
http://mtserver01.deepfoobar.com:8019
LABEL;
        $this->engine_struct_param->translate_relative_url = <<<LABEL
translate
LABEL;
        $this->engine_struct_param->contribute_relative_url = <<<LABEL
NULL
LABEL;
        $this->engine_struct_param->delete_relative_url = <<<LABEL
NULL
LABEL;
        $this->engine_struct_param->others = <<<'LABEL'
{}
LABEL;
        $this->engine_struct_param->class_load = <<<LABEL
foo_bar
LABEL;
        $this->engine_struct_param->extra_parameters = <<<'LABEL'
{}
LABEL;
        $this->engine_struct_param->penalty = <<<LABEL
1
LABEL;
        $this->engine_struct_param->active = <<<LABEL
0
LABEL;
        $this->engine_struct_param->uid = <<<LABEL
1
LABEL;

        $this->assertEquals($this->engine_struct_param, $this->engine_Dao->create($this->engine_struct_param));
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