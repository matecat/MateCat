<?php

/**
 * @group regression
 * @covers EnginesModel_EngineStruct::offsetUnset
 * User: dinies
 * Date: 20/04/16
 * Time: 19.20
 */
class OffsetUnset extends AbstractTest
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
     *It destructs the value of the variable that correspond to parameter string
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetUnset
     */
    public function test_offsetUnset_id_field()
    {
        $this->engine_struct_param->id = 10 ;

        $this->assertEquals(10,$this->engine_struct_param->id);
        $this->engine_struct_param->offsetUnset("id");
        $this->assertNull($this->engine_struct_param->id);

    }
}