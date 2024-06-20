<?php
//TODO:estendere 

/**
 * @group   regression
 * @covers  Engines_AbstractEngine::call
 * User: dinies
 * Date: 22/04/16
 * Time: 11.47
 */
class CallAbstractMyMemoryTest extends AbstractTest {
    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param_My_Memory;

    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;


    /**
     * @var string
     */
    protected $str_seg_1;
    protected $str_seg_2;
    protected $str_seg_3;
    protected $str_tra_1;
    protected $str_tra_2;
    protected $str_tra_3;

    /**
     * @var json
     */
    protected $prop;
    /**
     * @var array
     */
    protected $curl_param;
    protected $config_param_of_delete;
    protected $config_param_of_set;
    protected $array_param_of_call_for_set;
    protected $array_param_of_call_for_del;
    protected $array_param_of_call_for_get;

    protected $resultProperty;
    protected $test_key;
    /**
     * @var string
     */
    private $param_de = "demo@matecat.com";
    /**
     * @var EnginesModel_EngineDAO
     */
    private $engine_DAO;

    public function setUp() {
        parent::setUp();

        $this->engine_DAO = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );


        /**
         * obtaining My Memory Engine
         */
        $engine_struct_MyMemory = EnginesModel_EngineStruct::getStruct();

        $engine_struct_MyMemory->id = 1;

        $eng_My_Memory = $this->engine_DAO->read( $engine_struct_MyMemory );

        $this->engine_struct_param_My_Memory = $eng_My_Memory[ 0 ];

        $this->engine_MyMemory = new Engines_MyMemory( $this->engine_struct_param_My_Memory );

        $reflector            = new ReflectionClass( $this->engine_MyMemory );
        $this->resultProperty = $reflector->getProperty( "result" );
        $this->resultProperty->setAccessible( true );

        /**
         * parameters initialization
         */
        $this->curl_param = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => 10
        ];

        $this->test_key = "a6043e606ac9b5d7ff24";

        $this->str_seg_1 = "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.";
        $this->str_seg_2 = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;
        $this->str_seg_3 = "Il Sistema registra le informazioni sul nuovo film.";

        $this->str_tra_1 = "The system becomes bar and thinks foo.";
        $this->str_tra_2 = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;
        $this->str_tra_3 = "The system moves  hands up in the air.";

        $this->prop = <<<'LABEL'
{"project_id":"987654","project_name":"barfoo","job_id":"321"}
LABEL;

        $this->config_param_of_set = [
                'tnote'         => null,
                'source'        => "it-IT",
                'target'        => "en-US",
                'email'         => $this->param_de,
                'prop'          => $this->prop,
                'get_mt'        => 1,
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
                'id_user'       => []
        ];

        $this->config_param_of_delete = [
                'tnote'         => null,
                'source'        => "IT",
                'target'        => "EN",
                'email'         => $this->param_de,
                'prop'          => $this->prop,
                'get_mt'        => 1,
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
                'id_user'       => []
        ];


        $this->array_param_of_call_for_set = [
                'tnote'    => null,
                'langpair' => "it-IT|en-US",
                'de'       => $this->param_de,
                'prop'     => $this->prop,
                'key'      => "{$this->test_key}"
        ];

        $this->array_param_of_call_for_del = [
                'langpair' => "IT|EN",
                'de'       => $this->param_de,
        ];


        $this->array_param_of_call_for_get = [
                'langpair' => "it-IT|an-ES",
                'de'       => $this->param_de,
                'mt'       => true,
                'numres'   => "3",
                'key'      => "{$this->test_key}"
        ];


        /**
         * cleaning of matches for my_memory
         */
        $this->config_param_of_delete[ 'segment' ]     = $this->str_seg_1;
        $this->config_param_of_delete[ 'translation' ] = $this->str_tra_1;
        $this->engine_MyMemory->delete( $this->config_param_of_delete );
        $this->config_param_of_delete[ 'segment' ]     = $this->str_seg_2;
        $this->config_param_of_delete[ 'translation' ] = $this->str_tra_2;
        $this->engine_MyMemory->delete( $this->config_param_of_delete );
        $this->config_param_of_delete[ 'segment' ]     = $this->str_seg_3;
        $this->config_param_of_delete[ 'translation' ] = $this->str_tra_3;
        $this->engine_MyMemory->delete( $this->config_param_of_delete );
        sleep( 2 );
        /**
         * setting matches in my memory
         */
        $this->config_param_of_set[ 'segment' ]     = $this->str_seg_1;
        $this->config_param_of_set[ 'translation' ] = $this->str_tra_1;
        $this->engine_MyMemory->set( $this->config_param_of_set );
        $this->config_param_of_set[ 'segment' ]     = $this->str_seg_2;
        $this->config_param_of_set[ 'translation' ] = $this->str_tra_2;
        $this->engine_MyMemory->set( $this->config_param_of_set );
        $this->config_param_of_set[ 'segment' ]     = $this->str_seg_3;
        $this->config_param_of_set[ 'translation' ] = $this->str_tra_3;
        $this->engine_MyMemory->set( $this->config_param_of_set );
        sleep( 2 );


    }

    public function tearDown() {
        sleep( 1 );
        /**
         * cleaning of matches for my_memory
         */
        $this->config_param_of_delete[ 'segment' ]     = $this->str_seg_1;
        $this->config_param_of_delete[ 'translation' ] = $this->str_tra_1;
        $this->engine_MyMemory->delete( $this->config_param_of_delete );
        $this->config_param_of_delete[ 'segment' ]     = $this->str_seg_2;
        $this->config_param_of_delete[ 'translation' ] = $this->str_tra_2;
        $this->engine_MyMemory->delete( $this->config_param_of_delete );
        $this->config_param_of_delete[ 'segment' ]     = $this->str_seg_3;
        $this->config_param_of_delete[ 'translation' ] = $this->str_tra_3;
        $this->engine_MyMemory->delete( $this->config_param_of_delete );
        sleep( 1 );
        parent::tearDown();

    }


    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_set_from_MyMemory_Engine_general_check_1() {

        $params = [
                'tnote'    => null,
                'langpair' => 'it-IT|en-US',
                'de'       => $this->param_de,
                'prop'     => $this->prop,
                'key'      => $this->test_key,
                'seg'      => $this->str_seg_1,
                'tra'      => $this->str_tra_1,
        ];

        $this->engine_MyMemory->call( "contribute_relative_url", $params, true );

        /**
         * @var $actual_result Engines_Results_MyMemory_SetContributionResponse
         */
        $actual_result = $this->resultProperty->getValue( $this->engine_MyMemory );

        /**
         * general check on the keys of Engines_Results_MyMemory_SetContributionResponse object returned
         */
        $this->assertTrue( $actual_result instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertFalse( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );


    }


    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_set_from_MyMemory_Engine_stubbed__call_with_mock_1() {

        $params = [
                'tnote'    => null,
                'langpair' => 'it-IT|en-US',
                'de'       => $this->param_de,
                'prop'     => $this->prop,
                'key'      => $this->test_key,
                'seg'      => $this->str_seg_1,
                'tra'      => $this->str_tra_1,
        ];

        $mock_json_return = <<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":["0a64b364-f4f0-d301-66c4-5a6c04c2a2bf"]}
TAB;

        $curl_opts = [
                CURLOPT_POSTFIELDS  => $params,
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120
        ];

        /**
         * @var $engine_MyMemory Engines_MyMemory
         */
        $engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param_My_Memory ] )->setMethods( [ '_call' ] )->getMock();
        $engine_MyMemory->expects( $this->once() )
                ->method( '_call' )
                ->with( $this->equalTo( "https://api.mymemory.translated.net/set" ), $this->equalTo( $curl_opts ) )
                ->willReturn( $mock_json_return );

        $engine_MyMemory->call( "contribute_relative_url", $params, true );

        /**
         * Test that the _decode method returns an object of type:
         * @var $returned_object Engines_Results_MyMemory_SetContributionResponse
         */
        $returned_object = $this->resultProperty->getValue( $engine_MyMemory );

        $this->assertTrue( $returned_object instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertEquals( 200, $returned_object->responseStatus );
        $this->assertEquals( [ '0' => "0a64b364-f4f0-d301-66c4-5a6c04c2a2bf" ], $returned_object->responseDetails );
        $this->assertEquals( "OK", $returned_object->responseData );
        $this->assertNull( $returned_object->error );

        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $returned_object );
        $property  = $reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $returned_object ) );
    }


    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::call
     * @throws Exception
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_set_from_MyMemory_Engine_general_check_2() {

        $params = [
                'tnote'    => null,
                'langpair' => 'it-IT|en-US',
                'de'       => $this->param_de,
                'prop'     => $this->prop,
                'key'      => $this->test_key,
                'seg'      => $this->str_seg_2,
                'tra'      => $this->str_tra_2,
        ];

        $engine_Record                                    = $this->engine_DAO->read( new EnginesModel_EngineStruct( [ 'id' => 1 ] ) )[ 0 ];
        $engine_MyMemory                                  = new Engines_MyMemory( $engine_Record );
        $engine_MyMemory->getEngineRecord()[ 'base_url' ] = 'https://memory.wiremockapi.cloud';

        // start test
        $engine_MyMemory->call( "contribute_relative_url", $params, true );

        /**
         * Test that the _decode method returns an object of type:
         * @var $actual_result Engines_Results_MyMemory_SetContributionResponse
         */
        $actual_result = $this->resultProperty->getValue( $engine_MyMemory );

        /**
         * general check on the keys of Engines_Results_MyMemory_SetContributionResponse object returned
         */
        $this->assertTrue( $actual_result instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertFalse( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );

        $this->assertEquals( 200, $actual_result->responseStatus );
        $this->assertEquals( [ '0' => "0a64b364-f4f0-d301-66c4-5a6c04c2a2bf" ], $actual_result->responseDetails );
        $this->assertEquals( "OK", $actual_result->responseData );
        $this->assertNull( $actual_result->error );

    }

    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_delete_from_MyMemory_Engine_general_check() {

        $this->array_param_of_call_for_del[ 'seg' ] = $this->str_seg_3;
        $this->array_param_of_call_for_del[ 'tra' ] = $this->str_tra_3;


        $function_param = "delete_relative_url";


        $this->engine_MyMemory->call( $function_param, $this->array_param_of_call_for_del );
        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $actual_result = $this->resultProperty->getValue( $this->engine_MyMemory );

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $actual_result instanceof Engines_Results_MyMemory_TMS );
        $this->assertTrue( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );


    }


    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_segment_source_italian_target_english_triggered_by_method_delete_from_MyMemory_Engine_stubbed__call_with_mock() {

        $this->array_param_of_call_for_del[ 'seg' ] = $this->str_seg_3;
        $this->array_param_of_call_for_del[ 'tra' ] = $this->str_tra_3;

        $function_param = "delete_relative_url";

        $url_mock_param = "https://api.mymemory.translated.net/delete_by_id?langpair=IT%7CEN&de=demo%40matecat.com&seg=Il+Sistema+registra+le+informazioni+sul+nuovo+film.&tra=The+system+moves++hands+up+in+the+air.";

        $mock_json_return = <<<'TAB'
{"responseStatus":200,"responseData":"Found and deleted 1 segments"}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param_My_Memory ] )->setMethods( [ '_call' ] )->getMock();
        $engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $this->curl_param )->willReturn( $mock_json_return );

        $engine_MyMemory->call( $function_param, $this->array_param_of_call_for_del );


        /**
         * @var Engines_Results_MyMemory_TMS
         */
        $actual_result = $this->resultProperty->getValue( $engine_MyMemory );
        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue( $actual_result instanceof Engines_Results_MyMemory_TMS );
        $this->assertEquals( [], $actual_result->matches );
        $this->assertEquals( 200, $actual_result->responseStatus );
        $this->assertEquals( "", $actual_result->responseDetails );
        $this->assertEquals( "Found and deleted 1 segments", $actual_result->responseData );
        $this->assertNull( $actual_result->error );
        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $actual_result );
        $property  = $reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $actual_result ) );
    }


    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::call
     */
    public function test_call_with_wrong_function_name_for_code_coverage_purpose() {

        $this->array_param_of_call_for_set[ 'seg' ] = $this->str_seg_1;
        $this->array_param_of_call_for_set[ 'tra' ] = $this->str_tra_1;
        $function_param                             = "bar_and_foo_invalid";

        $this->engine_MyMemory->call( $function_param, $this->array_param_of_call_for_set );

        /**
         * @var $result_object Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->resultProperty->getValue( $this->engine_MyMemory );
        $code          = $result_object[ 'error' ][ 'code' ];
        $this->assertEquals( -43, $code );
        $message = $result_object[ 'error' ][ 'message' ];
        $this->assertEquals( " Bad Method Call. Requested method '" . $function_param . "' not Found.", $message );

    }
}