<?php

use Model\DataAccess\Database;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers \Model\DataAccess\Database::__destruct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.56
 */
class DestructTest extends AbstractTest
{

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * It tests that the destructor works correctly.
     * @group  regression
     * @covers \Model\DataAccess\Database::__destruct
     * @throws ReflectionException
     */
    public function test___destruct()
    {
        $instance_to_destruct = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
        $instance_to_destruct->connect();

        $reflector = new ReflectionClass($instance_to_destruct);
        $method = $reflector->getMethod("__destruct");
        $method->invoke($instance_to_destruct);

        $connection = $reflector->getProperty('connection');

        $current_value = $connection->getValue($instance_to_destruct);
        $this->assertNull($current_value);
    }

}