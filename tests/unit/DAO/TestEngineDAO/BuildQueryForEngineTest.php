<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers EnginesModel_EngineDAO::_buildQueryForEngine
 * User: dinies
 * Date: 14/04/16
 * Time: 19.40
 */
class BuildQueryForEngineTest extends AbstractTest
{

    protected $reflector;
    protected $method;
    protected $engine_struct;

    public function setUp()
    {
        parent::setUp();
        $this->databaseInstance = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->engine_struct    = new EnginesModel_EngineStruct();
        $this->method = $this->reflector->getMethod("_buildQueryForEngine");
        $this->method->setAccessible(true);
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with id initialized
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_ID_initialized()
    {
        $this->engine_struct->id = 10;
        $sql_query_result = $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE id = 10";
        $this->assertEquals($sql_query_expected, $this->getRawQuery($sql_query_result));
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with uid initialized
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_UID_inizialized()
    {

        $this->engine_struct->uid = 1;
        $sql_query_result = $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE uid = 1";
        $this->assertEquals($sql_query_expected, $this->getRawQuery($sql_query_result));
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with active initialized
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_active_inizialized()
    {

        $this->engine_struct->active = 88;
        $sql_query_result = $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE active = 88";
        $this->assertEquals($sql_query_expected, $this->getRawQuery($sql_query_result));
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with type initialized
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_type_inizialized()
    {

        $this->engine_struct->type = "MT";
        $sql_query_result = $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE type = 'MT'";
        $this->assertEquals($sql_query_expected, $this->getRawQuery($sql_query_result));
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with fake uid
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_uid_fake_null()
    {
        $this->engine_struct->uid = "NULL";
        $sql_query_result = $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE uid IS NULL";
        $this->assertEquals($sql_query_expected, $this->getRawQuery($sql_query_result));
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param without properties initialized
     * @group regression
     * @covers EnginesModel_EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_without_properties_inizialized()
    {

        $this->setExpectedException('\Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct);
    }
}