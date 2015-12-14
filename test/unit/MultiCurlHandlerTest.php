<?php
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
        $this->assertInstanceOf( 'MultiCurlHandler', $mh );
    }

    public function testCreateSingle(){

        $options = array(
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => 0,
                CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2
        );

        $mh = new MultiCurlHandler();

        $tokenHash = $mh->createResource( 'http://www.google.com/', $options );
        $this->assertNotEmpty( $tokenHash );

        $mh->multiExec();

        $singleContent = $mh->getSingleContent( $tokenHash );
        $multiContent  = $mh->getAllContents();

        $this->assertNotEmpty( $singleContent );
        $this->assertNotEmpty( $multiContent );
        $this->assertEquals( $singleContent, $multiContent[ $tokenHash ] );


    }

    public function testAddSingle() {

        $options = array(
                CURLOPT_URL => 'http://www.google.com/',
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => 0,
                CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2
        );

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

    public function testMultipleCurlCreate(){

        $options = array(
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => 0,
                CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2
        );

        $mh = new MultiCurlHandler();
        $hashes = array();

        $tokenHash1 = $mh->createResource( 'http://www.google.com/', $options );
        $this->assertNotEmpty( $tokenHash1 );
        $hashes[] = $tokenHash1;

        $tokenHash2 = $mh->createResource( 'https://it.yahoo.com/', $options );
        $this->assertNotEmpty( $tokenHash2 );
        $hashes[] = $tokenHash2;

        $tokenHash3 = $mh->createResource( 'http://www.bing.com/', $options );
        $this->assertNotEmpty( $tokenHash3 );
        $hashes[] = $tokenHash3;

        $tokenHash4 = $mh->createResource( 'http://www.translated.net/', $options );
        $this->assertNotEmpty( $tokenHash4 );
        $hashes[] = $tokenHash4;

        $mh->multiExec();

        $multiContent  = $mh->getAllContents();
        $this->assertNotEmpty( $multiContent );

        $singleContent = $mh->getSingleContent( $tokenHash2 );
        $this->assertNotEmpty( $singleContent );

        foreach( $multiContent as $hash => $result ){
            $this->assertEquals( $mh->getSingleContent( $hash ), $result );
        }

    }

}
