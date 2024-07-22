<?php
//TODO:estendere
use TestHelpers\AbstractTest;


/**
 * @group   regression
 * @covers  Engines_AbstractEngine::call
 * User: dinies
 * Date: 22/04/16
 * Time: 11.47
 */
class CallAbstractMyMemoryTest extends AbstractTest {

    /**
     * @var PHPUnit_Framework_MockObject_MockObject | Engines_MyMemory
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

    protected $prop;
    protected $resultProperty;
    protected $test_key;
    /**
     * @var string
     */
    private $param_de = "demo@matecat.com";
    /**
     * @var string
     */
    private $response_data;


    /**
     * @throws Exception
     */
    public function setUp() {
        parent::setUp();

        $engine_struct_MyMemory     = EnginesModel_EngineStruct::getStruct();
        $engine_struct_MyMemory->id = 1;

        $engine_struct_MyMemory = ( new EnginesModel_EngineDAO() )->read( $engine_struct_MyMemory )[ 0 ];

        /** @var $engine_MyMemory PHPUnit_Framework_MockObject_MockObject | Engines_MyMemory */
        $this->engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $engine_struct_MyMemory ] )->setMethods( [ '_call' ] )->getMock();

        $reflector            = new ReflectionClass( $this->engine_MyMemory );
        $this->resultProperty = $reflector->getProperty( "result" );
        $this->resultProperty->setAccessible( true );

        $this->test_key = "a6043e606ac9b5d7ff24";

        $this->str_seg_1 = "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.";
        $this->str_seg_2 = 'Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.';
        $this->str_seg_3 = "Il Sistema registra le informazioni sul nuovo film.";

        $this->str_tra_1 = "The system becomes bar and thinks foo.";
        $this->str_tra_2 = 'For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.';
        $this->str_tra_3 = "The system moves  hands up in the air.";

        $this->prop = '{"project_id":"987654","project_name":"barfoo","job_id":"321"}';

        $this->response_data = '{"responseData":"OK","responseStatus":200,"responseDetails":["0a64b364-f4f0-d301-66c4-5a6c04c2a2bf"]}';

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

        $curl_opts = [
                CURLOPT_POSTFIELDS  => $params,
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120
        ];

        $this->engine_MyMemory->expects( $this->once() )
                ->method( '_call' )
                ->with( $this->equalTo( "https://api.mymemory.translated.net/set" ), $this->equalTo( $curl_opts ) )
                ->willReturn( $this->response_data );

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

        $curl_opts = [
                CURLOPT_POSTFIELDS  => $params,
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120
        ];

        $this->engine_MyMemory->expects( $this->once() )
                ->method( '_call' )
                ->with( $this->equalTo( "https://api.mymemory.translated.net/set" ), $this->equalTo( $curl_opts ) )
                ->willReturn( $this->response_data );

        $this->engine_MyMemory->call( "contribute_relative_url", $params, true );

        /**
         * Test that the _decode method returns an object of type:
         * @var $returned_object Engines_Results_MyMemory_SetContributionResponse
         */
        $returned_object = $this->resultProperty->getValue( $this->engine_MyMemory );

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

        $curl_opts = [
                CURLOPT_POSTFIELDS  => $params,
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120
        ];

        $this->engine_MyMemory->expects( $this->once() )
                ->method( '_call' )
                ->with( $this->equalTo( "https://api.mymemory.translated.net/set" ), $this->equalTo( $curl_opts ) )
                ->willReturn( $this->response_data );

        $this->engine_MyMemory->call( "contribute_relative_url", $params, true );

        /**
         * Test that the _decode method returns an object of type:
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

        $params = [
                'langpair' => "IT|EN",
                'de'       => $this->param_de,
                'seg'      => $this->str_seg_3,
                'tra'      => $this->str_tra_3,
        ];


        $this->engine_MyMemory->call( "delete_relative_url", $params );
        /**
         * @var $actual_result Engines_Results_MyMemory_TMS
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

        $params = [
                'langpair' => "IT|EN",
                'de'       => $this->param_de,
                'seg'      => $this->str_seg_3,
                'tra'      => $this->str_tra_3,
        ];

        $url_mock_param = "https://api.mymemory.translated.net/delete_by_id";

        /** @var array $url_mock_param */
        $this->engine_MyMemory
                ->expects( $this->once() )
                ->method( '_call' )
                ->with( $url_mock_param, [
                        CURLOPT_POSTFIELDS  => $params,
                        CURLINFO_HEADER_OUT => true,
                        CURLOPT_TIMEOUT     => 120
                ] )
                ->willReturn( '{"responseStatus":200,"responseData":"Found and deleted 1 segments"}' );

        $this->engine_MyMemory->call( "delete_relative_url", $params, true );


        /**
         * @var $actual_result Engines_Results_MyMemory_TMS
         */
        $actual_result = $this->resultProperty->getValue( $this->engine_MyMemory );
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

        $function_param = "bar_and_foo_invalid";
        $this->engine_MyMemory->call( $function_param, [
                'langpair' => "IT|EN",
                'de'       => $this->param_de,
        ] );

        /**
         * @var $result_object Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->resultProperty->getValue( $this->engine_MyMemory );
        $code          = $result_object[ 'error' ][ 'code' ];
        $this->assertEquals( -43, $code );
        $message = $result_object[ 'error' ][ 'message' ];
        $this->assertEquals( " Bad Method Call. Requested method '" . $function_param . "' not Found.", $message );

    }

    /**
     * @group   regression
     * @covers  Engines_AbstractEngine::call
     * @throws Exception
     */
    public function test_real_get() {

        $params = [
                'de'       => $this->param_de,
                'key'      => $this->test_key,
                'langpair' => "en-US|it-IT",
                'q'        => 'Receipt Number:&#09;&#09; ciaone',
        ];

        $url_mock_param = 'https://api.mymemory.translated.net/get';

        $engine_struct_MyMemory     = EnginesModel_EngineStruct::getStruct();
        $engine_struct_MyMemory->id = 1;

        $engine_struct_MyMemory = ( new EnginesModel_EngineDAO() )->read( $engine_struct_MyMemory )[ 0 ];
        $engine_MyMemory        = new Engines_MyMemory( $engine_struct_MyMemory );

        $engine_MyMemory->call( "translate_relative_url", $params, true );

        $reflector      = new ReflectionClass( $engine_MyMemory );
        $resultProperty = $reflector->getProperty( "result" );
        $resultProperty->setAccessible( true );
        /**
         * @var $actual_result Engines_Results_MyMemory_TMS
         */
        $actual_result = $resultProperty->getValue( $engine_MyMemory );

        /**
         * check on the values of TMS object returned
         */
        $this->assertTrue( $actual_result instanceof Engines_Results_MyMemory_TMS );
        $this->assertCount( 3, $actual_result->matches );
        $this->assertEquals( 200, $actual_result->responseStatus );
        $this->assertEquals( "", $actual_result->responseDetails );
        $this->assertTrue( is_array( $actual_result->responseData ) );
        $this->assertNull( $actual_result->error );
        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $actual_result );
        $property  = $reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $actual_result ) );
    }
}