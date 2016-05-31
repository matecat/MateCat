<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_sanitizeInput
 * User: dinies
 * Date: 19/04/16
 * Time: 16.07
 */
class SanitizeInputTest extends AbstractTest
{
    protected $reflector;
    protected $method;
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $struct_input;

    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_sanitizeInput");
        $this->method->setAccessible(true);


    }

    /**
     * @param EnginesModel_EngineStruct
     * It sanitizes a struct with correct type and particular name with critical characters ( " , ' ).
     * @group regression
     * @covers DataAccess_AbstractDao::_sanitizeInput
     */
    public function test__sanitizeInput_with_correct_type_and_param()
    {

        $this->struct_input = new EnginesModel_EngineStruct();
        $this->struct_input->name = <<<LABEL
ba""r/foo'
LABEL;
        $type = "EnginesModel_EngineStruct";
        $this->assertEquals($this->struct_input, $this->method->invoke($this->reflectedClass, $this->struct_input, $type));
        $this->assertTrue($this->method->invoke($this->reflectedClass, $this->struct_input, $type) instanceof EnginesModel_EngineStruct);
    }


    /**
     * @param Chunks_ChunkStruct
     * It trows an exception because the struct isn't an instnce of  'EnginesModel_EngineStruct' .
     * @group regression
     * @covers DataAccess_AbstractDao::_sanitizeInput
     */
    public function test__sanitizeInput_with_wrong_param_not_instance_of_type()
    {


        $this->struct_input = new Chunks_ChunkStruct();

        $this->struct_input->owner = <<<LABEL
ba""r/foo'
LABEL;
        $type = "EnginesModel_EngineStruct";
        $this->setExpectedException("Exception");
        $invoke = $this->method->invoke($this->reflectedClass, $this->struct_input, $type);
        $this->assertFalse($invoke instanceof EnginesModel_EngineStruct);
    }

}