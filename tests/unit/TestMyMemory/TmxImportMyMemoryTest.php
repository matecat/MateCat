<?php

/**
 * @group  regression
 * @covers Engines_MyMemory
 * User: dinies
 * Date: 16/05/16
 * Time: 19.35
 */


/**
 *We are avoiding reports of deprecations because the global variable that will cause the deprecation
 * is set before the execution of the curl call by the curl handler.
 * @see  MultiCurlHandler::curl_setopt_array
 * @link http://nl1.php.net/manual/en/function.curl-setopt.php  @find  CURLOPT_POSTFIELDS
 */


use TestHelpers\AbstractTest;

error_reporting( ~E_DEPRECATED );


class TmxImportMyMemoryTest extends AbstractTest {

    protected $resource;

    public function tearDown() {
        if ( is_resource( $this->resource ) ) {
            fclose( $this->resource );
        }
        parent::tearDown();
    }

    /**
     * @covers Engines_MyMemory::import
     * @covers Engines_MyMemory::getStatus
     * @covers Engines_MyMemory::createExport
     * @covers Engines_MyMemory::checkExport
     * @covers Engines_MyMemory::downloadExport
     */
    public function test_about_best_case_scenario_of_TMX_import() {

        /**
         * Engine creation
         */


        $engineDAO         = new EnginesModel_EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $engine_struct     = EnginesModel_EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engine_struct_param = $eng[ 0 ];

        $engine_MyMemory = new Engines_MyMemory( $engine_struct_param );

        Langs_Languages::getInstance();


        /**
         * Path File initialization
         */

        $path_of_the_original_file = INIT::$ROOT . '/tests/resources/files/tmx/exampleForTestOriginal.tmx';

        $file_param = $path_of_the_original_file;
        $key_param  = "a6043e606ac9b5d7ff24";
        $name_param = "exampleForTestOriginal.tmx";


        /**
         * Importing
         */
        $result = $engine_MyMemory->import( $file_param, $key_param, $name_param );


        $this->assertTrue( $result instanceof Engines_Results_MyMemory_TmxResponse );
        $this->assertTrue( Utils::isTokenValid( $result->id ) );
        $this->assertEquals( 202, $result->responseStatus );
        $this->assertEquals( "", $result->responseDetails );
        $this->assertCount( 1, $result->responseData );
        $this->assertNull( $result->error );

        $reflector = new ReflectionClass( $result );
        $property  = $reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result ) );

        /**
         * Getting Status
         */
        $ready      = false;
        $time       = 0;
        while ( ( !$ready ) && ( $time < 100 ) ) {
            usleep( 500000 );//0.5 sec
            $time++;
            $importResult = $engine_MyMemory->getStatus( $result->id );
            if( $importResult->responseData[ 'status' ] == 1 ){
                $ready = true;
            }
        }

        $this->assertTrue( $importResult instanceof Engines_Results_MyMemory_TmxResponse );
        $this->assertTrue( Utils::isTokenValid( $importResult->id ) );
        $this->assertEquals( $importResult->id , $importResult->id );
        $this->assertEquals( 200, $importResult->responseStatus );
        $this->assertEquals( "", $importResult->responseDetails );
        $this->assertCount( 9, $importResult->responseData );

        $this->assertNull( $importResult->error );

        $reflector = new ReflectionClass( $importResult );
        $property  = $reflector->getProperty( '_rawResponse' );
        $property->setAccessible( true );

        $this->assertEquals( "", $property->getValue( $result ) );

    }


}