<?php

/**
 * @group regression
 * @covers Database::__construct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.51
 */
class ConstructTest extends AbstractTest
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

    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $this->reflectedClass->close();
        startConnection();
    }

    /**
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