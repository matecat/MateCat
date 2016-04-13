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
        $this->reflectedClass = Database::obtain();
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->reflectedClass->close();
        $this->property = $this->reflector->getProperty('instance');
        $this->property->setAccessible(true);
        $this->property->setValue($this->reflectedClass, null);
    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $this->reflectedClass->close();
        startConnection();
    }

    /**
     * @group regression
     * @covers Database::reconnect
     */
    public function test_reconnect_on_new_instance_without_old_connection()
    {
        /**
         * @var Database
         */
        $instance_after_reset = $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $instance_after_reset->reconnect();
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $current_value = $connection->getValue($instance_after_reset);

        $this->assertNotNull($current_value);
        $this->assertTrue($current_value instanceof PDO);
    }

    /**
     * This asserts that two different instances of connection haven't the same memory reference but they have same values.
     * @group regression
     * @covers Database::reconnect
     */
    public function test_reconnect_with_previous_connection()
    {
        $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $this->reflectedClass->connect();
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        /**
         * @var Database
         */
        $first_instance_of_connection = $connection->getValue($this->reflectedClass);
        $this->reflectedClass->reconnect();
        /**
         * @var Database
         */
        $second_instance_of_connection = $connection->getValue($this->reflectedClass);
        $first_connection_hash=spl_object_hash($first_instance_of_connection);
        $second_connection_hash=spl_object_hash($second_instance_of_connection);
        $this->assertEquals($first_instance_of_connection, $second_instance_of_connection);
        $this->assertNotEquals($first_connection_hash,$second_connection_hash);

        $this->assertNotNull($first_instance_of_connection);
        $this->assertNotNull($second_instance_of_connection);
    }
}