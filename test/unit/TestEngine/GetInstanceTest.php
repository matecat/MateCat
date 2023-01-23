<?php

/**
 * @group  regression
 * @covers Engine::getInstance
 * User: dinies
 * Date: 14/04/16
 * Time: 17.45
 */
class GetInstanceTest extends AbstractTest {
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

    protected $id_user;
    protected $id_database;

    public function setUp() {

        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        /**
         * user insertion
         */
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES ('100044', 'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo' );";
        $this->database_instance->getConnection()->query( $this->sql_insert_user );
        $this->id_user = $this->database_instance->getConnection()->lastInsertId();

        /**
         * engine insertion
         */
        $this->sql_insert_engine = "INSERT INTO " . INIT::$DB_DATABASE . ".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('10', 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'Apertium', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', " . $this->id_user . ");";
        $this->database_instance->getConnection()->query( $this->sql_insert_engine );
        $this->id_database = $this->database_instance->getConnection()->lastInsertId();


        $this->sql_delete_user   = "DELETE FROM users WHERE uid=" . $this->id_user . ";";
        $this->sql_delete_engine = "DELETE FROM engines WHERE id=" . $this->id_database . ";";
    }

    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->database_instance->getConnection()->query( $this->sql_delete_engine );
        $flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $flusher->select( INIT::$INSTANCE_ID );
        $flusher->flushdb();
        parent::tearDown();

    }

    /**
     * @param id of the engine previously constructed
     *
     * @group  regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_of_constructed_engine() {

        $engine = Engine::getInstance( $this->id_database );
        $this->assertTrue( $engine instanceof Engines_Apertium );
    }

    /**
     * @param id of the engine previously constructed
     *
     * @group  regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_of_constructed_engine_my_memory() {

        $engine = Engine::getInstance( 1 );
        $this->assertTrue( $engine instanceof Engines_MyMemory );
    }

    /**
     * @param id of the engine previously constructed
     *
     * @group  regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_of_default_engine() {
        $engine = Engine::getInstance( 0 );
        $this->assertTrue( $engine instanceof Engines_NONE );

    }

    /**
     * @param  ''
     *
     * @group  regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_without_id() {

        $this->setExpectedException( 'Exception' );
        Engine::getInstance( '' );
    }

    /**
     * @param null
     *
     * @group  regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_whit_null_id() {

        $this->setExpectedException( 'Exception' );
        Engine::getInstance( null );
    }

    /**
     * @param  99
     *
     * @group  regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_with_no_mach_for_engine_id() {

        $this->setExpectedException( 'Exception' );
        Engine::getInstance( $this->id_database + 1 );
    }

    /**
     * verify that the method  name of engine not match the classes of known engines
     * @group  regression
     * @covers Engine::getInstance
     */
    public function test_getInstance_with_no_mach_for_engine_class_name() {

        $sql_update_engine_class_name = "UPDATE `engines` SET class_load='YourMemory' WHERE id=" . $this->id_database . ";";

        $this->database_instance->getConnection()->query( $sql_update_engine_class_name );
        $flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $flusher->select( INIT::$INSTANCE_ID );
        $flusher->flushdb();

        $engine = Engine::getInstance( $this->id_database );
        $this->assertTrue( $engine instanceof Engines_NONE );
    }


}