<?php

use Model\Engines\EngineDAO;
use Model\Engines\EngineStruct;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers EngineDAO::_validatePrimaryKey
 * User: dinies
 * Date: 20/04/16
 * Time: 17.30
 */
class ValidatePrimaryKeyTest extends AbstractTest {

    /**
     * @var EngineDAO
     */
    protected $method;
    protected $reflector;
    /**
     * @var EngineStruct
     */
    protected $engine_struct_param;

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = new EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "_validatePrimaryKey" );
        $this->method->setAccessible( true );
        $this->engine_struct_param = new EngineStruct();

    }

    /**
     * It checks that 'id' and 'uid' aren't null values.
     * Test that no exceptions are thrown
     * @group  regression
     * @covers EngineDAO::_validatePrimaryKey
     * @doesNotPerformAssertions
     */
    public function test__validatePrimaryKey_valid_fields() {

        $this->engine_struct_param->id  = 33;
        $this->engine_struct_param->uid = 1;

        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }


    /**
     * It will raise an exception when it checks that 'id' field is NULL.
     * @group  regression
     * @covers EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey_invalid_id() {

        $this->engine_struct_param->id  = null;
        $this->engine_struct_param->uid = 1;
        $this->expectException( "Exception" );
        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }


    /**
     * It will raise an exception when it checks that 'uid' field is NULL.
     * @group  regression
     * @covers EngineDAO::_validatePrimaryKey
     */
    public function test__validatePrimaryKey_invalid_uid() {

        $this->engine_struct_param->id  = 33;
        $this->engine_struct_param->uid = null;
        $this->expectException( "Exception" );
        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }
}