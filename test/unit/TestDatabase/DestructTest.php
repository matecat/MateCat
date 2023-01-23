<?php

/**
 * @group regression
 * @covers Database::__destruct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.56
 */
class DestructTest extends AbstractTest
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
     * It tests that the destructor works correctly.
     * @group regression
     * @covers Database::__destruct
     */
    public function test___destruct()
    {

        /**
         * @var Database
         */
        $instance_to_destruct = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $instance_to_destruct->connect();
        $method = $this->reflector->getMethod("__destruct");
        $method->invoke($instance_to_destruct);
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $current_value = $connection->getValue($instance_to_destruct);
        $this->assertNull($current_value);
    }

}