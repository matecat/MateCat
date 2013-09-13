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
    
    public function testView2RawXliff() {

        $source_seg = <<<SRC
<g id="43">bang & olufsen < 3 ' > 1</g> <x id="33"/>
SRC;
        $source_expected = <<<SRC
<g id="43">bang &amp; olufsen &lt; 3 ' &gt; 1</g> <x id="33"/>
SRC;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $this->assertEquals( $source_seg, $source_expected );


    }

    public function testRawXliff2View(){

        $source_seg = <<<SRC
<g id="43">bang &amp; olufsen &lt; 3 ' &gt; 1</g> <x id="33"/>
SRC;

        $source_expected = <<<SRC
&lt;g id="43"&gt;bang & olufsen &lt; 3 ' &gt; 1&lt;/g&gt; &lt;x id="33"/&gt;
SRC;

        $source_seg = CatUtils::rawxliff2view( $source_seg );
        $this->assertEquals( $source_seg, $source_expected );

    }

    public function testSpaces1(){

        //" 1 " -> 20 31 20
        $source_seg = <<<SRC
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
SRC;

        //" 1 " -> C2 A0 31 C2 A0
        $target_seg = <<<TRG
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
TRG;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $target_seg = CatUtils::view2rawxliff( $target_seg );

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[0]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[0]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 C2 A0
        $this->assertEquals( '<g id="6"> 1 </g><g id="7">st  </g><g id="8"> Section of Tokyo, Osaka</g>', $normalized );


    }




}

?>
