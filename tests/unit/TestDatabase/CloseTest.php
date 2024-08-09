<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Database::close
 * User: dinies
 * Date: 12/04/16
 * Time: 16.22
 */
class CloseTest extends AbstractTest {

    protected $jobDao;

    public function setUp() {
        parent::setUp();
        $this->jobDao = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
    }

    public function tearDown() {
        parent::tearDown();
    }

    /**
     * It tests that after the call of the method 'close', the variable connection will be set NULL.
     * @group  regression
     * @covers Database::close
     * @throws ReflectionException
     */
    public function test_close() {

        $this->jobDao->close();

        $reflector  = new ReflectionClass( $this->jobDao );
        $connection = $reflector->getProperty( 'connection' );
        $connection->setAccessible( true );
        $current_value = $connection->getValue( $this->jobDao );
        $this->assertNull( $current_value );

    }

}