<?php

/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 09/10/13
 * Time: 15.31
 *
 */

use TestHelpers\AbstractTest;


/**
 * Class ServerCheckTest
 */
class ServerCheckTest extends AbstractTest {

    public function testSingleInstance(){
        $servCheck = ServerCheck::getInstance();
        $servCheck2 = ServerCheck::getInstance();
        $this->assertEquals( spl_object_hash($servCheck), spl_object_hash( $servCheck2 ) );
    }

    public function testServerParams(){
        $servCheck = ServerCheck::getInstance();
        $this->assertInstanceOf( 'ServerCheck', $servCheck );
        $params = $servCheck->getAllServerParams() ;

        $this->assertNotEmpty( $params );
        $this->assertNotEmpty( $params->getUpload() );

        $this->assertAttributeNotEmpty( 'upload', $params );

    }


    public function testUploadParams(){

        $servCheck = ServerCheck::getInstance();
        $this->assertInstanceOf( 'ServerCheck', $servCheck );
        $params = $servCheck->getUploadParams() ;

        $this->assertNotEmpty( $params );

        $this->assertAttributeNotEmpty( 'post_max_size', $params );
        $this->assertAttributeNotEmpty( 'upload_max_filesize', $params );

        $this->assertNotEquals( $params->getPostMaxSize(), -1 );
        $this->assertNotEquals( $params->getUploadMaxFilesize(), -1 );

    }

    public function testReadOnly(){

        $servCheck = ServerCheck::getInstance();

        $allServerParams = $servCheck->getAllServerParams() ;
        $upload = $servCheck->getUploadParams() ;
        $mysql_params = $servCheck->getMysqlParams();

        $this->assertEquals( $servCheck->getUploadParams(), $allServerParams->getUpload() );
        $this->assertEquals( $upload, $allServerParams->getUpload() );

        $this->assertNotEquals( spl_object_hash( $upload ), $allServerParams->getUpload() );
        $this->assertNotEquals( spl_object_hash( $upload ), $servCheck->getUploadParams() );

        $this->setExpectedException('DomainException');
        $allServerParams->field_test_not_existent = "kkk";
        $this->setExpectedException('Exception');
        echo $allServerParams->field_test_not_existent;


        $this->setExpectedException('DomainException');
        $upload->field_test_not_existent = "kkk";
        $this->setExpectedException('Exception');
        echo $upload->field_test_not_existent;


        $this->setExpectedException('DomainException');
        $mysql_params->field_test_not_existent = "kkk";
        $this->setExpectedException('Exception');
        echo $mysql_params->field_test_not_existent;

    }

    public function testMysql(){

        $servCheck = ServerCheck::getInstance();
        $mysql_params = $servCheck->getMysqlParams();

        $this->assertInstanceOf( 'ServerCheck_mysqlParams', $mysql_params );

    }

}
