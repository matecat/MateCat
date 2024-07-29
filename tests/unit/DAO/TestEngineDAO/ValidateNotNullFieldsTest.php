<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers EnginesModel_EngineDAO::_validateNotNullFields
 * User: dinies
 * Date: 15/04/16
 * Time: 12.28
 */
class ValidateNotNullFieldsTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineDAO
     */
    protected $method;
    protected $reflector;
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    public function setUp()
    {
        parent::setUp();
        $this->databaseInstance = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->method           = $this->reflector->getMethod("_validateNotNullFields");
        $this->method->setAccessible(true);
        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->name = "Moses_bar_and_foo";
        $this->engine_struct_param->description = "Machine translation from bar and foo.";
        $this->engine_struct_param->type = "TM";
        $this->engine_struct_param->base_url = "http://mtserver01.deepfoobar.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = NULL;
        $this->engine_struct_param->delete_relative_url = NULL;
        $this->engine_struct_param->others = "{}";
        $this->engine_struct_param->class_load = "foo_bar";
        $this->engine_struct_param->extra_parameters ="{}";
        $this->engine_struct_param->penalty = 1;
        $this->engine_struct_param->active = 0;
        $this->engine_struct_param->uid = 1;
    }

    /**
     * It checks that the method will raise an expected
     * exception if there is the property 'base_url' uninitialized.
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     */
    public function test__validateNotNullFields_base_url_field()
    {
        $this->engine_struct_param->base_url=null;
        $this->setExpectedException('Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct_param);
    }

    /**

     * It unleashes an exception because the field type was initialized
     * with an incompatible value ( an array instead of a string).
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     */
    public function test__validateNotNullFields_type_field_not_allowed_value_array()
    {

        $this->engine_struct_param->type=array(20 => "bar");
        $this->setExpectedException('Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct_param);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     * TODO: this test fails until the source code will be fixed
     */
    public function test__validateNotNullFields_type_field_not_allowed_value_string()
    {

        $this->engine_struct_param->type="bar";
        $this->setExpectedException('Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct_param);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     */
    public function test__validateNotNullFields_type_field_int_not_present()
    {

        $this->engine_struct_param->type=67;
        $this->setExpectedException('Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct_param);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     * TODO: this test fails until the source code will be fixed
     */
    public function test__validateNotNullFields_type_value_not_between_types1()
    {

        $this->engine_struct_param->type=1;
        $this->setExpectedException('Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct_param);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     * TODO: this test fails until the source code will be fixed
     */
    public function test__validateNotNullFields_type_value_not_between_types2()
    {

        $this->engine_struct_param->type=1000;
        $this->setExpectedException('Exception');
        $this->method->invoke($this->databaseInstance, $this->engine_struct_param);
    }

}