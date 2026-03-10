<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers EngineDAO::_buildQueryForEngine
 * User: dinies
 * Date: 14/04/16
 * Time: 19.40
 */
class BuildQueryForEngineTest extends AbstractTest
{

    protected $reflector;
    protected $method;
    protected $engine_struct;

    public function setUp(): void
    {
        parent::setUp();
        $this->databaseInstance = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->databaseInstance);
        $this->engine_struct = new EngineStruct();
        $this->method = $this->reflector->getMethod("_buildQueryForEngine");
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with id initialized
     * @group  regression
     * @covers EngineDAO::_buildQueryForEngine
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
     * @group  regression
     * @covers EngineDAO::_buildQueryForEngine
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
     * @group  regression
     * @covers EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_active_inizialized()
    {
        $this->engine_struct->active = 88;
        $sql_query_result = $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE active = 1";
        $this->assertEquals($sql_query_expected, $this->getRawQuery($sql_query_result));
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with type initialized
     * @group  regression
     * @covers EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_type_inizialized()
    {
        $this->engine_struct->type = EngineConstants::MT;
        $sql_query_result = $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $sql_query_expected = "SELECT * FROM engines WHERE type = 'MT'";
        $this->assertEquals($sql_query_expected, $this->getRawQuery($sql_query_result));
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param with fake uid
     * @group  regression
     * @covers EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_with_uid_fake_null()
    {
        $this->engine_struct->uid = null;
        $this->expectException(Exception::class);
        $this->method->invoke($this->databaseInstance, $this->engine_struct);
        $this->expectExceptionMessage("Where condition needed.");
    }

    /**
     * This test builds a sql query for an engine with an engine struct as @param without properties initialized
     * @group  regression
     * @covers EngineDAO::_buildQueryForEngine
     */
    public function test__buildQueryForEngine_with_given_engine_struct_without_properties_inizialized()
    {
        $this->expectException('\Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct);
    }
}