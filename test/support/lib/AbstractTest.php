<?php
/**
 * User: domenico
 * Date: 09/10/13
 * Time: 15.21
 *
 */

abstract class AbstractTest extends PHPUnit_Framework_TestCase {

    protected $thisTest;

    protected $reflectedClass;
    protected $reflectedMethod;

    public function setUp() {
        parent::setUp();
        $this->thisTest = microtime(true);
    }

    public function tearDown() {
        parent::tearDown();
        $resultTime = microtime(true) - $this->thisTest;
        echo " " . str_pad( get_class($this) . " " . $this->getName(false) , 35, " ", STR_PAD_RIGHT ). " - Did in " . $resultTime . " seconds.\n";
    }

}
