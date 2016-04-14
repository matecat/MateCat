<?php

/**
 * @group regression
 * @covers Database::close
 * User: dinies
 * Date: 12/04/16
 * Time: 16.22
 */
class CloseTest extends AbstractTest
{

    /**
     * @group regression
     * @covers Database::close
     */
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
     * @covers Database::close
     */
    public function test_close()
    {

        /**
         * @var Database
         */
        $instance_to_close = $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $instance_to_close->connect();
        $method = $this->reflector->getMethod("close");
        $method->invoke($instance_to_close);
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $current_value = $connection->getValue($instance_to_close);
        $this->assertNull($current_value);
    }
}