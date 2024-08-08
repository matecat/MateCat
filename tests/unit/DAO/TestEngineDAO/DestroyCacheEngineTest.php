<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers EnginesModel_EngineDAO::destroyCache
 * User: dinies
 * Date: 19/04/16
 * Time: 17.10
 */
class DestroyCacheEngineTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engineDAO;
    /**
     * @var \Predis\Client
     */
    protected $cache;
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct;

    public function setUp()
    {
        parent::setUp();
        $this->engineDAO = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));

        $this->engine_struct = new EnginesModel_EngineStruct();
        $this->cache= new Predis\Client(INIT::$REDIS_SERVERS);
    }

    public function tearDown()
    {
        $this->cache->flushdb();
        parent::tearDown();
    }

    /**
     * @param EnginesModel_EngineStruct
     * It cleans the cache memory about an engine that corresponds to the struct with initialized id passed as @param
     * @group regression
     * @covers EnginesModel_EngineDAO::destroyCache
     */
    public function test_destroyCache_with_given_engine_struct_with_ID_type_active_inizialized()
    {

        $this->engine_struct->id = 0;
        $this->engine_struct->type = "NONE";

        $cache_key = "SELECT * FROM ".INIT::$DB_DATABASE.".`engines` WHERE id = 0 AND active = 0 AND type = 'NONE'";

        $key = md5($cache_key);
        $cache_value = serialize($this->engine_struct);
        $this->cache->setex($key,20, $cache_value);
        $output_before_destruction=$this->cache->get($key);
        $this->assertEquals($cache_value,$output_before_destruction);
        $this->assertTrue(unserialize($output_before_destruction) instanceof EnginesModel_EngineStruct);
        $this->engineDAO->destroyCache($this->engine_struct);
        $output_after_destruction=$this->cache->get($cache_key);
        $this->assertNull($output_after_destruction);
        $this->assertFalse(unserialize($output_after_destruction) instanceof EnginesModel_EngineStruct);
    }
}