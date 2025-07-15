<?php

use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers \Model\DataAccess\Database::useDb
 * User: dinies
 * Date: 12/04/16
 * Time: 16.49
 */
class UseDbTest extends AbstractTest {

    /**
     * @var \Model\DataAccess\Database|IDatabase
     */
    protected $databaseInstance;

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
    }

    public function tearDown(): void {
        $this->databaseInstance->useDb( 'unittest_matecat_local' );
        parent::tearDown();
    }

    /**
     * This test confirms that 'useDB' change correctly the value
     * of the protected variable 'database' in the current instance of the database class.
     * @group  regression
     * @covers Database::useDb
     */
    public function test_useDb_check_private_variable() {

        $this->databaseInstance->useDb( 'information_schema' );

        $reflector = new ReflectionClass( $this->databaseInstance );
        $property  = $reflector->getProperty( 'database' );
        $property->setAccessible( true );

        $current_database_value = $property->getValue( $this->databaseInstance );
        $this->assertEquals( "information_schema", $current_database_value );
    }
}