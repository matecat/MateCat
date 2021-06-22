<?php

use Matecat\SubFiltering\MateCatFilter;

class PostProcessTest extends AbstractTest {

    /** @var MateCatFilter */
    protected $filter;

    /** @var FeatureSet */
    protected $featureSet;

    /**
     * @throws \Exception
     */
    public function setUp() {

        parent::setUp();

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        //$featureSet->loadFromString( "project_completion,translation_versions,qa_check_glossary,microsoft" );

        $this->filter = MateCatFilter::getInstance( $this->featureSet, 'en-EN','it-IT', [] );

    }

    public function testNestingTags() {

        $source_seg = <<<TRG
<g id="1621">By selecting this menu as shown in Fig.18 you can review the measurement records (refer to Fig.19), press the <x id="1622"/></g><g id="1623"> </g><g id="1624">or the <x id="1625"/></g><g id="1626"> </g><g id="1627">button to review the records page by page, the longer you press the<x id="1628"/></g><g id="1629">  </g><g id="1630">or<x id="1631"/></g><g id="1632"> </g><g id="1633">button the faster record page changes.</g>
TRG;

        $target_seg = <<<SRC
<g id="1621">By selecting this menu as shown in Fig.18 you can review the measurement records (refer to Fig.19), press the <x id="1622"/> </g> <g id="1623"> </g> <g id="1624"> or the <x id="1625"/></g><g id="1626"> </g> <g id="1627"> button to review the records page by page, the longer you press the <x id="1628"/></g><g id="1629">  </g><g id="1630"> or <x id="1631"/> </g><g id="1632"> </g><g id="1633"> button the faster record page changes. </g>
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );

        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( $source_seg, $normalized );

    }


    public function testSpaces1() {

        $source_seg = <<<SRC
 Only Text
SRC;

        $target_seg = <<<TRG
Only Text
TRG;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );

        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( ' Only Text', $normalized );

    }

    public function testRecursiveSpaces2() {

        $source_seg = <<<SRC
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of <.++* Tokyo <g id="9"><g id="10">Station</g></g>, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7"> st </g> <g id="8">&nbsp;Section of <.++* Tokyo <g id="9"> <g id="10"> Station </g> </g>, Osaka </g> </g>
TRG;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );

        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        //assert no exception
        $normalized = $check->getTrgNormalized();

//        echo Log::hexDump( $source_seg , false, true,true );
//        echo "\n";
//        echo Log::hexDump( $normalized , false, true,true );
//        echo "\n";

        //" 1 " -> 20 31 20
        $this->assertEquals( $source_seg, $normalized );

    }


    public function testSpaces3() {

        $source_seg = <<<TRG
<g id="1877">31-235</g> <g id="1878">The default PR upper alarm is120.</g>
TRG;

        $target_seg = <<<SRC
<g id="1877"> 31-235 </g><g id="1878"> L'impostazione predefinita PR IS120 allarme. </g>
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );

        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( '<g id="1877">31-235</g> <g id="1878">L\'impostazione predefinita PR IS120 allarme.</g>', $normalized );

    }

    public function testWrongTM() {

        $source_seg = <<<TRG
<g id="1877">31-235</g> <g id="1878">The default PR upper alarm is120.</g>
TRG;

        $target_seg = <<<SRC
<g id="1877"> 31-235 </g><x id="1878"/> L'impostazione predefinita PR IS120 allarme.
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );

        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertTrue( $check->thereAreErrors() );
        $this->assertTrue( $check->thereAreWarnings() );

        $this->assertEquals( count( $warnings ), 2 );
        $this->assertEquals( 1000, $warnings[ 0 ]->outcome );

        $this->assertCount( 2, $errors );
        $this->assertAttributeEquals( 1000, 'outcome', $errors[ 0 ] );
        $this->assertRegExp( '/\( 1 \)/', $check->getErrorsJSON() );
        $this->assertAttributeEquals( 4, 'outcome', $errors[ 1 ] );
        $this->assertRegExp( '/\( 1 \)/', $check->getErrorsJSON() );

        $this->setExpectedException( 'LogicException' );
        $normalized = $check->getTrgNormalized();

    }

    public function testQACompatibility() {

        $source_seg = <<<TRG
<g id="1877">31-235</g> <g id="1878">The default PR upper alarm is120.</g>
TRG;

        //" 1 " -> 20 31 20
        $target_seg = <<<SRC
<g id="1877"> 31-235 </g><g id="1879"> L'impostazione predefinita PR IS120 allarme. </g>
SRC;


        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );

        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        //tag ID mismatch / tag Mismatch
        $this->assertTrue( $check->thereAreErrors() );
        $this->assertTrue( $check->thereAreWarnings() );

        $this->setExpectedException( 'LogicException' );
        $normalized = $check->getTrgNormalized();

        $check->tryRealignTagID();
        $normalized = $check->getTrgNormalized();

        $this->assertFalse( $check->thereAreErrors() );

        $check->realignMTSpaces();
        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );

        $this->assertEquals( '<g id="1877">31-235</g><g id="1879">L\'impostazione predefinita PR IS120 allarme.</g>', $normalized );


    }

    public function testRealString1() {

        $source_seg = <<<TRG
<g id="1877">31-235</g>	<g id="1878">The default PR upper alarm is120.</g>
TRG;

        $target_seg = <<<SRC
<g id="1877"> 31-235 </g><g id="1878"> L'impostazione predefinita PR IS120 allarme. </g>
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );


        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );


        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[ 0 ]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[ 0 ]->outcome );

        $normalized = $check->getTrgNormalized();

        //trick strings are not exactly the same .. there's a tab between tags in source string
        $this->assertEquals( '<g id="1877">31-235</g><g id="1878">L\'impostazione predefinita PR IS120 allarme.</g>', $normalized );


    }

}

?>
