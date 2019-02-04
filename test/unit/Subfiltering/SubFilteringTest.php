<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 01/02/19
 * Time: 16.34
 *
 */

use SubFiltering\Filter;
use SubFiltering\Filters\LtGtDecode;

class SubFilteringTest extends AbstractTest {

    /**
     * @var \SubFiltering\Filter
     */
    protected $filter;

    /**
     * @throws \Exception
     */
    public function setUp() {

        parent::setUp();

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        //$featureSet->loadFromString( "project_completion,translation_versions,qa_check_glossary,microsoft" );

        $this->filter = Filter::getInstance( $featureSet );

    }

    /**
     * @throws \Exception
     */
    public function testSimpleString() {

        $segment = "The house is red.";
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $tmpLayer2 = ( new LtGtDecode() )->transform( $segmentL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $tmpLayer2 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $tmpLayer2 ) );

    }

    /**
     * @throws \Exception
     */
    public function testHtmlInXML() {

        $segment = '&lt;p&gt; Airbnb &amp;amp; Co. &amp; &lt;strong&gt;Use professional tools&lt;/strong&gt; in your &lt;a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank"&gt;';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $tmpLayer2 = ( new LtGtDecode() )->transform( $segmentL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $tmpLayer2 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $tmpLayer2 ) );

    }

    /**
     * @throws \Exception
     */
    public function testComplexXML(){

        $segment = '&lt;p&gt; Airbnb &amp;amp; Co. &amp; <ph id="PlaceHolder1" equiv-text="{0}"/> &quot; &apos;<ph id="PlaceHolder2" equiv-text="/users/settings?test=123&amp;ciccio=1"/> &lt;a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank"&gt;';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $tmpLayer2 = ( new LtGtDecode() )->transform( $segmentL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $tmpLayer2 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $tmpLayer2 ) );

    }

    /**
     * @throws \Exception
     */
    public function testComplexBrokenHtmlInXML(){

        $segment = '%{abb:flag.nolinkvalidation[0]} &lt;div class="panel"&gt; &lt;div class="panel-body"&gt; &lt;p&gt;You can read this article in &lt;a href="/help/article/1381?';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $tmpLayer2 = ( new LtGtDecode() )->transform( $segmentL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $tmpLayer2 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $tmpLayer2 ) );

    }

    /**
     * @throws \Exception
     */
    public function testComplexHtmlFilledWithXML(){

        $segment = '<g id="1">To: </g><g id="2">Novartis, Farmaco (Gen) <g id="3">&lt;fa</g><g id="4">rmaco.novartis@novartis.com&gt;</g></g>';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $tmpLayer2 = ( new LtGtDecode() )->transform( $segmentL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $tmpLayer2 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $tmpLayer2 ) );

    }

    /**
     * @throws \Exception
     */
    public function testPlainTextInXML(){

        $segment = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand

is &lt; 70 dB(A).';

        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $tmpLayer2 = ( new LtGtDecode() )->transform( $segmentL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $tmpLayer2 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $tmpLayer2 ) );

    }

    /**
     * @throws \Exception
     */
    public function testComplexHTML(){

        $segment = '<g id="1">	Si noti che ci vogliono circa 3 ore dopo aver ingerito</g><g id="2">&lt;a </g><g id="3"/>href<g id="4"> =</g><g id="5">"https://www.supersmart.com/fr--Phytonutriments--CBD-25-mg--0771--WNN" target<x id="6"/>=<x id="7"/><x id="8"/>"_blank"</g><g id="9">&gt;</g><g id="10">una capsula di CBD da 25 mg</g><g id="11">&lt;/a&gt;</g><bx id="12"/> affinché i livelli ematici raggiungano il picco.';

        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $tmpLayer2 = ( new LtGtDecode() )->transform( $segmentL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $tmpLayer2 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $tmpLayer2 ) );

    }

}