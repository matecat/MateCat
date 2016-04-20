<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::_buildResult
 * User: dinies
 * Date: 15/04/16
 * Time: 15.59
 */
class BuildResultTest extends AbstractTest
{

    protected $array_param;
    protected $reflector;
    protected $method;
    protected $array_to_build_expected_engine_obj;


    public function setUp()
    {

        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain());
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_buildResult");
        $this->method->setAccessible(true);


    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildResult
     */
    public function test_build_result_from_simple_array()
    {

        $this->array_param = array(0 =>
            array(
           'id' => "0",
           'name' => "NONE",
           'type' => "NONE",
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
        $this->array_to_build_expected_engine_obj = array(
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

        $expected_engine_obj_output=new EnginesModel_EngineStruct($this->array_to_build_expected_engine_obj);
        $actual_engine_obj_output_actual = $this->method->invoke($this->reflectedClass, $this->array_param);
        $this->assertEquals(array($expected_engine_obj_output), $actual_engine_obj_output_actual);
    }
}