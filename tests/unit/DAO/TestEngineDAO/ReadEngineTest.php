<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers EnginesModel_EngineDAO::read
 * User: dinies
 * Date: 15/04/16
 * Time: 15.56
 */
class ReadEngineTest extends AbstractTest {

    protected $engine_struct_simple;
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
    protected $id;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $actual;

    public function setUp() {
        parent::setUp();
        $this->database_instance   = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->engine_Dao          = new EnginesModel_EngineDAO( $this->database_instance );
        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->name                    = "Moses_bar_and_foo";
        $this->engine_struct_param->description             = "Machine translation from bar and foo.";
        $this->engine_struct_param->type                    = "TM";
        $this->engine_struct_param->base_url                = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url  = "translate";
        $this->engine_struct_param->contribute_relative_url = "contribute";
        $this->engine_struct_param->delete_relative_url     = "delete";
        $this->engine_struct_param->others                  = "{}";
        $this->engine_struct_param->class_load              = "MMT";
        $this->engine_struct_param->extra_parameters        = "{}";
        $this->engine_struct_param->penalty                 = 1;
        $this->engine_struct_param->active                  = 4;
        $this->engine_struct_param->uid                     = 1;

        $this->actual            = $this->engine_Dao->create( $this->engine_struct_param );
        $this->id                = $this->getTheLastInsertIdByQuery( $this->database_instance );
        $this->sql_select_engine = "SELECT * FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->id . "';";
        $this->sql_delete_engine = "DELETE FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->id . "';";
    }

    /**
     * @param EnginesModel_EngineStruct
     *
     * @return array
     * It reads a struct of an engine and @return an array of properties of the engine
     * @group  regression
     * @covers EnginesModel_EngineDAO::read
     */
    public function test_read_simple_engine_already_present_in_database() {

        $this->engine_struct_simple = new EnginesModel_EngineStruct();

        $this->engine_struct_simple->id                           = 0;
        $this->engine_struct_simple->name                         = "NONE";
        $this->engine_struct_simple->description                  = "No MT";
        $this->engine_struct_simple->type                         = "NONE";
        $this->engine_struct_simple->base_url                     = "";
        $this->engine_struct_simple->translate_relative_url       = "";
        $this->engine_struct_simple->contribute_relative_url      = "";
        $this->engine_struct_simple->delete_relative_url          = "";
        $this->engine_struct_simple->others                       = [];
        $this->engine_struct_simple->class_load                   = "NONE";
        $this->engine_struct_simple->extra_parameters             = null;
        $this->engine_struct_simple->google_api_compliant_version = null;
        $this->engine_struct_simple->penalty                      = "100";
        $this->engine_struct_simple->active                       = "0";
        $this->engine_struct_simple->uid                          = null;

        $array                      = [
                'id'                           => 0,
                'name'                         => "NONE",
                'type'                         => "NONE",
                'description'                  => "No MT",
                'base_url'                     => "",
                'translate_relative_url'       => "",
                'contribute_relative_url'      => "",
                'delete_relative_url'          => "",
                'others'                       => [],
                'class_load'                   => "NONE",
                'extra_parameters'             => [],
                'google_api_compliant_version' => "",
                'penalty'                      => "100",
                'active'                       => "0",
                'uid'                          => ""
        ];
        $expected_engine_obj_output = new EnginesModel_NONEStruct( $array );


        $this->assertEquals( [ $expected_engine_obj_output ], $this->engine_Dao->read( $this->engine_struct_simple ) );
    }


    /**
     * @param EnginesModel_EngineStruct
     *
     * @return array
     * It reads a struct of an engine and @return an array of properties of the engine
     * @group  regression
     * @covers EnginesModel_EngineDAO::read
     */
    public function test_read_engine_just_created_in_database() {

        $this->engine_Dao = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );

        $wrapped_result = $this->engine_Dao->read( $this->engine_struct_param );

        $result = $wrapped_result[ '0' ];
        $this->assertEquals( $this->id, $result[ 'id' ] );
        $this->assertEquals( "Moses_bar_and_foo", $result[ 'name' ] );
        $this->assertEquals( "Machine translation from bar and foo.", $result[ 'description' ] );
        $this->assertEquals( "TM", $result[ 'type' ] );
        $this->assertEquals( "http://mtserver01.deepfoobar.com:8019", $result[ 'base_url' ] );
        $this->assertEquals( "translate", $result[ 'translate_relative_url' ] );
        $this->assertEquals( "contribute", $result[ 'contribute_relative_url' ] );
        $this->assertEquals( "translate", $result[ 'translate_relative_url' ] );
        $this->assertEquals( "delete", $result[ 'delete_relative_url' ] );
        $this->assertEquals( [], $result[ 'others' ] );
        $this->assertEquals( "MMT", $result[ 'class_load' ] );
        $this->assertEquals( [], $result[ 'extra_parameters' ] );
        $this->assertEquals( 1, $result[ 'penalty' ] );
        $this->assertEquals( 4, $result[ 'active' ] );
        $this->assertEquals( 1, $result[ 'uid' ] );
    }
}