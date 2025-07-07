<?php

use Model\Database;
use Model\Engines\EngineDAO;
use Model\Engines\EngineStruct;
use TestHelpers\AbstractTest;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\SetContributionResponse;


/**
 * @group  regression
 * @covers MyMemory::_decode
 * User: dinies
 * Date: 28/04/16
 * Time: 17.58
 */
class DecodeMyMemoryTest extends AbstractTest {
    /**
     * @var EngineStruct
     */
    protected $engine_struct_param;
    protected $reflector;
    protected $method;
    protected $array_param;

    public function setUp(): void {
        parent::setUp();
        $engineDAO         = new EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EngineStruct
         */
        $this->engine_struct_param = $eng[ 0 ];


        $this->databaseInstance = new MyMemory( $this->engine_struct_param );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "_decode" );
        $this->method->setAccessible( true );

    }

    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     */
    public function test__decode_with_json_in_input_deusch_segment() {
        $json_input = <<<LAB
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"responderId":"235","matches":[]}
LAB;

        $this->array_param      = [
                'q'        => "- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.",
                'langpair' => "en-US|fr-FR",
                'de'       => "demo@matecat.com",
                'mt'       => null,
                'numres'   => 100
        ];
        $input_function_purpose = "gloss_get_relative_url";

        $actual_result = $this->method->invoke( $this->databaseInstance, $json_input, $this->array_param, $input_function_purpose );

        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $actual_result instanceof GetMemoryResponse );
        $this->assertTrue( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );


    }

    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     */
    public function test__decode_with_json_in_input_from_italian_to_aragonese_segment_with_private_TM() {
        $json_input = <<<LAB
{"responseData":{"translatedText":null,"match":null},"responseDetails":"","responseStatus":200,"responderId":null,"matches":[]}
LAB;

        $this->array_param      = [
                'q'        => "Il Sistema genera un numero di serie per quella copia e lo stampa (anche sotto forma di codice a barre) su un’etichetta adesiva.",
                'langpair' => "it-IT|an-ES",
                'de'       => "demo@matecat.com",
                'mt'       => true,
                'numres'   => "3",
                'key'      => "a6043e606ac9b5d7ff24"
        ];
        $input_function_purpose = "translate_relative_url";

        $actual_result = $this->method->invoke( $this->databaseInstance, $json_input, $this->array_param, $input_function_purpose );
        /**
         * general check on the keys of GetMemoryResponse object returned
         */
        $this->assertTrue( $actual_result instanceof GetMemoryResponse );
        $this->assertTrue( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );

    }

    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     */
    public function test__decode_with_json_in_input_from_italian_to_english_triggered_by_set_method_check_1() {
        $json_input = <<<LAB
{"responseData":"OK","responseStatus":200,"responseDetails":[484525156]}
LAB;

        $prop = <<<'LABEL'
{"project_id":"10","project_name":"tyuio","job_id":"10"}
LABEL;


        $this->array_param = [
                'seg'      => "Il Sistema registra le informazioni sul nuovo film.",
                'tra'      => "The system records the information on the new movie.",
                'tnote'    => null,
                'langpair' => "it-IT|en-US",
                'de'       => "demo@matecat.com",
                'prop'     => $prop,
                'key'      => "a6043e606ac9b5d7ff24"
        ];

        $input_function_purpose = "contribute_relative_url";

        $actual_result = $this->method->invoke( $this->databaseInstance, $json_input, $this->array_param, $input_function_purpose );
        /**
         * general check on the keys of SetContributionResponse object returned
         */
        $this->assertTrue( $actual_result instanceof SetContributionResponse );
        $this->assertFalse( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );

        $this->assertTrue( $actual_result instanceof SetContributionResponse );
        $this->assertEquals( 200, $actual_result->responseStatus );
        $this->assertEquals( [ '0' => 484525156 ], $actual_result->responseDetails );
        $this->assertEquals( "OK", $actual_result->responseData );
        $this->assertNull( $actual_result->error );
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass( $actual_result );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $actual_result ) );

    }


    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     */
    public function test__decode_with_json_in_input_from_italian_to_english_triggered_by_set_method_check_2() {
        $json_input = <<<LAB
{"responseData":"OK","responseStatus":200,"responseDetails":[484540480]}
LAB;
        $prop       = <<<'LABEL'
{"project_id":"9","project_name":"eryt","job_id":"9"}
LABEL;
        $segment    = <<<'LABEL'
Ad esempio, una copia del film <g id="10">Blade Runner</g> in formato DVD, con numero di serie 6457.
LABEL;

        $translation = <<<'LABEL'
For example, a copy of the film <g id="10">Flade Bunner</g> in DVD format, with numbers of 6457 series.
LABEL;


        $this->array_param = [
                'seg'      => $segment,
                'tra'      => $translation,
                'tnote'    => null,
                'langpair' => "it-IT|en-US",
                'de'       => "demo@matecat.com",
                'prop'     => $prop,
                'key'      => "a6043e606ac9b5d7ff24"
        ];

        $input_function_purpose = "contribute_relative_url";

        $actual_result = $this->method->invoke( $this->databaseInstance, $json_input, $this->array_param, $input_function_purpose );
        /**
         * general check on the keys of SetContributionResponse object returned
         */
        $this->assertTrue( $actual_result instanceof SetContributionResponse );
        $this->assertFalse( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );


        $this->assertEquals( 200, $actual_result->responseStatus );
        $this->assertEquals( [ '0' => 484540480 ], $actual_result->responseDetails );
        $this->assertEquals( "OK", $actual_result->responseData );
        $this->assertNull( $actual_result->error );
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass( $actual_result );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $actual_result ) );
    }


    /**
     * It tests the behaviour of the decoding of json input.
     * @group   regression
     * @covers  MyMemory::_decode
     */
    public function test__decode_with_json_in_input_from_italian_to_english_triggered_by_delete_method_check() {
        $json_input = <<<LAB
{"responseStatus":200,"responseData":"Found and deleted 1 segments"}
LAB;

        $this->array_param = [
                'seg'      => "Il Sistema registra le informazioni sul nuovo film.",
                'tra'      => "The system records the information on the new movie.",
                'langpair' => "IT|EN",
                'de'       => "demo@matecat.com",
        ];


        $input_function_purpose = "delete_relative_url";

        /**
         * @var GetMemoryResponse
         */
        $actual_result = $this->method->invoke( $this->databaseInstance, $json_input, $this->array_param, $input_function_purpose );


        /**
         * general check on the keys of TSM object returned
         */
        $this->assertTrue( $actual_result instanceof GetMemoryResponse );
        $this->assertTrue( property_exists( $actual_result, 'matches' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseStatus' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseDetails' ) );
        $this->assertTrue( property_exists( $actual_result, 'responseData' ) );
        $this->assertTrue( property_exists( $actual_result, 'error' ) );
        $this->assertTrue( property_exists( $actual_result, '_rawResponse' ) );

        $this->assertEquals( [], $actual_result->matches );
        $this->assertEquals( 200, $actual_result->responseStatus );
        $this->assertEquals( "", $actual_result->responseDetails );
        $this->assertEquals( "Found and deleted 1 segments", $actual_result->responseData );
        $this->assertNull( $actual_result->error );
        /**
         * check of protected property
         */
        $this->reflector = new ReflectionClass( $actual_result );
        $property        = $this->reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $actual_result ) );
    }


}