<?php

/**
 * @group regression
 * @covers EnginesModel_EngineStruct::offsetSet
 * User: dinies
 * Date: 20/04/16
 * Time: 19.18
 */
class OffsetSet extends  AbstractTest
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
     * It sets the value of the variable that correspond to the first @param with the value stored in the second @param
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetSet
     */
    public function test_offsetSet_id_field()
    {
        $this->engine_struct_param->id = 10 ;

        $this->assertEquals(10,$this->engine_struct_param->id);
        $this->engine_struct_param->offsetSet("id",999);
        $this->assertEquals(999,$this->engine_struct_param->id);

    }
}