<?php

use Model\Engines\EngineDAO;
use Model\Engines\EngineStruct;
use Predis\Client;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers EngineDAO::create
 * User: dinies
 * Date: 14/04/16
 * Time: 20.27
 */
class CreateEngineTest extends AbstractTest {

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

    public function setUp(): void {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );

        $this->database_instance->getConnection()->query( "DELETE FROM engines WHERE id > 2" );

        $this->engine_Dao          = new EngineDAO( $this->database_instance );
        $this->engine_struct_param = new EngineStruct();

        $this->engine_struct_param->name                    = "Moses_bar_and_foo";
        $this->engine_struct_param->description             = "Machine translation from bar and foo.";
        $this->engine_struct_param->type                    = "TM";
        $this->engine_struct_param->base_url                = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url  = "translate";
        $this->engine_struct_param->update_relative_url     = "";
        $this->engine_struct_param->contribute_relative_url = '';
        $this->engine_struct_param->delete_relative_url     = '';
        $this->engine_struct_param->others                  = '{}';
        $this->engine_struct_param->class_load              = "foo_bar";
        $this->engine_struct_param->extra_parameters        = '{}';
        $this->engine_struct_param->penalty                 = 1;
        $this->engine_struct_param->active                  = 1;
        $this->engine_struct_param->uid                     = 1;

    }


    public function tearDown(): void {

        $this->database_instance->getConnection()->query( $this->sql_delete_engine );
        $this->flusher = new Client( INIT::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * This test builds an engine object from an array that describes the properties
     * @group  regression
     * @covers EngineDAO::create
     */
    public function test_create_with_success() {

        $this->actual            = $this->engine_Dao->create( $this->engine_struct_param );
        $this->id                = $this->database_instance->last_insert();
        $this->sql_select_engine = "SELECT * FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->id . "';";
        $this->sql_delete_engine = "DELETE FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->id . "';";

        $this->assertEquals( $this->engine_struct_param, $this->actual );

        $wrapped_result = $this->database_instance->getConnection()->query( $this->sql_select_engine )->fetchAll( PDO::FETCH_ASSOC );
        $result         = $wrapped_result[ '0' ];
        $this->assertCount( 16, $result );
        $this->assertEquals( $this->id, $result[ 'id' ] );
        $this->assertEquals( "Moses_bar_and_foo", $result[ 'name' ] );
        $this->assertEquals( "TM", $result[ 'type' ] );
        $this->assertEquals( "Machine translation from bar and foo.", $result[ 'description' ] );
        $this->assertEquals( "http://mtserver01.deepfoobar.com:8019", $result[ 'base_url' ] );
        $this->assertEquals( "translate", $result[ 'translate_relative_url' ] );
        $this->assertEmpty( $result[ 'contribute_relative_url' ] );
        $this->assertEmpty( $result[ 'update_relative_url' ] );
        $this->assertEmpty( $result[ 'delete_relative_url' ] );
        $this->assertEquals( "{}", $result[ 'others' ] );
        $this->assertEquals( "foo_bar", $result[ 'class_load' ] );
        $this->assertEquals( "{}", $result[ 'extra_parameters' ] );
        $this->assertEquals( "2", $result[ 'google_api_compliant_version' ] );
        $this->assertEquals( "1", $result[ 'penalty' ] );
        $this->assertEquals( "1", $result[ 'active' ] );
        $this->assertEquals( "1", $result[ 'uid' ] );

    }

}