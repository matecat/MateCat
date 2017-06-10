<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/06/2017
 * Time: 09:52
 */

use Features\Dqf\Model\DqfQualityModel;

class DqfQualityModelTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        parent::setUp();
        TestHelper::resetDb();
    }

    public function testTrue() {
        $this->assertTrue( true );
    }
}
