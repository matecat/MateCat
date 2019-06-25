<?php

/**
 * @group regression
 * @covers  Engines_Moses::__construct
 * User: dinies
 * Date: 22/04/16
 * Time: 9.46
 */
class ConstructorMosesTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;
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

    public function setUp()
    {

        parent::setUp();
        $this->database_instance=Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        /**
         * user insertion
         */
        $this->sql_insert_user = "INSERT INTO ".INIT::$DB_DATABASE.".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, 'bar@foo.net', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo');";
        $this->database_instance->getConnection()->query($this->sql_insert_user);
        $this->id_user=$this->database_instance->getConnection()->lastInsertId();

        /**
         * engine insertion
         */
        $this->sql_insert_engine = "INSERT INTO ".INIT::$DB_DATABASE.".`engines` (`id`, `name`, `type`, `description`, `base_url`, `translate_relative_url`, `contribute_relative_url`, `delete_relative_url`, `others`, `class_load`, `extra_parameters`, `google_api_compliant_version`, `penalty`, `active`, `uid`) VALUES ('10', 'DeepLingo En/Fr iwslt', 'MT', 'DeepLingo Engine', 'http://mtserver01.deeplingo.com:8019', 'translate', NULL, NULL, '{}', 'Apertium', '{\"client_secret\":\"gala15 \"}', '2', '14', '1', ".$this->id_user.");";
        $this->database_instance->getConnection()->query($this->sql_insert_engine);
        $this->id_database=$this->database_instance->getConnection()->lastInsertId();


        $this->sql_delete_user ="DELETE FROM users WHERE uid=".$this->id_user.";";
        $this->sql_delete_engine ="DELETE FROM engines WHERE id=".$this->id_database.";";

        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct= EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = $this->id_database;
        $eng = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = @$eng[0];
    }

    public function tearDown()
    {

        $this->database_instance->getConnection()->query($this->sql_delete_user);
        $this->database_instance->getConnection()->query($this->sql_delete_engine);
        $flusher= new Predis\Client(INIT::$REDIS_SERVERS);
        $flusher->select( INIT::$INSTANCE_ID );
        $flusher->flushdb();
        parent::tearDown();

    }

    /**
     * It construct an engine and it initialises some globals from the abstract constructor
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_of_sub_engine_of_moses()
    {
        $this->reflectedClass = new Engines_Moses($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->property = $this->reflector->getProperty("engineRecord");
        $this->property->setAccessible(true);

        $this->assertEquals($this->engine_struct_param, $this->property->getValue($this->reflectedClass));

        $this->property = $this->reflector->getProperty("className");
        $this->property->setAccessible(true);

        $this->assertEquals("Engines_Moses", $this->property->getValue($this->reflectedClass));

        $this->property = $this->reflector->getProperty("curl_additional_params");
        $this->property->setAccessible(true);

        $this->assertEquals(6, count($this->property->getValue($this->reflectedClass)));

    }

    /**
     * It will raise an exception constructing an engine because of he wrong property of the struct.
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_failure()
    {
        $this->engine_struct_param->type = "fooo";
        $this->setExpectedException("Exception");
        new Engines_Moses($this->engine_struct_param);
    }
}