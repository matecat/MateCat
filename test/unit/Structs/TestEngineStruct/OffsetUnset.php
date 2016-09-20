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
     *It destructs the value of the variable that correspond to parameter string
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetUnset
     */
    public function test_offsetUnset_id_field()
    {

        $this->assertEquals( 999, $this->engine_struct_param->id);
        $this->assertEquals( "Moses_bar_and_foo" , $this->engine_struct_param->name);
        $this->assertEquals( "Machine translation from bar and foo." , $this->engine_struct_param->description);
        $this->assertEquals( "TM" , $this->engine_struct_param->type);
        $this->assertEquals( "http://mtserver01.deepfoobar.com:8019" , $this->engine_struct_param->base_url);
        $this->assertEquals( "translate" , $this->engine_struct_param->translate_relative_url);
        $this->assertEquals( "contribute" , $this->engine_struct_param->contribute_relative_url);
        $this->assertEquals( "delete" , $this->engine_struct_param->delete_relative_url);
        $this->assertEquals( "{}", $this->engine_struct_param->others);
        $this->assertEquals( "foo_bar" , $this->engine_struct_param->class_load);
        $this->assertEquals( "{}" , $this->engine_struct_param->extra_parameters);
        $this->assertEquals( 1 , $this->engine_struct_param->penalty);
        $this->assertEquals( 4 , $this->engine_struct_param->active);
        $this->assertEquals( 89999 , $this->engine_struct_param->uid);
        /**
         * Unset
         */
        $this->engine_struct_param->offsetUnset("id");
        $this->engine_struct_param->offsetUnset("name");
        $this->engine_struct_param->offsetUnset("description");
        $this->engine_struct_param->offsetUnset("type");
        $this->engine_struct_param->offsetUnset("base_url");
        $this->engine_struct_param->offsetUnset("translate_relative_url");
        $this->engine_struct_param->offsetUnset("contribute_relative_url");
        $this->engine_struct_param->offsetUnset("delete_relative_url");
        $this->engine_struct_param->offsetUnset("others");
        $this->engine_struct_param->offsetUnset("class_load");
        $this->engine_struct_param->offsetUnset("extra_parameters");
        $this->engine_struct_param->offsetUnset("penalty");
        $this->engine_struct_param->offsetUnset("active");
        $this->engine_struct_param->offsetUnset("uid");



        $this->assertNull($this->engine_struct_param->id);
        $this->assertNull($this->engine_struct_param->name);
        $this->assertNull($this->engine_struct_param->description);
        $this->assertNull($this->engine_struct_param->type);
        $this->assertNull($this->engine_struct_param->base_url);
        $this->assertNull($this->engine_struct_param->translate_relative_url);
        $this->assertNull($this->engine_struct_param->contribute_relative_url);
        $this->assertNull($this->engine_struct_param->delete_relative_url);
        $this->assertNull($this->engine_struct_param->others);
        $this->assertNull($this->engine_struct_param->class_load);
        $this->assertNull($this->engine_struct_param->extra_parameters);
        $this->assertNull($this->engine_struct_param->penalty);
        $this->assertNull($this->engine_struct_param->active);
        $this->assertNull($this->engine_struct_param->uid);

    }
}