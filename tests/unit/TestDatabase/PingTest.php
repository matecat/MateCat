<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Database::ping
 * User: dinies
 * Date: 12/04/16
 * Time: 16.26
 */
class PingTest extends AbstractTest {

    /**
     * @var Database|IDatabase
     */
    protected $databaseInstance;

    public function setUp() {
        parent::setUp();
        $this->databaseInstance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
    }

    public function tearDown() {
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers Database::ping
     */
    public function test_ping() {
        $this->assertTrue( $this->databaseInstance->ping() );
    }
}