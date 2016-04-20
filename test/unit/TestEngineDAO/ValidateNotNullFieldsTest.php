<?php

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
    {   $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain());
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_validateNotNullFields");
        $this->method->setAccessible(true);
        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->id = <<<LABEL
33
LABEL;
        $this->engine_struct_param->name = <<<LABEL
Moses_bar_and_foo
LABEL;
        $this->engine_struct_param->description = <<<LABEL
Machine translation from bar and foo.
LABEL;
        $this->engine_struct_param->type = <<<LABEL
TM
LABEL;
        $this->engine_struct_param->base_url = <<<LABEL
http://mtserver01.deepfoobar.com:8019
LABEL;
        $this->engine_struct_param->translate_relative_url = <<<LABEL
translate
LABEL;
        $this->engine_struct_param->contribute_relative_url = <<<LABEL
NULL
LABEL;
        $this->engine_struct_param->delete_relative_url= <<<LABEL
NULL
LABEL;
        $this->engine_struct_param->others = <<<'LABEL'
{}
LABEL;
        $this->engine_struct_param->class_load = <<<LABEL
foo_bar
LABEL;
        $this->engine_struct_param->extra_parameters = <<<'LABEL'
{}
LABEL;
        $this->engine_struct_param->penalty = <<<LABEL
1
LABEL;
        $this->engine_struct_param->active = <<<LABEL
0
LABEL;
        $this->engine_struct_param->uid = <<<LABEL
1
LABEL;
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     */
    public function test__validateNotNullFields_base_url_field()
    {
        $this->engine_struct_param->base_url=null;
        $this->setExpectedException('Exception');
        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }

    /**
     * @group regression
     * @covers EnginesModel_EngineDAO::_validateNotNullFields
     */
    public function test__validateNotNullFields_type_field_not_allowed_value()
    {
        //TODO:CHIEDERE A ROBERTO PERCHE IL METODO IN_ARRAY APPLICATO SULL'ARRAY DI COSTANTI NON SI COMPORTA COME MI ASPETTO ( MI DA SEMPRE TRUE FINCHE GLI PASSO UN INT O UNA STRING ANCHESE TRA I VALORI DELLE COSTANTI NON TROVO NULLA DI SIMILE) L'UNICO MODO CHE HO TROVATO PER FARMI RITORNARE FALSE È STATO QUELLO DI CERCARE UN ARRAY DENTRO ALL'ARRAY..

        $this->engine_struct_param->type=array(20 => "bar");
        $this->setExpectedException('Exception');
        $this->method->invoke($this->reflectedClass, $this->engine_struct_param);
    }

}