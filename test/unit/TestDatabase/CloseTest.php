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
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->reflectedClass->close();
        $this->property = $this->reflector->getProperty('instance');
        $this->property->setAccessible(true);
        $this->property->setValue($this->reflectedClass, null);
        $this->reflectedClass->close();
    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * It tests that after the call of the method 'close', the variable connection
     * of the instance of database will be set NULL.
     * @group regression
     * @covers Database::close
     */
    public function test_close()
    {

        /**
         * @var Database
         */
        $instance_to_close = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $instance_to_close->connect();
        $method = $this->reflector->getMethod("close");
        $method->invoke($instance_to_close);
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $current_value = $connection->getValue($instance_to_close);
        $this->assertNull($current_value);
    }
}