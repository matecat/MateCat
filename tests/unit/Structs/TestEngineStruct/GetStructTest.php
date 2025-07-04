<?php

use Model\Engines\EngineStruct;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers EngineStruct::getStruct
 * User: dinies
 * Date: 20/04/16
 * Time: 18.57
 */
class GetStructTest extends AbstractTest {
    protected $array_param;
    protected $reflector;
    protected $method;

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = new EngineStruct;
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "getStruct" );
    }

    /**
     * @return EngineStruct
     * It returns a EngineStruct
     * @group  regression
     * @covers EngineStruct::getStruct
     */
    public function test_getStruct_simple() {
        $this->assertTrue( $this->method->invoke( $this->databaseInstance, null ) instanceof EngineStruct );
    }
}