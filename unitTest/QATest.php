<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

if( !class_exists('INIT', false )){
    include '/var/www/cattool/inc/config.inc.php';
    @INIT::obtain();
    include '/var/www/cattool/lib/utils/log.class.php';
    include '/var/www/cattool/lib/utils/utils.class.php';
    include '/var/www/cattool/lib/utils/cat.class.php';
    include '/var/www/cattool/lib/utils/QA.php';
    require_once "PHPUnit/Autoload.php";
}

/**
 * Description of QATest
 *
 * @author domenico
 */
class QATest extends PHPUnit_Framework_TestCase {
    
    public $reflected;
    public $method;
    
    //put your code here
    public function setUp() {
        parent::setUp();
        $this->thisTest = microtime(true);

        $this->reflected = new ReflectionClass( 'QA' );
        $this->method = $this->reflected->getMethod('_checkContentConsistency');
        $this->method->setAccessible(true);

    }

    public function tearDown() {
        parent::tearDown();
        $resultTime = microtime(true) - $this->thisTest;
        echo " " . str_pad( $this->getName(false) , 35, " ", STR_PAD_RIGHT ). " - Did in " . $resultTime . " seconds.\n";
    }
    
    public function testWitespaces1() {
        $source_seg = <<<SRC
<g id="pt8">\rNegli \r\n</g>	<g id="pt9">anni 80</g> <g id="pt10"> si comprende il & processo di rapido sviluppo della moderna distribuzione con la sua esigenza di un’efficiente organizzazione industriale e logistica.</g>
SRC;

        $target_seg = <<<TRG
<g id="pt8">In </g><g id="pt9">	the 80ies</g><g id="pt10"> they understood the process of rapid development of the modern distribution system and the need for an efficient industrial and logistics organization.</g>
TRG;

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();
        print_r( $check->getWarnings() );

    }


}

?>
