<?php

/**
 * @group regression
 * @covers Database::useDb
 * User: dinies
 * Date: 12/04/16
 * Time: 16.49
 */
class UseDbTest extends AbstractTest
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
    }

    public function tearDown()
    {
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * This test confirm that 'useDB' change correctly the value
     * of the protected variable 'database' in current instance of database.
     * @group regression
     * @covers Database::useDb
     */
    public function test_useDb_check_private_variable(){

        /** @var Database $db */
        $db =  $this->reflectedClass;

        $instance_after_reset = $db->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $instance_after_reset->useDb('information_schema');
        $this->property = $this->reflector->getProperty('database');
        $this->property->setAccessible(true);
        $current_database_value = $this->property->getValue($instance_after_reset);
        $this->assertEquals("information_schema",$current_database_value);
    }
}