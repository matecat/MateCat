<?php

/**
 * @group  regression
 * @covers Database::last_insert
 * User: dinies
 * Date: 13/04/16
 * Time: 17.29
 */
class LastInsertTest extends AbstractTest {
    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $alfa_instance;
    protected $sql_create;
    protected $sql_insert_first_value;
    protected $sql_insert_second_value;
    protected $sql_read;
    protected $sql_drop;

    public function setUp() {
        parent::setUp();
        $this->reflectedClass = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflector      = new ReflectionClass( $this->reflectedClass );
        $this->property       = $this->reflector->getProperty( 'instance' );
        $this->reflectedClass->close();
        $this->property->setAccessible( true );
        $this->property->setValue( $this->reflectedClass, null );
        $this->alfa_instance = $this->reflectedClass->obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );


        $this->sql_create              = "CREATE TABLE Persons( PersonID INT )";
        $this->sql_insert_first_value  = "INSERT INTO Persons VALUES (475144 )";
        $this->sql_insert_second_value = "INSERT INTO Persons VALUES (890788 )";
        $this->sql_drop                = "DROP TABLE Persons";
        $this->sql_read                = "SELECT * FROM Persons";


    }

    public function tearDown() {

        $this->alfa_instance->getConnection()->query( $this->sql_drop );
        $this->reflectedClass = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * It tests that the Increment_index_ID of the last insertion is equal to the expected value,
     * in this particular case there isn't any insertion with the aid of an incrementation_index
     * so the return of last_insert will be forced to 0 for any number of single performed insertions made.
     * @group  regression
     * @covers Database::last_insert
     * User: dinies
     */
    public function test_last_insert_simple_table() {
        $this->alfa_instance->getConnection()->query( $this->sql_create );
        $this->alfa_instance->getConnection()->query( $this->sql_insert_first_value );
        $this->alfa_instance->getConnection()->query( $this->sql_insert_second_value );

        $result = $this->alfa_instance->last_insert();
        $this->assertEquals( 0, $result );
    }

}