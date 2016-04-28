<?php

/**
 * @group regression
 * @covers EnginesModel_EngineStruct::offsetGet
 * User: dinies
 * Date: 20/04/16
 * Time: 19.15
 */
class OffsetGetTest   extends AbstractTest
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
     * It takes the value of the variable that correspond to @param string
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetGet
     */
    public function test_offsetGet_id_field()
    {
        $this->engine_struct_param->id = 10 ;

        $this->assertEquals(10,$this->engine_struct_param->offsetGet("id"));
    }


}