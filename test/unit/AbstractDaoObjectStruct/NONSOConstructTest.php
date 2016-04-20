<?php
__halt_compiler();
/**
 * @group regression
 * @covers DataAccess_AbstractDaoObjectStruct::__construct
 * User: dinies
 * Date: 15/04/16
 * Time: 17.50
 */
class ConstructTest extends AbstractTest
{
  //  protected $array_param;
  //  protected $reflector;
  //  protected $method;
  //  protected $array_to_build_expected_engine_obj;


    protected $array_param;
    protected $engine_struct_param;
    public function setUp()
    {

 //       $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain());
 //       $this->reflector = new ReflectionClass($this->reflectedClass);
 //       $this->method = $this->reflector->getMethod("_buildResult");
 //       $this->method->setAccessible(true);


        $this->engine_struct_param = new EnginesModel_EngineStruct();


    }

    /**
     * @group regression
     * @covers DataAccess_AbstractDaoObjectStruct::__construct
     */
    public function test__fetch_array()
    {

        $this->array_param = array(
            "id" => 0,
            "name" => "NONE",
            "type" => "NONE",
            "description" => "No MT",
            "base_url" => "",
            "translate_relative_url" => "",
            "contribute_relative_url" => NULL,
            "delete_relative_url" => NULL,
            "others" => array(),
            "class_load" => "NONE",
            "extra_parameters" => NULL,
            "google_api_compliant_version" => NULL,
            "penalty" => "100",
            "active" => "0",
            "uid" => NULL
        );

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
   //    $this->array_to_build_expected_engine_obj = array(
   //        "id" => 0,
   //        "name" => "NONE",
   //        "type" => "NONE",
   //        "description" => "No MT",
   //        "base_url" => "",
   //        "translate_relative_url" => "",
   //        "contribute_relative_url" => NULL,
   //        "delete_relative_url" => NULL,
   //        "others" => array(),
   //        "class_load" => "NONE",
   //        "extra_parameters" => NULL,
   //        "google_api_compliant_version" => NULL,
   //        "penalty" => "100",
   //        "active" => "0",
   //        "uid" => NULL
   //    );


     //   $expected_engine_obj_output=new EnginesModel_EngineStruct($this->array_to_build_expected_engine_obj);
     //   $actual_engine_obj_output_actual = $this->method->invoke($this->reflectedClass, $this->array_param);

        $this->assertEquals($expected_engine_obj_output, $actual_engine_obj_output_actual);
    }
}