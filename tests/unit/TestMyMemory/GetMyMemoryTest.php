<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Engines_MyMemory::get
 * User: dinies
 * Date: 28/04/16
 * Time: 16.12
 */
class GetMyMemoryTest extends AbstractTest {

    /**
     * @var EnginesModel_EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var array
     */
    protected $others_param;
    /**
     * @var Engines_MyMemory
     */
    protected $engine_MyMemory;
    /**
     * @var array
     */
    protected $config_param_of_get;

    protected $reflector;


    public function setUp() {
        parent::setUp();

        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $this->engine_struct_param = @$eng[ 0 ];


        $this->config_param_of_get = [
                'translation'   => "",
                'tnote'         => null,
                'source'        => "it-IT",
                'target'        => "en-US",
                'email'         => "demo@matecat.com",
                'prop'          => null,
                'get_mt'        => true,
                'id_user'       => [ 0 => "a6043e606ac9b5d7ff24" ],
                'num_result'    => 3,
                'mt_only'       => false,
                'isConcordance' => false,
                'isGlossary'    => false,
        ];


    }

    /**
     * @group  regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_segment_dutch() {

        $this->engine_MyMemory = new Engines_MyMemory( $this->engine_struct_param );

        $this->config_param_of_get[ 'segment' ] = "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.";

        $result = $this->engine_MyMemory->get( $this->config_param_of_get );
        $this->assertEquals( 200, $result->responseStatus );
        $this->assertEquals( "", $result->responseDetails );
        $this->assertCount( 2, $result->responseData );
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_TMS );

        $this->reflector = new ReflectionClass( $result );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result ) );

    }

    /**
     * Test that verified the behaviour of a get request for the translation
     * of a segment given personal tm with respective id_user.
     * @group  regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_segment_italian_with_id_user_initialized() {

        $this->engine_MyMemory = new Engines_MyMemory( $this->engine_struct_param );


        $this->config_param_of_get[ 'segment' ] = "L’Amministratore inserisce titolo, anno di produzione e codice univoco del nuovo film.";


        $result = $this->engine_MyMemory->get( $this->config_param_of_get );

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_TMS );
        $this->assertTrue( property_exists( $result, 'matches' ) );
        $this->assertTrue( property_exists( $result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result, 'responseData' ) );
        $this->assertTrue( property_exists( $result, 'error' ) );
        $this->assertTrue( property_exists( $result, '_rawResponse' ) );

    }

    /**
     * Test that verified the behaviour of a get request for the translation of a
     * segment with wrong source language.
     * @group  regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_segment_italian_with_wrong_source_language_and_id_user_not_in_array_coverage_purpose() {

        $this->engine_MyMemory = new Engines_MyMemory( $this->engine_struct_param );


        $this->config_param_of_get[ 'segment' ] = "Scelta del Piano di studio parziale per il secondo anno ripetente secondo l’Ordinamento D.M. 270/04";
        $this->config_param_of_get[ 'id_user' ] = "bfb9bd80a43253670c8d";


        $result = $this->engine_MyMemory->get( $this->config_param_of_get );


        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_TMS );
        $this->assertTrue( property_exists( $result, 'matches' ) );
        $this->assertTrue( property_exists( $result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result, 'responseData' ) );
        $this->assertTrue( property_exists( $result, 'error' ) );
        $this->assertTrue( property_exists( $result, '_rawResponse' ) );

    }

    /**
     * It tests the behaviour with the return of the inner method _call simulated by a mock object.
     * This test certificates the righteousness of code without involving the _call method.
     * @group  regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_segment_with_mock_for__call0() {

        $this->config_param_of_get[ 'segment' ] = "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.";

        $curl_mock_param = [
                CURLOPT_POSTFIELDS  =>
                        [
                                'q'        => 'Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.',
                                'langpair' => 'it-IT|en-US',
                                'de'       => 'demo@matecat.com',
                                'mt'       => true,
                                'numres'   => 3,
                                'key'      => 'a6043e606ac9b5d7ff24',
                                'client_id' => 0
                        ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120
        ];

        $url_mock_param   = "https://api.mymemory.translated.net/get";
        $mock_json_return = <<<'TAB'
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"responderId":null,"matches":[]}
TAB;

        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = @$this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param ] )->setMethods( [ '_call' ] )->getMock();
        $this->engine_MyMemory->expects( $this->exactly( 1 ) )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $mock_json_return );

        $result = $this->engine_MyMemory->get( $this->config_param_of_get );
        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_TMS );
        $this->assertTrue( property_exists( $result, 'matches' ) );
        $this->assertTrue( property_exists( $result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result, 'responseData' ) );
        $this->assertTrue( property_exists( $result, 'error' ) );
        $this->assertTrue( property_exists( $result, '_rawResponse' ) );
        /**
         * specific check
         */
        $this->assertEquals( [], $result->matches );
        $this->assertEquals( 200, $result->responseStatus );
        $this->assertEquals( "", $result->responseDetails );
        $this->assertCount( 2, $result->responseData );
        $this->assertNull( $result->error );
        $this->assertNull( $result->responseData[ 'translatedText' ] );
        $this->assertNull( $result->responseData[ 'match' ] );
        $this->reflector = new ReflectionClass( $result );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result ) );

    }

    /**
     * It tests the behaviour with the return of the inner method _call simulated by a mock object.
     * This test certificates the righteousness of code without involving the _call method.
     * @group  regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_segment_with_mock_for__call_and_at_least_one_match_found_in_TM() {

        $this->config_param_of_get[ 'segment' ] = "Ciascuna copia è dotata di un numero di serie univoco.";
        $curl_mock_param = [
                CURLOPT_POSTFIELDS  =>
                        [
                                'q'        => 'Ciascuna copia è dotata di un numero di serie univoco.',
                                'langpair' => 'it-IT|en-US',
                                'de'       => 'demo@matecat.com',
                                'mt'       => true,
                                'numres'   => 3,
                                'key'      => 'a6043e606ac9b5d7ff24',
                                'client_id' => 0
                        ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120
        ];

        $url_mock_param   = "https://api.mymemory.translated.net/get";
        $mock_json_return = <<<'TAB'
{"responseData":{"translatedText":"Each copy has a unique serial number.","match":1},"responseDetails":"","responseStatus":200,"responderId":"238","matches":[{"id":"484523811","segment":"Ciascuna copia \u00e8 dotata di un numero di serie univoco.","translation":"Each copy has a unique serial number.","quality":"74","reference":"","usage-count":1,"subject":"All","created-by":"MyMemory_65655950851269d899c7","last-updated-by":"MyMemory_65655950851269d899c7","create-date":"2016-05-02 17:15:11","last-update-date":"2016-05-02 17:15:11","tm_properties":"[{\"type\":\"x-project_id\",\"value\":\"9\"},{\"type\":\"x-project_name\",\"value\":\"eryt\"},{\"type\":\"x-job_id\",\"value\":\"9\"}]","match":1,"key":"a6043e606ac9b5d7ff24"},{"id":0,"segment":"Ciascuna copia \u00e8 dotata di un numero di serie univoco.","translation":"Each copy has a unique serial number.","quality":70,"reference":"Machine Translation provided by Google, Microsoft, Worldlingo or MyMemory customized engine.","usage-count":1,"subject":false,"created-by":"MT!","last-updated-by":"MT!","create-date":"2016-05-02 17:20:51","last-update-date":"2016-05-02 17:20:51","tm_properties":"","match":0.85},{"id":"418675820","segment":"- un numero di serie univoco;","translation":"- a unique serial number;","quality":"0","reference":"http:\/\/eur-lex.europa.eu\/LexUriServ\/LexUriServ.do?uri=OJ:L:2007:084:0007:01:EN:HTML|@|http:\/\/eur-lex.europa.eu\/LexUriServ\/LexUriServ.do?uri=OJ:L:2007:084:0007:01:IT:HTML","usage-count":1,"subject":"Legal_and_Notarial","created-by":"Europa.eu","last-updated-by":"Europa.eu","create-date":"0000-00-00 00:00:00","last-update-date":"0000-00-00 00:00:00","tm_properties":null,"match":0.58}]}
TAB;


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = $this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param ] )->setMethods( [ '_call' ] )->getMock();
        $this->engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $mock_json_return );

        $result = $this->engine_MyMemory->get( $this->config_param_of_get );
        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_TMS );
        $this->assertTrue( property_exists( $result, 'matches' ) );
        $this->assertTrue( property_exists( $result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result, 'responseData' ) );
        $this->assertTrue( property_exists( $result, 'error' ) );
        $this->assertTrue( property_exists( $result, '_rawResponse' ) );
        /**
         * check of the 3 matches obtained from MyMemory
         */
        $this->assertCount( 3, $result->matches );
        $this->assertTrue( $result->matches[ 0 ] instanceof Engines_Results_MyMemory_Matches );
        $this->assertTrue( $result->matches[ 1 ] instanceof Engines_Results_MyMemory_Matches );
        $this->assertTrue( $result->matches[ 2 ] instanceof Engines_Results_MyMemory_Matches );

        $result->matches = $result->get_matches_as_array();

        /**
         * 1st match
         */
        $this->assertEquals( "484523811", $result->matches[ 0 ][ 'id' ] );
        $this->assertEquals( "Ciascuna copia è dotata di un numero di serie univoco.", $result->matches[ 0 ][ 'raw_segment' ] );
        $this->assertEquals( "Ciascuna copia è dotata di un numero di serie univoco.", $result->matches[ 0 ][ 'segment' ] );
        $this->assertEquals( "Each copy has a unique serial number.", $result->matches[ 0 ][ 'translation' ] );
        $this->assertEquals( "", $result->matches[ 0 ][ 'target_note' ] );
        $this->assertEquals( "Each copy has a unique serial number.", $result->matches[ 0 ][ 'raw_translation' ] );
        $this->assertEquals( "74", $result->matches[ 0 ][ 'quality' ] );
        $this->assertEquals( "", $result->matches[ 0 ][ 'reference' ] );
        $this->assertEquals( "1", $result->matches[ 0 ][ 'usage_count' ] );
        $this->assertEquals( "All", $result->matches[ 0 ][ 'subject' ] );
        $this->assertEquals( "MyMemory_65655950851269d899c7", $result->matches[ 0 ][ 'created_by' ] );
        $this->assertEquals( "MyMemory_65655950851269d899c7", $result->matches[ 0 ][ 'last_updated_by' ] );
        $this->assertEquals( "2016-05-02 17:15:11", $result->matches[ 0 ][ 'create_date' ] );
        $this->assertEquals( "2016-05-02", $result->matches[ 0 ][ 'last_update_date' ] );
        $this->assertEquals( "100%", $result->matches[ 0 ][ 'match' ] );
        $this->assertEquals( [], $result->matches[ 0 ][ 'prop' ] );
        $this->assertEquals( "", $result->matches[ 0 ][ 'source_note' ] );
        $this->assertEquals( "a6043e606ac9b5d7ff24", $result->matches[ 0 ][ 'memory_key' ] );
        /**
         * 2nd match
         */
        $this->assertEquals( "0", $result->matches[ 1 ][ 'id' ] );
        $this->assertEquals( "Ciascuna copia è dotata di un numero di serie univoco.", $result->matches[ 1 ][ 'raw_segment' ] );
        $this->assertEquals( "Ciascuna copia è dotata di un numero di serie univoco.", $result->matches[ 1 ][ 'segment' ] );
        $this->assertEquals( "Each copy has a unique serial number.", $result->matches[ 1 ][ 'translation' ] );
        $this->assertEquals( "", $result->matches[ 1 ][ 'target_note' ] );
        $this->assertEquals( "Each copy has a unique serial number.", $result->matches[ 1 ][ 'raw_translation' ] );
        $this->assertEquals( "70", $result->matches[ 1 ][ 'quality' ] );
        $this->assertEquals( "Machine Translation provided by Google, Microsoft, Worldlingo or MyMemory customized engine.", $result->matches[ 1 ][ 'reference' ] );
        $this->assertEquals( "1", $result->matches[ 1 ][ 'usage_count' ] );
        $this->assertEquals( false, $result->matches[ 1 ][ 'subject' ] );
        $this->assertEquals( "MT!", $result->matches[ 1 ][ 'created_by' ] );
        $this->assertEquals( "MT!", $result->matches[ 1 ][ 'last_updated_by' ] );
        $this->assertEquals( "2016-05-02 17:20:51", $result->matches[ 1 ][ 'create_date' ] );
        $this->assertEquals( "2016-05-02", $result->matches[ 1 ][ 'last_update_date' ] );
        $this->assertEquals( "85%", $result->matches[ 1 ][ 'match' ] );
        $this->assertEquals( [], $result->matches[ 1 ][ 'prop' ] );
        $this->assertEquals( "", $result->matches[ 1 ][ 'source_note' ] );
        $this->assertEquals( "", $result->matches[ 1 ][ 'memory_key' ] );
        /**
         * 3rd match
         */
        $this->assertEquals( "418675820", $result->matches[ 2 ][ 'id' ] );
        $this->assertEquals( "- un numero di serie univoco;", $result->matches[ 2 ][ 'raw_segment' ] );
        $this->assertEquals( "- un numero di serie univoco;", $result->matches[ 2 ][ 'segment' ] );
        $this->assertEquals( "- a unique serial number;", $result->matches[ 2 ][ 'translation' ] );
        $this->assertEquals( "", $result->matches[ 2 ][ 'target_note' ] );
        $this->assertEquals( "- a unique serial number;", $result->matches[ 2 ][ 'raw_translation' ] );
        $this->assertEquals( "0", $result->matches[ 2 ][ 'quality' ] );
        $this->assertEquals( "http://eur-lex.europa.eu/LexUriServ/LexUriServ.do?uri=OJ:L:2007:084:0007:01:EN:HTML|@|http://eur-lex.europa.eu/LexUriServ/LexUriServ.do?uri=OJ:L:2007:084:0007:01:IT:HTML", $result->matches[ 2 ][ 'reference' ] );
        $this->assertEquals( "1", $result->matches[ 2 ][ 'usage_count' ] );
        $this->assertEquals( "Legal_and_Notarial", $result->matches[ 2 ][ 'subject' ] );
        $this->assertEquals( "Europa.eu", $result->matches[ 2 ][ 'created_by' ] );
        $this->assertEquals( "Europa.eu", $result->matches[ 2 ][ 'last_updated_by' ] );
        $this->assertEquals( "0000-00-00 00:00:00", $result->matches[ 2 ][ 'create_date' ] );
        $this->assertEquals( "0000-00-00", $result->matches[ 2 ][ 'last_update_date' ] );
        $this->assertEquals( "58%", $result->matches[ 2 ][ 'match' ] );
        $this->assertEquals( [], $result->matches[ 2 ][ 'prop' ] );
        $this->assertEquals( "", $result->matches[ 2 ][ 'source_note' ] );
        $this->assertEquals( "", $result->matches[ 2 ][ 'memory_key' ] );
        /**
         *it is going on with TSM structure
         */
        $this->assertEquals( 200, $result->responseStatus );
        $this->assertEquals( "", $result->responseDetails );
        $this->assertCount( 2, $result->responseData );
        $this->assertEquals( "Each copy has a unique serial number.", $result->responseData[ 'translatedText' ] );
        $this->assertEquals( 1, $result->responseData[ 'match' ] );
        $this->assertNull( $result->error );
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass( $result );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result ) );

    }

    /**
     * @group  regression
     * @covers Engines_MyMemory::get
     */
    public function test_get_with_error_from_mocked__call_for_coverage_purpose() {

        $this->config_param_of_get[ 'segment' ] = "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.";

        $curl_mock_param = [
                CURLOPT_POSTFIELDS  =>
                        [
                                'q'        => 'Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.',
                                'langpair' => 'it-IT|en-US',
                                'de'       => 'demo@matecat.com',
                                'mt'       => true,
                                'numres'   => 3,
                                'key'      => 'a6043e606ac9b5d7ff24',
                                'client_id' => 0
                        ],
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT     => 120
        ];

        $url_mock_param  = "https://api.mymemory.translated.net/get";

        $rawValue_error = [
                'error'          => [
                        'code'     => -6,
                        'message'  => "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)",
                        'response' => "",
                ],
                'responseStatus' => 0
        ];


        /**
         * @var Engines_MyMemory
         */
        $this->engine_MyMemory = $this->getMockBuilder( '\Engines_MyMemory' )->setConstructorArgs( [ $this->engine_struct_param ] )->setMethods( [ '_call' ] )->getMock();
        $this->engine_MyMemory->expects( $this->once() )->method( '_call' )->with( $url_mock_param, $curl_mock_param )->willReturn( $rawValue_error );

        $result = $this->engine_MyMemory->get( $this->config_param_of_get );
        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $result instanceof Engines_Results_MyMemory_TMS );
        $this->assertTrue( property_exists( $result, 'matches' ) );
        $this->assertTrue( property_exists( $result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $result, 'responseData' ) );
        $this->assertTrue( property_exists( $result, 'error' ) );
        $this->assertTrue( property_exists( $result, '_rawResponse' ) );
        /**
         * specific check
         */
        $this->assertEquals( [], $result->matches );
        $this->assertEquals( 0, $result->responseStatus );
        $this->assertEquals( "", $result->responseDetails );
        $this->assertEquals( "", $result->responseData );
        $this->assertTrue( $result->error instanceof Engines_Results_ErrorMatches );

        $this->assertEquals( -6, $result->error->code );
        $this->assertEquals( "Could not resolve host: api.mymemory.translated.net. Server Not Available (http status 0)", $result->error->message );

        $this->reflector = new ReflectionClass( $result );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result ) );

    }

}