<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers EnginesModel_EngineStruct::getStruct
 * User: dinies
 * Date: 20/04/16
 * Time: 18.57
 */
class GetStructTest extends AbstractTest
{
    protected $array_param;
    protected $reflector;
    protected $method;

    public function setUp()
    {
        parent::setUp();
        $this->databaseInstance = new EnginesModel_EngineStruct;
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->method           = $this->reflector->getMethod("getStruct");
    }

    /**
     * @return EnginesModel_EngineStruct
     * It returns a EnginesModel_EngineStruct
     * @group regression
     * @covers EnginesModel_EngineStruct::getStruct
     */
    public function test_getStruct_simple()
    {
        $this->assertTrue($this->method->invoke($this->databaseInstance,NULL) instanceof EnginesModel_EngineStruct);
    }
}