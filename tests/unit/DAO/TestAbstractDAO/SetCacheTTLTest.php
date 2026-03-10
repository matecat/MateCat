<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers Model\DataAccess\AbstractDao::setCacheTTL
 * User: dinies
 * Date: 18/04/16
 * Time: 15.08
 */
class SetCacheTTLTest extends AbstractTest
{
    protected $reflector;
    protected $cache_TTL;

    public function setUp(): void
    {
        parent::setUp();
        $this->databaseInstance = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->databaseInstance);
        $this->cache_TTL = $this->reflector->getProperty("cacheTTL");
    }

    /**
     * It sets the cache TTL to 55.
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::setCacheTTL
     */
    public function test_setCacheTTL_to_value_not_zero()
    {
        $previous_TTL_value = $this->cache_TTL->getValue($this->databaseInstance);
        $this->databaseInstance->setCacheTTL(55);
        $this->assertEquals(55, $this->cache_TTL->getValue($this->databaseInstance));
        $this->cache_TTL->setValue($this->databaseInstance, $previous_TTL_value);
    }
}

