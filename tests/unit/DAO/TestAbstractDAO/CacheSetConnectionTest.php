<?php

use Predis\Connection\ConnectionException;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers DataAccess_AbstractDao::_cacheSetConnection
 * User: dinies
 * Date: 15/04/16
 * Time: 19.17
 */
class CacheSetConnectionTest extends AbstractTest {
    protected $reflector;
    protected $method;
    protected $cache_conn;

    protected $initial_redis_configuration;

    public function setUp() {
        parent::setUp();
        $this->databaseInstance = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "_cacheSetConnection" );
        $this->method->setAccessible( true );
        $this->cache_conn = $this->reflector->getProperty( "cache_con" );
        $this->cache_conn->setAccessible( true );

    }

    public function tearDown() {
        parent::tearDown();
        if( !empty( $this->initial_redis_configuration ) ) {
            INIT::$REDIS_SERVERS = $this->initial_redis_configuration;
        }
    }

    /**
     * It sets the connection to the DB after the creation of a new EnginesModel_EngineDAO .
     * @group  regression
     * @covers DataAccess_AbstractDao::_cacheSetConnection
     */
    public function test_set_connection_after_creation_of_engine() {

        $this->cache_conn->setValue( $this->databaseInstance, null );
        $this->method->invoke( $this->databaseInstance );
        $this->assertTrue( $this->cache_conn->getValue( $this->databaseInstance ) instanceof Predis\Client );
    }

    /**
     * It trows an exception because it is unable to set the connection with wrong global constant value.
     * @group  regression
     * @covers DataAccess_AbstractDao::_cacheSetConnection
     */
    public function test_set_connection_with_wrong_global_constant() {

        $this->cache_conn->setValue( $this->databaseInstance, null );
        $this->initial_redis_configuration = INIT::$REDIS_SERVERS;
        INIT::$REDIS_SERVERS   = "tcp://fake_localhost_and_fake_port:7777";
        $this->expectException( ConnectionException::class );
        $this->method->invoke( $this->databaseInstance );
    }
}