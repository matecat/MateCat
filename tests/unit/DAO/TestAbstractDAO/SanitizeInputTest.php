<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Jobs\JobStruct;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers Model\DataAccess\AbstractDao::_sanitizeInput
 * User: dinies
 * Date: 19/04/16
 * Time: 16.07
 */
class SanitizeInputTest extends AbstractTest
{
    protected ReflectionClass $reflector;
    protected ReflectionMethod $method;
    /**
     * @var EngineStruct
     */
    protected EngineStruct $struct_input;

    protected EngineDAO $engineDAO;

    /**
     * @throws ReflectionException
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->engineDAO = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->engineDAO);
        $this->method = $this->reflector->getMethod("_sanitizeInput");
    }

    /**
     * It sanitizes a struct with the correct type and particular name with critical characters ( " , ' ).
     *
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInput
     * @throws ReflectionException
     */
    public function test__sanitizeInput_with_correct_type_and_param()
    {
        $this->struct_input = new EngineStruct();
        $this->struct_input->name = <<<LABEL
ba""r/foo'
LABEL;
        $type = EngineStruct::class;
        $this->assertEquals($this->struct_input, $this->method->invoke($this->engineDAO, $this->struct_input, $type));
        $this->assertTrue($this->method->invoke($this->engineDAO, $this->struct_input, $type) instanceof EngineStruct);
    }


    /**
     * It trows an exception because the struct isn't an instnce of  'EngineStruct' .
     *
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInput
     * @throws ReflectionException
     */
    public function test__sanitizeInput_with_wrong_param_not_instance_of_type()
    {
        $struct_input = new JobStruct();

        $struct_input->owner = <<<LABEL
ba""r/foo'
LABEL;
        $type = EngineStruct::class;
        $this->expectException("Exception");
        $invoke = $this->method->invoke($this->engineDAO, $struct_input, $type);
        $this->assertFalse($invoke instanceof EngineStruct);
    }

}