<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 01/02/19
 * Time: 16.34
 *
 */

use Matecat\SubFiltering\Commons\Pipeline;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Filters\LtGtDecode;
use Matecat\SubFiltering\Filters\LtGtDoubleDecode;
use Matecat\SubFiltering\Filters\SprintfToPH;
use Matecat\SubFiltering\Filters\TwigToPh;

class SubFilteringTest extends AbstractTest {

    /**
     * @var MateCatFilter
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

        $this->filter = MateCatFilter::getInstance( $featureSet, 'en-EN','it-IT', [] );
    }

    /**
     * @throws \Exception
     */
    public function testSimpleString() {

        $segment   = "The house is red.";
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

        $segment   = '&lt;p&gt; Airbnb &amp;amp; Co. &amp;lt; <x id="1"> &lt;strong&gt;Use professional tools&lt;/strong&gt; in your &lt;a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank"&gt;';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

    }

    /**
     * @throws \Exception
     */
    public function testUIHtmlInXML() {

        $segment   = '&lt;p&gt; Airbnb &amp;amp; Co. &amp;lt; &lt;strong&gt;Use professional tools&lt;/strong&gt; in your &lt;a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank"&gt;';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        //Start test
        $string_from_UI = '<ph id="mtc_1" equiv-text="base64:Jmx0O3AmZ3Q7"/> Airbnb &amp; Co. &lt; <ph id="mtc_2" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>Use professional tools<ph id="mtc_3" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/> in your <ph id="mtc_4" equiv-text="base64:Jmx0O2EgaHJlZj0iL3VzZXJzL3NldHRpbmdzP3Rlc3Q9MTIzJmFtcDthbXA7Y2ljY2lvPTEiIHRhcmdldD0iX2JsYW5rIiZndDs="/>';

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );

        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * @throws \Exception
     */
    public function testComplexXML() {

        $segment   = '&lt;p&gt; Airbnb &amp;amp; Co. &amp;amp; <ph id="PlaceHolder1" equiv-text="{0}"/> &amp;quot; &amp;apos;<ph id="PlaceHolder2" equiv-text="/users/settings?test=123&amp;ciccio=1"/> &lt;a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank"&gt;';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $string_from_UI = '<ph id="mtc_1" equiv-text="base64:Jmx0O3AmZ3Q7"/> Airbnb &amp; Co. &amp; <ph id="PlaceHolder1" equiv-text="base64:ezB9"/> " \'<ph id="PlaceHolder2" equiv-text="base64:L3VzZXJzL3NldHRpbmdzP3Rlc3Q9MTIzJmFtcDtjaWNjaW89MQ=="/> <ph id="mtc_2" equiv-text="base64:Jmx0O2EgaHJlZj0iL3VzZXJzL3NldHRpbmdzP3Rlc3Q9MTIzJmFtcDthbXA7Y2ljY2lvPTEiIHRhcmdldD0iX2JsYW5rIiZndDs="/>';

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );

        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * Filters BUG, segmentation on HTML ( Should be fixed, anyway we try to cover )
     * @throws \Exception
     */
    public function testComplexBrokenHtmlInXML() {

        $segment   = '%{abb:flag.nolinkvalidation[0]} &lt;div class="panel"&gt; &lt;div class="panel-body"&gt; &lt;p&gt;You can read this article in &lt;a href="/help/article/1381?';
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
    public function testComplexHtmlFilledWithXML() {

        $segment   = '<g id="1">To: </g><g id="2">No-foo, Farmaco (Gen) <g id="3">&lt;fa</g><g id="4">foo.bar@foo.com&gt;</g></g>';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $string_from_UI = '<g id="1">To: </g><g id="2">No-foo, Farmaco (Gen) <g id="3">&lt;fa</g><g id="4">foo.bar@foo.com&gt;</g></g>';
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * @throws \Exception
     */
    public function testPlainTextInXMLWithNewLineFeed() {

        // 20 Aug 2019
        // ---------------------------
        // Originally we save new lines on DB ("level 0") without any encoding.
        // This of course generates a wrong XML, because in XML the new lines does not make sense.
        // Now we store them as "&#13;" entity in the DB, and return them as "##$_0A$##" for the view level ("level 2"")

        // this was the segment from the original test
//        $original_segment = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand
//
//is &lt; 70 dB(A).';
        $segment         = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand&#10;&#10;is &lt; 70 dB(A).';
        $expectedL1      = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand&#10;&#10;is &lt; 70 dB(A).';
        $expected_fromUI = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand##$_0A$####$_0A$##is &lt; 70 dB(A).';

        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $this->assertEquals( $segmentL1, $expectedL1 );

        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );
        $this->assertEquals( $expected_fromUI, $segmentL2 );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $expected_fromUI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $expected_fromUI ) );
    }

    /**
     * @throws \Exception
     */
    public function testPlainTextInXMLWithCarriageReturn() {
        $segment    = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand&#13;&#13;is &lt; 70 dB(A).';
        $expectedL1 = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand&#13;&#13;is &lt; 70 dB(A).';
        $expectedL2 = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand##$_0D$####$_0D$##is &lt; 70 dB(A).';

        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segmentL1, $expectedL1 );
        $this->assertEquals( $segmentL2, $expectedL2 );
        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $string_from_UI = 'The energetically averaged emission sound level of the pressure load cycling and bursting test stand##$_0D$####$_0D$##is &lt; 70 dB(A).';

        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );
    }

    /**
     * @throws \Exception
     */
    public function testComplexHTMLFromTradosOLDSystemSegmentation() {

        $segment = '<g id="1">	Si noti che ci vogliono circa 3 ore dopo aver ingerito</g><g id="2">&lt;a </g><g id="3"/>href<g id="4"> =</g><g id="5">"https://www.supersmart.com/fr--Phytonutriments--CBD-25-mg--0771--WNN" target<x id="6"/>=<x id="7"/><x id="8"/>"_blank"</g><g id="9">&gt;</g><g id="10">una capsula di CBD da 25 mg</g><g id="11">&lt;/a&gt;</g><bx id="12"/> affinché i livelli ematici raggiungano il picco.';

        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );

        //These tests are skipped because the integrity can not be granted
        $string_from_UI = '<g id="1">##$_09$##Si noti che ci vogliono circa 3 ore dopo aver ingerito</g><g id="2">&lt;a </g><g id="3"/>href<g id="4"> =</g><g id="5">"https://www.supersmart.com/fr--Phytonutriments--CBD-25-mg--0771--WNN" target<x id="6"/>=<x id="7"/><x id="8"/>"_blank"</g><g id="9">&gt;</g><g id="10">una capsula di CBD da 25 mg</g><g id="11"><ph id="mtc_1" equiv-text="base64:Jmx0Oy9hJmd0Ow=="/></g><bx id="12"/> affinché i livelli ematici raggiungano il picco.';
        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * @throws \Exception
     */
    public function test_2_HtmlInXML() {

        //DB segment
        $segment   = '&amp;lt;b&amp;gt;de %1$s, &amp;lt;/b&amp;gt;que';
        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );

    }

    public function test_3_HandlingNBSP() {

        $segment       = $expectedL1 = '5 tips for creating a great   guide';
        $segment_to_UI = $string_from_UI = '5 tips for creating a great ' . CatUtils::nbspPlaceholder . ' guide';

        $segmentL1 = $this->filter->fromLayer0ToLayer1( $segment );
        $segmentL2 = $this->filter->fromLayer0ToLayer2( $segment );

        $this->assertEquals( $segmentL1, $expectedL1 );
        $this->assertEquals( $segmentL2, $segment_to_UI );
        $this->assertEquals( $segment, $this->filter->fromLayer1ToLayer0( $segmentL1 ) );

        $this->assertEquals( $segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segmentL1 ) );
        $this->assertEquals( $segmentL1, $this->filter->fromLayer2ToLayer1( $string_from_UI ) );

    }

    /**
     * @throws \Exception
     */
    public function testHTMLFromLayer2() {

        //Original JSON value from Airbnb
        //"&lt;br>&lt;br>This will "

        //Xliff Value
        //"&amp;lt;br&gt;&amp;lt;br&gt;This will "

        //Fixed by airbnb plugin in Database
        //"&lt;br&gt;&lt;br&gt;This will"

        $expected_segment = '&lt;b&gt;de %1$s, &lt;/b&gt;que';

        //Start test
        $string_from_UI = '&lt;b&gt;de <ph id="mtc_1" equiv-text="base64:JTEkcw=="/>, &lt;/b&gt;que';
        $this->assertEquals( $expected_segment, $this->filter->fromLayer2ToLayer0( $string_from_UI ) );

        $string_in_layer1 = '<ph id="mtc_1" equiv-text="base64:Jmx0O2ImZ3Q7"/>de <ph id="mtc_2" equiv-text="base64:JTEkcw=="/>, <ph id="mtc_3" equiv-text="base64:Jmx0Oy9iJmd0Ow=="/>que';
        $this->assertEquals( $expected_segment, $this->filter->fromLayer1ToLayer0( $string_in_layer1 ) );

    }

    /**
     * @throws \Exception
     */
    public function testFixQA() {

        $seg [ 'segment' ] = 'Due to security concerns, we were not able to process your transaction.&amp;lt;br&gt;&amp;lt;br&gt;This will likely happen if you try again.&amp;lt;br&gt;&amp;lt;br&gt;If you feel you should be able to complete your transaction, contact us.';
        $translation       = 'Devido a questões de segurança, não foi possível processar sua transação. &lt;br&gt;&lt;br&gt; Isso provavelmente acontecerá se você tentar novamente. &lt;br&gt;&lt;br&gt;Se você acha que deve conseguir concluir sua transação , Contate-Nos.';

        $sanitize = ( new LtGtDoubleDecode() )->transform( $seg [ 'segment' ] );

        $check = new QA (
                $this->filter->fromLayer0ToLayer1( $sanitize ),
                $this->filter->fromLayer0ToLayer1( $translation )
        );

        $check->performTagCheckOnly();
        $this->assertFalse( $check->thereAreErrors() );

    }

    public function testFalseError() {

        $raw_segment    = 'You can always <ph id="mtc_1" equiv-text="base64:JXt1bmRvX2xpbmtfc3RhcnR9"/>undo these changes<ph id="mtc_2" equiv-text="base64:JXt1bmRvX2xpbmtfZW5kfQ=="/>.';
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

    public function testVariablesWithHTML() {

        $db_segment      = 'Airbnb account.%{\n}%{&lt;br&gt;}%{\n}1) From ';
        $segment_from_UI = 'Airbnb account.<ph id="mtc_1" equiv-text="base64:JXtcbn0="/>%{<ph id="mtc_2" equiv-text="base64:Jmx0O2JyJmd0Ow=="/>}<ph id="mtc_3" equiv-text="base64:JXtcbn0="/>1) From ';
        $segment_to_UI   = 'Airbnb account.&lt;ph id="mtc_1" equiv-text="base64:JXtcbn0="/&gt;%{&lt;ph id="mtc_2" equiv-text="base64:Jmx0O2JyJmd0Ow=="/&gt;}&lt;ph id="mtc_3" equiv-text="base64:JXtcbn0="/&gt;1) From&nbsp;';

        $segmentL2 = $this->filter->fromLayer0ToLayer2( $db_segment );

        $this->assertEquals( $segment_to_UI, $segmentL2 );
        $this->assertEquals( $db_segment, $this->filter->fromLayer1ToLayer0( $segment_from_UI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segment_from_UI ) );
        $this->assertEquals( $segment_from_UI, $this->filter->fromLayer0ToLayer1( $db_segment ) );

    }

    public function testSprintf() {

        $channel = new Pipeline();
        $channel->addLast( new SprintfToPH('hu-HU', 'az-AZ') );

        $segment         = 'Legalább 10%-os befejezett foglalás 20%-dir VAGY';
        $seg_transformed = $channel->transform( $segment );

        $this->assertEquals( $segment, $seg_transformed );

    }

    public function testTwigUngreedy() {
        $segment  = 'Dear {{customer.first_name}}, This is {{agent.alias}} with Airbnb.';
        $expected = 'Dear <ph id="mtc_1" equiv-text="base64:e3tjdXN0b21lci5maXJzdF9uYW1lfX0="/>, This is <ph id="mtc_2" equiv-text="base64:e3thZ2VudC5hbGlhc319"/> with Airbnb.';

        $channel = new Pipeline();
        $channel->addLast( new TwigToPh() );
        $seg_transformed = $channel->transform( $segment );
        $this->assertEquals( $expected, $seg_transformed );
    }

    public function testTwigWithPercents() {
        $db_segment      = 'Dear {{%%customer.first_name%%}}, This is %{%%agent.alias%%} with Airbnb.';
        $segment_from_UI = 'Dear <ph id="mtc_1" equiv-text="base64:e3slJWN1c3RvbWVyLmZpcnN0X25hbWUlJX19"/>, This is <ph id="mtc_2" equiv-text="base64:JXslJWFnZW50LmFsaWFzJSV9"/> with Airbnb.';
        $segment_to_UI   = 'Dear &lt;ph id="mtc_1" equiv-text="base64:e3slJWN1c3RvbWVyLmZpcnN0X25hbWUlJX19"/&gt;, This is &lt;ph id="mtc_2" equiv-text="base64:JXslJWFnZW50LmFsaWFzJSV9"/&gt; with Airbnb.';

        $segmentL2 = $this->filter->fromLayer0ToLayer2( $db_segment );

        $this->assertEquals( $segment_to_UI, $segmentL2 );
        $this->assertEquals( $db_segment, $this->filter->fromLayer1ToLayer0( $segment_from_UI ) );
        $this->assertEquals( $segmentL2, $this->filter->fromLayer1ToLayer2( $segment_from_UI ) );
        $this->assertEquals( $segment_from_UI, $this->filter->fromLayer0ToLayer1( $db_segment ) );
    }

    /**
     **************************
     * <ph> tags test (xliff 2.0)
     **************************
     */

    /**
     * @throws \Exception
     */
    public function testsPHPlaceholderWithDataRefForAirbnb() {
        $data_ref_map = [
                'source3' => '&lt;/a&gt;',
                'source4' => '&lt;br&gt;',
                'source5' => '&lt;br&gt;',
                'source1' => '&lt;br&gt;',
                'source2' => '&lt;a href=%s&gt;',
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance( $featureSet, 'en-EN','et-ET', $data_ref_map );

        $db_segment     = "Hi %s .";
        $db_translation = "Tere %s .";
        $expected_l1_segment = "Hi <ph id=\"mtc_1\" equiv-text=\"base64:JXM=\"/> .";
        $expected_l1_translation = "Tere <ph id=\"mtc_1\" equiv-text=\"base64:JXM=\"/> .";
        $expected_l2_segment = "Hi &lt;ph id=\"mtc_1\" equiv-text=\"base64:JXM=\"/&gt; .";
        $expected_l2_translation = "Tere &lt;ph id=\"mtc_1\" equiv-text=\"base64:JXM=\"/&gt; .";

        $l1_segment     = $Filter->fromLayer0ToLayer1( $db_segment );
        $l1_translation = $Filter->fromLayer0ToLayer1( $db_translation );
        $l2_segment     = $Filter->fromLayer1ToLayer2( $l1_segment );
        $l2_translation = $Filter->fromLayer1ToLayer2( $l1_translation );

        $this->assertEquals($l1_segment, $expected_l1_segment);
        $this->assertEquals($l1_translation, $expected_l1_translation);
        $this->assertEquals($l2_segment, $expected_l2_segment);
        $this->assertEquals($l2_translation, $expected_l2_translation);

        $back_to_db_segment =$Filter->fromLayer1ToLayer0($l1_segment);
        $back_to_db_translation =$Filter->fromLayer1ToLayer0($l1_translation);

        $this->assertEquals($back_to_db_segment, $db_segment);
        $this->assertEquals($back_to_db_translation, $db_translation);
    }

    /**
     * @throws \Exception
     */
    public function testPHPlaceholderWithDataRef() {
        $data_ref_map = [
                'source1' => '&lt;br&gt;',
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance( 'en-US','it-IT', $featureSet, 'it-IT', 'en-EN', $data_ref_map );

        $db_segment     = 'Frase semplice: <ph id="source1" dataRef="source1"/>.';
        $db_translation = 'Simple sentence: <ph id="source1" dataRef="source1"/>.';
        $expected_l1_segment = 'Frase semplice: <ph id="source1" dataRef="source1"/>.';
        $expected_l1_translation = 'Simple sentence: <ph id="source1" dataRef="source1"/>.';
        $expected_l2_segment = 'Frase semplice: &lt;ph id="source1" dataRef="source1" equiv-text="base64:Jmx0O2JyJmd0Ow=="/&gt;.';
        $expected_l2_translation = 'Simple sentence: &lt;ph id="source1" dataRef="source1" equiv-text="base64:Jmx0O2JyJmd0Ow=="/&gt;.';

        $l1_segment     = $Filter->fromLayer0ToLayer1( $db_segment );
        $l1_translation = $Filter->fromLayer0ToLayer1( $db_translation );
        $l2_segment     = $Filter->fromLayer1ToLayer2( $l1_segment );
        $l2_translation = $Filter->fromLayer1ToLayer2( $l1_translation );

        $this->assertEquals($l1_segment, $expected_l1_segment);
        $this->assertEquals($l1_translation, $expected_l1_translation);
        $this->assertEquals($l2_segment, $expected_l2_segment);
        $this->assertEquals($l2_translation, $expected_l2_translation);

        $back_to_db_segment =$Filter->fromLayer1ToLayer0($l1_segment);
        $back_to_db_translation =$Filter->fromLayer1ToLayer0($l1_translation);

        $this->assertEquals($back_to_db_segment, $db_segment);
        $this->assertEquals($back_to_db_translation, $db_translation);
    }

    /**
     **************************
     * <pc> tags test (xliff 2.0)
     **************************
     */

    public function testPCWithComplexDataRefMap() {
        $data_ref_map = [
                "source3" => "<g id=\"jcP-TFFSO2CSsuLt\" ctype=\"x-html-strong\" \/>",
                "source4" => "<g id=\"5StCYYRvqMc0UAz4\" ctype=\"x-html-ul\" \/>",
                "source5" => "<g id=\"99phhJcEQDLHBjeU\" ctype=\"x-html-li\" \/>",
                "source1" => "<g id=\"lpuxniQlIW3KrUyw\" ctype=\"x-html-p\" \/>",
                "source6" => "<g id=\"0HZug1d3LkXJU04E\" ctype=\"x-html-li\" \/>",
                "source2" => "<g id=\"d3TlPtomlUt0Ej1k\" ctype=\"x-html-p\" \/>",
                "source7" => "<g id=\"oZ3oW_0KaicFXFDS\" ctype=\"x-html-li\" \/>"
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance( $featureSet, 'it-IT', 'en-EN', $data_ref_map );

        $db_segment = '<pc id="source1" dataRefStart="source1">Click the image on the left, read the information and then select the contact type that would replace the red question mark.</pc><pc id="source2" dataRefStart="source2"><pc id="source3" dataRefStart="source3">Things to consider:</pc></pc><pc id="source4" dataRefStart="source4"><pc id="source5" dataRefStart="source5">The rider stated the car had a different tag from another state.</pc><pc id="source6" dataRefStart="source6">The rider stated the car had a color from the one registered in Bliss.</pc><pc id="source7" dataRefStart="source7">The rider can’t tell if the driver matched the profile picture.</pc></pc>';
        $expected_l1_segment = '<pc id="source1" dataRefStart="source1">Click the image on the left, read the information and then select the contact type that would replace the red question mark.</pc><pc id="source2" dataRefStart="source2"><pc id="source3" dataRefStart="source3">Things to consider:</pc></pc><pc id="source4" dataRefStart="source4"><pc id="source5" dataRefStart="source5">The rider stated the car had a different tag from another state.</pc><pc id="source6" dataRefStart="source6">The rider stated the car had a color from the one registered in Bliss.</pc><pc id="source7" dataRefStart="source7">The rider can’t tell if the driver matched the profile picture.</pc></pc>';
        $expected_l2_segment = '&lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:PGcgaWQ9ImxwdXhuaVFsSVczS3JVeXciIGN0eXBlPSJ4LWh0bWwtcCIgXC8+"/&gt;Click the image on the left, read the information and then select the contact type that would replace the red question mark.&lt;ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:PGcgaWQ9ImxwdXhuaVFsSVczS3JVeXciIGN0eXBlPSJ4LWh0bWwtcCIgXC8+"/&gt;&lt;ph id="source2_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UyIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTIiJmd0Ow==" dataRef="source2" equiv-text="base64:PGcgaWQ9ImQzVGxQdG9tbFV0MEVqMWsiIGN0eXBlPSJ4LWh0bWwtcCIgXC8+"/&gt;&lt;ph id="source3_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UzIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTMiJmd0Ow==" dataRef="source3" equiv-text="base64:PGcgaWQ9ImpjUC1URkZTTzJDU3N1THQiIGN0eXBlPSJ4LWh0bWwtc3Ryb25nIiBcLz4="/&gt;Things to consider:&lt;ph id="source3_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source3" equiv-text="base64:PGcgaWQ9ImpjUC1URkZTTzJDU3N1THQiIGN0eXBlPSJ4LWh0bWwtc3Ryb25nIiBcLz4="/&gt;&lt;ph id="source2_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source2" equiv-text="base64:PGcgaWQ9ImQzVGxQdG9tbFV0MEVqMWsiIGN0eXBlPSJ4LWh0bWwtcCIgXC8+"/&gt;&lt;ph id="source4_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2U0IiBkYXRhUmVmU3RhcnQ9InNvdXJjZTQiJmd0Ow==" dataRef="source4" equiv-text="base64:PGcgaWQ9IjVTdENZWVJ2cU1jMFVBejQiIGN0eXBlPSJ4LWh0bWwtdWwiIFwvPg=="/&gt;&lt;ph id="source5_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2U1IiBkYXRhUmVmU3RhcnQ9InNvdXJjZTUiJmd0Ow==" dataRef="source5" equiv-text="base64:PGcgaWQ9Ijk5cGhoSmNFUURMSEJqZVUiIGN0eXBlPSJ4LWh0bWwtbGkiIFwvPg=="/&gt;The rider stated the car had a different tag from another state.&lt;ph id="source5_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source5" equiv-text="base64:PGcgaWQ9Ijk5cGhoSmNFUURMSEJqZVUiIGN0eXBlPSJ4LWh0bWwtbGkiIFwvPg=="/&gt;&lt;ph id="source6_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2U2IiBkYXRhUmVmU3RhcnQ9InNvdXJjZTYiJmd0Ow==" dataRef="source6" equiv-text="base64:PGcgaWQ9IjBIWnVnMWQzTGtYSlUwNEUiIGN0eXBlPSJ4LWh0bWwtbGkiIFwvPg=="/&gt;The rider stated the car had a color from the one registered in Bliss.&lt;ph id="source6_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source6" equiv-text="base64:PGcgaWQ9IjBIWnVnMWQzTGtYSlUwNEUiIGN0eXBlPSJ4LWh0bWwtbGkiIFwvPg=="/&gt;&lt;ph id="source7_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2U3IiBkYXRhUmVmU3RhcnQ9InNvdXJjZTciJmd0Ow==" dataRef="source7" equiv-text="base64:PGcgaWQ9Im9aM29XXzBLYWljRlhGRFMiIGN0eXBlPSJ4LWh0bWwtbGkiIFwvPg=="/&gt;The rider can’t tell if the driver matched the profile picture.&lt;ph id="source7_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source7" equiv-text="base64:PGcgaWQ9Im9aM29XXzBLYWljRlhGRFMiIGN0eXBlPSJ4LWh0bWwtbGkiIFwvPg=="/&gt;&lt;ph id="source4_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source4" equiv-text="base64:PGcgaWQ9IjVTdENZWVJ2cU1jMFVBejQiIGN0eXBlPSJ4LWh0bWwtdWwiIFwvPg=="/&gt;';

        $l1_segment = $Filter->fromLayer0ToLayer1( $db_segment );
        $l2_segment = $Filter->fromLayer1ToLayer2( $l1_segment );

        $this->assertEquals($l1_segment, $expected_l1_segment);
        $this->assertEquals($l2_segment, $expected_l2_segment);

        $back_to_db_segment_from_l1 = $Filter->fromLayer1ToLayer0($l1_segment);

        $this->assertEquals($back_to_db_segment_from_l1, $db_segment);
    }

    public function testPCWithoutAnyDataRefMap() {
        $data_ref_map = [];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance( 'en-US','it-IT', $featureSet, 'it-IT', 'en-EN', $data_ref_map );

        $db_segment = 'Practice using <pc id="1b" type="fmt" subType="m:b">coaching frameworks</pc> and skills with peers and coaches in a safe learning environment.';
        $expected_l1_segment = 'Practice using <pc id="1b" type="fmt" subType="m:b">coaching frameworks</pc> and skills with peers and coaches in a safe learning environment.';
        $expected_l2_segment = 'Practice using &lt;ph id="mtc_u_1" equiv-text="base64:Jmx0O3BjIGlkPSIxYiIgdHlwZT0iZm10IiBzdWJUeXBlPSJtOmIiJmd0Ow=="/&gt;coaching frameworks&lt;ph id="mtc_u_2" equiv-text="base64:Jmx0Oy9wYyZndDs="/&gt; and skills with peers and coaches in a safe learning environment.';

        $l1_segment = $Filter->fromLayer0ToLayer1( $db_segment );
        $l2_segment = $Filter->fromLayer1ToLayer2( $l1_segment );

        $this->assertEquals($l1_segment, $expected_l1_segment);
        $this->assertEquals($l2_segment, $expected_l2_segment);

        $back_to_db_segment_from_l1 = $Filter->fromLayer1ToLayer0($l1_segment);

        $this->assertEquals($back_to_db_segment_from_l1, $db_segment);
    }


    public function testMostSimpleCaseOfPC() {
        $data_ref_map = [
                'd1' => '_',
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance( 'en-US','it-IT', $featureSet, 'it-IT', 'en-EN', $data_ref_map );

        $db_segment = 'Testo libero contenente <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d1" dataRefStart="d1">corsivo</pc>.';
        $db_translation = 'Free text containing <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d1" dataRefStart="d1">curvise</pc>.';
        $expected_l1_segment = 'Testo libero contenente <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d1" dataRefStart="d1">corsivo</pc>.';
        $expected_l1_translation = 'Free text containing <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d1" dataRefStart="d1">curvise</pc>.';
        $expected_l2_segment = 'Testo libero contenente &lt;ph id="1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSIxIiBjYW5Db3B5PSJubyIgY2FuRGVsZXRlPSJubyIgZGF0YVJlZkVuZD0iZDEiIGRhdGFSZWZTdGFydD0iZDEiJmd0Ow==" dataRef="d1" equiv-text="base64:Xw=="/&gt;corsivo&lt;ph id="1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="d1" equiv-text="base64:Xw=="/&gt;.';
        $expected_l2_translation = 'Free text containing &lt;ph id="1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSIxIiBjYW5Db3B5PSJubyIgY2FuRGVsZXRlPSJubyIgZGF0YVJlZkVuZD0iZDEiIGRhdGFSZWZTdGFydD0iZDEiJmd0Ow==" dataRef="d1" equiv-text="base64:Xw=="/&gt;curvise&lt;ph id="1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="d1" equiv-text="base64:Xw=="/&gt;.';

        $l1_segment     = $Filter->fromLayer0ToLayer1( $db_segment );
        $l1_translation = $Filter->fromLayer0ToLayer1( $db_translation );
        $l2_segment     = $Filter->fromLayer1ToLayer2( $l1_segment );
        $l2_translation = $Filter->fromLayer1ToLayer2( $l1_translation );

        $this->assertEquals($l1_segment, $expected_l1_segment);
        $this->assertEquals($l1_translation, $expected_l1_translation);
        $this->assertEquals($l2_segment, $expected_l2_segment);
        $this->assertEquals($l2_translation, $expected_l2_translation);

        $back_to_db_segment =$Filter->fromLayer1ToLayer0($l1_segment);
        $back_to_db_translation =$Filter->fromLayer1ToLayer0($l1_translation);

        $this->assertEquals($back_to_db_segment, $db_segment);
        $this->assertEquals($back_to_db_translation, $db_translation);
    }

    /**
     * @throws \Exception
     */
    public function testDoublePCPlaceholderWithDataRef() {
        $data_ref_map = [
                'd1' => '[',
                'd2' => '](http://repubblica.it)',
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance( 'en-US','it-IT', $featureSet, 'it-IT', 'en-EN', $data_ref_map );

        $db_segment     = 'Link semplice: <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d2" dataRefStart="d1">La Repubblica</pc>.';
        $db_translation = 'Simple link: <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d2" dataRefStart="d1">La Repubblica</pc>.';
        $expected_l1_segment = 'Link semplice: <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d2" dataRefStart="d1">La Repubblica</pc>.';
        $expected_l1_translation = 'Simple link: <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d2" dataRefStart="d1">La Repubblica</pc>.';
        $expected_l2_segment = 'Link semplice: &lt;ph id="1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSIxIiBjYW5Db3B5PSJubyIgY2FuRGVsZXRlPSJubyIgZGF0YVJlZkVuZD0iZDIiIGRhdGFSZWZTdGFydD0iZDEiJmd0Ow==" dataRef="d1" equiv-text="base64:Ww=="/&gt;La Repubblica&lt;ph id="1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="d2" equiv-text="base64:XShodHRwOi8vcmVwdWJibGljYS5pdCk="/&gt;.';
        $expected_l2_translation = 'Simple link: &lt;ph id="1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSIxIiBjYW5Db3B5PSJubyIgY2FuRGVsZXRlPSJubyIgZGF0YVJlZkVuZD0iZDIiIGRhdGFSZWZTdGFydD0iZDEiJmd0Ow==" dataRef="d1" equiv-text="base64:Ww=="/&gt;La Repubblica&lt;ph id="1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="d2" equiv-text="base64:XShodHRwOi8vcmVwdWJibGljYS5pdCk="/&gt;.';

        $l1_segment     = $Filter->fromLayer0ToLayer1( $db_segment );
        $l1_translation = $Filter->fromLayer0ToLayer1( $db_translation );
        $l2_segment     = $Filter->fromLayer1ToLayer2( $l1_segment );
        $l2_translation = $Filter->fromLayer1ToLayer2( $l1_translation );

        $this->assertEquals($l1_segment, $expected_l1_segment);
        $this->assertEquals($l1_translation, $expected_l1_translation);
        $this->assertEquals($l2_segment, $expected_l2_segment);
        $this->assertEquals($l2_translation, $expected_l2_translation);

        $back_to_db_segment =$Filter->fromLayer1ToLayer0($l1_segment);
        $back_to_db_translation =$Filter->fromLayer1ToLayer0($l1_translation);

        $this->assertEquals($back_to_db_segment, $db_segment);
        $this->assertEquals($back_to_db_translation, $db_translation);
    }

    public function testPCLayer2ToLayer0() {

        $data_ref_map = [
                'd1' => '_',
                'd2' => '**',
                'd3' => '`',
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance( 'en-US','it-IT', $featureSet, 'it-IT', 'en-EN', $data_ref_map );

        $expected_db_translation = 'Testo libero contenente <pc id="1" canCopy="no" canDelete="no" dataRefEnd="d1" dataRefStart="d1">corsivo</pc>';
        $l2_translation = 'Testo libero contenente <ph id="1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSIxIiBjYW5Db3B5PSJubyIgY2FuRGVsZXRlPSJubyIgZGF0YVJlZkVuZD0iZDEiIGRhdGFSZWZTdGFydD0iZDEiJmd0Ow==" dataRef="d1" equiv-text="base64:Xw=="/>corsivo<ph id="1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="d1" equiv-text="base64:Xw=="/>';

        $db_translation = $Filter->fromLayer2ToLayer0( $l2_translation );

        $this->assertEquals($db_translation, $expected_db_translation);
    }

    /**
     * @throws \Exception
     */
    public function testWithPCTagsWithAndWithoutDataRefInTheSameSegment() {

        $data_ref_map = [
                'source1' => 'x',
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $Filter = MateCatFilter::getInstance('en-US','it-IT', $featureSet, 'it-IT', 'en-EN', $data_ref_map );

        $db_segment = 'Text <pc id="source1" dataRefStart="source1" dataRefEnd="source1"><pc id="1u" type="fmt" subType="m:u">link</pc></pc>.';
        $db_translation = 'Testo <pc id="source1" dataRefStart="source1" dataRefEnd="source1"><pc id="1u" type="fmt" subType="m:u">link</pc></pc>.';
        $expected_l1_segment = 'Text <pc id="source1" dataRefStart="source1" dataRefEnd="source1"><pc id="1u" type="fmt" subType="m:u">link</pc></pc>.';
        $expected_l1_translation = 'Testo <pc id="source1" dataRefStart="source1" dataRefEnd="source1"><pc id="1u" type="fmt" subType="m:u">link</pc></pc>.';
        $expected_l2_segment = 'Text &lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiIGRhdGFSZWZFbmQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:eA=="/&gt;&lt;ph id="mtc_u_1" equiv-text="base64:Jmx0O3BjIGlkPSIxdSIgdHlwZT0iZm10IiBzdWJUeXBlPSJtOnUiJmd0Ow=="/&gt;link&lt;ph id="mtc_u_2" equiv-text="base64:Jmx0Oy9wYyZndDs="/&gt;&lt;ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:eA=="/&gt;.';
        $expected_l2_translation = 'Testo &lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiIGRhdGFSZWZFbmQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:eA=="/&gt;&lt;ph id="mtc_u_1" equiv-text="base64:Jmx0O3BjIGlkPSIxdSIgdHlwZT0iZm10IiBzdWJUeXBlPSJtOnUiJmd0Ow=="/&gt;link&lt;ph id="mtc_u_2" equiv-text="base64:Jmx0Oy9wYyZndDs="/&gt;&lt;ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:eA=="/&gt;.';

        $l1_segment     = $Filter->fromLayer0ToLayer1( $db_segment );
        $l1_translation = $Filter->fromLayer0ToLayer1( $db_translation );
        $l2_segment     = $Filter->fromLayer1ToLayer2( $l1_segment );
        $l2_translation = $Filter->fromLayer1ToLayer2( $l1_translation );

        $this->assertEquals($l1_segment, $expected_l1_segment);
        $this->assertEquals($l1_translation, $expected_l1_translation);
        $this->assertEquals($l2_segment, $expected_l2_segment);
        $this->assertEquals($l2_translation, $expected_l2_translation);

        $back_to_db_segment =$Filter->fromLayer1ToLayer0($l1_segment);
        $back_to_db_translation =$Filter->fromLayer1ToLayer0($l1_translation);

        $this->assertEquals($back_to_db_segment, $db_segment);
        $this->assertEquals($back_to_db_translation, $db_translation);
    }

    /**
     * @throws \Exception
     */
    public function testPCFromLayer1ToLayer2() {
        $data_ref_map = [
            'd1' => '&lt;a rel="noopener" href="https://www.forbes.com/sites/johnkoetsier/2020/06/05/the-100-safest-countries-in-the-world-for-covid-19/#3f405ed468c5" target="_blank" title="read more" data-anchor="#3f405ed468c5"&gt;',
            'd2' => '&lt;/a&gt;',
        ];

        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translated,mmt" );
        $Filter = \SubFiltering\Filter::getInstance('en-US','ca-ES', $featureSet, $data_ref_map );

        $l1_segment = '<ph id="1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="d2" equiv-text="base64:Jmx0Oy9hJmd0Ow=="/>';
        $l2_segment     = $Filter->fromLayer1ToLayer2( $l1_segment );

        $expected_l2_segment = '&lt;ph id="1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="d2" equiv-text="base64:Jmx0Oy9hJmd0Ow=="/&gt;';

        $this->assertEquals($l2_segment, $expected_l2_segment);

        $back_to_l1_segment =$Filter->fromLayer2ToLayer1($l1_segment);

        $this->assertEquals($back_to_l1_segment, $l1_segment);
    }



}