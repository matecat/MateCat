<?php

use TestHelpers\AbstractTest;


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
    protected $current_value;
    protected $property;

    public function setUp()
    {
        parent::setUp();

        $copy_server = INIT::$DB_SERVER;
        $copy_user = INIT::$DB_USER;
        $copy_password = INIT::$DB_PASS;
        $copy_database = INIT::$DB_DATABASE;
        $this->expected_value = new PDO(
            "mysql:host={$copy_server};dbname={$copy_database};charset=UTF8",
            $copy_user,
            $copy_password,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION // Raise exceptions on errors
            ));


        $this->databaseInstance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->property         = $this->reflector->getProperty('instance');
        $this->databaseInstance->close();
        $this->property->setAccessible(true);
        $this->property->setValue($this->databaseInstance, null);
        $this->instance_after_reset = $this->databaseInstance->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);


    }

    public function tearDown()
    {
        $this->databaseInstance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->databaseInstance->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * It checks the correct creation of a instance of PDO caused by 'getConnection';
     * @group regression
     * @covers Database::getConnection
     */
    public function test_getConnection_null_value()
    {
        $this->current_value = $this->instance_after_reset->getConnection();
        $this->assertTrue($this->current_value instanceof PDO);
        $this->assertNotNull($this->current_value);
        $this->assertEquals($this->expected_value, $this->current_value);
        $this->assertNotEquals(spl_object_hash($this->expected_value), spl_object_hash($this->current_value));
    }

    /**
     * This test checks that if the property connection isn't an instance of PDO,
     * the method getConnection will create a new PDO object like the expected object.
     * @group regression
     * @covers Database::getConnection
     */
    public function test_getConnection_not_instance_of_PDO()
    {
        $this->property = $this->reflector->getProperty('connection');
        $this->property->setAccessible(true);
        $this->property->setValue($this->instance_after_reset, array('x' => "hello", 'y' => "man"));
        $this->current_value = $this->instance_after_reset->getConnection();
        $this->assertTrue($this->current_value instanceof PDO);
        $this->assertNotNull($this->current_value);
        $this->assertEquals($this->expected_value, $this->current_value);
    }

    /**
     * This test checks that the property 'connection' initialized by getConnection is the expected PDO
     * instance created with the values of the global variables relatives to database
     * @group regression
     * @covers Database::getConnection
     */
    public function test_getConnection_instance_of_PDO()
    {
        $this->property = $this->reflector->getProperty('connection');
        $this->property->setAccessible(true);
        $this->property->setValue($this->instance_after_reset, $this->expected_value);
        $this->current_value = $this->instance_after_reset->getConnection();

        $this->assertTrue($this->current_value instanceof PDO);
        $this->assertNotNull($this->current_value);
        $this->assertEquals($this->expected_value, $this->current_value);
    }

}