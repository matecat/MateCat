<?php

/**
 * @group regression
 * @covers  Engines_AbstractEngine::_resetSpecialStrings
 * User: dinies
 * Date: 25/04/16
 * Time: 17.53
 */
class ResetSpecialStringTest extends AbstractTest
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
        $this->method = $this->reflector->getMethod("_resetSpecialStrings");
        $this->method->setAccessible(true);


    }
    /**
     * @group regression
     * @covers  Engines_AbstractEngine::_resetSpecialStrings
     */
    public function test__preserveSpecialStrings_simple_string_without_modifications(){
        $input_string= "maison est rouge.";
        $expected_string= "maison est rouge.";
        $this->assertEquals($expected_string, $this->method->invoke($this->reflectedClass,$input_string));
    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::_resetSpecialStrings
     */
    public function test__preserveSpecialStrings_complex_string_without_modifications(){
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
     * @covers  Engines_AbstractEngine::_resetSpecialStrings
     */
    public function test__resetSpecialStrings_with_strings(){
        $array_of_patterns= array('%d'=> "6509f252c35a", '%@' => "465cb57f10ce");
        $patterns_found= $this->reflector->getProperty("_patterns_found");
        $patterns_found->setAccessible(true);
        $patterns_found->setValue($this->reflectedClass,$array_of_patterns);

        $input_string= <<<'LABEL'
    /* Insert Element menu item */
    "Insert Element" = "Insert Element";
    /* Error string used for unknown error types. */
    "ErrorString_1" = "An unknown error occurred.";
    "Windows must have at least 6509f252c35a columns and 6509f252c35a rows." =
    "Les fenêtres doivent être composes au minimum de 6509f252c35a colonnes et 6509f252c35a lignes.";
    "File 465cb57f10ce not found." = "Le fichier 465cb57f10ce n’existe pas.";
LABEL;

        $expected_string= <<<'LABEL'
    /* Insert Element menu item */
    "Insert Element" = "Insert Element";
    /* Error string used for unknown error types. */
    "ErrorString_1" = "An unknown error occurred.";
    "Windows must have at least %d columns and %d rows." =
    "Les fenêtres doivent être composes au minimum de %d colonnes et %d lignes.";
    "File %@ not found." = "Le fichier %@ n’existe pas.";
LABEL;

        $this->assertEquals($expected_string, $this->method->invoke($this->reflectedClass,$input_string));

    }
}