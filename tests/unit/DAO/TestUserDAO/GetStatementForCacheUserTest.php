<?php

use Model\DataAccess\Database;
use Model\Users\UserDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers UserDao::_getStatementForQuery
 * User: dinies
 * Date: 27/05/16
 * Time: 19.55
 */
#[Group('PersistenceNeeded')]
class GetStatementForCacheUserTest extends AbstractTest
{

    protected ReflectionClass $reflector;
    protected ReflectionMethod $method;
    protected UserDao $userDao;


    public function setUp(): void
    {
        parent::setUp();

        $this->userDao = new UserDao(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->userDao);
        $this->method = $this->reflector->getMethod("_getStatementForQuery");
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function test__getStatementForCache()
    {
        $query = "SELECT email FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='barandfoo';";
        $result = $this->method->invoke($this->userDao, $query);
        $this->assertTrue($result instanceof PDOStatement);
        $this->assertEquals("SELECT email FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='barandfoo';", $result->queryString);
    }

}