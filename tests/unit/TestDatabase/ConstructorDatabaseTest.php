<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Database::__construct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.51
 */
class ConstructorDatabaseTest extends AbstractTest {

    protected $reflector;

    public function setUp(): void {
        parent::setUp();
        $this->jobDao = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->jobDao->close();

        $this->reflector = new ReflectionClass( $this->jobDao );

    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * This test checks that an Exception will be raised if the constructor is called without parameters.
     * @group  regression
     * @covers Database::__construct
     * @throws ReflectionException
     */
    public function test___construct_without_parameters() {

        // get the singleton static instance reference
        $property = $this->reflector->getProperty( 'instance' );
        $property->setAccessible( true );
        $property->setValue( $this->jobDao, null ); // unset

        $this->expectException( '\InvalidArgumentException' );

        $this->jobDao->obtain();


    }


}