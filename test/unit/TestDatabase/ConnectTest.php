<?php

/**
 * @group regression
 * @covers Database::connect
 * User: dinies
 * Date: 11/04/16
 * Time: 18.12
 */
class ConnectTest extends AbstractTest
{
    protected $reflector;
    protected $property;

    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->reflectedClass->close();
        $this->property = $this->reflector->getProperty('instance');
        $this->property->setAccessible(true);
        $this->property->setValue($this->reflectedClass, null);
    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * It verifies that the variable connection of the instance of database is initialized
     * after the call of the method 'connect'.
     * @group regression
     * @covers Database::connect
     */
    public function test_connect_connected()
    {
        /**
         * @var Database
         */
        $instance_after_reset = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $instance_after_reset->connect();
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $current_value = $connection->getValue($instance_after_reset);

        $this->assertNotNull($current_value);
        $this->assertTrue($current_value instanceof PDO);
    }

    /**
     * It checks that the variable 'connection' of the instance of a newly created database is NULL.
     * @group regression
     * @covers Database::connect
     */
    public function test_connect_not_connected()
    {
        $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $current_value = $connection->getValue($this->reflectedClass);
        $this->assertNull($current_value);

    }

    /**
     * This test checks the fact that two different databases newly created are different objects
     * despite the fact that they have the same initial values in their local variables.
     * @group regression
     * @covers Database::connect
     */
    public function test_connect_different_hash_between_two_PDO_objects()
    {
        /**
         * @var Database
         */
        $instance_after_first_reset = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $instance_after_first_reset->connect();
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $current_value_first_PDO = $connection->getValue($instance_after_first_reset);
        $hash_first_PDO = spl_object_hash($current_value_first_PDO);
        $instance_after_first_reset->close();
        $this->property->setValue($instance_after_first_reset, null);
        /**
         * @var Database
         */
        $instance_after_second_reset = $instance_after_first_reset->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $instance_after_second_reset->connect();
        $current_value_second_PDO = $connection->getValue($instance_after_second_reset);
        $hash_second_PDO = spl_object_hash($current_value_second_PDO);

        $this->assertNotEquals($hash_first_PDO, $hash_second_PDO);
    }

}