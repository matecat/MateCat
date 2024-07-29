<?php

use TestHelpers\AbstractTest;


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
        $this->engine_struct_param->id = 999 ; //sample value
    }


    /**
     * It sets the value of the variable that correspond to the first parameter with the value stored in the second parameter
     * @group regression
     * @covers EnginesModel_EngineStruct::offsetSet
     */
    public function test_offsetSet_id_field()
    {

        $this->engine_struct_param->offsetSet("id",000);
        $this->engine_struct_param->offsetSet("name","Moses_bar_and_foo");
        $this->engine_struct_param->offsetSet("description","Machine translation from bar and foo.");
        $this->engine_struct_param->offsetSet("type","TM");
        $this->engine_struct_param->offsetSet("base_url","http://mtserver01.deepfoobar.com:8019");
        $this->engine_struct_param->offsetSet("translate_relative_url","translate");
        $this->engine_struct_param->offsetSet("contribute_relative_url","contribute");
        $this->engine_struct_param->offsetSet("delete_relative_url","delete");
        $this->engine_struct_param->offsetSet("others","{}");
        $this->engine_struct_param->offsetSet("class_load","foo_bar");
        $this->engine_struct_param->offsetSet("extra_parameters","{}");
        $this->engine_struct_param->offsetSet("penalty",1);
        $this->engine_struct_param->offsetSet("active",4);
        $this->engine_struct_param->offsetSet("uid",89999);

        $this->assertEquals( 000, $this->engine_struct_param->id);
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

    }
}