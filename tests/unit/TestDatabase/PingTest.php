<?php

use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


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

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
    }

    public function tearDown(): void {
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