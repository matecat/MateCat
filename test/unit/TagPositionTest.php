<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 04/03/14
 * Time: 13.21
 *
 */

use LQA\QA;
use Matecat\SubFiltering\MateCatFilter;

/**
 * Description of QATest
 *
 * @author domenico
 */
class Tests_TagPositionTest extends AbstractTest {

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

    public function testTagPositionCheck_1() {

        $source_seg = <<<TRG
<g id="1877">31-235</g>	<g id="1878">The default <x id="1879"/>PR upper alarm is120.</g>
TRG;

        $target_seg = <<<SRC
<g id="1877"> 31-235</g> L'impostazione predefinita <x id="1879"/>PR IS120 allarme. <g id="1878"></g>
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );


        $check = new QA( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() );

        $checkPosition     = $check->getTargetTagPositionError();
        $checkPositionVals = array_keys( $checkPosition );

        $this->assertCount( 1, $checkPosition );

        $this->assertEquals( '<x id="1879"/>', html_entity_decode( end( $checkPosition ) ) );


    }

    public function testTagPositionCheck_2() {

        $source_seg = <<<TRG
<g id="1630">or<x id="1631"/></g><g id="1632"> </g><g id="1633">button the faster record page changes.</g>
TRG;

        $target_seg = <<<SRC
<g id="1630">or</g><x id="1631"/><g id="1632"> </g><g id="1633">button the faster record page changes.</g>
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );


        $check = new QA( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() );

        $checkPosition     = $check->getTargetTagPositionError();
        $checkPositionVals = array_keys( $checkPosition );

        $this->assertCount( 1, $checkPosition );

        $this->assertEquals( '</g>', html_entity_decode( end( $checkPosition ) ) );


    }

    public function testTagPositionHardNesting_1() {

        $source_seg = <<<TRG
<g id="1630">or<g id="1632"><x id="1631"/></g><g id="1633">button the faster record page changes.</g></g>
TRG;

        $target_seg = <<<SRC
<g id="1630">or<g id="1632"><x id="1631"/><g id="1633">button the faster record page changes.</g></g></g>
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );


        $check = new QA( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() );

        $checkPosition     = $check->getTargetTagPositionError();
        $checkPositionVals = array_keys( $checkPosition );

        $this->assertCount( 1, $checkPosition );

        $this->assertEquals( '<g id="1633">', html_entity_decode( end( $checkPosition ) ) );


    }

    public function testTagPositionHardNesting_2() {

        $source_seg = <<<SRC
<g id="6"> <g id="7">st</g><g id="8">&nbsp;<span class="this is in entities">Section</span> of <.++* Tokyo <g id="9"><g id="10">Station</g></g>, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7">st</g> <g id="8">&nbsp;<span class="this is in entities">Section</span> of <.++* Tokyo <g id="9"></g><g id="10"> Station</g>, Osaka </g></g>
TRG;

        $source_seg = $this->filter->fromLayer2ToLayer0( $source_seg );
        $target_seg = $this->filter->fromLayer2ToLayer0( $target_seg );


        $check = new QA( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->performConsistencyCheck();

        $this->assertTrue( $check->thereAreWarnings() );

        $checkPosition     = $check->getTargetTagPositionError();
        $checkPositionVals = array_keys( $checkPosition );

        $this->assertCount( 1, $checkPosition );

        $this->assertEquals( '</g>', html_entity_decode( end( $checkPosition ) ) );


    }


}

