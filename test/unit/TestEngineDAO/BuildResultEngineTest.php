<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::_buildResult
 * User: dinies
 * Date: 15/04/16
 * Time: 15.59
 */
class BuildResultEngineTest extends AbstractTest
{

    protected $array_param;
    protected $reflector;
    protected $method;


    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_buildResult");
        $this->method->setAccessible(true);


    }

    /**
     * This test builds an engine object from an array that describes the properties
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildResult
     */
    public function test_build_result_from_simple_array()
    {

        $this->array_param = array(0 =>
            array(
           'id' => "0",
           'name' => "bar",
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


        $actual_array_of_engine_structures = $this->method->invoke($this->reflectedClass, $this->array_param);
        $actual_engine_struct = $actual_array_of_engine_structures['0'];
        $this->assertTrue($actual_engine_struct instanceof EnginesModel_EngineStruct);

        $this->assertEquals("bar", $actual_engine_struct->name);
        $this->assertEquals("foo", $actual_engine_struct->type);
    }
}