<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::destroyCache
 * User: dinies
 * Date: 19/04/16
 * Time: 17.10
 */
class Destroy2CacheTest extends AbstractTest
{
    protected $engineDAO;
    protected $cache;
    protected $engine_struct;

    public function setUp()
    {
        $this->engineDAO = new EnginesModel_EngineDAO(Database::obtain());

        $this->engine_struct = new EnginesModel_EngineStruct();
        $this->cache= new Predis\Client(INIT::$REDIS_SERVERS);
    }
    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::destroyCache
     */
    public function test_destroyCache_with_given_engine_struct_with_ID_type_active_inizialized()
    {

        $this->engine_struct->id = 0;
        $this->engine_struct->type = "NONE";
        $this->engine_struct->id = 0;

        $cache_key = "SELECT * FROM engines WHERE id = 0 AND active = 0 AND type = 'NONE'";

        $key = md5($cache_key);
        $value = serialize($this->engine_struct);
        $this->cache->setex($key,20, $value);
        $output_before_destruction=$this->cache->get($key);
        $this->assertEquals($value,$output_before_destruction);
        $this->assertTrue(unserialize($output_before_destruction) instanceof EnginesModel_EngineStruct);
        $this->engineDAO->destroyCache($this->engine_struct);
        $output_after_destruction=$this->cache->get($cache_key);
        $this->assertNull($output_after_destruction);
        $this->assertFalse(unserialize($output_after_destruction) instanceof EnginesModel_EngineStruct);
    }
}