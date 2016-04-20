<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_getFromCache
 * User: dinies
 * Date: 18/04/16
 * Time: 17.31
 */
class GetFromCacheTest extends AbstractTest
{
    protected $reflector;
    protected $method;
    protected $cache_con;
    protected $cache_TTL;
    protected $cache_key;
    protected $cache_value_for_the_key;

    public function setUp()
    {

        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain());
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_getFromCache");
        $this->method->setAccessible(true);

        $this->cache_con = $this->reflector->getProperty("cache_con");
        $this->cache_con->setAccessible(true);

        require_once 'Predis/autoload.php';
        $this->cache_con->setValue($this->reflectedClass, new Predis\Client(INIT::$REDIS_SERVERS));

        $this->cache_TTL= $this->reflector->getProperty("cacheTTL");
        $this->cache_TTL->setAccessible(true);
        $this->cache_TTL->setValue($this->reflectedClass, 30);


    }
    public function tearDown()
    {
        $this->cache_con->getValue($this->reflectedClass)-> flushdb();
    }

    /**
     * @group regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_basic_key_value(){
        $this->cache_key = "key";
        $this->cache_value_for_the_key = "foo_bar";
        $this->cache_con->getValue($this->reflectedClass) ->setex( md5( $this->cache_key ), $this->cache_TTL->getValue($this->reflectedClass), serialize( $this->cache_value_for_the_key ));
        $expected_return= $this->method->invoke($this->reflectedClass , $this->cache_key);
        $this->assertEquals($this->cache_value_for_the_key,  $expected_return );
    }

    /**
     * @group regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_frequent_key_value(){
        $this->cache_key = "SELECT * FROM engines WHERE id = 0 AND active = 0 AND type = 'NONE'";

        $this->cache_value_for_the_key = array(0 =>
            array(
                "id" => "0",
                "name" => "NONE",
                "type" => "NONE",
                "description" => "No MT",
                "base_url" => "",
                "translate_relative_url" => "",
                "contribute_relative_url" => NULL,
                "delete_relative_url" => NULL,
                "others" => "{}",
                "class_load" => "NONE",
                "extra_parameters" => "",
                "google_api_compliant_version" => NULL,
                "penalty" => "100",
                "active" => "0",
                "uid" => NULL
            ));
        $this->cache_con->getValue($this->reflectedClass) ->setex( md5( $this->cache_key ), $this->cache_TTL->getValue($this->reflectedClass), serialize( $this->cache_value_for_the_key ));
        $expected_return= $this->method->invoke($this->reflectedClass , $this->cache_key);
        $this->assertEquals($this->cache_value_for_the_key,  $expected_return );
    }

}