<?php

/**
 * @group regression
 * @covers Database::fetch_array
 * User: dinies
 * Date: 13/04/16
 * Time: 15.30
 */
class FetchArrayTest extends AbstractTest
{

    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $alfa_instance;
    protected $sql_create;
    protected $sql_read;
    protected $sql_insert_first_value;
    protected $sql_insert_second_value;
    protected $sql_drop;
    public function setUp()
    {
parent::setUp();
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->property = $this->reflector->getProperty('instance');
        $this->reflectedClass->close();
        $this->property->setAccessible(true);
        $this->property->setValue($this->reflectedClass, null);
        $this->alfa_instance = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);

        $this->sql_create = "CREATE TABLE Persons( PersonID INT )";
        $this->sql_drop="DROP TABLE Persons";
        $this->sql_insert_first_value = "INSERT INTO Persons VALUES (475144 )";
        $this->sql_insert_second_value = "INSERT INTO Persons VALUES (890788 )";

        $this->sql_read = "SELECT * FROM Persons";


        $this->alfa_instance->query($this->sql_create);
        $this->alfa_instance->query($this->sql_insert_first_value);
        $this->alfa_instance->query($this->sql_insert_second_value);

    }

    public function tearDown()
    {

        $this->alfa_instance->query($this->sql_drop);
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * It tests if the method fetch_array returns an array with all the values contained in Persons table.
     * @group regression
     * @covers Database::fetch_array
     * User: dinies
     */
    public function test_fetch_array_simple_table(){
        $this->assertEquals(array(0 => array("PersonID" =>475144),1=> array("PersonID" =>890788)),$this->alfa_instance->fetch_array($this->sql_read));

    }
}