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
use SubFiltering\Filters\LtGtDoubleDecode;

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

        $segment = '&lt;p&gt; Airbnb &amp; Co. &amp; &lt;strong&gt;Use professional tools&lt;/strong&gt; in your &lt;a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank"&gt;';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        //Start test
        $string_from_UI = '<ph id="mtc_1" equiv-text="base64:Jmx0O3AmZ3Q7"/> Airbnb &amp; Co. &amp; <ph id="mtc_2" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>Use professional tools<ph id="mtc_3" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/> in your <ph id="mtc_4" equiv-text="base64:Jmx0O2EgaHJlZj0iL3VzZXJzL3NldHRpbmdzP3Rlc3Q9MTIzJmFtcDthbXA7YW1wO2NpY2Npbz0xIiB0YXJnZXQ9Il9ibGFuayImZ3Q7"/>';

        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * @throws \Exception
     */
    public function testComplexXML(){

        $segment = '&lt;p&gt; Airbnb &amp; Co. &amp; <ph id="PlaceHolder1" equiv-text="{0}"/> &quot; &apos;<ph id="PlaceHolder2" equiv-text="/users/settings?test=123&amp;ciccio=1"/> &lt;a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank"&gt;';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $string_from_UI = '<ph id="mtc_1" equiv-text="base64:Jmx0O3AmZ3Q7"/> Airbnb &amp; Co. &amp; <ph id="PlaceHolder1" equiv-text="base64:ezB9"/> " \'<ph id="PlaceHolder2" equiv-text="base64:L3VzZXJzL3NldHRpbmdzP3Rlc3Q9MTIzJmFtcDtjaWNjaW89MQ=="/> <ph id="mtc_2" equiv-text="base64:Jmx0O2EgaHJlZj0iL3VzZXJzL3NldHRpbmdzP3Rlc3Q9MTIzJmFtcDthbXA7YW1wO2NpY2Npbz0xIiB0YXJnZXQ9Il9ibGFuayImZ3Q7"/>';

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );


        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );

        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * Filters BUG, segmentation on HTML ( Should be fixed, anyway we try to cover )
     * @throws \Exception
     */
    public function testComplexBrokenHtmlInXML(){

        $segment = '%{abb:flag.nolinkvalidation[0]} &lt;div class="panel"&gt; &lt;div class="panel-body"&gt; &lt;p&gt;You can read this article in &lt;a href="/help/article/1381?';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $string_from_UI = '<ph id="mtc_1" equiv-text="base64:JXthYmI6ZmxhZy5ub2xpbmt2YWxpZGF0aW9uWzBdfQ=="/> <ph id="mtc_2" equiv-text="base64:Jmx0O2RpdiBjbGFzcz0icGFuZWwiJmd0Ow=="/> <ph id="mtc_3" equiv-text="base64:Jmx0O2RpdiBjbGFzcz0icGFuZWwtYm9keSImZ3Q7"/> <ph id="mtc_4" equiv-text="base64:Jmx0O3AmZ3Q7"/>You can read this article in &lt;a href="/help/article/1381?';

        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

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

        $string_from_UI = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand##$_0A$####$_0A$##is &lt; 70 dB(A).';

        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * @throws \Exception
     */
    public function testComplexHTMLFromTradosOLDSystemSegmentation(){

        $segment = '<g id="1">	Si noti che ci vogliono circa 3 ore dopo aver ingerito</g><g id="2">&lt;a </g><g id="3"/>href<g id="4"> =</g><g id="5">"https://www.supersmart.com/fr--Phytonutriments--CBD-25-mg--0771--WNN" target<x id="6"/>=<x id="7"/><x id="8"/>"_blank"</g><g id="9">&gt;</g><g id="10">una capsula di CBD da 25 mg</g><g id="11">&lt;/a&gt;</g><bx id="12"/> affinché i livelli ematici raggiungano il picco.';

        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );

        //These tests are skipped because the integrity can not be granted
//        $string_from_UI = '<g id="1">##$_09$##Si noti che ci vogliono circa 3 ore dopo aver ingerito</g><g id="2">&lt;a </g><g id="3"/>href<g id="4"> =</g><g id="5">"https://www.supersmart.com/fr--Phytonutriments--CBD-25-mg--0771--WNN" target<x id="6"/>=<x id="7"/><x id="8"/>"_blank"</g><g id="9">&gt;</g><g id="10">una capsula di CBD da 25 mg</g><g id="11"><ph id="mtc_1" equiv-text="base64:Jmx0Oy9hJmd0Ow=="/></g><bx id="12"/> affinché i livelli ematici raggiungano il picco.';
//        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
//        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * @throws \Exception
     */
    public function testHtmlInXML_2(){

        //DB segment
        $segment = '&amp;lt;b&amp;gt;de %1$s, &amp;lt;/b&amp;gt;que';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );

    }

    /**
     * @throws \Exception
     */
    public function testHTMLFromLayer2(){

        //Original JSON value from Airbnb
        //"&lt;br>&lt;br>This will "
        $expected_segment = '&amp;lt;b&gt;de %1$s, &lt;/b&gt;que';

        //Start test
        $string_from_UI = '&lt;b&gt;de <ph id="mtc_1" equiv-text="base64:JTEkcw=="/>, &lt;/b&gt;que';
        $this->assertEquals( $expected_segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );

        $string_in_layer1 = '<ph id="mtc_1" equiv-text="base64:Jmx0O2ImZ3Q7"/>de <ph id="mtc_2" equiv-text="base64:JTEkcw=="/>, <ph id="mtc_3" equiv-text="base64:Jmx0Oy9iJmd0Ow=="/>que';
        $this->assertEquals( $expected_segment, $this->filter->fromLayer1ToLayer0( $string_in_layer1 ) );

    }

    /**
     * @throws \Exception
     */
    public function testFix(){

        $seg [ 'segment' ] = 'Due to security concerns, we were not able to process your transaction.&amp;lt;br&gt;&amp;lt;br&gt;This will likely happen if you try again.&amp;lt;br&gt;&amp;lt;br&gt;If you feel you should be able to complete your transaction, contact us.';
        $translation = 'Devido a questões de segurança, não foi possível processar sua transação. &lt;br&gt;&lt;br&gt; Isso provavelmente acontecerá se você tentar novamente. &lt;br&gt;&lt;br&gt;Se você acha que deve conseguir concluir sua transação , Contate-Nos.';

        $sanitize = ( new LtGtDoubleDecode() )->transform( $seg [ 'segment' ]  );

        $check = new QA (
                $this->filter->fromLayer0ToLayer1( $sanitize ),
                $this->filter->fromLayer0ToLayer1( $translation )
        );

        $check->performTagCheckOnly();
        $this->assertFalse( $check->thereAreErrors() );

    }

    public function testFalseError(){

        $raw_segment = 'You can always <ph id="mtc_1" equiv-text="base64:JXt1bmRvX2xpbmtfc3RhcnR9"/>undo these changes<ph id="mtc_2" equiv-text="base64:JXt1bmRvX2xpbmtfZW5kfQ=="/>.';
        $suggestion_raw = '\u0130stedi\u011finiz zaman <ph id="mtc_1" equiv-text="base64:JXt1bmRvX2xpbmtfc3RhcnR9"/>bu de\u011fi\u015fiklikleri geri alabilirsiniz<ph id="mtc_2" equiv-text="base64:JXt1bmRvX2xpbmtfZW5kfQ=="/>.';

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        
        $check = new \PostProcess( $raw_segment, $suggestion_raw );
        $check->setFeatureSet( $featureSet );
        $check->realignMTSpaces();

        //this should every time be ok because MT preserve tags, but we use the check on the errors
        //for logic correctness
        $this->assertFalse( $check->thereAreErrors() );

    }

    public function testVariablesWithHTML(){

        $db_segment = 'Airbnb account.%{\n}%{&lt;br&gt;}%{\n}1) From ';
        $segment_from_UI = 'Airbnb account.<ph id="mtc_1" equiv-text="base64:JXtcbn0="/>%{<ph id="mtc_2" equiv-text="base64:Jmx0O2JyJmd0Ow=="/>}<ph id="mtc_3" equiv-text="base64:JXtcbn0="/>1) From ';
        $segment_to_UI = 'Airbnb account.&lt;ph id="mtc_1" equiv-text="base64:JXtcbn0="/&gt;%{&lt;ph id="mtc_2" equiv-text="base64:Jmx0O2JyJmd0Ow=="/&gt;}&lt;ph id="mtc_3" equiv-text="base64:JXtcbn0="/&gt;1) From&nbsp;';

        $segmentL2 = $this->filter->fromLayer0ToLayer2( $db_segment );

        $this->assertEquals( $segment_to_UI, $segmentL2 );

        $this->assertEquals( $db_segment, $this->filter->fromLayer1ToLayer0( $segment_from_UI ) );

        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segment_from_UI ) );

        $this->assertEquals( $segment_from_UI, $this->filter->fromLayer0ToLayer1( $db_segment ) );

    }

}