<?php

use TestHelpers\AbstractTest;


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
     * @return bool
     * It controls if the given field exists ( != NULL)  in the struct.
     * @group regression
     * @covers EnginesModel_EngineStruct::getStruct
     */
    public function test_offsetExists_id_field()
    {


        $this->assertTrue($this->engine_struct_param->offsetExists("id"));
        $this->assertTrue($this->engine_struct_param->offsetExists("name"));
        $this->assertTrue($this->engine_struct_param->offsetExists("description"));
        $this->assertTrue($this->engine_struct_param->offsetExists("type"));
        $this->assertTrue($this->engine_struct_param->offsetExists("base_url"));
        $this->assertTrue($this->engine_struct_param->offsetExists("translate_relative_url"));
        $this->assertTrue($this->engine_struct_param->offsetExists("contribute_relative_url"));
        $this->assertTrue($this->engine_struct_param->offsetExists("delete_relative_url"));
        $this->assertTrue($this->engine_struct_param->offsetExists("others"));
        $this->assertTrue($this->engine_struct_param->offsetExists("class_load"));
        $this->assertTrue($this->engine_struct_param->offsetExists("extra_parameters"));
        $this->assertTrue($this->engine_struct_param->offsetExists("penalty"));
        $this->assertTrue($this->engine_struct_param->offsetExists("active"));
        $this->assertTrue($this->engine_struct_param->offsetExists("uid"));
    }
}