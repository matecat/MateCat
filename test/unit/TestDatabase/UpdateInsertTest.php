<?php

/**
 * @group  regression
 * @covers Database::update
 * @covers Database::insert
 * User: dinies
 * Date: 13/04/16
 * Time: 15.36
 */
class UpdateInsertTest extends AbstractTest {
    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $alfa_instance;
    protected $affected_rows;
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
        $this->property = $this->reflector->getProperty( 'affected_rows' );
        $this->property->setAccessible( true );
        $this->alfa_instance = $this->reflectedClass->obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );

        $this->sql_create              = "CREATE TABLE Persons( PersonID INT )";
        $this->sql_insert_first_value  = "INSERT INTO Persons VALUES (475144 )";
        $this->sql_insert_second_value = "INSERT INTO Persons VALUES (890788 )";
        $this->sql_drop                = "DROP TABLE Persons";


        $this->sql_read = "SELECT * FROM Persons";


        $this->alfa_instance->getConnection()->query( $this->sql_create );
        $this->alfa_instance->getConnection()->query( $this->sql_insert_first_value );
        $this->alfa_instance->getConnection()->query( $this->sql_insert_second_value );


    }

    public function tearDown() {

        $this->alfa_instance->getConnection()->query( $this->sql_drop );
        $this->reflectedClass = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * Equivalent query executed from update :
     *     $query = "UPDATE Persons
     *    SET PersonID=(678432)
     *    WHERE PersonID=(475144);";
     * @group  regression
     * @covers Database::update
     * User: dinies
     */
    public function test_update_simple_table() {


        $table = "Persons";
        $data  = [ 'PersonID' => 678432 ];
        $where = [ "PersonID" => 475144 ];
        $this->alfa_instance->update( $table, $data, $where );
        $this->affected_rows = $this->property->getValue( $this->alfa_instance );

        $expected = [ 0 => [ "PersonID" => 678432 ], 1 => [ "PersonID" => 890788 ] ];
        $actual   = $this->alfa_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );

        $this->assertEquals( $expected, $actual );
        $this->assertEquals( 1, $this->affected_rows );

    }

    /**
     * This test perform an insertion in DB and checks if the operation  succeeded
     * @group  regression
     * @covers Database::insert
     */
    public function test_insert_simple_value() {


        $table = "Persons";
        $data  = [ 'PersonID' => 678432 ];

        $this->alfa_instance->insert( $table, $data );
        $this->affected_rows = $this->property->getValue( $this->alfa_instance );

        $expected = [ 0 => [ "PersonID" => 475144 ], 1 => [ "PersonID" => 890788 ], 2 => [ "PersonID" => 678432 ] ];
        $actual   = $this->alfa_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );


        $this->assertEquals( $expected, $actual );
        $this->assertEquals( 1, $this->affected_rows );

    }
}