<?php
/**
 * User: domenico
 * Date: 09/10/13
 * Time: 15.21
 * 
 */


if( !class_exists('INIT', false )){
    include '/var/www/cattool/inc/config.inc.php';
    @INIT::obtain();
    include_once INIT::$UTILS_ROOT . '/Utils.php';
    include_once INIT::$UTILS_ROOT . '/Log.php';
    include_once INIT::$MODEL_ROOT . '/Database.class.php';
}

abstract class Tests_AbstractTest extends PHPUnit_Framework_TestCase {

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
        echo " " . str_pad( $this->getName(false) , 35, " ", STR_PAD_RIGHT ). " - Did in " . $resultTime . " seconds.\n";
    }

}