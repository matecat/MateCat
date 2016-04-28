<?php

/**
 * @group regression
 * @covers EnginesModel_EngineStruct::offsetExists
 * User: dinies
 * Date: 20/04/16
 * Time: 19.10
 */
class OffsetExistsTest extends AbstractTest
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
     * @return bool
     * It controls if the given field exists ( != NULL)  in the struct.
     * @group regression
     * @covers EnginesModel_EngineStruct::getStruct
     */
    public function test_offsetExists_id_field()
    {
        $this->engine_struct_param->id = 10 ;

        $this->assertTrue($this->engine_struct_param->offsetExists("id"));
    }
}