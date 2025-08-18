<?php

use TestHelpers\AbstractTest;
use Utils\Network\MultiCurlHandler;
use Utils\Registry\AppConfig;


/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 09/10/13
 * Time: 15.31
 *
 */
class MultiCurlHandlerTest extends AbstractTest {

    public function testInstance() {
        $mh = new MultiCurlHandler();
        $this->assertInstanceOf( MultiCurlHandler::class, $mh );
    }

    public function testCreateSingle() {

        $options = [
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2
        ];

        $mh = new MultiCurlHandler();

        $tokenHash = $mh->createResource( 'https://www.google.com/', $options );
        $this->assertNotEmpty( $tokenHash );

        $mh->multiExec();

        $singleContent = $mh->getSingleContent( $tokenHash );
        $multiContent  = $mh->getAllContents();

        $this->assertNotEmpty( $singleContent );
        $this->assertNotEmpty( $multiContent );
        $this->assertEquals( $singleContent, $multiContent[ $tokenHash ] );


    }

    public function testAddSingle() {

        $options = [
                CURLOPT_URL            => 'https://www.google.com/',
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2
        ];

        $ch = curl_init();
        curl_setopt_array( $ch, $options );

        $mh = new MultiCurlHandler();

        $tokenHash = $mh->addResource( $ch );
        $this->assertNotEmpty( $tokenHash );

        $mh->multiExec();

        $singleContent = $mh->getSingleContent( $tokenHash );
        $multiContent  = $mh->getAllContents();

        $this->assertNotEmpty( $singleContent );
        $this->assertNotEmpty( $multiContent );
        $this->assertEquals( $singleContent, $multiContent[ $tokenHash ] );

    }

    public function testMultipleCurlCreate() {

        $options = [
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2
        ];

        $mh     = new MultiCurlHandler();
        $hashes = [];

        $tokenHash1 = $mh->createResource( 'https://www.google.com/', $options );
        $this->assertNotEmpty( $tokenHash1 );
        $hashes[] = $tokenHash1;

        $tokenHash2 = $mh->createResource( 'https://it.yahoo.com/', $options );
        $this->assertNotEmpty( $tokenHash2 );
        $hashes[] = $tokenHash2;

        $tokenHash3 = $mh->createResource( 'https://www.bing.com/', $options );
        $this->assertNotEmpty( $tokenHash3 );
        $hashes[] = $tokenHash3;

        $tokenHash4 = $mh->createResource( 'https://www.translated.net/', $options );
        $this->assertNotEmpty( $tokenHash4 );
        $hashes[] = $tokenHash4;

        $mh->multiExec();

        $multiContent = $mh->getAllContents();
        $this->assertNotEmpty( $multiContent );

        $singleContent = $mh->getSingleContent( $tokenHash2 );
        $this->assertNotEmpty( $singleContent );

        foreach ( $multiContent as $hash => $result ) {
            $this->assertEquals( $mh->getSingleContent( $hash ), $result );
        }

    }

}
