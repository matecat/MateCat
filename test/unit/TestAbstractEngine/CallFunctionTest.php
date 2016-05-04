<?php
//TODO:estendere 
/**
 * @group regression
 * @covers  Engines_AbstractEngine::call
 * User: dinies
 * Date: 22/04/16
 * Time: 11.47
 */
class CallFunctionTest extends AbstractTest
{
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var array
     */
    protected $array_param;
    protected $reflector;
    protected $property;
    /**
     * @var Engines_Moses
     */
    protected $mock_engine;

    public function setUp()
    {
        parent::setUp();
        $this->engine_struct_param = new EnginesModel_EngineStruct();

        $this->engine_struct_param->id = 10;
        $this->engine_struct_param->name = "DeepLingo En/Fr iwslt";
        $this->engine_struct_param->type = "MT";
        $this->engine_struct_param->description = "DeepLingo Engine";
        $this->engine_struct_param->base_url = "http://mtserver01.deeplingo.com:8019";
        $this->engine_struct_param->translate_relative_url = "translate";
        $this->engine_struct_param->contribute_relative_url = NULL;
        $this->engine_struct_param->delete_relative_url = NULL;
        $this->engine_struct_param->others = array();
        $this->engine_struct_param->class_load = "DeepLingo";
        $this->engine_struct_param->extra_parameters = array("client_secret" => "gala15");
        $this->engine_struct_param->google_api_compliant_version = "2";
        $this->engine_struct_param->penalty = "14";
        $this->engine_struct_param->active = "1";
        $this->engine_struct_param->uid = 44;

        $this->reflectedClass = new Engines_Moses($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);


        $this->mock_engine = $this->getMockBuilder('\Engines_DeepLingo')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();

    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_with_simple_segment()
    {
        $this->reflector = new ReflectionClass($this->mock_engine);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        $this->array_param= array( 'q' => "house is red", 'source' => "en" , 'target' => "fr" , 'key' => "gala15");
        $function_name= "translate_relative_url";

        $json_output_mock = <<<LAB
{
  "data": {
    "translations": [
      {
        "translatedText": "maison est rouge.", 
        "translatedTextRaw": "maison est rouge .", 
        "annotatedSource": "house is red", 
        "tokenization": {
          "src": [
            [
              0, 
              4
            ], 
            [
              6, 
              7
            ], 
            [
              9, 
              11
            ]
          ], 
          "tgt": [
            [
              0, 
              5
            ], 
            [
              7, 
              9
            ], 
            [
              11, 
              15
            ], 
            [
              16, 
              16
            ]
          ]
        }
      }
    ]
  }
}
LAB;
        $url_param_mock = "http://mtserver01.deeplingo.com:8019/translate?q=house+is+red&source=en&target=fr&key=gala15";

        $this->mock_engine->expects($this->any())->method('_call')->with($url_param_mock)->willReturn($json_output_mock);


        $this->mock_engine->call($function_name, $this->array_param);
        $mock_engine = $this->property->getValue($this->mock_engine) ;
        $this->assertEquals("maison est rouge.", $mock_engine["translation"]);
    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_with_more_complex_segment()
    {
        $this->reflector = new ReflectionClass($this->mock_engine);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);

        $this->array_param= array( 'q' => "house is red and apple is green soup", 'source' => "en" , 'target' => "fr" , 'key' => "gala15");
        $function_name= "translate_relative_url";

        $json_output_mock = <<<'LAB'
{
  "data": {
    "translations": [
      {
        "translatedText": "maison est rouge et la soupe Apple est vert.", 
        "translatedTextRaw": "maison est rouge et la soupe Apple est vert .", 
        "annotatedSource": "house is red and apple is green soup", 
        "tokenization": {
          "src": [
            [
              0, 
              4
            ], 
            [
              6, 
              7
            ], 
            [
              9, 
              11
            ], 
            [
              13, 
              15
            ], 
            [
              17, 
              21
            ], 
            [
              23, 
              24
            ], 
            [
              26, 
              30
            ], 
            [
              32, 
              35
            ]
          ], 
          "tgt": [
            [
              0, 
              5
            ], 
            [
              7, 
              9
            ], 
            [
              11, 
              15
            ], 
            [
              17, 
              18
            ], 
            [
              20, 
              21
            ], 
            [
              23, 
              27
            ], 
            [
              29, 
              33
            ], 
            [
              35, 
              37
            ], 
            [
              39, 
              42
            ], 
            [
              43, 
              43
            ]
          ]
        }
      }
    ]
  }
}
LAB;

        $url_param_mock = "http://mtserver01.deeplingo.com:8019/translate?q=house+is+red+and+apple+is+green+soup&source=en&target=fr&key=gala15";

        $this->mock_engine->expects($this->any())->method('_call')->with($url_param_mock)->willReturn($json_output_mock);

        $this->mock_engine->call($function_name, $this->array_param);

        $mock_engine = $this->property->getValue($this->mock_engine);
        $this->assertEquals("maison est rouge et la soupe Apple est vert.", $mock_engine["translation"]);
    }

}