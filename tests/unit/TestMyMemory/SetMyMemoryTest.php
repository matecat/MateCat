<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Engines_MyMemory::set
 * User: dinies
 * Date: 02/05/16
 * Time: 18.22
 */
class SetMyMemoryTest extends AbstractTest {

    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;

    protected $reflector;
    protected $property;

    /**
     * @var string
     */
    protected $str_seg_1;
    protected $str_seg_2;
    protected $str_tra_1;
    protected $str_tra_2;

    /**
     * @var json
     */
    protected $prop;

    /**
     * @throws ReflectionException
     */
    public function setUp() {
        parent::setUp();

        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = $eng[ 0 ];


        /**
         * parameters initialization
         */

        $this->str_seg_1 = "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.";
        $this->str_seg_2 = 'Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.';

        $this->str_tra_1 = "The system becomes bar and thinks foo.";
        $this->str_tra_2 = 'For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.';
        $this->prop      = '{"project_id":"987654","project_name":"barfoo","job_id":"321"}';

        $this->engine_MyMemory = new Engines_MyMemory( $this->engine_struct_param );
        $this->reflector       = new ReflectionClass( $this->engine_MyMemory );
        $this->property        = $this->reflector->getProperty( "result" );
        $this->property->setAccessible( true );


    }

    /**
     * @group  regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_1_general_check() {

        $params = [
                'tnote'         => null,
                'source'        => "it-IT",
                'target'        => "en-US",
                'email'         => "demo@matecat.com",
                'prop'          => $this->prop,
                'get_mt'        => 1,
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
                'id_user'       => [],
                'segment'       => $this->str_seg_1,
                'translation'   => $this->str_tra_1,
                'key'           => 'a6043e606ac9b5d7ff24'
        ];

        $result = $this->engine_MyMemory->set( $params );

        $this->assertTrue( (bool)preg_match( '/^[\dA-F]{8}-[\dA-F]{4}-[\dA-F]{4}-[\dA-F]{4}-[\dA-F]{12}$/i', $result ) );

        /**
         *  general check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var $result_object Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue( $this->engine_MyMemory );

        $this->assertTrue( $result_object instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertFalse( property_exists( $result_object, 'matches' ) );
        $this->assertTrue( property_exists( $result_object, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result_object, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result_object, 'responseData' ) );
        $this->assertTrue( property_exists( $result_object, 'error' ) );
        $this->assertTrue( property_exists( $result_object, '_rawResponse' ) );


    }

    /**
     * @group  regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_1_with_mock() {

        $params = [
                'tnote'         => null,
                'source'        => "it-IT",
                'target'        => "en-US",
                'email'         => "demo@matecat.com",
                'prop'          => $this->prop,
                'get_mt'        => 1,
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
                'id_user'       => [ 'a6043e606ac9b5d7ff24' ],
                'segment'       => $this->str_seg_1,
                'translation'   => $this->str_tra_1
        ];

        $mock_json_return = <<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484525156]}
TAB;

        $curl_params = [
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120,
                CURLOPT_POSTFIELDS  => [
                        'seg'       => 'Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.',
                        'tra'       => 'The system becomes bar and thinks foo.',
                        'tnote'     => null,
                        'langpair'  => 'it-IT|en-US',
                        'de'        => 'demo@matecat.com',
                        'mt'        => true,
                        'client_id' => 0,
                        'prop'      => '{"project_id":"987654","project_name":"barfoo","job_id":"321"}',
                        'key'       => 'a6043e606ac9b5d7ff24'
                ],
        ];


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param ] )->setMethods( [ '_call' ] )->getMock();
        $this->engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $this->anything(), $curl_params )->willReturn( $mock_json_return );

        $actual_result = $this->engine_MyMemory->set( $params );

        $this->assertEquals( 484525156, $actual_result );

        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var $result_object Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue( $this->engine_MyMemory );

        $this->assertTrue( $result_object instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertEquals( 200, $result_object->responseStatus );
        $this->assertEquals( [ '0' => 484525156 ], $result_object->responseDetails );
        $this->assertEquals( "OK", $result_object->responseData );
        $this->assertNull( $result_object->error );
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass( $result_object );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result_object ) );


    }

    /**
     * @group  regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_segment_2_general_check_with_id_user_not_in_array_coverage_purpose() {

        $params = [
                'tnote'         => null,
                'source'        => "it-IT",
                'target'        => "en-US",
                'email'         => "demo@matecat.com",
                'prop'          => $this->prop,
                'get_mt'        => 1,
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
                'id_user'       => [],
                'segment'       => $this->str_seg_1,
                'translation'   => $this->str_tra_1,
                'key'           => 'a6043e606ac9b5d7ff24'
        ];

        $result = $this->engine_MyMemory->set( $params );

        $this->assertTrue( (bool)preg_match( '/^[\dA-F]{8}-[\dA-F]{4}-[\dA-F]{4}-[\dA-F]{4}-[\dA-F]{12}$/i', $result ) );

        /**
         *  general check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var $result_object Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue( $this->engine_MyMemory );

        $this->assertTrue( $result_object instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertFalse( property_exists( $result_object, 'matches' ) );
        $this->assertTrue( property_exists( $result_object, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result_object, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result_object, 'responseData' ) );
        $this->assertTrue( property_exists( $result_object, 'error' ) );
        $this->assertTrue( property_exists( $result_object, '_rawResponse' ) );


    }

    public function test_set_segment_2_with_mock() {

        $params = [
                'tnote'         => null,
                'source'        => "it-IT",
                'target'        => "en-US",
                'email'         => "demo@matecat.com",
                'prop'          => $this->prop,
                'get_mt'        => 1,
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
                'segment'       => $this->str_seg_2,
                'translation'   => $this->str_tra_2,
                'id_user'       => [ 'a6043e606ac9b5d7ff24' ]
        ];

        $url_mock_param = "https://api.mymemory.translated.net/set";

        $mock_json_return = <<<'TAB'
{"responseData":"OK","responseStatus":200,"responseDetails":[484540480]}
TAB;

        $curl_params = [
                CURLOPT_POSTFIELDS  => [
                        'seg'       => 'Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.',
                        'tra'       => 'For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.',
                        'tnote'     => null,
                        'langpair'  => 'it-IT|en-US',
                        'de'        => 'demo@matecat.com',
                        'mt'        => true,
                        'client_id' => 0,
                        'prop'      => '{"project_id":"987654","project_name":"barfoo","job_id":"321"}',
                        'key'       => 'a6043e606ac9b5d7ff24'
                ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120,
        ];

        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param ] )->setMethods( [ '_call' ] )->getMock();
        $this->engine_MyMemory->expects( $this->once() )->method( '_call' )->with(
                $url_mock_param,
                $curl_params
        )->willReturn( $mock_json_return );

        $actual_result = $this->engine_MyMemory->set( $params );

        $this->assertEquals( 484540480, $actual_result );


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue( $this->engine_MyMemory );

        $this->assertTrue( $result_object instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertEquals( 200, $result_object->responseStatus );
        $this->assertEquals( [ '0' => 484540480 ], $result_object->responseDetails );
        $this->assertEquals( "OK", $result_object->responseData );
        $this->assertNull( $result_object->error );
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass( $result_object );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result_object ) );


    }

    /**
     * @group  regression
     * @covers Engines_MyMemory::set
     */
    public function test_set_with_error_from_mocked__call() {

        $params = [
                'tnote'         => null,
                'source'        => "it-IT",
                'target'        => "en-US",
                'email'         => "demo@matecat.com",
                'prop'          => $this->prop,
                'get_mt'        => 1,
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
                'segment'       => $this->str_seg_1,
                'translation'   => $this->str_tra_1,
                'id_user'       => [ 'a6043e606ac9b5d7ff24' ]
        ];

        $url_mock_param = "https://api.mymemory.translated.net/set";

        $rawValue_error = [
                'error'          => [
                        'code'     => -6,
                        'message'  => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                        'response' => "",
                ],
                'responseStatus' => 0
        ];

        $curl_params = [
                CURLOPT_POSTFIELDS  => [
                        'seg'       => 'Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.',
                        'tra'       => 'The system becomes bar and thinks foo.',
                        'tnote'     => null,
                        'langpair'  => 'it-IT|en-US',
                        'de'        => 'demo@matecat.com',
                        'prop'      => '{"project_id":"987654","project_name":"barfoo","job_id":"321"}',
                        'key'       => 'a6043e606ac9b5d7ff24',
                        'mt'        => true,
                        'client_id' => 0
                ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120,
        ];


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param ] )->setMethods( [ '_call' ] )->getMock();
        $this->engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_params )->willReturn( $rawValue_error );

        $actual_result = $this->engine_MyMemory->set( $params );

        $this->assertFalse( $actual_result );


        /**
         * check of the Engines_Results_MyMemory_SetContributionResponse object
         * @var Engines_Results_MyMemory_SetContributionResponse
         */
        $result_object = $this->property->getValue( $this->engine_MyMemory );

        $this->assertTrue( $result_object instanceof Engines_Results_MyMemory_SetContributionResponse );
        $this->assertEquals( 0, $result_object->responseStatus );
        $this->assertEquals( "", $result_object->responseDetails );
        $this->assertEquals( "", $result_object->responseData );
        $this->assertTrue( $result_object->error instanceof Engines_Results_ErrorMatches );

        $this->assertEquals( -6, $result_object->error->code );
        $this->assertEquals( "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result_object->error->message );

        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass( $result_object );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result_object ) );


    }

}