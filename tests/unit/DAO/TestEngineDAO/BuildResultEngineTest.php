<?php

use TestHelpers\AbstractTest;


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
        $this->databaseInstance = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->method           = $this->reflector->getMethod("_buildResult");
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
           'base_url' => "base/sample",
           'translate_relative_url' => "translate",
           'contribute_relative_url' => "contribute",
           'update_relative_url' => "update",
           'delete_relative_url' => "delete",
           'others' => "{}",
           'class_load' => "NONE",
           'extra_parameters' => "",
           'google_api_compliant_version' => "1.1",
           'penalty' => "100",
           'active' => "0",
           'uid' => "5678"
        ));

        $actual_array_of_engine_structures = $this->method->invoke($this->databaseInstance, $this->array_param);
        $actual_engine_struct = $actual_array_of_engine_structures['0'];
        $this->assertTrue($actual_engine_struct instanceof EnginesModel_EngineStruct);

        $this->assertEquals("0", $actual_engine_struct->id);
        $this->assertEquals("bar", $actual_engine_struct->name);
        $this->assertEquals("foo", $actual_engine_struct->type);
        $this->assertEquals("No MT", $actual_engine_struct->description);
        $this->assertEquals("base/sample", $actual_engine_struct->base_url);
        $this->assertEquals("translate", $actual_engine_struct->translate_relative_url);
        $this->assertEquals("contribute", $actual_engine_struct->contribute_relative_url);
        $this->assertEquals("update", $actual_engine_struct->update_relative_url);
        $this->assertEquals("delete", $actual_engine_struct->delete_relative_url);
        $this->assertEquals(array(), $actual_engine_struct->others);
        $this->assertEquals("NONE", $actual_engine_struct->class_load);
        $this->assertEquals("", $actual_engine_struct->extra_parameters);
        $this->assertEquals("1.1", $actual_engine_struct->google_api_compliant_version);
        $this->assertEquals("100", $actual_engine_struct->penalty);
        $this->assertEquals("0", $actual_engine_struct->active);
        $this->assertEquals("5678", $actual_engine_struct->uid);
    }
}