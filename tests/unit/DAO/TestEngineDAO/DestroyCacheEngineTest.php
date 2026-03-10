<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers EngineDAO::destroyCache
 * User: dinies
 * Date: 19/04/16
 * Time: 17.10
 */
class DestroyCacheEngineTest extends AbstractTest
{
    /**
     * @var EngineDAO
     */
    protected $engineDAO;
    /**
     * @var \Predis\Client
     */
    protected $cache;
    /**
     * @var EngineStruct
     */
    protected $engine_struct;

    public function setUp(): void
    {
        parent::setUp();
        $this->engineDAO = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));

        $this->engine_struct = new EngineStruct();
        $this->cache = new Predis\Client(AppConfig::$REDIS_SERVERS);
    }

    public function tearDown(): void
    {
        $this->cache->flushdb();
        parent::tearDown();
    }

    /**
     * @throws Exception
     * @group  regression
     * @covers EngineDAO::destroyCache
     */
    public function test_destroyCache_with_given_engine_struct_with_ID_type_active_inizialized()
    {
        $this->engine_struct->id = 0;
        $this->engine_struct->type = "NONE";

        $cache_key = "SELECT * FROM " . AppConfig::$DB_DATABASE . ".`engines` WHERE id = 0 AND active = 0 AND type = 'NONE'";

        $key = md5($cache_key);
        $cache_value = serialize($this->engine_struct);
        $this->cache->setex($key, 20, $cache_value);
        $output_before_destruction = $this->cache->get($key);
        $this->assertEquals($cache_value, $output_before_destruction);
        $this->assertTrue(unserialize($output_before_destruction) instanceof EngineStruct);
        $this->engineDAO->destroyCache($this->engine_struct);
        $output_after_destruction = $this->cache->get($cache_key);
        $this->assertNull($output_after_destruction);
    }
}