<?php

/**
 * @group regression
 * @covers  Engines_AbstractEngine::_preserveSpecialStrings
 * User: dinies
 * Date: 22/04/16
 * Time: 13.49
 */
class PreserveSpecialStringsTest extends AbstractTest
{
    protected $engine_struct_param;
    protected $reflector;
    protected $method;

    public function setUp()
    {
        parent::setUp();
        $this->engine_struct_param = new EnginesModel_EngineStruct();
        $this->engine_struct_param->type = "MT";

        $this->reflectedClass = new Engines_Moses($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_preserveSpecialStrings");
        $this->method->setAccessible(true);


    }
    /**
     * @group regression
     * @covers  Engines_AbstractEngine::_preserveSpecialStrings
     */
    public function test__preserveSpecialStrings(){
        $input_string= "la casa è rossa";
        $expected_string= "la casa è rossa";
        $this->assertEquals($expected_string, $this->method->invoke($this->reflectedClass,$input_string));
    }


    /**
     * @group regression
     * @covers  Engines_AbstractEngine::_preserveSpecialStrings
     */
    public function test__preserveSpecialStrings_complex(){
        $input_string= <<<'LAB'
"lsdfòoi2342'ì850dàrpKPHIOtlh234'950\\     è' rossa"
        2345\\\          ///* /5*   
LAB;

        $expected_string= <<<'LAB'
"lsdfòoi2342'ì850dàrpKPHIOtlh234'950\\     è' rossa"
        2345\\\          ///* /5*   
LAB;
        $this->assertEquals($expected_string, $this->method->invoke($this->reflectedClass,$input_string));
    }


    /**
     * @group regression
     * @covers  Engines_AbstractEngine::_preserveSpecialStrings
     */
    public function test__preserveSpecialStrings_more_complex(){

        $input_string= <<<'LABEL'
    dgb            !"£JAéI-OF
              0'''  asdfaf
    asdf            W      sd
                asae1\\
               
                w'087ew-à
                da
     //           
LABEL;
        $expected_string= <<<'LABEL'
    dgb            !"£JAéI-OF
              0'''  asdfaf
    asdf            W      sd
                asae1\\
               
                w'087ew-à
                da
     //           
LABEL;
        $this->assertEquals($expected_string, $this->method->invoke($this->reflectedClass,$input_string));
    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::_preserveSpecialStrings
     */
    public function test__preserveSpecialStrings_with_strings(){

        $input_string= <<<'LABEL'
    /* Insert Element menu item */
    "Insert Element" = "Insert Element";
    /* Error string used for unknown error types. */
    "ErrorString_1" = "An unknown error occurred.";
    "Windows must have at least %d columns and %d rows." =
    "Les fenêtres doivent être composes au minimum de %d colonnes et %d lignes.";
    "File %@ not found." = "Le fichier %@ n’existe pas.";
LABEL;

        $this->assertRegExp('/[^%d,%@]/', $this->method->invoke($this->reflectedClass,$input_string));

    }





}