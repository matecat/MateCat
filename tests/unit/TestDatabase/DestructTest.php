<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Database::__destruct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.56
 */
class DestructTest extends AbstractTest {

    public function setUp() {
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
    }

    /**
     * It tests that the destructor works correctly.
     * @group  regression
     * @covers Database::__destruct
     * @throws ReflectionException
     */
    public function test___destruct() {

        $instance_to_destruct = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $instance_to_destruct->connect();

        $reflector = new ReflectionClass( $instance_to_destruct );
        $method = $reflector->getMethod( "__destruct" );
        $method->invoke( $instance_to_destruct );

        $connection = $reflector->getProperty( 'connection' );
        $connection->setAccessible( true );
        $current_value = $connection->getValue( $instance_to_destruct );
        $this->assertNull( $current_value );
    }

}