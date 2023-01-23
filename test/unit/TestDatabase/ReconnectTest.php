<?php

/**
 * @group regression
 * @covers Database::reconnect
 * User: dinies
 * Date: 12/04/16
 * Time: 16.33
 */
class ReconnectTest extends AbstractTest
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
     * It ensures that after a reconnection the instance 'connection' will be reinitialized if isn't an instance of PDO
     * @group regression
     * @covers Database::reconnect
     */
    public function test_reconnect_on_new_instance_without_old_connection()
    {
        /**
         * @var Database
         */
        $instance_after_reset = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $instance_after_reset->reconnect();
        $this->property = $this->reflector->getProperty('connection');
        $this->property->setAccessible(true);
        $current_value = $this->property->getValue($instance_after_reset);

        $this->assertNotNull($current_value);
        $this->assertTrue($current_value instanceof PDO);
    }

    /**
     * This test asserts that two different instances of connection haven't the same memory reference but they have same values.
     * @group regression
     * @covers Database::reconnect
     */
    public function test_reconnect_with_previous_connection()
    {
        $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->reflectedClass->connect();
        $this->property = $this->reflector->getProperty('connection');
        $this->property->setAccessible(true);
        /**
         * @var Database
         */
        $first_instance_of_connection = $this->property->getValue($this->reflectedClass);
        $this->reflectedClass->reconnect();
        /**
         * @var Database
         */
        $second_instance_of_connection = $this->property->getValue($this->reflectedClass);
        $first_connection_hash = spl_object_hash($first_instance_of_connection);
        $second_connection_hash = spl_object_hash($second_instance_of_connection);
        $this->assertEquals($first_instance_of_connection, $second_instance_of_connection);
        $this->assertNotEquals($first_connection_hash, $second_connection_hash);

        $this->assertNotNull($first_instance_of_connection);
        $this->assertNotNull($second_instance_of_connection);
    }
}