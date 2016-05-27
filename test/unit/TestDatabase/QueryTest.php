<?php

/**
 * @group regression
 * @covers Database::query 
 * User: dinies
 * Date: 12/04/16
 * Time: 19.45
 */
class QueryTest extends AbstractTest
{

    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $alfa_instance;
    protected $affected_rows;
    protected $sql_create;
    protected $sql_read;
    protected $sql_insert_value;
    protected $sql_drop;
    public function setUp()
    {
       parent::setUp();
        $this->reflectedClass = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->property = $this->reflector->getProperty('instance');
        $this->reflectedClass->close();
        $this->property->setAccessible(true);
        $this->property->setValue($this->reflectedClass, null);
        $this->property = $this->reflector->getProperty('affected_rows');
        $this->property->setAccessible(true);
        $this->alfa_instance = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);

        $this->sql_create = "CREATE TABLE Persons( PersonID INT )";
        $this->sql_drop="DROP TABLE Persons";
        $this->sql_insert_value = "INSERT INTO Persons VALUES (475144 )";
        $this->sql_read = "SELECT * FROM Persons";


        $this->alfa_instance->query($this->sql_create);
        $this->alfa_instance->query($this->sql_insert_value);
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
     * It checks the behaviour of the function query with a valid sql statement.
     * @group regression
     * @covers Database::query
     */
    public function test_query_valid()
    {
        $result = $this->alfa_instance->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);
        $this->affected_rows= $this->property->getValue($this->alfa_instance);

        $this->assertEquals(array(0 => array("PersonID" =>475144)), $result);
        $this->assertEquals(1, $this->affected_rows);
    }

}