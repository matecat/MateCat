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

    public function testPlainTextSpaces(){
        $source_seg = <<<SRC
La città in fiamme
SRC;

        $target_seg = <<<TRG
 The city in flames
TRG;

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertTrue( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 1100, $warnings[0]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[0]->outcome );

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

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[0]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[0]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( '<g id="6"> 1 </g><g id="7">st  </g><g id="8"> Section of Tokyo, Osaka</g>', $normalized );

    }

    public function testSpaces2(){

        $source_seg = <<<SRC
<g id="7">st</g><g id="8">&nbsp;Section of Tokyo, Osaka </g>
SRC;

        $target_seg = <<<TRG
<g id="7">st </g><g id="8"> &nbsp;Section of Tokyo, Osaka</g>
TRG;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $target_seg = CatUtils::view2rawxliff( $target_seg );

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertTrue( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 2 );
        $this->assertEquals( 1100, $warnings[0]->outcome );
        $this->assertEquals( 1100, $warnings[1]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[0]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/ 2 /', $check->getWarningsJSON() );

    }

    public function testRecursiveSpaces1(){

        $source_seg = <<<SRC
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of Tokyo, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7">st </g><g id="8">Section of Tokyo, Osaka </g> </g>
TRG;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $target_seg = CatUtils::view2rawxliff( $target_seg );
//Log::doLog("---------------");
//        Log::doLog($source_seg);
//        Log::doLog($target_seg);
        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();
//die();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertTrue( $check->thereAreWarnings() );

        $this->assertEquals( 3, count( $warnings ) );
        $this->assertEquals( 1100, $warnings[0]->outcome );
        $this->assertEquals( 1100, $warnings[1]->outcome );
        $this->assertEquals( 1100, $warnings[2]->outcome );

        $this->assertEquals( 1, count( $errors ) );
        $this->assertEquals( 0, $errors[0]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/ 3 /', $check->getWarningsJSON() );

    }

    public function testSpaces3(){

        //" 1 " -> C2 A0 31 C2 A0
        $source_seg = <<<TRG
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
TRG;

        //" 1 " -> 20 31 20
        $target_seg = <<<SRC
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
SRC;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $target_seg = CatUtils::view2rawxliff( $target_seg );

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[0]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[0]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( '<g id="6"> 1 </g><g id="7">st  </g><g id="8"> Section of Tokyo, Osaka</g>', $normalized );

    }

    public function testRecursiveSpaces2(){

        $source_seg = <<<SRC
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of Tokyo <g id="9"><g id="10">Station</g></g>, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of Tokyo <g id="9"> <g id="10">Station </g></g>, Osaka </g></g>
TRG;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $target_seg = CatUtils::view2rawxliff( $target_seg );

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $warnings = $check->getWarnings();

        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertTrue( $check->thereAreWarnings() );

        $this->assertEquals( 3, count( $warnings ) );
        $this->assertEquals( 1100, $warnings[0]->outcome );
        $this->assertEquals( 1100, $warnings[1]->outcome );
        $this->assertEquals( 1100, $warnings[2]->outcome );

        $this->assertEquals( 1, count( $errors ) );
        $this->assertEquals( 0, $errors[0]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/ 3 /', $check->getWarningsJSON() );

    }

    public function testAnalysisRecursive(){

        //Strings already converted to raw xliff

        $source_seg = <<<SRC
<g id="pt231"><g id="pt232">APPARTEMENT ELSA ET JOY </g><g id="pt233">&lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt231"><g id="pt232"> ELSA AND JOY'S APARTMENT </g><g id="pt233"> &lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() ); //a space after tag: <g id="pt233">

        $warnings = $check->getWarnings();

        $this->assertCount( 2, $warnings );
        $this->assertAttributeEquals( 1100, 'outcome', $warnings[0] );
        $this->assertAttributeEquals( 1100, 'outcome', $warnings[1] );

        $this->assertRegExp( '/ 2 /', $check->getWarningsJSON() );

        $this->assertEquals('[{"outcome":1100,"debug":"More\/fewer whitespaces found next to the tags. ( 2 )"}]', $check->getWarningsJSON() );


        $check = new QA($source_seg, $target_seg);
        $check->performTagCheckOnly();
        $this->assertFalse( $check->thereAreErrors() );


        //PHASE 2 check for errors
        $source_seg = <<<SRC
<g id="pt231"><g id="pt232">APPARTEMENT ELSA ET JOY </g><g id="pt233">&lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt231"><g id="pt232">ELSA AND JOY'S<x id="231"/> APARTMENT </g><g id="pt233"> &lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() ); //a space after tag: <g id="pt233"> and a mismatch <x id="231"/>
        $this->assertTrue( $check->thereAreErrors() );   //mismatch <x id="231"/>

        $errors = $check->getErrors();
        $warnings = $check->getWarnings();

        $this->assertCount( 1, $errors );
        $this->assertCount( 2, $warnings );

        $this->assertAttributeEquals( 1000, 'outcome', $errors[0] );
        $this->assertAttributeEquals( 1100, 'outcome', $warnings[0] );

        $this->assertRegExp( '/ 1 /', $check->getWarningsJSON() );
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );



        $check = new QA($source_seg, $target_seg);
        $check->performTagCheckOnly();
        $this->assertTrue( $check->thereAreErrors() );
        $errors = $check->getErrors();
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[0] );
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );


        //CHECK TAG ID MISMATCH
        $source_seg = <<<SRC
<g id="6"> <g id="7">st<x id="1234"/></g><g id="8">&nbsp;Section of Tokyo <g id="9"> <g id="10">Station </g></g>, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7">st<x id="1236"/></g><g id="8">&nbsp;Section of Tokyo <g id="9"> <g id="10">Station </g></g>, Osaka </g></g>
TRG;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $target_seg = CatUtils::view2rawxliff( $target_seg );

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();
        $this->assertTrue( $check->thereAreWarnings() ); //
        $this->assertTrue( $check->thereAreErrors() );   //mismatch <x id="231"/> -> <x id="232"/>
        $errors = $check->getErrors();
        $warnings = $check->getWarnings();

        $this->assertCount( 1, $errors );
        $this->assertCount( 1, $warnings ); // warnings are not checked because of tag mismatch,
                                            // analysis on space warnings skipped de facto
        $this->assertAttributeEquals( 4, 'outcome', $errors[0] );

        //PHASE 3 check for particular tag mismatch: unclosed x tag <x id="231">
        $source_seg = <<<SRC
<g id="pt231"><g id="pt232">APPARTEMENT ELSA ET JOY </g><g id="pt233">&lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt231"><g id="pt232">ELSA AND JOY'S<x id="231"/> APARTMENT <x id="230"></g><g id="pt233"> &lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() ); //a space after tag: <g id="pt233"> and a mismatch <x id="231"/>
        $this->assertTrue( $check->thereAreErrors() );   //mismatch <x id="231"/>

        $errors = $check->getErrors();
        $warnings = $check->getWarnings();

        $this->assertCount( 2, $errors );
        $this->assertCount( 2, $warnings ); // warnings are not checked because of tag mismatch,
                                            // analysis on space warnings skipped de facto

        $this->assertAttributeEquals( 13, 'outcome', $errors[0] );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[1] );
        $this->assertRegExp( '/ 1 /', $check->getWarningsJSON() );
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );

        $this->assertEquals( $check->getErrorsJSON(), $check->getWarningsJSON() );
        $this->assertEquals( '[{"outcome":13,"debug":"Wrong format for x tag. Should be < x .... \/> ( 1 )"},{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getWarningsJSON() );

        //export normalized string only if there are no exceptions
        $this->setExpectedException('LogicException');
        $check->getTrgNormalized();

    }


    public function testTryRealignTagID(){

        $segment = <<<SRC
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
SRC;

        $translation = <<<SRC
<g id="7"> 1 </g><g id="8">&#170;  </g><g id="9">&nbsp;Sezione di Tokyo, Osaka</g>
SRC;

        //&nbsp; --> C2 A0
        //&#170; --> C2 AA
        $expected_translation = <<<SRC
<g id="6"> 1 </g><g id="7">ª  </g><g id="8"> Sezione di Tokyo, Osaka</g>
SRC;

        $segment = CatUtils::view2rawxliff( $segment );
        $translation = CatUtils::view2rawxliff( $translation );

        $qaRealign = new QA( $segment, $translation );
        $qaRealign->tryRealignTagID();

        $this->assertFalse($qaRealign->thereAreErrors());

        $new_target = $qaRealign->getTrgNormalized();
        $this->assertEquals( $expected_translation, $new_target );


        //there are not the same number of target in source and translation
        $translation = <<<SRC
<g id="7"> 1 </g><g id="8">&#170;  </g><x id="abc" /><g id="9">&nbsp;Sezione di Tokyo, Osaka</g>
SRC;
        $translation = CatUtils::view2rawxliff( $translation );

        $qaRealign = new QA( $segment, $translation );
        $qaRealign->tryRealignTagID();

        $this->assertTrue( $qaRealign->thereAreErrors() );
        $errors = $qaRealign->getErrors();
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[0] );
        $this->setExpectedException( 'LogicException' );
        $qaRealign->getTrgNormalized();


        //there are the same number of tags in source and target, but types differs
        $segment = <<<SRC
<g id="6"> 1 </g><g id="7">st  </g><g id="abc"></g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
SRC;
        $translation = <<<SRC
<g id="7"> 1 </g><g id="8">&#170;  </g><x id="abc" /><g id="9">&nbsp;Sezione di Tokyo, Osaka</g>
SRC;
        $segment = CatUtils::view2rawxliff( $segment );
        $translation = CatUtils::view2rawxliff( $translation );

        $qaRealign = new QA( $segment, $translation );
        $qaRealign->tryRealignTagID();

        $this->assertTrue( $qaRealign->thereAreErrors() );
        $errors = $qaRealign->getErrors();
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[0] );
        $this->setExpectedException( 'LogicException' );
        $qaRealign->getTrgNormalized();


    }

    public function testBadXml(){

        //PHASE 3 check for particular tag mismatch: unclosed x tag <x id="231">
        $source_seg = <<<SRC
<g id="pt235"><g id="pt236"> <x id="abc/></g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA($source_seg, $target_seg);
        $check->performTagCheckOnly();
        $this->assertTrue( $check->thereAreErrors() );
        $errors = $check->getErrors();
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[0] );;
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );

        $this->assertEquals( '[{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getWarningsJSON() );
        $this->assertEquals( '[{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getErrorsJSON() );

    }

}

?>
