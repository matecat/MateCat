<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_getFromCache
 * User: dinies
 * Date: 18/04/16
 * Time: 17.31
 */
class AbstractGetFromCacheEngineTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_Dao;
    protected $reflector;
    protected $method;
    protected $cache_con;
    protected $cache_TTL;
    protected $cache_key;
    protected $cache_value;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $sql_delete_engine;
    protected $id;
    protected $engine_struct_param;
    protected $method_buildQueryForEngine;

    public function setUp()
    {
        parent::setUp();
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->engine_Dao = new EnginesModel_EngineDAO($this->database_instance);
        $this->reflector = new ReflectionClass($this->engine_Dao);
        $this->method = $this->reflector->getMethod("_getFromCache");
        $this->method->setAccessible(true);

        $this->cache_con = $this->reflector->getProperty("cache_con");
        $this->cache_con->setAccessible(true);
        $this->cache_con->setValue($this->engine_Dao, new Predis\Client(INIT::$REDIS_SERVERS));

        $this->cache_TTL= $this->reflector->getProperty("cacheTTL");
        $this->cache_TTL->setAccessible(true);
        $this->cache_TTL->setValue($this->engine_Dao, 30);

        $this->engine_struct_param = new EnginesModel_EngineStruct();
        $this->engine_struct_param->id=null;
        $this->engine_struct_param->name = "Moses_bar_and_foo";
        $this->engine_struct_param->description = "Machine translation from bar and foo.";
        $this->engine_struct_param->type = "TM";
        $this->engine_struct_param->base_url = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = "contribute";
        $this->engine_struct_param->delete_relative_url = "delete";
        $this->engine_struct_param->others = "{}";
        $this->engine_struct_param->class_load = "foo_bar";
        $this->engine_struct_param->extra_parameters ="{}";
        $this->engine_struct_param->penalty = 1;
        $this->engine_struct_param->active = 4;
        $this->engine_struct_param->uid = 1;


        $this->engine_Dao->create($this->engine_struct_param);
        $this->id= $this->database_instance->last_insert();

        $this->engine_struct_param->id= $this->id;

        $this->sql_delete_engine = "DELETE FROM engines WHERE uid='" . $this->id . "';";



        $this->cache_value = array(0 =>
            array(
                "id" => "{$this->id}",
                "name" => "Moses_bar_and_foo",
                "type" => "TM",
                "description" => "Machine translation from bar and foo.",
                "base_url" => "http://mtserver01.deepfoobar.com:8019",
                "translate_relative_url" => "translate",
                "contribute_relative_url" => "contribute",
                "delete_relative_url" => "delete",
                "others" => '"{}"',
                "class_load" => "foo_bar",
                "extra_parameters" => '"{}"',
                'google_api_compliant_version' => '2',
                "penalty" => "1",
                "active" => "4",
                "uid" => "1"
            ));

        $this->method_buildQueryForEngine= $this->reflector->getMethod("_buildQueryForEngine");
        $this->method_buildQueryForEngine->setAccessible(true);
        $this->cache_key= $this->method_buildQueryForEngine->invoke($this->engine_Dao, $this->engine_struct_param);

    }
    public function tearDown()
    {
        $this->database_instance->query($this->sql_delete_engine);

        $this->cache_con->getValue($this->engine_Dao)-> flushdb();
        parent::tearDown();
    }
    

    /**
     * It gets from the cache a common engine tied to a frequent key.
     * @group regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_simple_engine_with_artificial_insertion_in_cache(){

        $this->cache_con->getValue($this->engine_Dao) ->setex( md5( $this->cache_key ), $this->cache_TTL->getValue($this->engine_Dao), serialize( $this->cache_value ));
        $expected_return= $this->method->invoke($this->engine_Dao , $this->cache_key);
        $this->assertEquals($this->cache_value,  $expected_return );
    }

    /**
     * It gets from the cache a common engine tied to a frequent key.
     * @group regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_engine_just_created(){

        $this->engine_Dao->read($this->engine_struct_param);
        $expected_return= $this->method->invoke($this->engine_Dao , $this->cache_key);
        $this->assertEquals($this->cache_value,  $expected_return );
    }

}