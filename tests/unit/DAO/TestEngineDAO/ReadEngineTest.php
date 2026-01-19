<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Predis\Client;
use TestHelpers\AbstractTest;
use Utils\Engines\NONE;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers EngineDAO::read
 * User: dinies
 * Date: 15/04/16
 * Time: 15.56
 */
class ReadEngineTest extends AbstractTest
{

    protected $engine_struct_simple;
    /**
     * @var Client
     */
    protected $flusher;
    /**
     * @var EngineDAO
     */
    protected $engine_Dao;
    protected $engine_struct_param;
    protected $sql_delete_engine;
    protected $sql_select_engine;
    protected $id;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $actual;

    public function setUp(): void
    {
        parent::setUp();
        $this->database_instance = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
        $this->engine_Dao = new EngineDAO($this->database_instance);
        $this->engine_struct_param = new EngineStruct();

        $this->engine_struct_param->name = "Moses_bar_and_foo";
        $this->engine_struct_param->description = "Machine translation from bar and foo.";
        $this->engine_struct_param->type = "TM";
        $this->engine_struct_param->base_url = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = "contribute";
        $this->engine_struct_param->delete_relative_url = "delete";
        $this->engine_struct_param->others = [];
        $this->engine_struct_param->class_load = "MMT";
        $this->engine_struct_param->extra_parameters = [];
        $this->engine_struct_param->penalty = 1;
        $this->engine_struct_param->active = 4;
        $this->engine_struct_param->uid = 1;

        $this->actual = $this->engine_Dao->create($this->engine_struct_param);
        $this->id = $this->getTheLastInsertIdByQuery($this->database_instance);
        $this->sql_select_engine = "SELECT * FROM " . AppConfig::$DB_DATABASE . ".`engines` WHERE id='" . $this->id . "';";
        $this->sql_delete_engine = "DELETE FROM " . AppConfig::$DB_DATABASE . ".`engines` WHERE id='" . $this->id . "';";
    }

    /**
     * It reads a struct of an engine and returns an array of engine's properties
     * @throws Exception
     * @group  regression
     * @covers EngineDAO::read
     */
    public function test_read_simple_engine_already_present_in_database()
    {
        $this->engine_struct_simple = new EngineStruct();

        $this->engine_struct_simple->id = 0;
        $this->engine_struct_simple->name = "NONE";
        $this->engine_struct_simple->description = "No MT";
        $this->engine_struct_simple->type = "NONE";
        $this->engine_struct_simple->base_url = "";
        $this->engine_struct_simple->translate_relative_url = "";
        $this->engine_struct_simple->contribute_relative_url = null;
        $this->engine_struct_simple->delete_relative_url = null;
        $this->engine_struct_simple->others = [];
        $this->engine_struct_simple->class_load = NONE::class;
        $this->engine_struct_simple->extra_parameters = [];
        $this->engine_struct_simple->google_api_compliant_version = null;
        $this->engine_struct_simple->penalty = "100";
        $this->engine_struct_simple->active = "0";
        $this->engine_struct_simple->uid = null;

        $this->assertEquals([clone $this->engine_struct_simple], $this->engine_Dao->read($this->engine_struct_simple));
    }


    /**
     *
     * It reads a struct of an engine and returns an array engine's properties
     * @group  regression
     * @covers EngineDAO::read
     */
    public function test_read_engine_just_created_in_database()
    {
        $this->engine_Dao = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));

        $wrapped_result = $this->engine_Dao->read($this->engine_struct_param);

        $result = $wrapped_result['0'];
        $this->assertEquals($this->id, $result['id']);
        $this->assertEquals("Moses_bar_and_foo", $result['name']);
        $this->assertEquals("Machine translation from bar and foo.", $result['description']);
        $this->assertEquals("TM", $result['type']);
        $this->assertEquals("http://mtserver01.deepfoobar.com:8019", $result['base_url']);
        $this->assertEquals("translate", $result['translate_relative_url']);
        $this->assertEquals("contribute", $result['contribute_relative_url']);
        $this->assertEquals("translate", $result['translate_relative_url']);
        $this->assertEquals("delete", $result['delete_relative_url']);
        $this->assertEquals([], $result['others']);
        $this->assertEquals("MMT", $result['class_load']);
        $this->assertEquals([], $result['extra_parameters']);
        $this->assertEquals(1, $result['penalty']);
        $this->assertEquals(4, $result['active']);
        $this->assertEquals(1, $result['uid']);
    }
}