<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Database::connect
 * User: dinies
 * Date: 11/04/16
 * Time: 18.12
 */
class ConnectTest extends AbstractTest {
    protected $reflector;
    protected $property;

    /**
     * @var Database|IDatabase
     */
    protected $databaseInstance;

    /**
     * @throws ReflectionException
     */
    public function setUp() {
        parent::setUp();

        // get the singleton and close the connection
        $this->databaseInstance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->databaseInstance->close();

        // reset the singleton static field instance
        $this->reflector = new ReflectionClass( $this->databaseInstance );
        $this->property  = $this->reflector->getProperty( 'instance' );
        $this->property->setAccessible( true );
        $this->property->setValue( $this->databaseInstance, null );

    }

    /**
     * @throws ReflectionException
     */
    protected function checkInstanceReset() {
        // verify that the setup method has reset the connection
        $connection = $this->reflector->getProperty( 'connection' );
        $connection->setAccessible( true );
        $current_value = $connection->getValue( $this->databaseInstance );
        $this->assertNull( $current_value );
    }

    public function tearDown() {
        parent::tearDown();
    }

    /**
     * It verifies that the variable connection in the database class is initialized
     * after the call of the method 'connect'.
     * @group  regression
     * @covers Database::connect
     * @throws ReflectionException
     */
    public function test_connect_connected() {

        // verify that the setup method has reset the connection
        $this->checkInstanceReset();

        // recreate the instance
        $instance_after_reset = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $instance_after_reset->connect();

        $connection = $this->reflector->getProperty( 'connection' );
        $connection->setAccessible( true );
        $current_value = $connection->getValue( $instance_after_reset );

        $this->assertNotNull( $current_value );
        $this->assertTrue( $current_value instanceof PDO );
    }

    /**
     * It checks that the variable 'connection' of a newly created database instance is NULL without an explicit call to the 'connect' method.
     * @group  regression
     * @covers Database::connect
     * @throws ReflectionException
     */
    public function test_connect_not_connected_without_explicit_call_to_connect() {

        // verify that the setup method has reset the connection
        $this->checkInstanceReset();

        $newDatabaseClassInstance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $connection               = $this->reflector->getProperty( 'connection' );
        $connection->setAccessible( true );
        $current_value = $connection->getValue( $newDatabaseClassInstance );
        $this->assertNull( $current_value );
    }

    /**
     * This test checks the fact that two different newly created database connections are different objects
     * despite the fact that they have the same initial values in their local variables.
     * @group  regression
     * @covers Database::connect
     * @throws ReflectionException
     */
    public function test_connect_different_hash_between_two_PDO_objects() {

        // verify that the setup method has reset the connection
        $this->checkInstanceReset();

        // ensure connection
        $instance_after_first_reset = $this->databaseInstance->obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $instance_after_first_reset->connect();

        // get the PDO internal resource
        $connection = $this->reflector->getProperty( 'connection' );
        $connection->setAccessible( true );
        $current_value_first_PDO = $connection->getValue( $instance_after_first_reset );
        $hash_first_PDO          = spl_object_hash( $current_value_first_PDO );

        // close the PDO connection (set to null)
        $instance_after_first_reset->close();

        // get a fresh new connection
        $instance_after_second_reset = $instance_after_first_reset->obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $instance_after_second_reset->connect();

        // get the PDO internal resource
        $current_value_second_PDO = $connection->getValue( $instance_after_second_reset );
        $hash_second_PDO          = spl_object_hash( $current_value_second_PDO );

        $this->assertNotEquals( $hash_first_PDO, $hash_second_PDO );

    }

}