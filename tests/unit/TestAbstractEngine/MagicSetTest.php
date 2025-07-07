<?php

use Model\Engines\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\AbstractEngine;
use Utils\Engines\NONE;


/**
 * @group   regression
 * @covers  AbstractEngine::__set
 * User: dinies
 * Date: 26/04/16
 * Time: 17.12
 */
class MagicSetTest extends AbstractTest {
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
        $this->engine                                = new NONE( $this->engine_struct_param );


    }

    /**
     * @group   regression
     * @covers  AbstractEngine::__set
     */
    public function test_magic__set_protected_variable_in_engine_struct() {

        $this->engine->name = "DeepLingo_Changed_name";
        $this->assertEquals( "DeepLingo_Changed_name", $this->engine->name );

        $this->engine->type = "MT_Changed";
        $this->assertEquals( "MT_Changed", $this->engine->type );

    }

    /**
     * @group   regression
     * @covers  AbstractEngine::__set
     */
    public function test_magic__set_values_in_array_variable_others() {

        $this->engine->alfa = "one_changed";

        $this->assertEquals( "one_changed", $this->engine->alfa );

        $this->engine->beta = "two_changed";

        $this->assertEquals( "two_changed", $this->engine->beta );

    }


    /**
     * @group   regression
     * @covers  AbstractEngine::__set
     */
    public function test_magic__set_values_in_array_variable_extra_parameters() {

        $this->engine->gamma = "three_changed";

        $this->assertEquals( "three_changed", $this->engine->gamma );

        $this->engine->delta = "four_changed";


        $this->assertEquals( "four_changed", $this->engine->delta );
    }

    /**
     * @group   regression
     * @covers  AbstractEngine::__set
     */
    public function test_magic__set_values_with_not_existent_variable() {

        $this->expectException( DomainException::class );
        $this->engine->notExistentVariable = "bar and foo";
    }
}