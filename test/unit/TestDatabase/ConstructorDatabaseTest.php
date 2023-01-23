<?php

/**
 * @group regression
 * @covers Database::__construct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.51
 */
class ConstructorDatabaseTest extends AbstractTest
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

    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * This test checks that an Exception will be raised if the constructor is called without parameters.
     * @group regression
     * @covers Database::__construct
     */
    public function test___construct_without_parameters()
    {

        $this->property->setValue($this->reflectedClass, null);

        $this->setExpectedException('\InvalidArgumentException');

        $this->reflectedClass->obtain();


    }


}