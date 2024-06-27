<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers Engines_MyMemory::__construct
 * * User: dinies
 * Date: 28/04/16
 * Time: 15.45
 */
class ConstructorMyMemoryTest extends AbstractTest
{


    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var array
     */
    protected $others_param;
    protected $reflector;
    protected $property;
    
    public function setUp()
    {
        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct= EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = $eng[0];
    }

    /**
     * It construct an engine and it initialises some globals from the abstract constructor
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_of_sub_engine_of_moses()
    {
        $this->databaseInstance = new Engines_MyMemory($this->engine_struct_param);
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->property         = $this->reflector->getProperty("engineRecord");
        $this->property->setAccessible(true);

        $this->assertEquals($this->engine_struct_param, $this->property->getValue($this->databaseInstance));

        $this->property = $this->reflector->getProperty("className");
        $this->property->setAccessible(true);

        $this->assertEquals("Engines_MyMemory", $this->property->getValue($this->databaseInstance));

        $this->property = $this->reflector->getProperty("curl_additional_params");
        $this->property->setAccessible(true);

        $this->assertEquals(6, count($this->property->getValue($this->databaseInstance)));

    }


    /**
     * It will raise an exception constructing an engine because of he wrong property of the struct.
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_failure()
    {
        $this->engine_struct_param->type = "fooo";
        $this->setExpectedException("Exception");
        new Engines_MyMemory($this->engine_struct_param);
    }
}