<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers DataAccess_AbstractDao::_getFromCache
 * User: dinies
 * Date: 09/06/16
 * Time: 21.31
 */
class GetFromCacheTest extends AbstractTest
{
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;
    protected $reflector;
    protected $method_getFromCache;
    protected $cache_con;
    protected $cache_TTL;
    /**
     * @var Database
     */
    protected $database_instance;


    public function setUp()
    {
        parent::setUp();
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->job_Dao = new Jobs_JobDao($this->database_instance);
        $this->reflector = new ReflectionClass($this->job_Dao);

        $this->method_getFromCache = $this->reflector->getMethod("_getFromCache");
        $this->method_getFromCache->setAccessible(true);

        $this->cache_con = $this->reflector->getProperty("cache_con");
        $this->cache_con->setAccessible(true);
        $this->cache_con->setValue($this->job_Dao, new Predis\Client(INIT::$REDIS_SERVERS));

        $this->cache_TTL= $this->reflector->getProperty("cacheTTL");
        $this->cache_TTL->setAccessible(true);
        $this->cache_TTL->setValue($this->job_Dao, 30);
    }
    public function tearDown()
    {

        $this->cache_con->getValue($this->job_Dao)-> flushdb();
        parent::tearDown();
    }
    /**
     * It gets from the cache a value bound to a simple key.
     * @group regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_simple_engine_with_basic_key_value(){

        $cache_key = "key";
        $cache_value = "foo_bar";



        $this->cache_con->getValue($this->job_Dao) ->setex( md5( $cache_key ), $this->cache_TTL->getValue($this->job_Dao), serialize( $cache_value ));
        $expected_return= $this->method_getFromCache->invoke($this->job_Dao , $cache_key);
        $this->assertEquals($cache_value,  $expected_return );
    }
}