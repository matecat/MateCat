<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Predis\Connection\Resource\Exception\StreamInitException;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers Model\DataAccess\AbstractDao::_cacheSetConnection
 * User: dinies
 * Date: 15/04/16
 * Time: 19.17
 */
#[Group('PersistenceNeeded')]
class CacheSetConnectionTest extends AbstractTest
{
    protected ReflectionClass $reflector;
    protected ReflectionMethod $method;
    protected ReflectionProperty $cache_conn;
    protected EngineDAO $dao;

    protected string $initial_redis_configuration;

    /**
     * @throws ReflectionException
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->dao = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->dao);
        $this->method = $this->reflector->getMethod("_cacheSetConnection");
        $this->cache_conn = $this->reflector->getProperty("cache_con");
    }

    public function tearDown(): void
    {
        parent::tearDown();
        if (!empty($this->initial_redis_configuration)) {
            AppConfig::$REDIS_SERVERS = $this->initial_redis_configuration;
        }
    }

    /**
     * It sets the connection to the DB after the creation of a new EngineDAO .
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_cacheSetConnection
     * @throws ReflectionException
     */
    #[Test]
    public function test_set_connection_after_creation_of_engine()
    {
        $this->cache_conn->setValue($this->dao, null);
        $this->method->invoke($this->dao);
        $this->assertTrue($this->cache_conn->getValue($this->dao) instanceof Predis\Client);
    }

    /**
     * It trows an exception because it is unable to set the connection with wrong global constant value.
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_cacheSetConnection
     * @throws ReflectionException
     */
    #[Test]
    public function test_set_connection_with_wrong_global_constant()
    {
        $this->cache_conn->setValue($this->dao, null);
        $this->initial_redis_configuration = AppConfig::$REDIS_SERVERS;
        AppConfig::$REDIS_SERVERS = "tcp://fake_localhost_and_fake_port:7777";
        $this->expectException(StreamInitException::class);
        $this->method->invoke($this->dao);
    }
}