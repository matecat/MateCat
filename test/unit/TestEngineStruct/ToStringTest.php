<?php

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
    }


    /**
     * It checks if the string of summary useful for confrontations between instances of engines is created correctly.
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetUnset
     */
    public function test_offsetUnset_id_field()
    {
        $this->engine_struct_param->id = 10 ;
        $this->engine_struct_param->name = "bar" ;
        $this->engine_struct_param->description = "bar and foo and foo again but suddenly bar" ;
        $expected_string="10barbar and foo and foo again but suddenly bar";

        $this->assertEquals($expected_string,$this->engine_struct_param->__toString());
    }
}