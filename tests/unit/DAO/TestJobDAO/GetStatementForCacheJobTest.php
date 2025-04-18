<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Jobs_JobDao::_getStatementForQuery
 * User: dinies
 * Date: 31/05/16
 * Time: 12.32
 */
class GetStatementForCacheJobTest extends AbstractTest {
    protected $reflector;
    protected $method;


    public function setUp(): void {
        parent::setUp();

        $this->databaseInstance = new Jobs_JobDao( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "_getStatementForQuery" );
        $this->method->setAccessible( true );


    }

    public function test__getStatementForCache() {

        $propReflection = $this->reflector->getProperty( '_query_cache' );
        $propReflection->setAccessible( true );

        $result = $this->method->invoke( $this->databaseInstance, $propReflection->getValue( $this->databaseInstance ) );
        $this->assertTrue( $result instanceof PDOStatement );
        $this->assertEquals( "SELECT * FROM jobs WHERE " . " id = :id_job AND password = :password ", $result->queryString );

    }
}