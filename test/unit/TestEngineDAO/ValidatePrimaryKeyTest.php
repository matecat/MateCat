<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::_validatePrimaryKey
 * User: dinies
 * Date: 20/04/16
 * Time: 17.30
 */
class ValidatePrimaryKeyTest extends AbstractTest
{

    /**
     * @var EnginesModel_EngineDAO
     */
    protected $method;
    protected $reflector;
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    public function setUp()
    {   $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain());
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_validatePrimaryKey");
        $this->method->setAccessible(true);
        $this->engine_struct_param = new EnginesModel_EngineStruct();

    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey§_valid_fields()
    {

        $this->engine_struct_param->id = <<<LABEL
33
LABEL;

        $this->engine_struct_param->uid = <<<LABEL
1
LABEL;
        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }


    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey_invalid_id()
    {

        $this->engine_struct_param->id = NULL;

        $this->engine_struct_param->uid = <<<LABEL
1
LABEL;
        $this->setExpectedException("Exception");
        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }


    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey_invalid_uid()
    {

        $this->engine_struct_param->id = <<<LABEL
33
LABEL;
        $this->engine_struct_param->uid = NULL;
        $this->setExpectedException("Exception");
        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }
}