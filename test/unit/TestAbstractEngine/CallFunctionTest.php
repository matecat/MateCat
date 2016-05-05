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
    protected $others_param;
    protected $reflector;
    protected $property;
    /**
     * @var Engines_Moses
     */
    protected $mock_engine;
    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;



    public function setUp(){
        parent::setUp();

        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain() );
        $engine_struct= EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = @$eng[0];

        $this->engine_MyMemory= new Engines_MyMemory($this->engine_struct_param);

    }

    public function tearDown(){
        //TODO deletare da my memory tutti i segmenti casicati con le set e portare tutte le stringhe da deletare, nel setup come variabili di classe
        //TODO promemoria togli gli any dai mock e metti once.
    }
    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_with_simple_segment_triggered_by_get()
    {

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
        //togliere dal setup
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
        $this->assertEquals("maison est rouge.",$this->property->getValue($this->mock_engine)["translation"]);
    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_with_more_complex_segment_triggered_by_get()
    {

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
        //togliere dal setup

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
        $this->assertEquals("maison est rouge et la soupe Apple est vert.",$this->property->getValue($this->mock_engine)["translation"]);
    }
    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_aragonese_triggered_by_get(){




        $function_param= "translate_relative_url";
        $this->array_param= array(
            'q' => "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.",
            'langpair' => "it-IT|an-ES",
            'de' => "demo@matecat.com",
            'mt' => true,
            'numres' => "3",
            'key' => "a6043e606ac9b5d7ff24"           
        );

        $this->engine_MyMemory->call($function_param,$this->array_param);

        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $actual_result=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($actual_result,'matches'));
        $this->assertTrue(property_exists($actual_result,'responseStatus'));
        $this->assertTrue(property_exists($actual_result,'responseDetails'));
        $this->assertTrue(property_exists($actual_result,'responseData'));
        $this->assertTrue(property_exists($actual_result,'error'));
        $this->assertTrue(property_exists($actual_result,'_rawResponse'));



    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_set_from_MyMemory_Engine_general_check_1(){




        $function_param= "contribute_relative_url";

        $prop=<<<'LABEL'
{"project_id":"10","project_name":"tyuio","job_id":"10"}
LABEL;

        $this->array_param= array(
            'seg' => "Il Sistema registra le informazioni sul nuovo film.",
            'tra' => "The system records the information on the new movie.",
            'tnote' => NULL,
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'prop' => $prop,
            'key' => "a6043e606ac9b5d7ff24"
        );

        $this->engine_MyMemory->call($function_param,$this->array_param);

        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $actual_result=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of Engines_Results_MyMemory_SetContributionResponse object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertFalse(property_exists($actual_result,'matches'));
        $this->assertTrue(property_exists($actual_result,'responseStatus'));
        $this->assertTrue(property_exists($actual_result,'responseDetails'));
        $this->assertTrue(property_exists($actual_result,'responseData'));
        $this->assertTrue(property_exists($actual_result,'error'));
        $this->assertTrue(property_exists($actual_result,'_rawResponse'));



    }


    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_set_from_MyMemory_Engine_stubbed__call_with_mock_1(){


        $function_param= "contribute_relative_url";

        $prop=<<<'LABEL'
{"project_id":"10","project_name":"tyuio","job_id":"10"}
LABEL;

        $this->array_param= array(
            'seg' => "Il Sistema registra le informazioni sul nuovo film.",
            'tra' => "The system records the information on the new movie.",
            'tnote' => NULL,
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'prop' => $prop,
            'key' => "a6043e606ac9b5d7ff24"
        );
        
        $curl_mock_param=array(
            '80' => true,
            '13' => 10
        );
        
        
        $url_mock_param= "http://api.mymemory.translated.net/set?seg=Il+Sistema+registra+le+informazioni+sul+nuovo+film.&tra=The+system+records+the+information+on+the+new+movie.&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%2210%22%2C%22project_name%22%3A%22tyuio%22%2C%22job_id%22%3A%2210%22%7D&key=a6043e606ac9b5d7ff24";

        $mock_json_return=<<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484525156]}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $engine_MyMemory->expects($this->any())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $engine_MyMemory->call($function_param,$this->array_param);


        $this->reflector = new ReflectionClass($engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $returned_object=$this->property->getValue($engine_MyMemory);

        $this->assertTrue($returned_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertEquals(200, $returned_object->responseStatus);
        $this->assertEquals(array('0' => 484525156), $returned_object->responseDetails);
        $this->assertEquals("OK", $returned_object->responseData);
        $this->assertNull($returned_object->error);
        /**
         * check of protected property
         */
        $this->reflector= new ReflectionClass($returned_object);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($returned_object));
    }


    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_set_from_MyMemory_Engine_general_check_2(){


        $function_param= "contribute_relative_url";

        $prop = <<<'LABEL'
{"project_id":"9","project_name":"eryt","job_id":"9"}
LABEL;
        $segment = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;

        $translation = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;


        $this->array_param = array(
            'seg' => $segment,
            'tra' => $translation,
            'tnote' => NULL,
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'prop' => $prop,
            'key' => "a6043e606ac9b5d7ff24"
        );

        $this->engine_MyMemory->call($function_param,$this->array_param);

        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $actual_result=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of Engines_Results_MyMemory_SetContributionResponse object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertFalse(property_exists($actual_result,'matches'));
        $this->assertTrue(property_exists($actual_result,'responseStatus'));
        $this->assertTrue(property_exists($actual_result,'responseDetails'));
        $this->assertTrue(property_exists($actual_result,'responseData'));
        $this->assertTrue(property_exists($actual_result,'error'));
        $this->assertTrue(property_exists($actual_result,'_rawResponse'));



    }


    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_set_from_MyMemory_Engine_stubbed__call_with_mock_2(){


        $function_param= "contribute_relative_url";

        $prop = <<<'LABEL'
{"project_id":"9","project_name":"eryt","job_id":"9"}
LABEL;
        $segment = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;

        $translation = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;


        $this->array_param = array(
            'seg' => $segment,
            'tra' => $translation,
            'tnote' => NULL,
            'langpair' => "it-IT|en-US",
            'de' => "demo@matecat.com",
            'prop' => $prop,
            'key' => "a6043e606ac9b5d7ff24"
        );

        $curl_mock_param=array(
            '80' => true,
            '13' => 10
        );


        $url_mock_param= "http://api.mymemory.translated.net/set?seg=Ad+esempio%2C+una+copia+del+film+%3Cg+id%3D%2210%22%3EBlade+Runner%3C%2Fg%3E+in+formato+DVD%2C+con+numero+di+serie+6457.&tra=For+example%2C+a+copy+of+the+film+%3Cg+id%3D%2210%22%3EFlade+Bunner%3C%2Fg%3E+in+DVD+format%2C+with+numbers+of+6457+series.&langpair=it-IT%7Cen-US&de=demo%40matecat.com&prop=%7B%22project_id%22%3A%229%22%2C%22project_name%22%3A%22eryt%22%2C%22job_id%22%3A%229%22%7D&key=a6043e606ac9b5d7ff24";

        $mock_json_return=<<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484540480]}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $engine_MyMemory->expects($this->any())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $engine_MyMemory->call($function_param,$this->array_param);


        $this->reflector = new ReflectionClass($engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
        /**
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $returned_object=$this->property->getValue($engine_MyMemory);

        $this->assertTrue($returned_object instanceof Engines_Results_MyMemory_SetContributionResponse);
        $this->assertEquals(200, $returned_object->responseStatus);
        $this->assertEquals(array('0' => 484540480), $returned_object->responseDetails);
        $this->assertEquals("OK", $returned_object->responseData);
        $this->assertNull($returned_object->error);
        /**
         * check of protected property
         */
        $this->reflector= new ReflectionClass($returned_object);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($returned_object));
    }

    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_delete_from_MyMemory_Engine_general_check(){



        /**
         * initialization of the value to delete
         */

        $prop=<<<'LABEL'
{"project_id":"12","project_name":"ForDeleteTest","job_id":"12"}
LABEL;

        $config_param= array(
            'segment' => "Il Sistema registra le informazioni sul nuovo film.",
            'translation' => "The system records the destruction on the newnew movie.",
            'tnote' => NULL,
            'source' => "it-IT",
            'target' => "en-US",
            'email' => "demo@matecat.com",
            'prop' => $prop,
            'get_mt' => 1,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
            'id_user' => array()
        );


        $this->engine_MyMemory->set($config_param);
        sleep(1);

        /**
         * end of initialization
         */

        $function_param= "delete_relative_url";

        $this->array_param = array(
            'seg' => "Il Sistema registra le informazioni sul nuovo film.",
            'tra' => "The system records the destruction on the newnew movie.",
            'langpair' => "IT|EN",
            'de' => "demo@matecat.com",
        );

        $this->engine_MyMemory->call($function_param,$this->array_param);

        $this->reflector = new ReflectionClass($this->engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $actual_result=$this->property->getValue($this->engine_MyMemory);

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_TMS);
        $this->assertTrue(property_exists($actual_result,'matches'));
        $this->assertTrue(property_exists($actual_result,'responseStatus'));
        $this->assertTrue(property_exists($actual_result,'responseDetails'));
        $this->assertTrue(property_exists($actual_result,'responseData'));
        $this->assertTrue(property_exists($actual_result,'error'));
        $this->assertTrue(property_exists($actual_result,'_rawResponse'));



    }


    /**
     * @group regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_delete_from_MyMemory_Engine_stubbed__call_with_mock(){



        $function_param= "delete_relative_url";

        $this->array_param = array(
            'seg' => "Il Sistema registra le informazioni sul nuovo film.",
            'tra' => "The system records the information on the new movie.",
            'langpair' => "IT|EN",
            'de' => "demo@matecat.com",
        );


        $curl_mock_param=array(
            '80' => true,
            '13' => 10
        );


        $url_mock_param= "http://api.mymemory.translated.net/delete?seg=Il+Sistema+registra+le+informazioni+sul+nuovo+film.&tra=The+system+records+the+information+on+the+new+movie.&langpair=IT%7CEN&de=demo%40matecat.com";

        $mock_json_return=<<<'TAB'
{"responseStatus":200,"responseData":"Found and deleted 1 segments"}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $engine_MyMemory= $this->getMockBuilder('\Engines_MyMemory')->setConstructorArgs(array($this->engine_struct_param))->setMethods(array('_call'))->getMock();
        $engine_MyMemory->expects($this->any())->method('_call')->with($url_mock_param,$curl_mock_param)->willReturn($mock_json_return);

        $engine_MyMemory->call($function_param,$this->array_param);


        $this->reflector = new ReflectionClass($engine_MyMemory);
        $this->property = $this->reflector->getProperty("result");
        $this->property->setAccessible(true);
        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $actual_result=$this->property->getValue($engine_MyMemory);
        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue($actual_result instanceof Engines_Results_MyMemory_TMS);
        $this->assertEquals(array(),$actual_result->matches);
        $this->assertEquals(200, $actual_result->responseStatus);
        $this->assertEquals("", $actual_result->responseDetails);
        $this->assertEquals("Found and deleted 1 segments", $actual_result->responseData);
        $this->assertNull($actual_result->error);
        /**
         * check of protected property
         */
        $this->reflector= new ReflectionClass($actual_result);
        $property= $this->reflector->getProperty('_rawResponse');
        $property->setAccessible(true);

        $this->assertEquals("",$property->getValue($actual_result));
    }

}