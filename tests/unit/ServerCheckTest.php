<?php

/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 09/10/13
 * Time: 15.31
 *
 */

use TestHelpers\AbstractTest;
use Utils\ServerCheck\ServerCheck;


/**
 * Class ServerCheckTest
 */
class ServerCheckTest extends AbstractTest
{

    public function testSingleInstance()
    {
        $servCheck = ServerCheck::getInstance();
        $servCheck2 = ServerCheck::getInstance();
        $this->assertEquals(spl_object_hash($servCheck), spl_object_hash($servCheck2));
    }

    public function testUploadParams()
    {
        $servCheck = ServerCheck::getInstance();
        $this->assertInstanceOf(ServerCheck::class, $servCheck);
        $params = $servCheck->getUploadParams();

        $this->assertNotEmpty($params);

        $this->assertNotEmpty($params->getPostMaxSize());
        $this->assertNotEmpty($params->getUploadMaxFilesize());

        $this->assertNotEquals($params->getPostMaxSize(), -1);
        $this->assertNotEquals($params->getUploadMaxFilesize(), -1);
    }

    public function testReadOnly()
    {
        $servCheck = ServerCheck::getInstance();

        $upload = $servCheck->getUploadParams();

        $this->assertNotEquals(spl_object_hash($upload), $servCheck->getUploadParams());


        $this->expectException('DomainException');
        $upload->field_test_not_existent = "kkk";
        $this->expectException('Exception');
        echo $upload->field_test_not_existent;
    }


}
