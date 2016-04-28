<?php

/**
 * @group regression
 * @covers  Engines_Moses::__construct
 * User: dinies
 * Date: 22/04/16
 * Time: 9.46
 */
class ConstructorMosesTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;
    protected $reflector;
    protected $property;

    public function setUp()
    {
        parent::setUp();
        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->id = 10;
        $this->engine_struct_param->name = "Moses En/Fr iwslt";
        $this->engine_struct_param->type = "MT";
        $this->engine_struct_param->description = "Moses Engine";
        $this->engine_struct_param->base_url = "http://mtserver01.Moses.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = NULL;
        $this->engine_struct_param->delete_relative_url = NULL;
        $this->engine_struct_param->others = '{}';
        $this->engine_struct_param->class_load = "Moses";
        $this->engine_struct_param->extra_parameters = '{"client_secret":"gala15 "}';
        $this->engine_struct_param->google_api_compliant_version = "2";
        $this->engine_struct_param->penalty = "14";
        $this->engine_struct_param->active = "1";
        $this->engine_struct_param->uid = 44;
    }

    /**
     * It construct an engine and it initialises some globals from the abstract constructor
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_of_sub_engine_of_moses()
    {
        $this->reflectedClass = new Engines_Moses($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->property = $this->reflector->getProperty("engineRecord");
        $this->property->setAccessible(true);

        $this->assertEquals($this->engine_struct_param, $this->property->getValue($this->reflectedClass));

        $this->property = $this->reflector->getProperty("className");
        $this->property->setAccessible(true);

        $this->assertEquals("Engines_Moses", $this->property->getValue($this->reflectedClass));

        $this->property = $this->reflector->getProperty("curl_additional_params");
        $this->property->setAccessible(true);

        $this->assertEquals(6, count($this->property->getValue($this->reflectedClass)));

    }

    /**
     * It will raise an exception constructing an engine because of he wrong property of the struct.
     * @group regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_failure()
    {
        $this->engine_struct_param->type = "fooo";
        $this->setExpectedException("Exception");
        new Engines_Moses($this->engine_struct_param);
    }
}