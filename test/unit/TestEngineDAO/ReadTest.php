<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::read
 * User: dinies
 * Date: 15/04/16
 * Time: 15.56
 */
class ReadTest extends  AbstractTest
{

    /**
     * @var EnginesModel_EngineDAO
     */
    protected $engine_Dao;
    protected $engine_struct_param;

    /**
     * @param EnginesModel_EngineStruct
     * @return array
     * It reads a struct of an engine and @return an array of properties of the engine
     * @group regression
     * @covers EnginesModel_EngineDAO::read
     */
    public function test_read_given_engine_struct()
    {

        $this->engine_Dao = new EnginesModel_EngineDAO(Database::obtain());

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

        $array =     array(
                'id' => 0,
                'name' => "NONE",
                'type' => "NONE",
                'description' => "No MT",
                'base_url' => "",
                'translate_relative_url' => "",
                'contribute_relative_url' => NULL,
                'delete_relative_url' => NULL,
                'others' => array(),
                'class_load' => "NONE",
                'extra_parameters' => NULL,
                'google_api_compliant_version' => NULL,
                'penalty' => "100",
                'active' => "0",
                'uid' => NULL
            );
        $expected_engine_obj_output=new EnginesModel_EngineStruct($array);


        $this->assertEquals(array($expected_engine_obj_output),$this->engine_Dao->read($this->engine_struct_param));
    }
}