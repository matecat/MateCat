<?php
/**
 * User: domenico
 * Date: 09/10/13
 * Time: 15.21
 *
 */

if( !class_exists('INIT', false ) ){
    $root = realpath( dirname(__FILE__) . '/../../inc/' );
    include $root . '/config.inc.php';
    Bootstrap::start();

    set_include_path ( get_include_path() . PATH_SEPARATOR . realpath( dirname(__FILE__) . '/../' ) );

}

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
        echo " " . str_pad( $this->getName(false) , 35, " ", STR_PAD_RIGHT ). " - Did in " . $resultTime . " seconds.\n";
    }

}
