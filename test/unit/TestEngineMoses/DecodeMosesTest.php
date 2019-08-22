<?php

/**
 * @group regression
 * @covers  Engines_Moses::_decode
 * User: dinies
 * Date: 25/04/16
 * Time: 17.44
 */
class DecodeMosesTest extends AbstractTest
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
        $this->engine_struct_param->name = "DeepLingoTestEngine";

        $this->reflectedClass = new Engines_Moses($this->engine_struct_param);
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_decode");
        $this->method->setAccessible(true);
        
    }
    /**
     * It tests the behaviour of the decoding of json input.
     * @group regression
     * @covers  Engines_Moses::_decode
     */
    public function test__decode_with_json_in_input(){
        $json_input= <<<LAB
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
        
        $input_parameters= array(
            'q' => "house is red" ,
            'source' => "en",
            'target' => "fr",
            'key' => "gala15"
        );
        $input_function_purpose= "translate_relative_url";

        $actual_return=$this->method->invoke($this->reflectedClass,$json_input,$input_parameters, $input_function_purpose );
        $this->assertContains(" 'id' => '0' ",$actual_return);
        $this->assertContains(" 'raw_segment' => 'house is red'",$actual_return);
        $this->assertContains(" 'translation' => 'maison est rouge.'",$actual_return);
        $this->assertContains("  'target_note' => '' ",$actual_return);
        $this->assertContains(" 'raw_translation' => 'maison est rouge.' ",$actual_return);
        $this->assertContains(" 'quality' => 0 ",$actual_return);
        $this->assertContains(" 'reference' => '' ",$actual_return);
        $this->assertContains(" 'usage_count' => 0 ",$actual_return);
        $this->assertContains(" 'subject' => '' ",$actual_return);
        $this->assertContains(" 'created_by' => 'MT-DeepLingoTestEngine' ",$actual_return);
        $this->assertContains(" 'last_updated_by' => '' ",$actual_return);
        $this->assertContains(" 'prop' => array() ",$actual_return);
        $this->assertContains(" 'source_note' => '' ",$actual_return);
        $this->assertContains(" 'memory_key' => '' ",$actual_return);
        $this->assertContains(" 'sentence_confidence' => NULL ",$actual_return);
        $this->assertCount(21,$actual_return);
        $last_update_date = $actual_return['last_update_date'];
        $this->assertRegExp( '/^[0-9]{4}-[0,1][0-9]-[0-3][0-9]$/', $last_update_date);
        $create_date = $actual_return['create_date'];
        $this->assertRegExp( '/^[0-9]{4}-[0,1][0-9]-[0-3][0-9]$/', $create_date);
        $match = $actual_return['match'];
        $this->assertRegExp( '/^1?[0-9]{1,2}%$/', $match);

        
        
        
    }

    /**
     * It tests the behaviour of the decoding of json input.
     * @group regression
     * @covers  Engines_Moses::_decode
     */
    public function test__decode_with_null_in_input(){
        $input_parameters= array(
            'q' => "house is red" ,
            'source' => "en",
            'target' => "fr",
            'key' => "gala15"
        );
        $input_function_purpose= "translate_relative_url";

        $actual_return=$this->method->invoke($this->reflectedClass,NULL,$input_parameters, $input_function_purpose );
        $this->assertContains(" 'id' => '0' ",$actual_return);
        $this->assertContains(" 'raw_segment' => 'house is red'",$actual_return);
        $this->assertContains(" 'translation' => 'maison est rouge.'",$actual_return);
        $this->assertContains("  'target_note' => '' ",$actual_return);
        $this->assertContains(" 'raw_translation' => '' ",$actual_return);
        $this->assertContains(" 'quality' => 0 ",$actual_return);
        $this->assertContains(" 'reference' => '' ",$actual_return);
        $this->assertContains(" 'usage_count' => 0 ",$actual_return);
        $this->assertContains(" 'subject' => '' ",$actual_return);
        $this->assertContains(" 'created_by' => 'MT-DeepLingoTestEngine' ",$actual_return);
        $this->assertContains(" 'last_updated_by' => '' ",$actual_return);
        $this->assertContains(" 'prop' => array() ",$actual_return);
        $this->assertContains(" 'source_note' => '' ",$actual_return);
        $this->assertContains(" 'memory_key' => '' ",$actual_return);
        $this->assertContains(" 'sentence_confidence' => NULL ",$actual_return);
        $this->assertCount(21,$actual_return);
        $last_update_date = $actual_return['last_update_date'];
        $this->assertRegExp( '/^[0-9]{4}-[0,1][0-9]-[0-3][0-9]$/', $last_update_date);
        $create_date = $actual_return['create_date'];
        $this->assertRegExp( '/^[0-9]{4}-[0,1][0-9]-[0-3][0-9]$/', $create_date);
        $match = $actual_return['match'];
        $this->assertRegExp( '/^1?[0-9]{1,2}%$/', $match);

    }


}