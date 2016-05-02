<?php

/**
 * @group regression
 * @covers  Engines_Moses::_fixLangCode
 * User: dinies
 * Date: 22/04/16
 * Time: 12.25
 */
class FixLangCodeTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
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
        $this->method = $this->reflector->getMethod("_fixLangCode");
        $this->method->setAccessible(true);
    }
    /**
     * It tests the behaviour of the fixing of a simple string.
     * @group regression
     * @covers  Engines_Moses::_fixLangCode
     */
    public function test__fixLangCode_with_simple_string(){
        $string= "ENG";
        $this->assertEquals("eng", $this->method->invoke($this->reflectedClass,$string ));
    }


    /**
     * It tests the behaviour of the fixing of a particular string.
     * @group regression
     * @covers  Engines_Moses::_fixLangCode
     */
    public function test__fixLangCode_with_particular_string(){
        $input_string= <<<'LABEL'
    dgb            !"£JAéI-OF
              0'''  asdfaf
    asdf            W      sd
                asae1\\
               
                w'087ew-à
                da
     //           
LABEL;
;
        $expected_string= <<<'LABEL'
dgb            !"£jaéi
LABEL;

        $this->assertEquals( $expected_string, $this->method->invoke($this->reflectedClass,$input_string ));
    }

    /**
     * It tests the behaviour of the fixing of en-US.
     * @group regression
     * @covers  Engines_Moses::_fixLangCode
     */
    public function test__fixLangCode_with_eng_language(){
        $string= "en-US";
        $this->assertEquals("en", $this->method->invoke($this->reflectedClass,$string ));
    }

    /**
     * It tests the behaviour of the fixing of it-IT.
     * @group regression
     * @covers  Engines_Moses::_fixLangCode
     */
    public function test__fixLangCode_with_italian_language(){
        $string= "it-IT";
        $this->assertEquals("it", $this->method->invoke($this->reflectedClass,$string ));
    }
}
