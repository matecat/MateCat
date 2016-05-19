<?php

/**
 * @group regression
 * @covers Engine::createTempInstance
 * User: dinies
 * Date: 20/04/16
 * Time: 18.49
 */
class CreateTempInstanceTest extends AbstractTest
{

    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;



    /**
     * It checks if the creation of an istance of engine will be successfully created when it invokes the method.
     * @param EnginesModel_EngineStruct
     * @group regression
     * @covers Engine::createTempInstance
     */
    public function test_createTempInstance_of_constructed_engine(){

        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->type = "MT";
        $this->engine_struct_param->class_load = "DeepLingo";


        $engine = Engine::createTempInstance($this->engine_struct_param);
        $this->assertTrue($engine instanceof Engines_DeepLingo);
    }
}