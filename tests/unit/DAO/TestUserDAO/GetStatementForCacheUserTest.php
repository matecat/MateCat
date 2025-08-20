<?php

use Model\DataAccess\Database;
use Model\Users\UserDao;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers UserDao::_getStatementForQuery
 * User: dinies
 * Date: 27/05/16
 * Time: 19.55
 */
class GetStatementForCacheUserTest extends AbstractTest {

    protected $reflector;
    protected $method;


    public function setUp(): void {
        parent::setUp();

        $this->databaseInstance = new UserDao( Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE ) );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "_getStatementForQuery" );
        $this->method->setAccessible( true );


    }

    public function test__getStatementForCache() {
        $query  = "SELECT email FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='barandfoo';";
        $result = $this->method->invoke( $this->databaseInstance, $query );
        $this->assertTrue( $result instanceof PDOStatement );
        $this->assertEquals( "SELECT email FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='barandfoo';", $result->queryString );

    }

}