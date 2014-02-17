<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 09/10/13
 * Time: 15.31
 * 
 */
include_once("AbstractTest.php");

class Tests_ServerCheckTest extends Tests_AbstractTest {

    public function testServerParams(){
        $servCheck = ServerCheck::getInstance();
        $this->assertInstanceOf( 'ServerCheck', $servCheck );
        $params = $servCheck->getServerParams() ;

        $this->assertNotEmpty( $params );
        $this->assertNotEmpty( $params['upload'] );

        $this->assertArrayHasKey( 'upload', $params );

    }


    public function testUploadParams(){

        $servCheck = ServerCheck::getInstance();
        $this->assertInstanceOf( 'ServerCheck', $servCheck );
        $params = $servCheck->getUploadParams() ;

        $this->assertNotEmpty( $params );

        $this->assertArrayHasKey( 'post_max_size', $params );
        $this->assertArrayHasKey( 'upload_max_filesize', $params );

        $this->assertNotEquals( $params['post_max_size'], -1 );
        $this->assertNotEquals( $params['upload_max_filesize'], -1 );

    }



}

