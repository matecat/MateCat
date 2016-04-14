<?php

/**
 * @group regression
 * @covers Database::getConnection
 * User: dinies
 * Date: 11/04/16
 * Time: 19.06
 */
class GetConnection extends AbstractTest
{
    protected $reflector;
    /**
     * @var Database
     */
    protected $instance_after_reset;
    protected $expected_value;

    public function setUp()
    {
        parent::setUp();

        $copy_server = "localhost";
        $copy_user = "unt_matecat_user";
        $copy_password = "unt_matecat_user";
        $copy_database = "unittest_matecat_local";
        $this->expected_value = new PDO(
            "mysql:host={$copy_server};dbname={$copy_database};charset=UTF8",
            $copy_user,
            $copy_password,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION // Raise exceptions on errors
            ));


        $this->reflectedClass = Database::obtain();
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $property = $this->reflector->getProperty('instance');
        $this->reflectedClass->close();
        $property->setAccessible(true);
        $property->setValue($this->reflectedClass, null);
        $this->instance_after_reset = $this->reflectedClass->obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");


    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain("localhost", "unt_matecat_user", "unt_matecat_user", "unittest_matecat_local");
        $this->reflectedClass->close();
        startConnection();
    }

    /**
     * @group regression
     * @covers Database::getConnection
     */
    public function test_getConnection_null_value()
    {
        $current_value =  $this->instance_after_reset->getConnection();
        $this->assertTrue($current_value instanceof PDO);
        $this->assertNotNull($current_value);
        $this->assertEquals($this->expected_value, $current_value);
        $this->assertNotEquals(spl_object_hash($this->expected_value), spl_object_hash($current_value));
    }

    /**
     * @group regression
     * @covers Database::getConnection
     */
    public function test_getConnection_not_instance_of_PDO()
    {
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $connection->setValue($this->instance_after_reset, array('x' => "hello", 'y' => "man"));
        $current_value=$this->instance_after_reset->getConnection();
        $this->assertTrue($current_value instanceof PDO);
        $this->assertNotNull($current_value);
        $this->assertEquals($this->expected_value, $current_value);
    }
    /**
     * @group regression
     * @covers Database::getConnection
     */
    public function test_getConnection_instance_of_PDO()
    {
        $connection = $this->reflector->getProperty('connection');
        $connection->setAccessible(true);
        $connection->setValue($this->instance_after_reset, $this->expected_value);
        $current_value=$this->instance_after_reset->getConnection();

        $this->assertTrue($current_value instanceof PDO);
        $this->assertNotNull($current_value);
        $this->assertEquals($this->expected_value, $current_value);
    }

}