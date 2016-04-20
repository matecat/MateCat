<?php

/**
 * @group regression
 * @covers EnginesModel_EngineDAO::_buildQueryForEngine
 * User: dinies
 * Date: 14/04/16
 * Time: 19.40
 */
class uBuildQueryForEngineTest extends AbstractTest
{

    protected $reflector;
    protected $method;
    protected $engine_struct;

    public function setUp()
    {

        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain());
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->engine_struct = new EnginesModel_EngineStruct();
        $this->method = $this->reflector->getMethod("_buildQueryForEngine");
        $this->method->setAccessible(true);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_ID_inizialized()
    {

        $this->engine_struct->id = 10;
        $sql_query_result = $this->method->invoke($this->reflectedClass, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE id = 10";
        $this->assertEquals($sql_query_expected, $sql_query_result);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_UID_inizialized()
    {

        $this->engine_struct->uid = 1;
        $sql_query_result = $this->method->invoke($this->reflectedClass, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE uid = 1";
        $this->assertEquals($sql_query_expected, $sql_query_result);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_active_inizialized()
    {

        $this->engine_struct->active = 88;
        $sql_query_result = $this->method->invoke($this->reflectedClass, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE active = 88";
        $this->assertEquals($sql_query_expected, $sql_query_result);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_type_inizialized()
    {

        $this->engine_struct->type = "MT";
        $sql_query_result = $this->method->invoke($this->reflectedClass, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE type = 'MT'";
        $this->assertEquals($sql_query_expected, $sql_query_result);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_uid_fake_null()
    {
        $this->engine_struct->uid = "NULL";
        $sql_query_result = $this->method->invoke($this->reflectedClass, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE uid IS NULL";
        $this->assertEquals($sql_query_expected, $sql_query_result);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_without_parameters_inizialized()
    {

        $this->setExpectedException('\Exception');
        $this->method->invoke($this->reflectedClass, $this->engine_struct);
    }
}