<?php

use Model\DataAccess\Database;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers \Model\DataAccess\Database::close
 * User: dinies
 * Date: 12/04/16
 * Time: 16.22
 */
class CloseTest extends AbstractTest
{

    protected $databaseInstance;

    public function setUp(): void
    {
        parent::setUp();
        $this->databaseInstance = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * It tests that after the call of the method 'close', the variable connection will be set NULL.
     * @group  regression
     * @covers \Model\DataAccess\Database::close
     * @throws ReflectionException
     */
    public function test_close()
    {
        $this->databaseInstance->close();

        $reflector = new ReflectionClass($this->databaseInstance);
        $connection = $reflector->getProperty('connection');

        $current_value = $connection->getValue($this->databaseInstance);
        $this->assertNull($current_value);
    }

}