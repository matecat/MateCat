<?php

/**
 * @group regression
 * @covers Database::ping
 * User: dinies
 * Date: 12/04/16
 * Time: 16.26
 */
class PingTest extends AbstractTest
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
        $this->reflectedClass->close();
    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $this->reflectedClass->close();
        startConnection();
    }

    /**
     * @group regression
     * @covers Database::ping
     */
    public function test_ping()
    {
        /**
         * @var Database
         */
        $instance_to_ping = $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $this->assertTrue($instance_to_ping->ping());
    }
}