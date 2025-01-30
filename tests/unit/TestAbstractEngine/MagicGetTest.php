<?php

use TestHelpers\AbstractTest;


/**
 * @group   regression
 * @covers  Engines_AbstractEngine::__get
 * User: dinies
 * Date: 26/04/16
 * Time: 17.05
 */
class MagicGetTest extends AbstractTest {
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;
    protected $reflector;
    /**
     * @var Engines_NONE
     */
    protected $engine;

    public function setUp(): void {
        parent::setUp();
        $this->engine_struct_param                   = new EnginesModel_EngineStruct();
        $this->engine_struct_param->type             = Constants_Engines::MT;
        $this->engine_struct_param->name             = "DeepLingoTestEngine";
        $this->engine_struct_param->others           = [ 'alfa' => "one", 'beta' => "two" ];
        $this->engine_struct_param->extra_parameters = [ 'gamma' => "three", 'delta' => "four" ];


        $this->engine = new Engines_NONE( $this->engine_struct_param );


    }

    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::__get
     */
    public function test_magic__get() {

        $this->assertEquals( "DeepLingoTestEngine", $this->engine->name );
        $this->assertEquals( Constants_Engines::MT, $this->engine->type );
        $this->assertEquals( "one", $this->engine->alfa );
        $this->assertEquals( "two", $this->engine->beta );
        $this->assertEquals( "three", $this->engine->gamma );
        $this->assertEquals( "four", $this->engine->delta );
    }

    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::__get
     */
    public function test_magic__get_with_not_existent_variable() {

        $this->assertNull( $this->engine->notExistentVariable );
    }


}