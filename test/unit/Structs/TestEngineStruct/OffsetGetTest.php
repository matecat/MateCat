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
        $this->engine_struct_param->id = 999 ; //sample value
        $this->engine_struct_param->name = "Moses_bar_and_foo";
        $this->engine_struct_param->description = "Machine translation from bar and foo.";
        $this->engine_struct_param->type = "TM";
        $this->engine_struct_param->base_url = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = "contribute";
        $this->engine_struct_param->delete_relative_url = "delete";
        $this->engine_struct_param->others = "{}";
        $this->engine_struct_param->class_load = "foo_bar";
        $this->engine_struct_param->extra_parameters ="{}";
        $this->engine_struct_param->penalty = 1;
        $this->engine_struct_param->active = 4;
        $this->engine_struct_param->uid = 89999;  //sample value
    }


    /**
     * It takes the value of the variable that correspond to @param string
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetGet
     */
    public function test_offsetGet_id_field()
    {

        $this->assertEquals( 999, $this->engine_struct_param->offsetGet("id"));
        $this->assertEquals( "Moses_bar_and_foo" , $this->engine_struct_param->offsetGet("name"));
        $this->assertEquals( "Machine translation from bar and foo." , $this->engine_struct_param->offsetGet("description"));
        $this->assertEquals( "TM" , $this->engine_struct_param->offsetGet("type"));
        $this->assertEquals( "http://mtserver01.deepfoobar.com:8019" , $this->engine_struct_param->offsetGet("base_url"));
        $this->assertEquals( "translate" , $this->engine_struct_param->offsetGet("translate_relative_url"));
        $this->assertEquals( "contribute" , $this->engine_struct_param->offsetGet("contribute_relative_url"));
        $this->assertEquals( "delete" , $this->engine_struct_param->offsetGet("delete_relative_url"));
        $this->assertEquals( "{}", $this->engine_struct_param->offsetGet("others"));
        $this->assertEquals( "foo_bar" , $this->engine_struct_param->offsetGet("class_load"));
        $this->assertEquals( "{}" , $this->engine_struct_param->offsetGet("extra_parameters"));
        $this->assertEquals( 1 , $this->engine_struct_param->offsetGet("penalty"));
        $this->assertEquals( 4 , $this->engine_struct_param->offsetGet("active"));
        $this->assertEquals( 89999 , $this->engine_struct_param->offsetGet("uid"));
    }


}