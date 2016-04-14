<?php

/**
 * @group regression
 * @covers Database::obtain
 * User: dinies
 * Date: 11/04/16
 * Time: 16.22
 */
class ObtainTest extends AbstractTest
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
     * @covers Database::obtain
     */
    public function test_obtain_with_null_parameter_instance()
    {
        $this->property->setValue($this->reflectedClass, null);
        /**
         * @var Database
         */
        $instance_after_reset = $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");

        $this->assertTrue($instance_after_reset instanceof Database);
        $this->assertNotNull($instance_after_reset);
    }

    /**
     * @group regression
     * @covers Database::obtain
     */
    public function test_obtain_confrontation_between_two_instances_with_same_values()
    {
        /**
         * @var Database
         */
        $first_instance = $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        /**
         * @var Database
         */
        $second_instance = $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $hash_first_instance = spl_object_hash($first_instance);
        $hash_second_instance = spl_object_hash($second_instance);

        $this->assertEquals($first_instance, $second_instance);
        $this->assertNotEquals($hash_first_instance, $hash_second_instance);
    }
}