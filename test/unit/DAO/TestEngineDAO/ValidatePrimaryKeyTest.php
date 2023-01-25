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
    {
        parent::setUp();
        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_validatePrimaryKey");
        $this->method->setAccessible(true);
        $this->engine_struct_param = new EnginesModel_EngineStruct();

    }

    /**
     * It checks that 'id' and 'uid' aren't null values.
     * @group regression
     * @covers EnginesModel_EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey_valid_fields()
    {

        $this->engine_struct_param->id = 33;
        $this->engine_struct_param->uid = 1;

        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }


    /**
     * It will raise an exception when it checks that 'id' field is NULL.
     * @group regression
     * @covers EnginesModel_EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey_invalid_id()
    {

        $this->engine_struct_param->id = NULL;
        $this->engine_struct_param->uid = 1;
        $this->setExpectedException("Exception");
        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }


    /**
     * It will raise an exception when it checks that 'uid' field is NULL.
     * @group regression
     * @covers EnginesModel_EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey_invalid_uid()
    {

        $this->engine_struct_param->id = 33;
        $this->engine_struct_param->uid = NULL;
        $this->setExpectedException("Exception");
        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }
}