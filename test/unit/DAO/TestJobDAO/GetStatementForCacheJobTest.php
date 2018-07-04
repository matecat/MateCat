<?php

/**
 * @group regression
 * @covers Jobs_JobDao::_getStatementForCache
 * User: dinies
 * Date: 31/05/16
 * Time: 12.32
 */
class GetStatementForCacheJobTest extends AbstractTest
{
    protected $reflector;
    protected $method;


    public function setUp()
    {
        parent::setUp();

        $this->reflectedClass = new Jobs_JobDao(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_getStatementForCache");
        $this->method->setAccessible(true);


    }

    public function test__getStatementForCache(){
        $result= $this->method->invoke($this->reflectedClass);
        $this->assertTrue($result instanceof PDOStatement);
        $this->assertEquals("SELECT * FROM jobs WHERE " . " id = :id_job AND password = :password ", $result->queryString);

    }
}