<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Database::useDb
 * User: dinies
 * Date: 12/04/16
 * Time: 16.49
 */
class UseDbTest extends AbstractTest {

    /**
     * @var Database|IDatabase
     */
    protected $jobDao;

    public function setUp(): void {
        parent::setUp();
        $this->jobDao = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
    }

    public function tearDown(): void {
        $this->jobDao->useDb( 'unittest_matecat_local' );
        parent::tearDown();
    }

    /**
     * This test confirms that 'useDB' change correctly the value
     * of the protected variable 'database' in the current instance of the database class.
     * @group  regression
     * @covers Database::useDb
     */
    public function test_useDb_check_private_variable() {

        $this->jobDao->useDb( 'information_schema' );

        $reflector = new ReflectionClass( $this->jobDao );
        $property  = $reflector->getProperty( 'database' );
        $property->setAccessible( true );

        $current_database_value = $property->getValue( $this->jobDao );
        $this->assertEquals( "information_schema", $current_database_value );
    }
}