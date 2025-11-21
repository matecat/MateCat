<?php

use Model\DataAccess\Database;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers \Model\DataAccess\Database::__construct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.51
 */
class ConstructorDatabaseTest extends AbstractTest {

    protected ReflectionClass $reflector;

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
        $this->databaseInstance->close();

        $this->reflector = new ReflectionClass( $this->databaseInstance );

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
        
        $property->setValue( $this->databaseInstance, null ); // unset

        $this->expectException( TypeError::class );

        $this->databaseInstance->obtain();


    }


}