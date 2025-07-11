<?php

use Model\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers EngineDAO::_validateNotNullFields
 * User: dinies
 * Date: 15/04/16
 * Time: 12.28
 */
class ValidateNotNullFieldsTest extends AbstractTest {
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
        $this->method           = $this->reflector->getMethod( "_validateNotNullFields" );
        $this->method->setAccessible( true );
        $this->engine_struct_param = new EngineStruct();

        $this->engine_struct_param->name                    = "Moses_bar_and_foo";
        $this->engine_struct_param->description             = "Machine translation from bar and foo.";
        $this->engine_struct_param->type                    = "TM";
        $this->engine_struct_param->base_url                = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url  = "translate";
        $this->engine_struct_param->contribute_relative_url = null;
        $this->engine_struct_param->delete_relative_url     = null;
        $this->engine_struct_param->others                  = "{}";
        $this->engine_struct_param->class_load              = "foo_bar";
        $this->engine_struct_param->extra_parameters        = "{}";
        $this->engine_struct_param->penalty                 = 1;
        $this->engine_struct_param->active                  = 0;
        $this->engine_struct_param->uid                     = 1;
    }

    /**
     * It checks that the method will raise an expected
     * exception if there is the property 'base_url' uninitialized.
     * @group  regression
     * @covers EngineDAO::_validateNotNullFields
     */
    public function test__validateNotNullFields_base_url_field() {
        $this->engine_struct_param->base_url = null;
        $this->expectException( 'Exception' );
        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }

    /**
     * @group  regression
     * @covers EngineDAO::_validateNotNullFields
     * TODO: this test fails until the source code will be fixed
     */
    public function test__validateNotNullFields_type_field_not_allowed_value_string() {

        $this->engine_struct_param->type = "bar";
        $this->expectException( 'Exception' );
        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }

    /**
     * @group  regression
     * @covers EngineDAO::_validateNotNullFields
     */
    public function test__validateNotNullFields_type_field_int_not_present() {

        $this->engine_struct_param->type = 67;
        $this->expectException( 'Exception' );
        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }

    /**
     * @group  regression
     * @covers EngineDAO::_validateNotNullFields
     * TODO: this test fails until the source code will be fixed
     */
    public function test__validateNotNullFields_type_value_not_between_types1() {

        $this->engine_struct_param->type = 1;
        $this->expectException( 'Exception' );
        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }

    /**
     * @group  regression
     * @covers EngineDAO::_validateNotNullFields
     * TODO: this test fails until the source code will be fixed
     */
    public function test__validateNotNullFields_type_value_not_between_types2() {

        $this->engine_struct_param->type = 1000;
        $this->expectException( 'Exception' );
        $this->method->invoke( $this->databaseInstance, $this->engine_struct_param );
    }

}