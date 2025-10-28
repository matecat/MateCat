<?php

use Model\DataAccess\Database;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers Database::getConnection
 * User: dinies
 * Date: 11/04/16
 * Time: 19.06
 */
class GetConnection extends AbstractTest {
    protected $reflector;
    /**
     * @var \Model\DataAccess\Database
     */
    protected $instance_after_reset;
    protected $expected_value;
    protected $current_value;
    protected $property;

    public function setUp(): void {
        parent::setUp();

        $copy_server          = AppConfig::$DB_SERVER;
        $copy_user            = AppConfig::$DB_USER;
        $copy_password        = AppConfig::$DB_PASS;
        $copy_database        = AppConfig::$DB_DATABASE;
        $this->expected_value = new PDO(
                "mysql:host={$copy_server};dbname={$copy_database};charset=UTF8",
                $copy_user,
                $copy_password,
                [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION // Raise exceptions on errors
                ] );


        $this->databaseInstance = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->property         = $this->reflector->getProperty( 'instance' );
        $this->databaseInstance->close();
        
        $this->property->setValue( $this->databaseInstance, null );
        $this->instance_after_reset = $this->databaseInstance->obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );


    }

    public function tearDown(): void {
        $this->databaseInstance = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
        $this->databaseInstance->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * It checks the correct creation of a instance of PDO caused by 'getConnection';
     * @group  regression
     * @covers Database::getConnection
     */
    public function test_getConnection_null_value() {
        $this->current_value = $this->instance_after_reset->getConnection();
        $this->assertTrue( $this->current_value instanceof PDO );
        $this->assertNotNull( $this->current_value );
        $this->assertEquals( $this->expected_value, $this->current_value );
        $this->assertNotEquals( spl_object_hash( $this->expected_value ), spl_object_hash( $this->current_value ) );
    }

    /**
     * This test checks that if the property connection isn't an instance of PDO,
     * the method getConnection will create a new PDO object like the expected object.
     * @group  regression
     * @covers Database::getConnection
     */
    public function test_getConnection_not_instance_of_PDO() {
        $this->property = $this->reflector->getProperty( 'connection' );
        
        $this->property->setValue( $this->instance_after_reset, [ 'x' => "hello", 'y' => "man" ] );
        $this->current_value = $this->instance_after_reset->getConnection();
        $this->assertTrue( $this->current_value instanceof PDO );
        $this->assertNotNull( $this->current_value );
        $this->assertEquals( $this->expected_value, $this->current_value );
    }

    /**
     * This test checks that the property 'connection' initialized by getConnection is the expected PDO
     * instance created with the values of the global variables relatives to database
     * @group  regression
     * @covers Database::getConnection
     */
    public function test_getConnection_instance_of_PDO() {
        $this->property = $this->reflector->getProperty( 'connection' );
        
        $this->property->setValue( $this->instance_after_reset, $this->expected_value );
        $this->current_value = $this->instance_after_reset->getConnection();

        $this->assertTrue( $this->current_value instanceof PDO );
        $this->assertNotNull( $this->current_value );
        $this->assertEquals( $this->expected_value, $this->current_value );
    }

}