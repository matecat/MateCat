<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers EngineDAO::_validatePrimaryKey
 * User: dinies
 * Date: 20/04/16
 * Time: 17.30
 */
#[Group('PersistenceNeeded')]
class ValidatePrimaryKeyTest extends AbstractTest
{

    protected ReflectionMethod $method;
    protected ReflectionClass $reflector;
    /**
     * @var EngineStruct
     */
    protected EngineStruct $engine_struct_param;
    protected EngineDAO $engineDAO;

    public function setUp(): void
    {
        parent::setUp();
        $this->engineDAO = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->engineDAO);
        $this->method = $this->reflector->getMethod("_validatePrimaryKey");
        $this->engine_struct_param = new EngineStruct();
    }

    /**
     * It checks that 'id' and 'uid' aren't null values.
     * Test that no exceptions are thrown
     * @group  regression
     * @covers EngineDAO::_validatePrimaryKey
     * @doesNotPerformAssertions
     */
    #[doesNotPerformAssertions]
    #[Test]
    public function test__validatePrimaryKey_valid_fields()
    {
        $this->engine_struct_param->id = 33;
        $this->engine_struct_param->uid = 1;

        $this->method->invoke($this->engineDAO, $this->engine_struct_param);
    }


    /**
     * It will raise an exception when it checks that 'id' field is NULL.
     * @group  regression
     * @covers EngineDAO::_validatePrimaryKey
     */
    #[Test]
    public function test__validatePrimaryKey_invalid_id()
    {
        $this->engine_struct_param->id = null;
        $this->engine_struct_param->uid = 1;
        $this->expectException("Exception");
        $this->method->invoke($this->engineDAO, $this->engine_struct_param);
    }


    /**
     * It will raise an exception when it checks that 'uid' field is NULL.
     * @group  regression
     * @covers EngineDAO::_validatePrimaryKey
     */
    #[Test]
    public function test__validatePrimaryKey_invalid_uid()
    {
        $this->engine_struct_param->id = 33;
        $this->engine_struct_param->uid = null;
        $this->expectException("Exception");
        $this->method->invoke($this->engineDAO, $this->engine_struct_param);
    }
}