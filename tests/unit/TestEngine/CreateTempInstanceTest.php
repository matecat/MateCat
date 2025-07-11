<?php

use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\DeepL;
use Utils\Engines\EnginesFactory;


/**
 * @group  regression
 * @covers EnginesFactory::createTempInstance
 * User: dinies
 * Date: 20/04/16
 * Time: 18.49
 */
class CreateTempInstanceTest extends AbstractTest {

    /**
     * @var EngineStruct
     */
    protected $engine_struct_param;


    /**
     * It checks if the creation of an engine instance is successfully created when it invokes the method.
     * @group  regression
     * @covers EnginesFactory::createTempInstance
     */
    public function test_createTempInstance_of_constructed_engine() {

        $this->engine_struct_param = new EngineStruct();

        $this->engine_struct_param->type       = EngineConstants::MT;
        $this->engine_struct_param->class_load = "DeepL";


        $engine = EnginesFactory::createTempInstance( $this->engine_struct_param );
        $this->assertTrue( $engine instanceof DeepL );
    }
}