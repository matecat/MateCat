<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_cacheSetConnection
 * User: dinies
 * Date: 15/04/16
 * Time: 19.17
 */
class CacheSetConnectionTest extends AbstractTest
{
    protected $reflector;
    protected $method;
    protected $cache_conn;
    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_cacheSetConnection");
        $this->method->setAccessible(true);
        $this->cache_conn= $this->reflector->getProperty("cache_con");
        $this->cache_conn->setAccessible(true);
           
    }
    
    /**
     * It sets the connection to the DB after the creation of a new EnginesModel_EngineDAO .
     * @group regression
     * @covers DataAccess_AbstractDao::_cacheSetConnection
     */
    public function test_set_connection_after_creation_of_engine(){

        $this->cache_conn->setValue($this->reflectedClass , NULL);
        $this->method->invoke($this->reflectedClass );
        $this->assertTrue($this->cache_conn->getValue($this->reflectedClass) instanceof Predis\Client);
    }

    /**
     * It trows an exception because it is unable to set the connection with wrong global constant value.
     * @group regression
     * @covers DataAccess_AbstractDao::_cacheSetConnection
     */
    public function test_set_connection_with_wrong_global_constant(){

        $this->cache_conn->setValue($this->reflectedClass , NULL);
        $initial_configuration= INIT::$REDIS_SERVERS;
        INIT::$REDIS_SERVERS= "http//:fake_localhost_and_fake_port/7777";
        $this->setExpectedException(   $this->method->invoke($this->reflectedClass ));
        INIT::$REDIS_SERVERS= $initial_configuration;
    }
}