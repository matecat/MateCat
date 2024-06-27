<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers Users_UserDao::_getStatementForCache
 * User: dinies
 * Date: 27/05/16
 * Time: 19.55
 */
class GetStatementForCacheUserTest extends AbstractTest
{

    protected $reflector;
    protected $method;


    public function setUp()
    {
        parent::setUp();

        $this->databaseInstance = new Users_UserDao(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE));
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->method           = $this->reflector->getMethod("_getStatementForCache");
        $this->method->setAccessible(true);


    }

    public function test__getStatementForCache()
    {
        $query = "SELECT email FROM ".INIT::$DB_DATABASE.".`users` WHERE uid='barandfoo';";
        $result = $this->method->invoke($this->databaseInstance, $query);
        $this->assertTrue($result instanceof PDOStatement);
        $this->assertEquals("SELECT email FROM ".INIT::$DB_DATABASE.".`users` WHERE uid='barandfoo';", $result->queryString);

    }

}