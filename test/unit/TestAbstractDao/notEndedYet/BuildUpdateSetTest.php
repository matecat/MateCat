<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::buildUpdateSet
 * User: dinies
 * Date: 18/04/16
 * Time: 19.24
 */
class BuildUpdateSetTest extends AbstractTest
{
    protected $array_param;
    protected $reflector;
    protected $method;

    public function setUp()
    {

        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain());
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("buildUpdateSet");
        $this->method->setAccessible(true);
    }
    /**
     * @group regression
     * @covers DataAccess_AbstractDao::buildUpdateSet
     */
    public function test_buildUpdateSet_simple_array_of_params()
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
        
        //$this->assertEquals("",$this->method->invoke( null,$this->array_param,array()));
    }
}