<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_destroyCache
 * User: dinies
 * Date: 18/04/16
 * Time: 18.17
 */
class DestroyCacheAbstractClassTest extends AbstractTest
{
    protected $reflector;
    protected $method;
    /**
     * @var \Predis\Client
     */
    protected $cache_con;
    protected $cache_TTL;
    protected $cache_key;
    protected $cache_value_for_the_key;
    protected $number_of_keys_removed;
    protected $bool_cache_hit;

    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_destroyCache");
        $this->method->setAccessible(true);

        $this->cache_con = $this->reflector->getProperty("cache_con");
        $this->cache_con->setAccessible(true);
        $this->cache_con->setValue($this->reflectedClass, new Predis\Client(INIT::$REDIS_SERVERS));

        $this->cache_TTL= $this->reflector->getProperty("cacheTTL");
        $this->cache_TTL->setAccessible(true);
        $this->cache_TTL->setValue($this->reflectedClass, 30);


    }
    public function tearDown()
    {
        $this->cache_con->getValue($this->reflectedClass)-> flushdb();
        parent::tearDown();
    }

    /**
     * @param string :  key
     * It destroy the cache memory about a given key.
     * @group regression
     * @covers DataAccess_AbstractDao::_destroyCache
     */
    public function test__destroyCache_basic_key_value(){
        $this->cache_key = "key";
        $this->cache_value_for_the_key = "foo_bar";
        $key = md5($this->cache_key);
        $TTL = $this->cache_TTL->getValue($this->reflectedClass);
        $value = serialize($this->cache_value_for_the_key);
        $this->cache_con->getValue($this->reflectedClass) ->setex( $key, $TTL, $value);
        
        $this->number_of_keys_removed= $this->method->invoke($this->reflectedClass,$this->cache_key);
        $this->bool_cache_hit= $this->cache_con->getValue($this->reflectedClass)->get($this->cache_key);
        $this->assertNull($this->bool_cache_hit );
        $this->assertEquals(1,$this->number_of_keys_removed);

    }
    /**
     * @param string :  key
     * It fails to destroy the cache memory about a given key because this key isn't cached.
     * @group regression
     * @covers DataAccess_AbstractDao::_destroyCache
     */
    public function test__destroyCache_not_cached_key_value(){
        $this->cache_key = "key";
        $this->cache_value_for_the_key = "foo_bar";
        $this->number_of_keys_removed=$this->method->invoke($this->reflectedClass,$this->cache_key);
        $this->bool_cache_hit= $this->cache_con->getValue($this->reflectedClass)->get($this->cache_key);
        $this->assertNull($this->bool_cache_hit );
        $this->assertEquals(0,$this->number_of_keys_removed);

    }
    /**
     * @param string :  key
     * It destroy the cache memory about a given key.
     * @group regression
     * @covers DataAccess_AbstractDao::_destroyCache
     */
    public function test__destroyCache_frequent_key_value(){
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
        $key = md5($this->cache_key);
        $TTL = $this->cache_TTL->getValue($this->reflectedClass);
        $value = serialize($this->cache_value_for_the_key);
        $this->cache_con->getValue($this->reflectedClass) ->setex( $key, $TTL, $value);

        $this->number_of_keys_removed= $this->method->invoke($this->reflectedClass,$this->cache_key);
        $this->bool_cache_hit= $this->cache_con->getValue($this->reflectedClass)->get($this->cache_key);
        $this->assertNull($this->bool_cache_hit );
        $this->assertEquals(1,$this->number_of_keys_removed);

    }
}