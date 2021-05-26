<?php

use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use Matecat\SubFiltering\MateCatFilter;

class QATest extends AbstractTest {

    public function testView2RawXliff() {

        $source_seg      = <<<SRC
<g id="43">bang & olufsen < 3 ' > 1</g> <x id="33"/>
SRC;
        $source_expected = <<<SRC
<g id="43">bang &amp; olufsen &lt; 3 ' &gt; 1</g> <x id="33"/>
SRC;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $this->assertEquals( $source_seg, $source_expected );


    }

    public function testRawXliff2View() {

        $source_seg = <<<SRC
<g id="43">bang &amp; olufsen &lt; 3 ' &gt; 1</g> <x id="33"/>
SRC;

        $source_expected = <<<SRC
&lt;g id="43"&gt;bang & olufsen &lt; 3 ' &gt; 1&lt;/g&gt; &lt;x id="33"/&gt;
SRC;

        $source_seg = CatUtils::layer0ToLayer2( $source_seg );
        $this->assertEquals( $source_seg, $source_expected );

    }

    public function testPlainTextSpaces() {
        $source_seg = <<<SRC
La città in fiamme
SRC;

        $target_seg = <<<TRG
 The city in flames
TRG;

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $notices  = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreNotices() );

        $this->assertEquals( count( $notices ), 1 );
        $this->assertEquals( 1100, $notices[ 0 ]->outcome );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

    }

    public function testSpaces1() {

        //" 1 " -> 20 31 20
        $source_seg = <<<SRC
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
SRC;

        //" 1 " -> C2 A0 31 C2 A0
        $target_seg = <<<TRG
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
TRG;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $target_seg = CatUtils::layer2ToLayer0( $target_seg );

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $notices  = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertFalse( $check->thereAreNotices() );

        $this->assertEquals( 1, count( $notices ) );
        $this->assertEquals( 0, $notices[ 0 ]->outcome );

        $this->assertEquals( 1, count( $warnings ) );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( 1, count( $errors ) );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/\[ 0 \]/', $check->getWarningsJSON() );
        $this->assertRegExp( '/\[ 0 \]/', $check->getNoticesJSON() );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( '<g id="6"> 1 </g><g id="7">st  </g><g id="8"> Section of Tokyo, Osaka</g>', $normalized );

    }

    public function testSpaces2() {

        $source_seg = <<<SRC
<g id="7">st</g><g id="8">&nbsp;Section of Tokyo, Osaka </g>
SRC;

        $target_seg = <<<TRG
<g id="7">st </g><g id="8"> &nbsp;Section of Tokyo, Osaka</g>
TRG;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $target_seg = CatUtils::layer2ToLayer0( $target_seg );

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $warnings = $check->getWarnings();

        $notices  = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreNotices() );

        $this->assertEquals( count( $notices ), 2 );
        $this->assertEquals( 1100, $notices[ 0 ]->outcome );
        $this->assertEquals( 1100, $notices[ 1 ]->outcome );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/\[ 0 \]/', $check->getWarningsJSON() );
        $this->assertRegExp( '/ 2 /', $check->getNoticesJSON() );

    }

    public function testRecursiveSpaces1() {

        $source_seg = <<<SRC
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of Tokyo, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7">st </g><g id="8">Section of Tokyo, Osaka </g> </g>
TRG;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $target_seg = CatUtils::layer2ToLayer0( $target_seg );
//Log::doJsonLog("---------------");
//        Log::doJsonLog($source_seg);
//        Log::doJsonLog($target_seg);
        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();
//die();

        $notices  = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreNotices() );

        $this->assertEquals( 3, count( $notices ) );
        $this->assertEquals( 1100, $notices[ 0 ]->outcome );
        $this->assertEquals( 1100, $notices[ 1 ]->outcome );
        $this->assertEquals( 1100, $notices[ 2 ]->outcome );

        $this->assertEquals( 1, count( $warnings ) );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( 1, count( $errors ) );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/\[ 0 \]/', $check->getWarningsJSON() );
        $this->assertRegExp( '/ 3 /', $check->getNoticesJSON() );

    }

    public function testSpaces3() {

        //" 1 " -> C2 A0 31 C2 A0
        $source_seg = <<<TRG
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
TRG;

        //" 1 " -> 20 31 20
        $target_seg = <<<SRC
<g id="6"> 1 </g><g id="7">st  </g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
SRC;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $target_seg = CatUtils::layer2ToLayer0( $target_seg );

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $notices  = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertFalse( $check->thereAreNotices() );

        $this->assertEquals( 1, count( $notices ) );
        $this->assertEquals( 0, $notices[ 0 ]->outcome );

        $this->assertEquals( 1, count( $warnings ) );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( 1, count( $errors ) );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/\[ 0 \]/', $check->getWarningsJSON() );
        $this->assertRegExp( '/\[ 0 \]/', $check->getNoticesJSON() );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( '<g id="6"> 1 </g><g id="7">st  </g><g id="8"> Section of Tokyo, Osaka</g>', $normalized );

    }

    public function testRecursiveSpaces2() {

        $source_seg = <<<SRC
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of Tokyo <g id="9"><g id="10">Station</g></g>, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of Tokyo <g id="9"> <g id="10">Station </g></g>, Osaka </g></g>
TRG;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $target_seg = CatUtils::layer2ToLayer0( $target_seg );

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $notices  = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreNotices() );

        $this->assertEquals( 3, count( $notices ) );
        $this->assertEquals( 1100, $notices[ 0 ]->outcome );
        $this->assertEquals( 1100, $notices[ 1 ]->outcome );
        $this->assertEquals( 1100, $notices[ 2 ]->outcome );

        $this->assertEquals( 1, count( $warnings ) );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( 1, count( $errors ) );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $this->assertRegExp( '/\[ 0 \]/', $check->getErrorsJSON() );
        $this->assertRegExp( '/\[ 0 \]/', $check->getWarningsJSON() );
        $this->assertRegExp( '/ 3 /', $check->getNoticesJSON() );

    }

    public function testAnalysisRecursive1() {

        //Strings already converted to raw xliff

        $source_seg = <<<SRC
<g id="pt231"><g id="pt232">APPARTEMENT ELSA ET JOY </g><g id="pt233">&lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt231"><g id="pt232"> ELSA AND JOY'S APARTMENT </g><g id="pt233"> &lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreNotices() ); //a space after tag: <g id="pt233">

        $notices = $check->getNotices();

        $this->assertCount( 2, $notices );
        $this->assertAttributeEquals( 1100, 'outcome', $notices[ 0 ] );
        $this->assertAttributeEquals( 1100, 'outcome', $notices[ 1 ] );

        $this->assertRegExp( '/ 2 /', $check->getNoticesJSON() );

        $this->assertEquals( '[{"outcome":1100,"debug":"More\/fewer whitespaces found next to the tags. ( 2 )"}]', $check->getNoticesJSON() );


        $check = new QA( $source_seg, $target_seg );
        $check->performTagCheckOnly();
        $this->assertFalse( $check->thereAreErrors() );

    }

    public function testAnalysisRecursive2() {

        //PHASE 2 check for errors
        $source_seg = <<<SRC
<g id="pt231"><g id="pt232">APPARTEMENT ELSA ET JOY </g><g id="pt233">&lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt231"><g id="pt232">ELSA AND JOY'S<x id="231"/> APARTMENT </g><g id="pt233"> &lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreNotices() ); //a space after tag: <g id="pt233"> and a mismatch <x id="231"/>
        $this->assertTrue( $check->thereAreWarnings() ); //order changed in the tags
        $this->assertTrue( $check->thereAreErrors() );   //mismatch <x id="231"/>

        $errors   = $check->getErrors();
        $warnings = $check->getWarnings();
        $notices  = $check->getNotices();

        $this->assertCount( 1, $errors );
        $this->assertCount( 2, $warnings );
        $this->assertCount( 3, $notices );

        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 0 ] );

        $this->assertAttributeEquals( 15, 'outcome', $warnings[ 0 ] );
        $this->assertAttributeEquals( 1000, 'outcome', $warnings[ 1 ] );

        $this->assertAttributeEquals( 1100, 'outcome', $notices[ 0 ] );
        $this->assertAttributeEquals( 15, 'outcome', $notices[ 1 ] );
        $this->assertAttributeEquals( 1000, 'outcome', $notices[ 2 ] );

        $this->assertEquals( '[{"outcome":15,"debug":"Tag order mismatch ( 1 )"},{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getWarningsJSON() );
        $this->assertEquals( '[{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getErrorsJSON() );
        $this->assertEquals( '[{"outcome":1100,"debug":"More\\/fewer whitespaces found next to the tags. ( 1 )"},{"outcome":15,"debug":"Tag order mismatch ( 1 )"},{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getNoticesJSON() );


        $check = new QA( $source_seg, $target_seg );
        $check->performTagCheckOnly();
        $this->assertTrue( $check->thereAreErrors() );
        $errors = $check->getErrors();

        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 0 ] );
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );

        $this->assertTrue( $check->thereAreNotices() );
        $this->assertTrue( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreErrors() );

        $this->assertEquals( $check->getNotices(), $check->getWarnings() );
        $this->assertEquals( $check->getNotices(), $check->getErrors() );
        $this->assertEquals( $check->getWarnings(), $check->getErrors() );

    }

    public function testTagOnlyAnalysisRecursive3() {

        $source_seg = <<<SRC
<g id="pt231"><g id="pt232">APPARTEMENT ELSA ET JOY </g><g id="pt233">&lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt231"><g id="pt232">ELSA AND JOY'S<x id="231"/> APARTMENT </g><g id="pt233"> &lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA( $source_seg, $target_seg );
        $check->performTagCheckOnly();
        $this->assertTrue( $check->thereAreErrors() );
        $errors = $check->getErrors();

        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 0 ] );
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );

        $this->assertTrue( $check->thereAreNotices() );
        $this->assertTrue( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreErrors() );

        $this->assertEquals( $check->getNotices(), $check->getWarnings() );
        $this->assertEquals( $check->getNotices(), $check->getErrors() );
        $this->assertEquals( $check->getWarnings(), $check->getErrors() );


    }

    public function testAnalysisRecursive4() {

        //CHECK TAG ID MISMATCH
        $source_seg = <<<SRC
<g id="6"> <g id="7">st<x id="1234"/></g><g id="8">&nbsp;Section of Tokyo <g id="9"> <g id="10">Station </g></g>, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7">st<x id="1236"/></g><g id="8">&nbsp;Section of Tokyo <g id="9"> <g id="10">Station </g></g>, Osaka </g></g>
TRG;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $target_seg = CatUtils::layer2ToLayer0( $target_seg );

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();
        $this->assertTrue( $check->thereAreWarnings() ); //
        $this->assertTrue( $check->thereAreErrors() );   //mismatch <x id="231"/> -> <x id="232"/>
        $errors   = $check->getErrors();
        $warnings = $check->getWarnings();

        $this->assertCount( 1, $errors );
        $this->assertCount( 2, $warnings ); // warnings are not checked because of tag mismatch,
        // analysis on space warnings skipped de facto
        $this->assertAttributeEquals( 4, 'outcome', $errors[ 0 ] );
    }

    public function testAnalysisRecursive5() {

        //PHASE 3 check for particular tag mismatch: unclosed x tag <x id="231">
        $source_seg = <<<SRC
<g id="pt231"><g id="pt232">APPARTEMENT ELSA ET JOY </g><g id="pt233">&lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt231"><g id="pt232">ELSA AND JOY'S<x id="231"/> APARTMENT <x id="230"></g><g id="pt233"> &lt;&lt;1.0&gt;&gt;</g></g> <g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() ); //a space after tag: <g id="pt233"> and a mismatch <x id="231"/>
        $this->assertTrue( $check->thereAreErrors() );   //mismatch <x id="231"/>

        $errors   = $check->getErrors();
        $warnings = $check->getWarnings();

        $this->assertCount( 2, $errors );
        $this->assertCount( 2, $warnings ); // warnings are not checked because of tag mismatch,
        // analysis on space warnings skipped de facto

        $this->assertAttributeEquals( 13, 'outcome', $errors[ 0 ] );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 1 ] );
        $this->assertRegExp( '/ 1 /', $check->getWarningsJSON() );
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );

        $this->assertEquals( $check->getErrorsJSON(), $check->getWarningsJSON() );
        $this->assertEquals( '[{"outcome":13,"debug":"Wrong format for x tag. Should be < x .... \/> ( 1 )"},{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getWarningsJSON() );

        //export normalized string only if there are no exceptions
        $this->setExpectedException( 'LogicException' );
        $check->getTrgNormalized();

    }


    public function testTryRealignTagID() {

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

        $segment     = CatUtils::layer2ToLayer0( $segment );
        $translation = CatUtils::layer2ToLayer0( $translation );

        $qaRealign = new QA( $segment, $translation );
        $qaRealign->tryRealignTagID();

        $this->assertFalse( $qaRealign->thereAreErrors() );

        $new_target = $qaRealign->getTrgNormalized();
        $this->assertEquals( $expected_translation, $new_target );


        //there are not the same number of target in source and translation
        $translation = <<<SRC
<g id="7"> 1 </g><g id="8">&#170;  </g><x id="abc" /><g id="9">&nbsp;Sezione di Tokyo, Osaka</g>
SRC;
        $translation = CatUtils::layer2ToLayer0( $translation );

        $qaRealign = new QA( $segment, $translation );
        $qaRealign->tryRealignTagID();

        $this->assertTrue( $qaRealign->thereAreErrors() );
        $errors = $qaRealign->getErrors();
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 0 ] );
        $this->setExpectedException( 'LogicException' );
        $qaRealign->getTrgNormalized();


        //there are the same number of tags in source and target, but types differs
        $segment     = <<<SRC
<g id="6"> 1 </g><g id="7">st  </g><g id="abc"></g><g id="8">&nbsp;Section of Tokyo, Osaka</g>
SRC;
        $translation = <<<SRC
<g id="7"> 1 </g><g id="8">&#170;  </g><x id="abc" /><g id="9">&nbsp;Sezione di Tokyo, Osaka</g>
SRC;
        $segment     = CatUtils::layer2ToLayer0( $segment );
        $translation = CatUtils::layer2ToLayer0( $translation );

        $qaRealign = new QA( $segment, $translation );
        $qaRealign->tryRealignTagID();

        $this->assertTrue( $qaRealign->thereAreErrors() );
        $errors = $qaRealign->getErrors();
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 0 ] );
        $this->setExpectedException( 'LogicException' );
        $qaRealign->getTrgNormalized();


    }

    public function testBadXml() {

        //PHASE 3 check for particular tag mismatch: unclosed x tag <x id="231">
        $source_seg = <<<SRC
<g id="pt235"><g id="pt236"> <x id="abc/></g></g> <g id="pt238"><g id="pt239">Elsa sur son ordinateur tape des lignes de code quand sa colocataire, Joy, entre dans sa chambre.</g></g>
SRC;

        $target_seg = <<<TRG
<g id="pt235"><g id="pt236"> </g></g> <g id="pt238"><g id="pt239">Elsa's on her computer typing lines of code when her roommate, Joy, enters the room.</g></g>
TRG;

        $check = new QA( $source_seg, $target_seg );
        $check->performTagCheckOnly();
        $this->assertTrue( $check->thereAreErrors() );
        $errors = $check->getErrors();
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 0 ] );
        $this->assertRegExp( '/ 1 /', $check->getErrorsJSON() );

        $this->assertEquals( '[{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getWarningsJSON() );
        $this->assertEquals( '[{"outcome":1000,"debug":"Tag mismatch ( 1 )"}]', $check->getErrorsJSON() );

    }

    public function testBugWindowsPathsFromPost() {

        //Source from post raw

        $source_seg = <<<SRC
C:\\Users\\user\\Downloads\\File per campo test\\1\\gui_plancompression.html
SRC;

        $check = new QA( $source_seg, $source_seg );
        $check->performConsistencyCheck();

        $errors = $check->getErrors();
        $this->assertFalse( $check->thereAreErrors() );
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 0, 'outcome', $errors[ 0 ] );;

        $new_target = $check->getTrgNormalized();
        $this->assertEquals( $source_seg, $new_target );


        $source_seg = <<<SRC
C:\\Users\\user\\Downloads\\File per campo test\\1\\gui_email.html \\\' \' \\\\\' \\\\\\
SRC;

        $check = new QA( $source_seg, $source_seg );
        $check->performConsistencyCheck();

        $errors = $check->getErrors();
        $this->assertFalse( $check->thereAreErrors() );
        $this->assertCount( 1, $errors );
        $this->assertAttributeEquals( 0, 'outcome', $errors[ 0 ] );;

        $new_target = $check->getTrgNormalized();
        $this->assertEquals( $source_seg, $new_target );

    }

    public function testBugWindowsPathFromDB() {

        $DB_SERVER   = "localhost"; //database server
        $DB_DATABASE = "unittest_matecat_local"; //database name
        $DB_USER     = "unt_matecat_user"; //database login
        $DB_PASS     = "unt_matecat_user"; //databasepassword
        $db          = Database::obtain( $DB_SERVER, $DB_USER, $DB_PASS, $DB_DATABASE );
        $db->connect();

        $query   = "select * from segment_translations where id_segment = 1";
        $results = $db->query_first( $query );

        $source_seg = $results[ 'translation' ];

        $check = new QA( $source_seg, $source_seg );
        $check->performConsistencyCheck();

        $errors = $check->getErrors();
        $this->assertFalse( $check->thereAreErrors() );
        $this->assertCount( 1, $errors );


        $this->assertAttributeEquals( 0, 'outcome', $errors[ 0 ] );;

        $new_target = $check->getTrgNormalized();
        $this->assertEquals( $source_seg, $new_target );

    }

    public function testMultilineStringInput() {

        $source_seg = <<<SRC
The National Security Authority ( NSA ) , the Designated Security Authority ( DSA ) or any other competent authority of each Member State shall ensure , to the extent possible under national laws and regulations , that contractors and subcontractors registered in their territory take all appropriate measures to protect EUCI in pre-contract negotiations and when performing a classified contract
SRC;

        $target_seg = urldecode( 'L%27Autorit%C3%A0+di+Sicurezza+Nazionale+%28NSA%29%2C+l%27Autorit%C3%A0+di+Sicurezza+Designata+%28DSA%29+o+qualsiasi+altra+autorit%C3%A0+nazionale+competente+di+ciascuno+Stato+membro+assicura%2C+per+quanto+possibile%0Aai+sensi+delle+disposizioni+legislative+e+regolamentari+nazionali%2C%0Ache+i+contraenti+e+i+subcontraenti+registrati+nel+suo+territorio%0Aadottino+le+misure+adeguate+per+proteggere+le+ICUE+nelle+trattative+precontrattuali+e+nell%27esecuzione+di+un+contratto+classificato.' );

        $segment     = CatUtils::layer2ToLayer0( $source_seg );
        $translation = CatUtils::layer2ToLayer0( $target_seg );

        $check = new QA( $segment, $translation );
        $check->performConsistencyCheck();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertNotEmpty( $check->getTrgNormalized() );

    }

    public function testSpaces_Notices() {

        $source_seg = <<<SRC
<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>
SRC;

        $target_seg = <<<TRG
<g id="pt2"> WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Internal Revenue Service di oggi hanno chiesto un commento pubblico sulle questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che verranno applicate a certi datori di lavoro a partire dal 2014. </g>
TRG;

        $source_seg = CatUtils::layer2ToLayer0( $source_seg );
        $target_seg = CatUtils::layer2ToLayer0( $target_seg );

        $check = new QA( $source_seg, $target_seg );
        $check->performConsistencyCheck();

        $notices  = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreNotices() );

        $this->assertEquals( count( $notices ), 2 );
        $this->assertEquals( 1100, $notices[ 0 ]->outcome );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( '<g id="pt2"> WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Internal Revenue Service di oggi hanno chiesto un commento pubblico sulle questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che verranno applicate a certi datori di lavoro a partire dal 2014. </g>', $normalized );

    }

    /**
     * @test
     * @throws Exception
     */
    public function testWithEmptyGTags() {

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );

        $segment     = "Xml string with <x/> and <g id=\"23\"></g> <g id=\"24\"></g> let's see what happen.";
        $translation = "String Xml con <x/> e <g id=\"23\"></g> <g id=\"24\"></g> vediamo che fa.";

        $check = new QA( $segment, $translation );
        $check->setFeatureSet( $featureSet );
        $check->setSourceSegLang( "en-US" );
        $check->setTargetSegLang( "it-IT" );
        $check->performConsistencyCheck();

        $targetNormalized = $check->getTrgNormalized();

        $this->assertEquals( $targetNormalized, $translation );
        $this->assertEquals( $check->getTargetSeg(), $translation );
    }

    /**
     * In Matecat there are some special characters mapped in data_ref_map (like &#39; for example)
     * that can be omitted in the target.
     *
     * In this case no error should be detected from QA class
     *
     * @throws \Exception
     */
    public function testCheckThereAreNoErrorsWithDataRefTagsToBeSkipped() {

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $filter = MateCatFilter::getInstance( $featureSet, 'en-EN','pt-PT', [] );

        $targetLang  = 'pt-PT';
        $segment     = '<ph id="source1" dataRef="source1"/>When you partner with Uber Eats, you have access to the Restaurant Dashboard to manage your establishment<ph id="source2" dataRef="source2"/>s orders.';
        $translation = 'Ao fazer parceria com o Uber Eats, você tem acesso ao Painel do Restaurante para gerenciar os pedidos dos seus estabelecimentos.<ph id="source1" dataRef="source1"/> ';
        $dataRefMap  = [
                'source3' => '&#39;',
                'source4' => '&lt;a class=&quot;cmln__link&quot; href=&quot;https://restaurant-dashboard.uber.com/&quot; target=&quot;_blank&quot;&gt;',
                'source5' => '&lt;/a&gt;',
                'source1' => '&lt;p class=&quot;cmln__paragraph&quot;&gt;',
                'source6' => '&lt;/p&gt;',
                'source2' => '&#39;',
        ];

        $segment     = $filter->fromLayer0ToLayer1( $segment );
        $translation = $filter->fromLayer0ToLayer1( $translation );

        $dataRefReplacer     = new DataRefReplacer( $dataRefMap );
        $replacedSegment     = $dataRefReplacer->replace( $segment );
        $replacedTranslation = $dataRefReplacer->replace( $translation );

        $check = new QA ( $replacedSegment, $replacedTranslation );
        $check->setFeatureSet( $featureSet );
        $check->setTargetSegLang( $targetLang );
        $check->performTagCheckOnly();

        $this->assertFalse($check->thereAreErrors());
    }
}

