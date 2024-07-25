<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers EnginesModel_EngineStruct::__toString
 * User: dinies
 * Date: 20/04/16
 * Time: 19.22
 */
class ToStringTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    public function setUp()
    {
        parent::setUp();
        $this->engine_struct_param = new EnginesModel_EngineStruct();
        $this->engine_struct_param->id = 999 ; //sample value
        $this->engine_struct_param->name = "Moses_bar_and_foo";
        $this->engine_struct_param->description = "Machine translation from bar and foo.";
    }


    /**
     * It checks if the string of summary useful for confrontations between instances of engines is being created correctly.
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetUnset
     */
    public function test_toString_field()
    {
        $expected_string= '999Moses_bar_and_fooMachine translation from bar and foo.';
        
        $this->assertEquals($expected_string,$this->engine_struct_param->__toString());
    }
}