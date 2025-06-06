<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers DataAccess\AbstractDao::setCacheTTL
 * User: dinies
 * Date: 18/04/16
 * Time: 15.08
 */
class SetCacheTTLTest extends AbstractTest {
    protected $reflector;
    protected $cache_TTL;

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->cache_TTL        = $this->reflector->getProperty( "cacheTTL" );
        $this->cache_TTL->setAccessible( true );

    }

    /**
     * It sets the cache TTL to 55.
     * @group  regression
     * @covers DataAccess\AbstractDao::setCacheTTL
     */
    public function test_setCacheTTL_to_value_not_zero() {
        $previous_TTL_value = $this->cache_TTL->getValue( $this->databaseInstance );
        $this->databaseInstance->setCacheTTL( 55 );
        $this->assertEquals( 55, $this->cache_TTL->getValue( $this->databaseInstance ) );
        $this->cache_TTL->setValue( $this->databaseInstance, $previous_TTL_value );
    }
}

