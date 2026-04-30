<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers Model\DataAccess\AbstractDao::setCacheTTL
 * User: dinies
 * Date: 18/04/16
 * Time: 15.08
 */
#[Group('PersistenceNeeded')]
class SetCacheTTLTest extends AbstractTest
{
    protected ReflectionClass $reflector;
    protected ReflectionProperty $cache_TTL;
    protected EngineDAO $dao;

    public function setUp(): void
    {
        parent::setUp();
        $this->dao = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->dao);
        $this->cache_TTL = $this->reflector->getProperty("cacheTTL");
    }

    /**
     * It sets the cache TTL to 55.
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::setCacheTTL
     */
    #[Test]
    public function test_setCacheTTL_to_value_not_zero()
    {
        $previous_TTL_value = $this->cache_TTL->getValue($this->dao);
        $this->dao->setCacheTTL(55);
        $this->assertEquals(55, $this->cache_TTL->getValue($this->dao));
        $this->cache_TTL->setValue($this->dao, $previous_TTL_value);
    }
}

