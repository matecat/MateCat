<?php

use Model\Database;
use TestHelpers\AbstractTest;
use Utils\Engines\Apertium;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\Engines\NONE;


/**
 * @group  regression
 * @covers EnginesFactory::getInstance
 * User: dinies
 * Date: 14/04/16
 * Time: 17.45
 */
class GetInstanceTest extends AbstractTest {
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

    public function setUp(): void {

        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        /**
         * user insertion
         */
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES ( '" . uniqid( '', true ) . "bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo' );";
        $this->database_instance->getConnection()->query( $this->sql_insert_user );
        $this->id_user = $this->database_instance->getConnection()->lastInsertId();

        /**
         * engine insertion
         */
        $this->sql_insert_engine = "INSERT INTO " . INIT::$DB_DATABASE . ".`engines` (`name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ( 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo EnginesFactory', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'Apertium', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', " . $this->id_user . ");";
        $this->database_instance->getConnection()->query( $this->sql_insert_engine );
        $this->id_database = $this->database_instance->getConnection()->lastInsertId();


        $this->sql_delete_user   = "DELETE FROM users WHERE uid=" . $this->id_user . ";";
        $this->sql_delete_engine = "DELETE FROM engines WHERE id=" . $this->id_database . ";";
    }

    public function tearDown(): void {

        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->database_instance->getConnection()->query( $this->sql_delete_engine );
        $flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $flusher->select( INIT::$INSTANCE_ID );
        $flusher->flushdb();
        parent::tearDown();

    }

    /**
     * @throws Exception
     * @group  regression
     * @covers EnginesFactory::getInstance
     */
    public function test_getInstance_of_constructed_engine() {

        $engine = EnginesFactory::getInstance( $this->id_database );
        $this->assertTrue( $engine instanceof Apertium );
    }

    /**
     * @throws Exception
     * @group  regression
     * @covers EnginesFactory::getInstance
     */
    public function test_getInstance_of_constructed_engine_my_memory() {

        $engine = EnginesFactory::getInstance( 1 );
        $this->assertTrue( $engine instanceof MyMemory );
    }

    /**
     * @throws Exception
     * @group  regression
     * @covers EnginesFactory::getInstance
     */
    public function test_getInstance_of_default_engine() {
        $engine = EnginesFactory::getInstance( 0 );
        $this->assertTrue( $engine instanceof NONE );

    }

    /**
     * @throws Exception
     * @group  regression
     * @covers EnginesFactory::getInstance
     */
    public function test_getInstance_without_id() {

        $this->expectException( Exception::class );
        EnginesFactory::getInstance( '' );
    }

    /**
     * @throws Exception
     * @group  regression
     * @covers EnginesFactory::getInstance
     */
    public function test_getInstance_whit_null_id() {

        $this->expectException( Exception::class );
        EnginesFactory::getInstance( null );
    }

    /**
     * @throws Exception
     * @group  regression
     * @covers EnginesFactory::getInstance
     */
    public function test_getInstance_with_no_mach_for_engine_id() {

        $this->expectException( Exception::class );
        EnginesFactory::getInstance( $this->id_database + 1 );
    }

    /**
     * verify that the method  name of engine not match the classes of known engines
     * @group  regression
     * @covers EnginesFactory::getInstance
     * @throws Exception
     */
    public function test_getInstance_with_no_mach_for_engine_class_name() {

        $sql_update_engine_class_name = "UPDATE `engines` SET class_load='YourMemory' WHERE id=" . $this->id_database . ";";

        $this->database_instance->getConnection()->query( $sql_update_engine_class_name );
        $flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $flusher->select( INIT::$INSTANCE_ID );
        $flusher->flushdb();

        $engine = EnginesFactory::getInstance( $this->id_database );
        $this->assertTrue( $engine instanceof NONE );
    }


}