<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_fetch_array
 * User: dinies
 * Date: 15/04/16
 * Time: 16.18
 */
class FetchArray2Test extends AbstractTest
{
    protected $sql_param_to_get_engine;
    protected $reflector;
    protected $method;
    protected $cache_con;
    protected $cache_TTL;
    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_fetch_array");
        $this->method->setAccessible(true);

        $this->cache_con = $this->reflector->getProperty("cache_con");
        $this->cache_con->setAccessible(true);
        $this->cache_con->setValue($this->reflectedClass, new Predis\Client(INIT::$REDIS_SERVERS));

        $this->cache_TTL= $this->reflector->getProperty("cacheTTL");
        $this->cache_TTL->setAccessible(true);
        $this->cache_TTL->setValue($this->reflectedClass, 30);
    }

    /**
     * This tear_down is needed to manage that the cache service offered from redis is cleaned.
     * It avoids situations where the access to the DB was prevented by cache hits.
     */
    public function tearDown()
    {

        $this->cache_con->getValue($this->reflectedClass)-> flushdb();
        parent::tearDown();
    }

    /**
     * @param string : sql_query
     * @return array()  :  engine
     * It resolve an sql query in the DB memory because the key => value isn't cached yet.
     * @group regression
     * @covers DataAccess_AbstractDao::_fetch_array
     */
    public function test__fetch_array_to_find_in_DB()
    {
        $this->sql_param_to_get_engine = "SELECT * FROM engines WHERE id = 0 AND active = 0 AND type = 'NONE'";

        $array_output_expected = array(0 =>
            array(
                "id" => "0",
                "name" => "NONE",
                "type" => "NONE",
                "description" => "No MT",
                "base_url" => "",
                "translate_relative_url" => "",
                "contribute_relative_url" => NULL,
                "update_relative_url" => NULL,
                "delete_relative_url" => NULL,
                "others" => "{}",
                "class_load" => "NONE",
                "extra_parameters" => "",
                "google_api_compliant_version" => NULL,
                "penalty" => "100",
                "active" => "0",
                "uid" => NULL
            ));

        $array_output_actual = $this->method->invoke($this->reflectedClass, $this->sql_param_to_get_engine);
        $this->assertEquals($array_output_expected, $array_output_actual);
    }

    /**
     * @param string : sql_query
     * @return array()  :  engine
     * It resolve an sql query in the cache memory.
     * @group regression
     * @covers DataAccess_AbstractDao::_fetch_array
     */
    public function test__fetch_array_to_find_in_cache()
    {
        $this->sql_param_to_get_engine = "SELECT * FROM engines WHERE id = 0 AND active = 0 AND type = 'NONE'";

        $array_output_expected = array(0 =>
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
        $TTL = $this->cache_TTL->getValue($this->reflectedClass);
        $key = md5($this->sql_param_to_get_engine);
        $value = serialize($array_output_expected);
        $this->cache_con->getValue($this->reflectedClass) ->setex( $key, $TTL, $value);

        $this->assertEquals($array_output_expected, $this->method->invoke($this->reflectedClass, $this->sql_param_to_get_engine));
    }
}