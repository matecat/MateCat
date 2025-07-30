<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Engines_MyMemory::fastAnalysis
 * User: dinies
 * Date: 21/05/16
 * Time: 12.26
 */
class FastAnalysisTest extends AbstractTest {

    /**
     * @group  regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis_general_check() {
        $segment_third_position  = <<<'Z'
<g id=\"1\">Attività formativa</g><g id=\"2\">	<g id=\"3\">SSD</g>	<g id=\"4\">CFU</g></g>

Z;
        $segment_fourth_position = <<<'Z'
<g id=\"1\">Roma ___/___/_____</g><g id=\"2\">	Firma</g>
Z;
        $segment_fifth_position  = <<<'Z'
<g id="1">1</g><g id="2"> Indicare con una croce in questa colonna se l'esame è stato già sostenuto e verbalizzato.</g>
Z;


        $array_paramemeter = [
                '0' => [
                        'jsid'         => "76-1:dfc26ab0205e",
                        'segment'      => "TOTALE CFU verbalizzati",
                        'segment_hash' => "eccb9938b7ae95cb8f37acdb36a58063"
                ],
                '1' => [
                        'jsid'         => "77-1:dfc26ab0205e",
                        'segment'      => "Consapevole del fatto che gli esami del primo anno costituiscono un prerequisito fondamentale per esami degli anni successivi, e pertanto il loro studio non va rimandato, chiedo di poter sostenere al secondo anno ripetente i seguenti esami del terzo anno (fino a 20 CFU):",
                        'segment_hash' => "fdcf6cbdcfac919a045675cc82d90927"
                ],
                '2' => [
                        'jsid'         => "78-1:dfc26ab0205e",
                        'segment'      => $segment_third_position,
                        'segment_hash' => "eac56a5c904d674e15b89b176bded854"
                ],
                '3' => [
                        'jsid'         => "79-1:dfc26ab0205e",
                        'segment'      => $segment_fourth_position,
                        'segment_hash' => "aece7135923fad6843a1460ee2eb2c4a"
                ],
                '4' => [
                        'jsid'         => "81-1:dfc26ab0205e",
                        'segment'      => $segment_fifth_position,
                        'segment_hash' => "fdd0bc16ed4ddd492dc4afd79d177257"
                ]

        ];
        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        $engine_MyMemory = new Engines_MyMemory( $engine_struct_param );
        $result          = $engine_MyMemory->fastAnalysis( $array_paramemeter );

        /**
         * general check of the result object structure
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_AnalyzeResponse );
        $this->assertTrue( property_exists( $result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result, 'responseData' ) );
        $this->assertTrue( property_exists( $result, 'error' ) );
        $this->assertTrue( property_exists( $result, '_rawResponse' ) );
    }

    /**
     * @group  regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis_specific_check() {
        $segment_third_position  = <<<'Z'
<g id=\"1\">Attività formativa</g><g id=\"2\">	<g id=\"3\">SSD</g>	<g id=\"4\">CFU</g></g>

Z;
        $segment_fourth_position = <<<'Z'
<g id=\"1\">Roma ___/___/_____</g><g id=\"2\">	Firma</g>
Z;
        $segment_fifth_position  = <<<'Z'
<g id="1">1</g><g id="2"> Indicare con una croce in questa colonna se l'esame è stato già sostenuto e verbalizzato.</g>
Z;


        $array_paramemeter = [
                '0' => [
                        'jsid'         => "76-1:dfc26ab0205e",
                        'segment'      => "TOTALE CFU verbalizzati",
                        'segment_hash' => "eccb9938b7ae95cb8f37acdb36a58063"
                ],
                '1' => [
                        'jsid'         => "77-1:dfc26ab0205e",
                        'segment'      => "Consapevole del fatto che gli esami del primo anno costituiscono un prerequisito fondamentale per esami degli anni successivi, e pertanto il loro studio non va rimandato, chiedo di poter sostenere al secondo anno ripetente i seguenti esami del terzo anno (fino a 20 CFU):",
                        'segment_hash' => "fdcf6cbdcfac919a045675cc82d90927"
                ],
                '2' => [
                        'jsid'         => "78-1:dfc26ab0205e",
                        'segment'      => $segment_third_position,
                        'segment_hash' => "eac56a5c904d674e15b89b176bded854"
                ],
                '3' => [
                        'jsid'         => "79-1:dfc26ab0205e",
                        'segment'      => $segment_fourth_position,
                        'segment_hash' => "aece7135923fad6843a1460ee2eb2c4a"
                ],
                '4' => [
                        'jsid'         => "81-1:dfc26ab0205e",
                        'segment'      => $segment_fifth_position,
                        'segment_hash' => "fdd0bc16ed4ddd492dc4afd79d177257"
                ]

        ];
        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        $engine_MyMemory = new Engines_MyMemory( $engine_struct_param );
        $result          = $engine_MyMemory->fastAnalysis( $array_paramemeter );

        /**
         * Specific check of the result object structure
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_AnalyzeResponse );

        $this->assertEquals( 200, $result->responseStatus );
        $this->assertEquals( "OK", $result->responseDetails );
        $this->assertCount( 5, $result->responseData );
        $this->assertArrayHasKey( '76-1:dfc26ab0205e', $result->responseData );
        $this->assertArrayHasKey( '77-1:dfc26ab0205e', $result->responseData );
        $this->assertArrayHasKey( '78-1:dfc26ab0205e', $result->responseData );
        $this->assertArrayHasKey( '79-1:dfc26ab0205e', $result->responseData );
        $this->assertArrayHasKey( '81-1:dfc26ab0205e', $result->responseData );
        /**
         * Checking arrays of result in responseData field
         * first
         */
        $array_result_data = $result->responseData[ '76-1:dfc26ab0205e' ];
        $this->assertCount( 2, $array_result_data );
        $this->assertArrayHasKey( 'type', $array_result_data );
        $this->assertArrayHasKey( 'wc', $array_result_data );

        $type = $array_result_data[ 'type' ];
        $this->assertEquals( "NO_MATCH", $type );
        $wc = $array_result_data[ 'wc' ];
        $this->assertEquals( "3", $wc );
        /**
         * second
         */
        $array_result_data = $result->responseData[ '77-1:dfc26ab0205e' ];
        $this->assertCount( 2, $array_result_data );
        $this->assertArrayHasKey( 'type', $array_result_data );
        $this->assertArrayHasKey( 'wc', $array_result_data );

        $type = $array_result_data[ 'type' ];
        $this->assertEquals( "NO_MATCH", $type );
        $wc = $array_result_data[ 'wc' ];
        $this->assertEquals( "44", $wc );
        /**
         * third
         */
        $array_result_data = $result->responseData[ '78-1:dfc26ab0205e' ];
        $this->assertCount( 2, $array_result_data );
        $this->assertArrayHasKey( 'type', $array_result_data );
        $this->assertArrayHasKey( 'wc', $array_result_data );

        $type = $array_result_data[ 'type' ];
        $this->assertEquals( "NO_MATCH", $type );
        $wc = $array_result_data[ 'wc' ];
        $this->assertEquals( 4, $wc );
        /**
         * fourth
         */
        $array_result_data = $result->responseData[ '79-1:dfc26ab0205e' ];
        $this->assertCount( 2, $array_result_data );
        $this->assertArrayHasKey( 'type', $array_result_data );
        $this->assertArrayHasKey( 'wc', $array_result_data );

        $type = $array_result_data[ 'type' ];
        $this->assertEquals( "NO_MATCH", $type );
        $wc = $array_result_data[ 'wc' ];
        $this->assertEquals( 5, $wc );
        /**
         * fifth
         */
        $array_result_data = $result->responseData[ '81-1:dfc26ab0205e' ];
        $this->assertCount( 2, $array_result_data );
        $this->assertArrayHasKey( 'type', $array_result_data );
        $this->assertArrayHasKey( 'wc', $array_result_data );

        $type = $array_result_data[ 'type' ];
        $this->assertEquals( "NO_MATCH", $type );
        $wc = $array_result_data[ 'wc' ];
        $this->assertEquals( 17, $wc );


        $this->assertNull( $result->error );
        /**
         * check of protected property
         */
        $reflector = new ReflectionClass( $result );
        $property  = $reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );
        $this->assertEquals( "", $property->getValue( $result ) );

    }


    /**
     * @group  regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis_with_no_array_as_param_for_coverage_purpose() {

        $array_paramemeter = "bar_and_foo";


        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        $engine_MyMemory = new Engines_MyMemory( $engine_struct_param );
        $this->assertNull( $engine_MyMemory->fastAnalysis( $array_paramemeter ) );
    }


    /**
     * @group  regression
     * @covers Engines_MyMemory::fastAnalysis
     */
    public function test_fastAnalysis_with_error_from_mocked__call_for_coverage_purpose() {
        $segment_third_position  = <<<'Z'
<g id=\"1\">Attività formativa</g><g id=\"2\">	<g id=\"3\">SSD</g>	<g id=\"4\">CFU</g></g>

Z;
        $segment_fourth_position = <<<'Z'
<g id=\"1\">Roma ___/___/_____</g><g id=\"2\">	Firma</g>
Z;
        $segment_fifth_position  = <<<'Z'
<g id="1">1</g><g id="2"> Indicare con una croce in questa colonna se l'esame è stato già sostenuto e verbalizzato.</g>
Z;


        $array_paramemeter = [
                '0' => [
                        'jsid'         => "76-1:dfc26ab0205e",
                        'segment'      => "TOTALE CFU verbalizzati",
                        'segment_hash' => "eccb9938b7ae95cb8f37acdb36a58063"
                ],
                '1' => [
                        'jsid'         => "77-1:dfc26ab0205e",
                        'segment'      => "Consapevole del fatto che gli esami del primo anno costituiscono un prerequisito fondamentale per esami degli anni successivi, e pertanto il loro studio non va rimandato, chiedo di poter sostenere al secondo anno ripetente i seguenti esami del terzo anno (fino a 20 CFU):",
                        'segment_hash' => "fdcf6cbdcfac919a045675cc82d90927"
                ],
                '2' => [
                        'jsid'         => "78-1:dfc26ab0205e",
                        'segment'      => $segment_third_position,
                        'segment_hash' => "eac56a5c904d674e15b89b176bded854"
                ],
                '3' => [
                        'jsid'         => "79-1:dfc26ab0205e",
                        'segment'      => $segment_fourth_position,
                        'segment_hash' => "aece7135923fad6843a1460ee2eb2c4a"
                ],
                '4' => [
                        'jsid'         => "81-1:dfc26ab0205e",
                        'segment'      => $segment_fifth_position,
                        'segment_hash' => "fdd0bc16ed4ddd492dc4afd79d177257"
                ]

        ];

        $curl_mock_param = [
                CURLOPT_POSTFIELDS  => json_encode( $array_paramemeter ),
                CURLOPT_TIMEOUT     => 120,
                CURLINFO_HEADER_OUT => true,
        ];

        $url_mock_param = "https://analyze.mymemory.translated.net/api/v1/analyze";

        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );


        $rawValue_error = [
                'error'          => [
                        'code'     => -6,
                        'message'  => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                        'response' => "",
                ],
                'responseStatus' => 0
        ];


        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];


        /**
         * @var Engines_MyMemory
         */
        $engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $engine_struct_param ] )->setMethods( [ '_call' ] )->getMock();
        $engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $rawValue_error );

        $result = $engine_MyMemory->fastAnalysis( $array_paramemeter );

        /**
         * general check of the result object structure
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_AnalyzeResponse );
        $this->assertEquals( 0, $result->responseStatus );
        $this->assertEquals( "", $result->responseDetails );
        $this->assertEquals( "", $result->responseData );
        $this->assertTrue( $result->error instanceof Engines_Results_ErrorMatches );

        $this->assertEquals( -6, $result->error->code );
        $this->assertEquals( "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result->error->message );

        $reflector = new ReflectionClass( $result );
        $property  = $reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result ) );


    }

}