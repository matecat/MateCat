<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::updateStruct
 * User: dinies
 * Date: 19/04/16
 * Time: 15.24
 */
class UpdateStructTest extends AbstractTest
{

    protected $reflector;
    protected $method;

    protected $engine_struct_param;
    protected $array_param;

    /**
     * @group regression
     * @covers DataAccess_AbstractDao::updateStruct
     */
    public function test_updateStruct_update_name_type_id_of_simple_struct()
    {
        

        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->id = 0 ;
        $this->engine_struct_param->name = "NONE";
        $this->engine_struct_param->description = "No MT";
        $this->engine_struct_param->type = "NONE";
        $this->engine_struct_param->base_url = "";
        $this->engine_struct_param->translate_relative_url = "";
        $this->engine_struct_param->contribute_relative_url = NULL;
        $this->engine_struct_param->delete_relative_url= NULL;
        $this->engine_struct_param->others = array();
        $this->engine_struct_param->class_load = "NONE";
        $this->engine_struct_param->extra_parameters = NULL;
        $this->engine_struct_param->google_api_compliant_version=NULL;
        $this->engine_struct_param->penalty = "100";
        $this->engine_struct_param->active = "0";
        $this->engine_struct_param->uid = NULL;

        $this->array_param = array(0 =>
            array(
                'id' => "1",
                'name' => "Bar",
                'type' => "foo",
                'description' => "No MT",
                'base_url' => "",
                'translate_relative_url' => "",
                'contribute_relative_url' => NULL,
                'delete_relative_url' => NULL,
                'others' => "{}",
                'class_load' => "NONE",
                'extra_parameters' => "",
                'google_api_compliant_version' => NULL,
                'penalty' => "100",
                'active' => "0",
                'uid' => NULL
            ));
     //  $this->assertTrue( DataAccess_AbstractDao::updateStruct($this->engine_struct_param));
    }
}