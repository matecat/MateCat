<?php

use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\AbstractEngine;
use Utils\Engines\NONE;


/**
 * @group   regression
 * @covers  AbstractEngine::__get
 * User: dinies
 * Date: 26/04/16
 * Time: 17.05
 */
class MagicGetTest extends AbstractTest {
    /**
     * @var EngineStruct
     */
    protected $engine_struct_param;
    protected $reflector;
    /**
     * @var NONE
     */
    protected $engine;

    public function setUp(): void {
        parent::setUp();
        $this->engine_struct_param                   = new EngineStruct();
        $this->engine_struct_param->type             = EngineConstants::MT;
        $this->engine_struct_param->name             = "DeepLingoTestEngine";
        $this->engine_struct_param->others           = [ 'alfa' => "one", 'beta' => "two" ];
        $this->engine_struct_param->extra_parameters = [ 'gamma' => "three", 'delta' => "four" ];


        $this->engine = new NONE( $this->engine_struct_param );


    }

    /**
     * @group   regression
     * @covers  AbstractEngine::__get
     */
    public function test_magic__get() {

        $this->assertEquals( "DeepLingoTestEngine", $this->engine->name );
        $this->assertEquals( EngineConstants::MT, $this->engine->type );
        $this->assertEquals( "one", $this->engine->alfa );
        $this->assertEquals( "two", $this->engine->beta );
        $this->assertEquals( "three", $this->engine->gamma );
        $this->assertEquals( "four", $this->engine->delta );
    }

    /**
     * @group   regression
     * @covers  AbstractEngine::__get
     */
    public function test_magic__get_with_not_existent_variable() {

        $this->assertNull( $this->engine->notExistentVariable );
    }


}