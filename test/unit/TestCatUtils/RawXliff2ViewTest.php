<?php

use Matecat\SubFiltering\MateCatFilter;

/**
 * @group  regression
 *
 * this battery of tests sends one string in input as $source_segment to CatUtils::rawXliff2view method and
 * verifies that the output is equal to the $expected_segment.
 * User: dinies
 * Date: 30/03/16
 * Time: 18.05
 */
class RawXliff2ViewTest extends AbstractTest {
    protected $source_segment;
    protected $expected_segment;


    /** @var Filter */
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

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view1() {
        $this->source_segment   = <<<LAB
<g id="1">[AH1]</g><g id="2">Is fold &amp; crease the same??</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;[AH1]&lt;/g&gt;&lt;g id="2"&gt;Is fold &amp; crease the same??&lt;/g&gt;
LAB;

        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view_2() {
        $this->source_segment   = <<<LAB
<g id="1">SIA “Bio2You”,</g><g id="2"> Reg. no</g><g id="3">40103243404, </g><g id="4">address: Ganibu Dambis 24A, Riga, Latvia ("the Franchisor")  </g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;SIA “Bio2You”,&lt;/g&gt;&lt;g id="2"&gt; Reg. no&lt;/g&gt;&lt;g id="3"&gt;40103243404, &lt;/g&gt;&lt;g id="4"&gt;address: Ganibu Dambis 24A, Riga, Latvia ("the Franchisor")&nbsp; &lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view3() {
        $this->source_segment   = <<<LAB
<g id="1">USB </g><g id="2">(to wake to your USB music)</g><g id="1">DISC </g><g id="2">(to wake to your DISC music)</g><g id="1">BUZZER </g><g id="2">(to wake to a buzzer sound)</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;USB &lt;/g&gt;&lt;g id="2"&gt;(to wake to your USB music)&lt;/g&gt;&lt;g id="1"&gt;DISC &lt;/g&gt;&lt;g id="2"&gt;(to wake to your DISC music)&lt;/g&gt;&lt;g id="1"&gt;BUZZER &lt;/g&gt;&lt;g id="2"&gt;(to wake to a buzzer sound)&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view4() {

        $this->source_segment   = <<<LAB
<g id="1">併症や </g><g id="2">QOL</g><g id="3"> 低下の観点から外科切除は行わない傾向に</g><g id="1">胃悪性リンパ腫の治療は，これまで外科的切除が積極 的に行われてきたが，最近では胃温存療法が外科的切除 に劣らない治療成績を示し</g><g id="2">1)</g><g id="3">，外科的切除に伴う術後合</g><g id="2">考</g><g id="3"></g><g id="4">察</g><g id="1">Antecolic gastrojejunostomy with a braun anastomosi</g><g id="2">8)</g><g id="3">.</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;併症や &lt;/g&gt;&lt;g id="2"&gt;QOL&lt;/g&gt;&lt;g id="3"&gt; 低下の観点から外科切除は行わない傾向に&lt;/g&gt;&lt;g id="1"&gt;胃悪性リンパ腫の治療は，これまで外科的切除が積極 的に行われてきたが，最近では胃温存療法が外科的切除 に劣らない治療成績を示し&lt;/g&gt;&lt;g id="2"&gt;1)&lt;/g&gt;&lt;g id="3"&gt;，外科的切除に伴う術後合&lt;/g&gt;&lt;g id="2"&gt;考&lt;/g&gt;&lt;g id="3"&gt;&lt;/g&gt;&lt;g id="4"&gt;察&lt;/g&gt;&lt;g id="1"&gt;Antecolic gastrojejunostomy with a braun anastomosi&lt;/g&gt;&lt;g id="2"&gt;8)&lt;/g&gt;&lt;g id="3"&gt;.&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view5() {
        $this->source_segment   = <<<LAB
<g id="1">入院時検査所見</g><g id="2">: TP 5.7 mg</g><g id="3">／</g><g id="4">dL</g><g id="5">，</g><g id="6">Alb</g><g id="7"> </g><g id="8">2.9 mg</g><g id="9">／</g><g id="10">dL</g><g id="11"> と低</g><g id="1">入院時現症</g><g id="2">:</g><g id="3"> 腹部に明らかな腫瘤は触れず，表在リン</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;入院時検査所見&lt;/g&gt;&lt;g id="2"&gt;: TP 5.7 mg&lt;/g&gt;&lt;g id="3"&gt;／&lt;/g&gt;&lt;g id="4"&gt;dL&lt;/g&gt;&lt;g id="5"&gt;，&lt;/g&gt;&lt;g id="6"&gt;Alb&lt;/g&gt;&lt;g id="7"&gt; &lt;/g&gt;&lt;g id="8"&gt;2.9 mg&lt;/g&gt;&lt;g id="9"&gt;／&lt;/g&gt;&lt;g id="10"&gt;dL&lt;/g&gt;&lt;g id="11"&gt; と低&lt;/g&gt;&lt;g id="1"&gt;入院時現症&lt;/g&gt;&lt;g id="2"&gt;:&lt;/g&gt;&lt;g id="3"&gt; 腹部に明らかな腫瘤は触れず，表在リン&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view6() {
        $this->source_segment   = <<<LAB
<g id="1">[0065] </g><g id="2">y</g><g id="3">1</g><g id="4">(</g><g id="5">z</g><g id="6">O</g><g id="7">, t</g><g id="8">m</g><g id="9">) </g><g id="10">= min</g><g id="11">[</g><g id="12">y</g><g id="13">1</g><g id="14">(</g><g id="15">z, t</g><g id="16">m</g><g id="17">)]</g><g id="18">;             </g><g id="19">0 : : : z ::: L                                              </g><g id="20">(Equation 16)</g>
LAB;
        $this->expected_segment = '&lt;g id="1"&gt;[0065] &lt;/g&gt;&lt;g id="2"&gt;y&lt;/g&gt;&lt;g id="3"&gt;1&lt;/g&gt;&lt;g id="4"&gt;(&lt;/g&gt;&lt;g id="5"&gt;z&lt;/g&gt;&lt;g id="6"&gt;O&lt;/g&gt;&lt;g id="7"&gt;, t&lt;/g&gt;&lt;g id="8"&gt;m&lt;/g&gt;&lt;g id="9"&gt;) &lt;/g&gt;&lt;g id="10"&gt;= min&lt;/g&gt;&lt;g id="11"&gt;[&lt;/g&gt;&lt;g id="12"&gt;y&lt;/g&gt;&lt;g id="13"&gt;1&lt;/g&gt;&lt;g id="14"&gt;(&lt;/g&gt;&lt;g id="15"&gt;z, t&lt;/g&gt;&lt;g id="16"&gt;m&lt;/g&gt;&lt;g id="17"&gt;)]&lt;/g&gt;&lt;g id="18"&gt;;&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;  &lt;/g&gt;&lt;g id="19"&gt;0 : : : z ::: L&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &lt;/g&gt;&lt;g id="20"&gt;(Equation 16)&lt;/g&gt;';
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view7() {
        $this->source_segment   = <<<LAB
<g id="1">•••••••••</g><g id="2"> EMILIA-ROMAGNA</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;•••••••••&lt;/g&gt;&lt;g id="2"&gt; EMILIA-ROMAGNA&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view8() {
        $this->source_segment   = <<<LAB
<g id="1">lip = </g><g id="2">0 :   </g><g id="3">: <g id="4">lip = </g></g><g id="5">0</g><g id="1">c:::::&gt;</g><g id="2">  200</g><g id="1">\ </g><g id="2">FRONT</g><g id="2">v·    </g><g id="3">• . .</g>
LAB;
        $this->expected_segment = '&lt;g id="1"&gt;lip = &lt;/g&gt;&lt;g id="2"&gt;0 :&nbsp;  &lt;/g&gt;&lt;g id="3"&gt;: &lt;g id="4"&gt;lip = &lt;/g&gt;&lt;/g&gt;&lt;g id="5"&gt;0&lt;/g&gt;&lt;g id="1"&gt;c:::::&gt;&lt;/g&gt;&lt;g id="2"&gt;&nbsp; 200&lt;/g&gt;&lt;g id="1"&gt;\ &lt;/g&gt;&lt;g id="2"&gt;FRONT&lt;/g&gt;&lt;g id="2"&gt;v·&nbsp; &nbsp; &lt;/g&gt;&lt;g id="3"&gt;• . .&lt;/g&gt;';
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view9() {
        $this->source_segment   = <<<LAB
In certain embodiments, the value of <g id="2">E </g>may vary or be determined by a user.
LAB;
        $this->expected_segment = <<<LAB
In certain embodiments, the value of &lt;g id="2"&gt;E &lt;/g&gt;may vary or be determined by a user.
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view10() {
        $this->source_segment   = <<<LAB
<g id="2">L  <g id="3">0</g></g>, and <g id="4">C </g>is the orifice flow coefficient.
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="2"&gt;L&nbsp; &lt;g id="3"&gt;0&lt;/g&gt;&lt;/g&gt;, and &lt;g id="4"&gt;C &lt;/g&gt;is the orifice flow coefficient.
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view11() {
        $this->source_segment   = <<<LAB
リストン鉗子をかけた大弯口側端へ，<ex id="1"/><g id="2">Bill-roth </g><bx id="3"/>Ⅱ法に準じて胃空腸端側吻合を行った。
LAB;
        $this->expected_segment = <<<LAB
リストン鉗子をかけた大弯口側端へ，&lt;ex id="1"/&gt;&lt;g id="2"&gt;Bill-roth &lt;/g&gt;&lt;bx id="3"/&gt;Ⅱ法に準じて胃空腸端側吻合を行った。
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view12() {
        $this->source_segment   = <<<LAB
<g id="1">R-CHOP </g><g id="2">療法中に幽門部狭窄を来し胃空腸バイパス術を施行した</g><g id="3"> </g><g id="4">胃原発 </g><g id="5">Diffuse Large B-Cell Lymphoma</g><g id="6"> の </g><g id="7">1</g><g id="8"> 例</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;R-CHOP &lt;/g&gt;&lt;g id="2"&gt;療法中に幽門部狭窄を来し胃空腸バイパス術を施行した&lt;/g&gt;&lt;g id="3"&gt; &lt;/g&gt;&lt;g id="4"&gt;胃原発 &lt;/g&gt;&lt;g id="5"&gt;Diffuse Large B-Cell Lymphoma&lt;/g&gt;&lt;g id="6"&gt; の &lt;/g&gt;&lt;g id="7"&gt;1&lt;/g&gt;&lt;g id="8"&gt; 例&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view13() {
        $this->source_segment   = <<<LAB
<g id="1">•••••••••</g><g id="2"> EMILIA-ROMAGNA</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;•••••••••&lt;/g&gt;&lt;g id="2"&gt; EMILIA-ROMAGNA&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view14() {
        $this->source_segment   = <<<LAB
<g id="1">[0054] </g><g id="2">y<g id="3">(</g>z</g><g id="4">1</g><g id="5">, t</g><g id="6">m</g><g id="7">) </g><g id="8">= d - r</g><g id="9">O                                                                                                                      </g><g id="10">(Equation 11)</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;[0054] &lt;/g&gt;&lt;g id="2"&gt;y&lt;g id="3"&gt;(&lt;/g&gt;z&lt;/g&gt;&lt;g id="4"&gt;1&lt;/g&gt;&lt;g id="5"&gt;, t&lt;/g&gt;&lt;g id="6"&gt;m&lt;/g&gt;&lt;g id="7"&gt;) &lt;/g&gt;&lt;g id="8"&gt;= d - r&lt;/g&gt;&lt;g id="9"&gt;O&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &lt;/g&gt;&lt;g id="10"&gt;(Equation 11)&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view15() {
        $this->source_segment   = <<<LAB
In such case Franch<ex id="1"/><bx id="2"/>isor receives back its all instalments, furniture and goods at cost of Franchisee, and the Franchisee must cover all unearned profit of the Franchisor.
LAB;
        $this->expected_segment = <<<LAB
In such case Franch&lt;ex id="1"/&gt;&lt;bx id="2"/&gt;isor receives back its all instalments, furniture and goods at cost of Franchisee, and the Franchisee must cover all unearned profit of the Franchisor.
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view16() {
        $this->source_segment   = <<<LAB
<g id="1">9.4</g><g id="2"> On expiry of this Agreement all licences referred to in this Clause 9 shall expire and the Franchisee agrees to immediately cease use of all of the Franchisor's intellectual property.</g><g id="1">9.3</g><g id="2"> This Agreement does not convey or transfer to the Franchisee any ownership or interest in any intellectual prop</g><g id="3">erty owned by the Franchisor.</g><g id="1">9.2</g><g id="2"> The Trade Mark shall not be used</g><g id="3"> in any manner liable to invalidate the registration of the Trade Mark and the Franchisee shall not permit them to be used by third parties.</g><g id="1">9.1</g><g id="2"> The Franchisor grants to the Franchisee licence in the Territory to use its logos, trade marks, service marks, trade names, literature, copyrights, database rights and patents subject to the restrictions in Clause 9.2.</g><g id="1">8.3. provide Franchisor with </g><g id="2">daily, weekly and monthly reports.</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;9.4&lt;/g&gt;&lt;g id="2"&gt; On expiry of this Agreement all licences referred to in this Clause 9 shall expire and the Franchisee agrees to immediately cease use of all of the Franchisor's intellectual property.&lt;/g&gt;&lt;g id="1"&gt;9.3&lt;/g&gt;&lt;g id="2"&gt; This Agreement does not convey or transfer to the Franchisee any ownership or interest in any intellectual prop&lt;/g&gt;&lt;g id="3"&gt;erty owned by the Franchisor.&lt;/g&gt;&lt;g id="1"&gt;9.2&lt;/g&gt;&lt;g id="2"&gt; The Trade Mark shall not be used&lt;/g&gt;&lt;g id="3"&gt; in any manner liable to invalidate the registration of the Trade Mark and the Franchisee shall not permit them to be used by third parties.&lt;/g&gt;&lt;g id="1"&gt;9.1&lt;/g&gt;&lt;g id="2"&gt; The Franchisor grants to the Franchisee licence in the Territory to use its logos, trade marks, service marks, trade names, literature, copyrights, database rights and patents subject to the restrictions in Clause 9.2.&lt;/g&gt;&lt;g id="1"&gt;8.3. provide Franchisor with &lt;/g&gt;&lt;g id="2"&gt;daily, weekly and monthly reports.&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view17() {
        $this->source_segment   = <<<LAB
A tale scopo verrà implementato il programma custom “<g id="2">Rilascio Massivo Contratti Migrati E4E</g>” <ex id="1"/><g id="3">(</g><g id="4">ZMM4R_IT_P_RILFDM</g>) che imposterà l’<bx id="5"/>“Indicatore di Rilascio” (EKKO-FRGKE) = 3 (PO Non Changeable) per tutti i Documenti di acquisto indicati in un file di <g id="6">Input</g> in formato Excel da specificare in Selection Screen (vd.
LAB;
        $this->expected_segment = <<<LAB
A tale scopo verrà implementato il programma custom “&lt;g id="2"&gt;Rilascio Massivo Contratti Migrati E4E&lt;/g&gt;” &lt;ex id="1"/&gt;&lt;g id="3"&gt;(&lt;/g&gt;&lt;g id="4"&gt;ZMM4R_IT_P_RILFDM&lt;/g&gt;) che imposterà l’&lt;bx id="5"/&gt;“Indicatore di Rilascio” (EKKO-FRGKE) = 3 (PO Non Changeable) per tutti i Documenti di acquisto indicati in un file di &lt;g id="6"&gt;Input&lt;/g&gt; in formato Excel da specificare in Selection Screen (vd.
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view18() {
        $this->source_segment   = <<<LAB
<g id="1">总之，通过对</g><g id="2">2012-2015年间美企所中国军情研究的统计和特点分析，可以做出以下判断：美企所是保守主义思想浓</g><bx id="3"/>厚的智库，对中国军事力量的正常发展观点激进，态度偏激；美企所近年来中国军情研究主要聚焦在南海、东海等海洋领土争端问题上；美企所提出的诸如加强“航行自由”、联盟体系的建议在美国政府的政策举措上有所表现。<g id="2">从上文</g><g id="3">对26篇文章的内容简述，可以清晰地看出，美企所非常关注中国海空军力的发展，并以此作为加强美军在亚太地区军力部署、更新作战概念、增加军费预算的理由。</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;总之，通过对&lt;/g&gt;&lt;g id="2"&gt;2012-2015年间美企所中国军情研究的统计和特点分析，可以做出以下判断：美企所是保守主义思想浓&lt;/g&gt;&lt;bx id="3"/&gt;厚的智库，对中国军事力量的正常发展观点激进，态度偏激；美企所近年来中国军情研究主要聚焦在南海、东海等海洋领土争端问题上；美企所提出的诸如加强“航行自由”、联盟体系的建议在美国政府的政策举措上有所表现。&lt;g id="2"&gt;从上文&lt;/g&gt;&lt;g id="3"&gt;对26篇文章的内容简述，可以清晰地看出，美企所非常关注中国海空军力的发展，并以此作为加强美军在亚太地区军力部署、更新作战概念、增加军费预算的理由。&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view19() {
        $this->source_segment   = <<<LAB
</g>
<g id="1">me@GW: Hoa aus Vietnam</g><g id="2">
Ihr wahrgewordener Traum, und wie sie ihr Lieblingsfach in den Arbeitsalltag integriert.

<x id="3"/>
</g>
LAB;
        $this->expected_segment = <<<'LAB'
&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;me@GW: Hoa aus Vietnam&lt;/g&gt;&lt;g id="2"&gt;##$_0A$##Ihr wahrgewordener Traum, und wie sie ihr Lieblingsfach in den Arbeitsalltag integriert.##$_0A$####$_0A$##&lt;x id="3"/&gt;##$_0A$##&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }

    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view20() {
        $this->source_segment   = <<<LAB
<g 𐎆 𐏉</g>
LAB;
        $this->expected_segment = <<<LAB
&amp;lt;g &#66438; &#66505;&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }


    /**
     * @group  regression
     *
     */
    public function test_raw_X_liff2view21() {
        $this->source_segment   = <<<LAB
<g id="1">ψ</g>😴<g 😆id="2">🛠λ</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;ψ&lt;/g&gt;&#128564;&amp;lt;g &#128518;id="2"&amp;gt;&#128736;λ&lt;/g&gt;
LAB;
        $this->assertEquals( $this->expected_segment, $this->filter->fromLayer0ToLayer2( $this->source_segment ) );
    }


}


