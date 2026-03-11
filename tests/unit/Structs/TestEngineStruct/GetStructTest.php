<?php

use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers EngineStruct::getStruct
 * User: dinies
 * Date: 20/04/16
 * Time: 18.57
 */
class GetStructTest extends AbstractTest
{
    protected ReflectionMethod $method;
    protected EngineStruct $engineStruct;

    public function setUp(): void
    {
        parent::setUp();
        $this->engineStruct = new EngineStruct;
        $reflector = new ReflectionClass($this->engineStruct);
        $this->method = $reflector->getMethod("getStruct");
    }

    /**
     * It returns an EngineStruct
     * @group  regression
     * @covers EngineStruct::getStruct
     * @throws ReflectionException
     */
    public function test_getStruct_simple()
    {
        $this->assertTrue($this->method->invoke($this->engineStruct, null) instanceof EngineStruct);
    }
}