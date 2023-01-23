<?php

/**
 * @group  regression
 * @covers Database::escape
 * User: dinies
 * Date: 13/04/16
 * Time: 18.00
 */
class EscapeTest extends AbstractTest {
    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $alfa_instance;
    protected $sql_create;
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

        $this->sql_create = "CREATE TABLE Phrases( Piece VARCHAR(255) )";
        $this->sql_drop   = "DROP TABLE Phrases";
        $this->sql_read   = "SELECT * FROM Phrases";


    }

    public function tearDown() {

        $this->reflectedClass = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * @param string
     * It checks that a source string will match with the correctly escaped expected string.
     *
     * @group  regression
     * @covers Database::escape
     * User: dinies
     */
    public function test_escape_with_simple_string() {


        $source = <<<LABEL
a wolf isn't a "dog"
LABEL;
        $actual = $this->alfa_instance->escape( $source );

        $expected = <<<LABEL
a wolf isn\'t a \"dog\"
LABEL;

        $this->assertEquals( $expected, $actual );
    }

    /**
     * It checks that the escaped string will be inserted in the database and that it will be visible in the table 'Phrases'.
     * @group  regression
     * @covers Database::escape
     * User: dinies
     */
    public function test_escape_and_insert_the_result_in_db() {

        $source                  = <<<LABEL
a w''olf "i"sn'''t a' "dog"
LABEL;
        $actual                  = $this->alfa_instance->escape( $source );
        $sql_insert_source_value = "INSERT INTO Phrases (Piece) VALUES ('$actual')";
        $expected_string         = <<<LABEL
a w\'\'olf \"i\"sn\'\'\'t a\' \"dog\"
LABEL;
        $this->assertEquals( $expected_string, $actual );

        $expected = ( [ 0 => [ "Piece" => $source ] ] );
        $this->alfa_instance->getConnection()->query( $this->sql_create );
        $this->alfa_instance->getConnection()->query( $sql_insert_source_value );
        $read_result = $this->alfa_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );

        $this->assertEquals( $expected, $read_result );

        $this->alfa_instance->getConnection()->query( $this->sql_drop );

    }

}