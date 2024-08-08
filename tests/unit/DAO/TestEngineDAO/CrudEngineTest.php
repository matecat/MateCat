<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers EnginesModel_EngineDAO::delete
 * User: dinies
 * Date: 20/04/16
 * Time: 17.43
 */
class CrudEngineTest extends AbstractTest {
    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $database_instance;

    protected $user_id;
    protected $engine_id;
    protected $engine_struct_param;
    protected $flusher;
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_DAO;

    public function setUp() {
        parent::setUp();

        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->database_instance->getConnection()->query( "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE email='bar@foo.net'" );
        $this->database_instance->getConnection()->query( "DELETE FROM engines WHERE id > 1" );

        $sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL,'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo' );";
        $this->database_instance->getConnection()->query( $sql_insert_user );
        $this->user_id = $this->database_instance->last_insert();


        $sql_insert_engine = "INSERT INTO " . INIT::$DB_DATABASE . ".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES (NULL, 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'DeepLingo', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', '" . $this->user_id . "');";
        $this->database_instance->getConnection()->query( $sql_insert_engine );
        $this->engine_id = $this->database_instance->last_insert();


        $this->engine_DAO = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );

        $this->flusher                  = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->engine_struct_param      = new EnginesModel_EngineStruct();
        $this->engine_struct_param->id  = $this->engine_id;
        $this->engine_struct_param->uid = $this->user_id;

    }

    public function tearDown() {

        $this->database_instance->getConnection()->query( "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE email='bar@foo.net'" );
        $this->database_instance->getConnection()->query( "DELETE FROM engines WHERE id > 1" );
        $this->flusher->flushdb();
        parent::tearDown();

    }


    /**
     * This test delete the struct of an engine from the database that corresponds
     * to the artificially constructed engine passed as @param.
     * @group  regression
     * @covers EnginesModel_EngineDAO::delete
     */
    public function test_delete_the_struct_of_constructed_engine() {


        $sql_engine = "SELECT name FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->engine_id . "' and uid='" . $this->user_id . "'";
        $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC );
        $this->assertEquals( [ 0 => [ 'name' => "DeepLingo En/Fr iwslt" ] ], $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC ) );
        $this->engine_DAO->delete( $this->engine_struct_param );
        $this->flusher->flushdb();
        $this->assertEquals( [], $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC ) );

    }

    /**
     * This test doesn't delete the struct of an engine from the database that corresponds
     * to the artificially constructed engine passed as @param.
     * @group  regression
     * @covers EnginesModel_EngineDAO::delete
     */
    public function test_delete_the_struct_of_engine_with_wrong_uid_avoiding_the_delete() {

        $this->engine_struct_param->uid++;
        $this->assertNull( $this->engine_DAO->delete( $this->engine_struct_param ) );

    }

    /**
     * @param EnginesModel_EngineStruct
     * It disables the struct of the engine passed as @param
     *
     * @group  regression
     * @covers EnginesModel_EngineDAO::disable
     */
    public function test_disable_the_struct_of_constructed_engine() {


        $sql_engine = "SELECT active FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->engine_id . "' and uid='" . $this->user_id . "'";
        $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC );
        $this->assertEquals( [ 0 => [ 'active' => 1 ] ], $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC ) );
        $this->engine_DAO->disable( $this->engine_struct_param );
        $this->flusher->flushdb();
        $this->assertEquals( [ 0 => [ 'active' => 0 ] ], $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC ) );

    }

    /**
     * @param EnginesModel_EngineStruct
     * It fails in disabling the struct of the engine because the engine passed as @param has wrong uid
     *
     * @group  regression
     * @covers EnginesModel_EngineDAO::disable
     */
    public function test_disable_the_struct_of_engine_with_wrong_uid_avoiding_the_disable() {

        $this->engine_struct_param->uid++;
        $this->assertNull( $this->engine_DAO->disable( $this->engine_struct_param ) );

    }

    /**
     * It updates the struct of an engine checking the righteousness through the field 'name'.
     * @group  regression
     * @covers EnginesModel_EngineDAO::updateByStruct
     */
    public function test_update_the_struct_of_constructed_engine_check_by_name() {

        $this->engine_struct_param->name                         = "NONE";
        $this->engine_struct_param->description                  = "No MT";
        $this->engine_struct_param->type                         = "NONE";
        $this->engine_struct_param->base_url                     = "";
        $this->engine_struct_param->translate_relative_url       = "";
        $this->engine_struct_param->contribute_relative_url      = null;
        $this->engine_struct_param->delete_relative_url          = null;
        $this->engine_struct_param->others                       = null;
        $this->engine_struct_param->class_load                   = "NONE";
        $this->engine_struct_param->extra_parameters             = null;
        $this->engine_struct_param->google_api_compliant_version = null;
        $this->engine_struct_param->penalty                      = "100";
        $this->engine_struct_param->active                       = "0";

        $sql_engine = "SELECT name FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->engine_id . "' and uid='" . $this->user_id . "'";

        //perform a query to set result in cache
//        $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC );

        //getResult from cache
        $this->assertEquals( [ 0 => [ 'name' => "DeepLingo En/Fr iwslt" ] ], $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC ) );

        //change the real record
        $this->engine_DAO->updateByStruct( $this->engine_struct_param );

        $this->assertEquals( [ 0 => [ 'name' => "NONE" ] ], $this->database_instance->getConnection()->query( $sql_engine )->fetchAll( PDO::FETCH_ASSOC ) );

    }


    /**
     * It doesn't update the struct of an engine because the
     * @throws Exception @group regression
     * @covers EnginesModel_EngineDAO::updateFields
     */
    public function test_update_the_struct_of_engine_with_wrong_uid_avoiding_any_update() {

        $this->engine_struct_param->uid++;
        $this->engine_DAO->updateFields( $this->engine_struct_param->toArray(), [
                'id'  => $this->engine_struct_param,
                'uid' => $this->engine_struct_param->uid
        ] );

        //update on the same object is null
        $this->assertEquals( 0, $this->engine_DAO->updateFields( $this->engine_struct_param->toArray(), [
                'id'  => $this->engine_struct_param,
                'uid' => $this->engine_struct_param->uid
        ] ) );

        $sql_engine = "SELECT * FROM " . INIT::$DB_DATABASE . ".`engines` WHERE id='" . $this->engine_id . "' and uid='" . $this->user_id . "'";

        $engine = $this->database_instance->getConnection()->query( $sql_engine )->fetch( PDO::FETCH_ASSOC );
        $this->assertNotNull( $engine );

        //assert that UID is never updated
        $this->assertEquals( $this->engine_struct_param->uid - 1, $engine[ 'uid' ] );


    }


}