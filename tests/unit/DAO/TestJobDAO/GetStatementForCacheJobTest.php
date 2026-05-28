<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Jobs\JobDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers JobDao::_getStatementForQuery
 * User: dinies
 * Date: 31/05/16
 * Time: 12.32
 */
class GetStatementForCacheJobTest extends AbstractTest
{
    protected ReflectionClass $reflector;
    protected ReflectionMethod $method;
    protected JobDao $jobDAO;


    public function setUp(): void
    {
        parent::setUp();

        $this->jobDAO = new JobDao(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->jobDAO);
        $this->method = $this->reflector->getMethod("_getStatementForQuery");
    }

    #[Test]
    public function test__getStatementForCache()
    {
        $propReflection = $this->reflector->getProperty('_query_cache');


        $result = $this->method->invoke($this->jobDAO, $propReflection->getValue($this->jobDAO));
        $this->assertTrue($result instanceof PDOStatement);
        $this->assertEquals("SELECT * FROM jobs WHERE " . " id = :id_job AND password = :password ", $result->queryString);
    }
}