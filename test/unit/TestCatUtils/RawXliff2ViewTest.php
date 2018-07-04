<?php

/**
 * @group regression
 * @covers CatUtils::rawXliff2view
 * this battery of tests sends one string in input as $source_segment to CatUtils::rawXliff2view method and
 * verifies that the output is equal to the $expected_segment.
 * User: dinies
 * Date: 30/03/16
 * Time: 18.05
 */
class RawXliff2ViewTest extends AbstractTest
{
    protected $source_segment;
    protected $expected_segment;
    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view1()
    {
        $this->source_segment = <<<LAB
<g id="1">[AH1]</g><g id="2">Is fold &amp; crease the same??</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;[AH1]&lt;/g&gt;&lt;g id="2"&gt;Is fold & crease the same??&lt;/g&gt;
LAB;

        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view_2()
    {
        $this->source_segment = <<<LAB
<g id="1">SIA “Bio2You”,</g><g id="2"> Reg. no</g><g id="3">40103243404, </g><g id="4">address: Ganibu Dambis 24A, Riga, Latvia  ("the Franchisor")  </g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;SIA “Bio2You”,&lt;/g&gt;&lt;g id="2"&gt; Reg. no&lt;/g&gt;&lt;g id="3"&gt;40103243404, &lt;/g&gt;&lt;g id="4"&gt;address: Ganibu Dambis 24A, Riga, Latvia &nbsp;("the Franchisor") &nbsp;&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view3()
    {
        $this->source_segment = <<<LAB
<g id="1">USB </g><g id="2">(to wake to your USB music)</g><g id="1">DISC </g><g id="2">(to wake to your DISC music)</g><g id="1">BUZZER </g><g id="2">(to wake to a buzzer sound)</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;USB &lt;/g&gt;&lt;g id="2"&gt;(to wake to your USB music)&lt;/g&gt;&lt;g id="1"&gt;DISC &lt;/g&gt;&lt;g id="2"&gt;(to wake to your DISC music)&lt;/g&gt;&lt;g id="1"&gt;BUZZER &lt;/g&gt;&lt;g id="2"&gt;(to wake to a buzzer sound)&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view4()
    {

        $this->source_segment = <<<LAB
<g id="1">併症や </g><g id="2">QOL</g><g id="3"> 低下の観点から外科切除は行わない傾向に</g><g id="1">胃悪性リンパ腫の治療は，これまで外科的切除が積極 的に行われてきたが，最近では胃温存療法が外科的切除 に劣らない治療成績を示し</g><g id="2">1)</g><g id="3">，外科的切除に伴う術後合</g><g id="2">考</g><g id="3">   </g><g id="4">察</g><g id="1">Antecolic gastrojejunostomy with a braun anastomosi</g><g id="2">8)</g><g id="3">.</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;併症や &lt;/g&gt;&lt;g id="2"&gt;QOL&lt;/g&gt;&lt;g id="3"&gt; 低下の観点から外科切除は行わない傾向に&lt;/g&gt;&lt;g id="1"&gt;胃悪性リンパ腫の治療は，これまで外科的切除が積極 的に行われてきたが，最近では胃温存療法が外科的切除 に劣らない治療成績を示し&lt;/g&gt;&lt;g id="2"&gt;1)&lt;/g&gt;&lt;g id="3"&gt;，外科的切除に伴う術後合&lt;/g&gt;&lt;g id="2"&gt;考&lt;/g&gt;&lt;g id="3"&gt; &nbsp; &lt;/g&gt;&lt;g id="4"&gt;察&lt;/g&gt;&lt;g id="1"&gt;Antecolic gastrojejunostomy with a braun anastomosi&lt;/g&gt;&lt;g id="2"&gt;8)&lt;/g&gt;&lt;g id="3"&gt;.&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view5()
    {
        $this->source_segment = <<<LAB
<g id="1">入院時検査所見</g><g id="2">: TP 5.7 mg</g><g id="3">／</g><g id="4">dL</g><g id="5">，</g><g id="6">Alb</g><g id="7"> </g><g id="8">2.9 mg</g><g id="9">／</g><g id="10">dL</g><g id="11"> と低</g><g id="1">入院時現症</g><g id="2">:</g><g id="3"> 腹部に明らかな腫瘤は触れず，表在リン</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;入院時検査所見&lt;/g&gt;&lt;g id="2"&gt;: TP 5.7 mg&lt;/g&gt;&lt;g id="3"&gt;／&lt;/g&gt;&lt;g id="4"&gt;dL&lt;/g&gt;&lt;g id="5"&gt;，&lt;/g&gt;&lt;g id="6"&gt;Alb&lt;/g&gt;&lt;g id="7"&gt; &lt;/g&gt;&lt;g id="8"&gt;2.9 mg&lt;/g&gt;&lt;g id="9"&gt;／&lt;/g&gt;&lt;g id="10"&gt;dL&lt;/g&gt;&lt;g id="11"&gt; と低&lt;/g&gt;&lt;g id="1"&gt;入院時現症&lt;/g&gt;&lt;g id="2"&gt;:&lt;/g&gt;&lt;g id="3"&gt; 腹部に明らかな腫瘤は触れず，表在リン&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view6()
    {
        $this->source_segment = <<<LAB
<g id="1">[0065] </g><g id="2">y</g><g id="3">1</g><g id="4">(</g><g id="5">z</g><g id="6">O</g><g id="7">, t</g><g id="8">m</g><g id="9">) </g><g id="10">= min</g><g id="11">[</g><g id="12">y</g><g id="13">1</g><g id="14">(</g><g id="15">z, t</g><g id="16">m</g><g id="17">)]</g><g id="18">;             </g><g id="19">0 : : : z ::: L                                              </g><g id="20">(Equation 16)</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;[0065] &lt;/g&gt;&lt;g id="2"&gt;y&lt;/g&gt;&lt;g id="3"&gt;1&lt;/g&gt;&lt;g id="4"&gt;(&lt;/g&gt;&lt;g id="5"&gt;z&lt;/g&gt;&lt;g id="6"&gt;O&lt;/g&gt;&lt;g id="7"&gt;, t&lt;/g&gt;&lt;g id="8"&gt;m&lt;/g&gt;&lt;g id="9"&gt;) &lt;/g&gt;&lt;g id="10"&gt;= min&lt;/g&gt;&lt;g id="11"&gt;[&lt;/g&gt;&lt;g id="12"&gt;y&lt;/g&gt;&lt;g id="13"&gt;1&lt;/g&gt;&lt;g id="14"&gt;(&lt;/g&gt;&lt;g id="15"&gt;z, t&lt;/g&gt;&lt;g id="16"&gt;m&lt;/g&gt;&lt;g id="17"&gt;)]&lt;/g&gt;&lt;g id="18"&gt;; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &lt;/g&gt;&lt;g id="19"&gt;0 : : : z ::: L &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&lt;/g&gt;&lt;g id="20"&gt;(Equation 16)&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view7()
    {
        $this->source_segment = <<<LAB
<g id="1">•••••••••</g><g id="2"> EMILIA-ROMAGNA</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;•••••••••&lt;/g&gt;&lt;g id="2"&gt; EMILIA-ROMAGNA&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view8()
    {
        $this->source_segment = <<<LAB
<g id="1">lip = </g><g id="2">0 :   </g><g id="3">: <g id="4">lip = </g></g><g id="5">0</g><g id="1">c:::::&gt;</g><g id="2">  200</g><g id="1">\ </g><g id="2">FRONT</g><g id="2">v·    </g><g id="3">• . .</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;lip = &lt;/g&gt;&lt;g id="2"&gt;0 : &nbsp; &lt;/g&gt;&lt;g id="3"&gt;: &lt;g id="4"&gt;lip = &lt;/g&gt;&lt;/g&gt;&lt;g id="5"&gt;0&lt;/g&gt;&lt;g id="1"&gt;c:::::&gt;&lt;/g&gt;&lt;g id="2"&gt; &nbsp;200&lt;/g&gt;&lt;g id="1"&gt;\ &lt;/g&gt;&lt;g id="2"&gt;FRONT&lt;/g&gt;&lt;g id="2"&gt;v· &nbsp; &nbsp;&lt;/g&gt;&lt;g id="3"&gt;• . .&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view9()
    {
        $this->source_segment = <<<LAB
In certain embodiments, the value of <g id="2">E </g>may vary or be determined by a user.
LAB;
        $this->expected_segment = <<<LAB
In certain embodiments, the value of &lt;g id="2"&gt;E &lt;/g&gt;may vary or be determined by a user.
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view10()
    {
        $this->source_segment = <<<LAB
<g id="2">L  <g id="3">0</g></g>, and <g id="4">C </g>is the orifice flow coefficient.
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="2"&gt;L &nbsp;&lt;g id="3"&gt;0&lt;/g&gt;&lt;/g&gt;, and &lt;g id="4"&gt;C &lt;/g&gt;is the orifice flow coefficient.
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view11()
    {
        $this->source_segment = <<<LAB
リストン鉗子をかけた大弯口側端へ，<ex id="1"/><g id="2">Bill-roth </g><bx id="3"/>Ⅱ法に準じて胃空腸端側吻合を行った。
LAB;
        $this->expected_segment = <<<LAB
リストン鉗子をかけた大弯口側端へ，&lt;ex id="1"/&gt;&lt;g id="2"&gt;Bill-roth &lt;/g&gt;&lt;bx id="3"/&gt;Ⅱ法に準じて胃空腸端側吻合を行った。
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view12()
    {
        $this->source_segment = <<<LAB
<g id="1">R-CHOP </g><g id="2">療法中に幽門部狭窄を来し胃空腸バイパス術を施行した</g><g id="3"> </g><g id="4">胃原発 </g><g id="5">Diffuse Large B-Cell Lymphoma</g><g id="6"> の </g><g id="7">1</g><g id="8"> 例</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;R-CHOP &lt;/g&gt;&lt;g id="2"&gt;療法中に幽門部狭窄を来し胃空腸バイパス術を施行した&lt;/g&gt;&lt;g id="3"&gt; &lt;/g&gt;&lt;g id="4"&gt;胃原発 &lt;/g&gt;&lt;g id="5"&gt;Diffuse Large B-Cell Lymphoma&lt;/g&gt;&lt;g id="6"&gt; の &lt;/g&gt;&lt;g id="7"&gt;1&lt;/g&gt;&lt;g id="8"&gt; 例&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view13()
    {
        $this->source_segment = <<<LAB
<g id="1">•••••••••</g><g id="2"> EMILIA-ROMAGNA</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;•••••••••&lt;/g&gt;&lt;g id="2"&gt; EMILIA-ROMAGNA&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view14()
    {
        $this->source_segment = <<<LAB
<g id="1">[0054] </g><g id="2">y<g id="3">(</g>z</g><g id="4">1</g><g id="5">, t</g><g id="6">m</g><g id="7">) </g><g id="8">= d - r</g><g id="9">O                                                                                                                      </g><g id="10">(Equation 11)</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;[0054] &lt;/g&gt;&lt;g id="2"&gt;y&lt;g id="3"&gt;(&lt;/g&gt;z&lt;/g&gt;&lt;g id="4"&gt;1&lt;/g&gt;&lt;g id="5"&gt;, t&lt;/g&gt;&lt;g id="6"&gt;m&lt;/g&gt;&lt;g id="7"&gt;) &lt;/g&gt;&lt;g id="8"&gt;= d - r&lt;/g&gt;&lt;g id="9"&gt;O &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&lt;/g&gt;&lt;g id="10"&gt;(Equation 11)&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view15()
    {
        $this->source_segment = <<<LAB
In such case Franch<ex id="1"/><bx id="2"/>isor receives back its all instalments, furniture and goods at cost of Franchisee, and the Franchisee must cover all unearned profit of the Franchisor.
LAB;
        $this->expected_segment = <<<LAB
In such case Franch&lt;ex id="1"/&gt;&lt;bx id="2"/&gt;isor receives back its all instalments, furniture and goods at cost of Franchisee, and the Franchisee must cover all unearned profit of the Franchisor.
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view16()
    {
        $this->source_segment = <<<LAB
<g id="1">9.4</g><g id="2"> On expiry of this Agreement all licences referred to in this Clause 9 shall expire and the Franchisee agrees to immediately cease use of all of the Franchisor's intellectual property.</g><g id="1">9.3</g><g id="2"> This Agreement does not convey or transfer to the Franchisee any ownership or interest in any intellectual prop</g><g id="3">erty owned by the Franchisor.</g><g id="1">9.2</g><g id="2"> The Trade Mark shall not be used</g><g id="3"> in any manner liable to invalidate the registration of the Trade Mark and the Franchisee shall not permit them to be used by third parties.</g><g id="1">9.1</g><g id="2"> The Franchisor grants to the Franchisee licence in the Territory to use its logos, trade marks, service marks, trade names, literature, copyrights, database rights and patents subject to the restrictions in Clause 9.2.</g><g id="1">8.3. provide Franchisor with </g><g id="2">daily, weekly and monthly reports.</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;9.4&lt;/g&gt;&lt;g id="2"&gt; On expiry of this Agreement all licences referred to in this Clause 9 shall expire and the Franchisee agrees to immediately cease use of all of the Franchisor's intellectual property.&lt;/g&gt;&lt;g id="1"&gt;9.3&lt;/g&gt;&lt;g id="2"&gt; This Agreement does not convey or transfer to the Franchisee any ownership or interest in any intellectual prop&lt;/g&gt;&lt;g id="3"&gt;erty owned by the Franchisor.&lt;/g&gt;&lt;g id="1"&gt;9.2&lt;/g&gt;&lt;g id="2"&gt; The Trade Mark shall not be used&lt;/g&gt;&lt;g id="3"&gt; in any manner liable to invalidate the registration of the Trade Mark and the Franchisee shall not permit them to be used by third parties.&lt;/g&gt;&lt;g id="1"&gt;9.1&lt;/g&gt;&lt;g id="2"&gt; The Franchisor grants to the Franchisee licence in the Territory to use its logos, trade marks, service marks, trade names, literature, copyrights, database rights and patents subject to the restrictions in Clause 9.2.&lt;/g&gt;&lt;g id="1"&gt;8.3. provide Franchisor with &lt;/g&gt;&lt;g id="2"&gt;daily, weekly and monthly reports.&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view17()
    {
        $this->source_segment = <<<LAB
A tale scopo verrà implementato il programma custom “<g id="2">Rilascio Massivo Contratti Migrati E4E</g>” <ex id="1"/><g id="3">(</g><g id="4">ZMM4R_IT_P_RILFDM</g>) che imposterà l’<bx id="5"/>“Indicatore di Rilascio” (EKKO-FRGKE) = 3 (PO Non Changeable) per tutti i Documenti di acquisto indicati in un file di <g id="6">Input</g> in formato Excel da specificare in Selection Screen (vd.
LAB;
        $this->expected_segment = <<<LAB
A tale scopo verrà implementato il programma custom “&lt;g id="2"&gt;Rilascio Massivo Contratti Migrati E4E&lt;/g&gt;” &lt;ex id="1"/&gt;&lt;g id="3"&gt;(&lt;/g&gt;&lt;g id="4"&gt;ZMM4R_IT_P_RILFDM&lt;/g&gt;) che imposterà l’&lt;bx id="5"/&gt;“Indicatore di Rilascio” (EKKO-FRGKE) = 3 (PO Non Changeable) per tutti i Documenti di acquisto indicati in un file di &lt;g id="6"&gt;Input&lt;/g&gt; in formato Excel da specificare in Selection Screen (vd.
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view18()
    {
        $this->source_segment = <<<LAB
<g id="1">总之，通过对</g><g id="2">2012-2015年间美企所中国军情研究的统计和特点分析，可以做出以下判断：美企所是保守主义思想浓</g><bx id="3"/>厚的智库，对中国军事力量的正常发展观点激进，态度偏激；美企所近年来中国军情研究主要聚焦在南海、东海等海洋领土争端问题上；美企所提出的诸如加强“航行自由”、联盟体系的建议在美国政府的政策举措上有所表现。<g id="2">从上文</g><g id="3">对26篇文章的内容简述，可以清晰地看出，美企所非常关注中国海空军力的发展，并以此作为加强美军在亚太地区军力部署、更新作战概念、增加军费预算的理由。</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;总之，通过对&lt;/g&gt;&lt;g id="2"&gt;2012-2015年间美企所中国军情研究的统计和特点分析，可以做出以下判断：美企所是保守主义思想浓&lt;/g&gt;&lt;bx id="3"/&gt;厚的智库，对中国军事力量的正常发展观点激进，态度偏激；美企所近年来中国军情研究主要聚焦在南海、东海等海洋领土争端问题上；美企所提出的诸如加强“航行自由”、联盟体系的建议在美国政府的政策举措上有所表现。&lt;g id="2"&gt;从上文&lt;/g&gt;&lt;g id="3"&gt;对26篇文章的内容简述，可以清晰地看出，美企所非常关注中国海空军力的发展，并以此作为加强美军在亚太地区军力部署、更新作战概念、增加军费预算的理由。&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view19()
    {
        $this->source_segment = <<<LAB
</g>
<g id="1">me@GW: Hoa aus Vietnam</g><g id="2">
Ihr wahrgewordener Traum, und wie sie ihr Lieblingsfach in den Arbeitsalltag integriert.

<x id="3"/>
</g>
LAB;
        $this->expected_segment = <<<'LAB'
&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;me@GW: Hoa aus Vietnam&lt;/g&gt;&lt;g id="2"&gt;##$_0A$##Ihr wahrgewordener Traum, und wie sie ihr Lieblingsfach in den Arbeitsalltag integriert.##$_0A$####$_0A$##&lt;x id="3"/&gt;##$_0A$##&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view20()
    {
        $this->source_segment = <<<LAB
<g 𐎆 𐏉</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g &#66438; &#66505;&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }


    /**
     * @group regression
     * @covers CatUtils::rawXliff2view
     */
    public function test_raw_X_liff2view21()
    {
        $this->source_segment = <<<LAB
<g id="1">ψ</g>😴<g 😆id="2">🛠λ</g>
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;ψ&lt;/g&gt;&#128564;&lt;g &#128518;id="2"&gt;&#128736;λ&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }

    public function test_raw_X_liff2view22()
    {
        $this->source_segment = <<<'LAB'
<g id="2">sIL-2R</g><g id="3"> は </g><g id="4">447</g><g id="5"> </g><g id="6">U</g><g id="7">／</g>
<g id="1">入院時検査所見</g><g id="2">: TP 5.7 mg</g><g id="3">／</g><g id="4">dL</g><g id="5">，</g><g id="6">Alb</g><g id="7"> </g><g id="8">2.9 mg</g><g id="9">／</g><g id="10">dL</g><g id="11"> と低</g>
<g id="1">入院時現症</g><g id="2">:</g><g id="3"> 腹部に明らかな腫瘤は触れず，表在リン</g>
<g id="1">既往歴</g><g id="2">:</g><g id="3"> 糖尿病，脂質異常症。</g>
<g id="1">Fig. 2  </g><g id="2">After<g id="3"> </g>two cycle of R-CHOP chemotherapy</g>
<g id="1">Fig. 1  </g><g id="2">Radiological findings on arrival</g>
<g id="1">連絡先</g><g id="2">:</g><g id="3"> 〒 </g><g id="4">515-8566</g><g id="5"> 松阪市川井町字小望 </g><g id="6">102</g><g id="7"> 松阪中央総合病院・内科 玉井 康将</g>
<g id="1">＊1</g><g id="2"> 松阪中央病院・内科 </g><g id="3">＊2</g><g id="4"> 三重大学医学系研究科・血液・腫瘍内科学</g>
し 現病歴<ex id="1"/><g id="2">:</g><g id="3"> 胃部不快感にて近医受診し，上部消化管内視 かし，化学療法の効果により出血，穿孔，消化管閉塞と 鏡検査で体下部小弯から幽門輪に広がる巨大な不整潰瘍 いった重篤な合併症が生じた際は手術適応になる場合が を認め，精査加療目的で当院入院となった。</g>
的切除に劣らない治療成績を示し，手術の危険性，<ex id="5"/><g id="6">QOL</g><g id="7"> 主訴</g><g id="8">:</g><g id="9"> 胃部不快感。</g>
<g id="1">（</g><g id="2">DLBCL</g><g id="3">）に対する治療法は，外科的切除に加えて術後 化学療法が行われていたが，最近では胃温存療法が外科 患者</g><g id="4">: 59</g><bx id="5"/> 歳，男性。
<g id="2">症</g><g id="3">   </g><g id="4">例</g>
<g id="1">限局期限局胃原発 </g><g id="2">diffuse large B-cell lymphoma</g>
<g id="1">果が得られた </g><g id="2">1</g><g id="3"> 例を経験したので報告する。</g>
<g id="1">胃空腸バイパス術を行い，</g><g id="2">QOL</g><g id="3"> を損なわずに良好な結</g>
化学療法により通過障害を来しても胃空腸バイパス術にて経口で栄養状態を維持し，<ex id="27"/><g id="28">QOL</g><g id="29"> を損なわずに化学療法を継続 することができた症例を経験したので報告する。</g>
胃切除後の化学療法は <ex id="25"/><g id="26">QOL</g><bx id="27"/> を低下させ る。
評価の <ex id="21"/><g id="22">CT</g><g id="23">，</g><g id="24">PET-CT</g><bx id="25"/> では完全寛解であった。
手術後，<ex id="19"/><g id="20">Alb</g><bx id="21"/> と体重の改善を認めた。
その後，嘔吐はなく食事摂取が可能となり，<ex id="15"/><g id="16">R-CHOP</g><g id="17"> 療法 </g><g id="18">6</g><bx id="19"/> コースを終了できた。
<g id="10">R-CHOP</g><g id="11"> 療法 </g><g id="12">2</g><g id="13"> コー ス後頻回に嘔吐を認め，</g><g id="14">CT</g><bx id="15"/> および上部内視鏡検査を施行したところ，腫瘍は縮小していたが幽門部の狭窄を認めた。
<g id="1">で病変部と胃周囲リンパ節に </g><g id="2">FDG</g><g id="3"> 集積を認めたため，</g><g id="4">Lugano</g><g id="5"> 分類Ⅱ</g><g id="6">1</g><g id="7">期胃原発 </g><g id="8">DLBCL</g><g id="9"> と診断した。</g>
胃生検で潰瘍部から <ex id="3"/><g id="4">diffuse large B-cell lymphoma</g><g id="5">（</g><g id="6">DLBCL</g><g id="7">），</g><g id="8">PET-CT</g>
<g id="1">要旨 症例は </g><g id="2">59</g><bx id="3"/> 歳，男性。
<g id="13">13</g><g id="14">,</g><g id="15"> </g><g id="16">2011</g><g id="17">)</g>
<g id="5">3</g><g id="6">,</g><g id="7"> </g><g id="8">2010</g><g id="9">／</g><g id="10">Accepted Jan</g><g id="11">.</g>
<g id="1">（</g><g id="2">Received Dec</g><g id="3">.</g>
Key words: Gastric lymphoma, Diffuse large<ex id="21"/><g id="22">-</g><g id="23">B</g><g id="24">-</g><g id="25">cell lymphoma, Stenosis, Gastrojejunal bypass</g>
A computed tomography<ex id="17"/><g id="18">（</g><g id="19">CT</g><g id="20">）</g><bx id="21"/>examination and endoscopy showed that the tumor decreased, but a tight stenosis was located at the pylorus.
With a diagnosis of diffuse large B<ex id="9"/><g id="10">-</g><g id="11">cell lymphoma</g><g id="12">（</g><g id="13">DLBCL</g><g id="14">）</g><g id="15">based on biopsy findings, the patient was treated with R</g><g id="16">-</g><bx id="17"/>CHOP chemotherapy.
<g id="1">A 59</g><g id="2">-</g><g id="3">year</g><g id="4">-</g><g id="5">old man presented to his general practitioner</g><g id="6">（</g><g id="7">GP</g><g id="8">）</g><bx id="9"/>complaining of gastric discomfort.
<g id="1">A Case of Gastric Stenosis Due to Primary Gastric Malignant Lymphoma during Administration of R</g><g id="2">-</g><g id="3">CHOP: Yasuyuki Tamai</g><g id="4">＊1</g><g id="5">, Eiko Murakami</g><g id="6">＊1</g><g id="7">, Yoshiki Nakamori</g><g id="8">＊2</g><g id="9">, Minoru Mizutani</g><g id="10">＊1</g><g id="11"> and Takao Sekine</g><g id="12">＊1</g><g id="13">（<g id="14">＊1</g></g><g id="15">Dept</g><g id="16">. <g id="17">of Hematology</g>, <g id="18">Matsusaka</g> <g id="19">Chuo General Hospital</g>,<g id="20"> </g></g><g id="21">＊2</g><g id="22">Dept</g><g id="23">.<g id="24"> of Hematology and Oncology</g>,<g id="25"> Mie University Graduate School of Medicine</g></g><g id="26">)</g>
<g id="1">〔</g><g id="2">Jpn J Cancer Chemother</g><g id="3"> </g><g id="4">38</g><g id="5">º8¼: 1371-1373,</g><g id="6"> </g><g id="7">August, 2011</g><g id="8">〕</g>
玉井 康将<g id="2">＊1</g>   村上 瑛子<g id="3">＊1</g>   中森 良樹<g id="4">＊2</g>   水 谷  実<g id="5">＊1</g>   関根 隆夫<g id="6">＊1</g>
<g id="1">R-CHOP </g><g id="2">療法中に幽門部狭窄を来し胃空腸バイパス術を施行した</g><g id="3"> </g><g id="4">胃原発 </g><g id="5">Diffuse Large B-Cell Lymphoma</g><g id="6"> の </g><g id="7">1</g><g id="8"> 例</g>
<g id="1">● </g><g id="2">症 例</g><g id="3"> ●</g>
<g id="1">2011 </g><g id="2">年</g><g id="3"> 8 </g><g id="4">月</g>
<g id="1">第 </g><g id="2">38</g><g id="3"> 巻 第 </g><g id="4">8</g><g id="5"> 号</g>
Družba Exim Ex d.o.o., Letališka 27, Ljubljana sporoča, da je na svoji spletni strani <g id="2">www.eximex.si</g> pomotoma uporabila znamke družbe SCA Capital N V, Culliganlaan 1 D, Machelen (Brabant), Belgija, in sicer naslednje znamke: SMARTONE, lotus, lotus PROFESSIONAL, SmartOne Lotus PROFESSIONAL, SmartOne lotus PROFESSIONAL.
<g id="1">30373-7 </g><g id="2">Cordón de algodón marrón.</g><g id="3">.</g>
<g id="1">R.E.A. 288572 (FC) - Codice</g><g id="2"> Fiscale</g><g id="3"> e</g><g id="4"> Partita</g><g id="5"> IVA</g><g id="6"> 03154520401</g>
info@irsternr, it - <g id="2">www.irst.emr.it</g>
<g id="1">T.</g><g id="2"> +39.0543.739100 -</g><g id="3"> F.</g><g id="4"> +39.0543.739123</g>
<g id="1">Istituto</g><g id="2"> Scientifico Romagnolo</g><g id="3"> per</g><g id="4"> lo Studio</g><g id="5"> e la Cura</g><g id="6"> dei Tumori (IRST) S.r.l. IRCCS</g>
<g id="1">PEC:</g><g id="2"> <g id="3">direzione.generale@irstiegalmail.it</g>
</g>
<g id="1">direzione.generale@irst.emr.it</g><g id="2"> -</g><g id="3"> <g id="4">www.irst.emr.it</g>
</g>
<g id="1">T. +39.0543.739412/9415 -</g><g id="2"> F.</g><g id="3"> +39.0543.739123</g>
<g id="1">Via P. Maroncelli, 40</g><g id="2"> -</g><g id="3"> 47014</g><g id="4"> Meldola</g><g id="5"> (FC)</g>
<g id="1">Direzione</g><g id="2"> Sanitaria</g>
<g id="1">R.E A. 288572</g><g id="2"> (FC) - Codice Fiscale</g><g id="3"> e Partita</g><g id="4"> IVA 03154520401</g>
<g id="1">info@irst.emr.it</g><g id="2"> - <g id="3">www.irst.emr.it</g></g>
<g id="1">T. +39.0543.739100</g><g id="2"> -</g><g id="3"> F. +39.0543.739123</g>
<g id="1">Via</g><g id="2"> P.</g><g id="3"> Maroncelli, 40 - 47014 Meldola (FC)</g>
<g id="2">Istituto Scientifico Romagnolo per</g><g id="3"> lo Studio</g><g id="4"> e</g><g id="5"> la Cura dei</g><g id="6"> Tumori (IRST) S.r.l. IRCCS</g>
PEC: <g id="2">direzione.generale@irst.legalmail.it</g>
<g id="1">direzione.generale@irstemrit -</g><g id="2"> <g id="3">www.irst.emr.it</g>
</g>
<g id="1">Via P. Maroncelli, 40 - 47014</g><g id="2"> Meldola</g><g id="3"> (FC)</g>
<g id="1">Direzione</g><g id="2"> Sanitaria</g>
OGGETTO: Protocollo <g id="2">S-AVANT </g>dal titolo "Follow-up dello studio AVANT a 8 e 10 anni (mediana del follow-up) nei pazienti con tumore al colon" di GERCOR: <g id="3">AUTORIZZAZIONE </g>
<g id="2">Alla c.a. Dott.ssa </g><g id="3">Kelly Lutchia GERCOR 151 rue du Faubourg St. Antoine 75011 Paris - Francia</g>
<g id="1">codice interno: <g id="2">L2P1212 </g></g><bx id="3"/> (Reg.
<g id="1">Prot.: </g><g id="2">/q6.5 </g><g id="3">V,1/</g><g id="4"> /2e) f6</g>
<g id="1">Meldola, </g><g id="2">2</g><g id="3">4</g><g id="4">.7fr</g>
<g id="1">:::•.:*</g><g id="2"> •</g><g id="3"> '</g><g id="4"> Istituto Scientifico Romagnolo per</g><g id="5"> lo</g><g id="6"> Studio e la Cura dei Tumori</g>
<g id="1">•••••••••</g><g id="2"> EMILIA-ROMAGNA</g>
<g id="1">'••</g><g id="2"> • • •</g><g id="3"> *** SERVIZIO SANITARIO REGIONALE</g>
<g id="1">PER LO</g><g id="2"> STUDIO</g><g id="3"> E LA CURA DEI TUM RI</g>
<g id="1">ISTITUTO </g><g id="2">SCIENTIFC• ROMAGNOLO</g>
8<ex id="1"/><g id="2"> 800</g>
<g id="2">I   </g><g id="3">820 </g><g id="4">1 1   </g><g id="5">822 </g><g id="6">I</g>
<g id="1">-</g><g id="2">810    </g><g id="3">\ </g><g id="4">/</g>
<g id="1">INTERFACE </g><g id="2">808</g><g id="3"> 806</g>
<g id="1">COMMUNICATION </g><g id="2">812   802 804</g>
<g id="1">DISPLAY   </g><g id="2">PROCESSOR MEMORY MODULE</g>
0   0.2 0.4 0.6 0.8 <g id="1">1</g>
<g id="1">I I I I </g><g id="2">I </g><g id="3">I </g><g id="4">I </g><g id="5">I </g><g id="6">I</g>
<g id="1">_ _ _ _ _ _ _ J</g><g id="2">I</g>
0.02<g id="1">  I</g>
<g id="1">I </g><g id="2">I </g><g id="3">I</g>
<g id="1">tP    </g><g id="2">0.04</g><g id="3">    I</g>
<g id="1">- - - - - - - l   </g><g id="2">L 2</g><g id="3">----</g>
<g id="1">I</g><g id="2">   I</g>
<g id="2">I </g><g id="3">---------------!</g><g id="4">........... 1</g>
<g id="1">I I I I I I </g><g id="2">I</g>
<g id="1">I </g><g id="2">....</g><g id="3">.....</g>
<g id="1">I </g><g id="2">....</g>
<g id="1">I </g><g id="2">....</g>
<g id="2">....</g><g id="3">....</g><g id="4">..r....</g>
<g id="1">D(mm)   </g><g id="2">1.5</g>
<g id="1">f(z)  </g><g id="2">0.5</g>
<g id="1">......</g><g id="2">......    </g><g id="3">I</g>
<g id="1">z</g><g id="2">0 </g><g id="3">ALONG THE HORIZONTAL LENGTH OF</g>
<g id="1">304   </g><g id="2">WELLBORES AND FRONT PROPAGATION</g>
<g id="2">302   </g><g id="3">INITIALIZE ALGORITHM WITH UNIFORM FLOW CONTROL DEVICE PLACEMENT </g><g id="4">f(z) </g><g id="5">= 1</g>
<g id="1">y/d   </g><g id="2">0.85</g>
<g id="2">--=--=--=--=--=--=--=--=--=--=--=--=--::- </g><g id="3">=</g><g id="4">--=</g><g id="5">.      ·</g><g id="6">--=--:-..:::--</g><g id="7">-c;..::,</g>
<g id="2">n </g><g id="3">=</g>
<g id="1">1 </g><g id="2">16</g>
<g id="3">d </g><g id="4">.I</g>
<g id="1">lip = </g><g id="2">0 :   </g><g id="3">: <g id="4">lip = </g></g><g id="5">0</g>
<g id="1">c:::::&gt;</g><g id="2">  200</g>
<g id="1">\ </g><g id="2">FRONT</g>
<g id="2">v·    </g><g id="3">• . .</g>
<g id="2">"· </g><g id="3">. </g><g id="4">. </g><g id="5">.</g><g id="6">. "</g><g id="7">. </g><g id="8">. ·fl'  .</g>
<g id="1">: </g><g id="2">. <g id="3">v</g></g>
<g id="3">..</g><g id="4">-·.   <g id="5">:v .</g></g>
1     <g id="2">1</g>
1     <g id="3">1     </g>1
I   <g id="3">1     </g>1
1     1     <g id="2">1</g>
1   <g id="2">1</g>
LAB;
        $this->expected_segment = <<<'LAB'
&lt;g id="2"&gt;sIL-2R&lt;/g&gt;&lt;g id="3"&gt; は &lt;/g&gt;&lt;g id="4"&gt;447&lt;/g&gt;&lt;g id="5"&gt; &lt;/g&gt;&lt;g id="6"&gt;U&lt;/g&gt;&lt;g id="7"&gt;／&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;入院時検査所見&lt;/g&gt;&lt;g id="2"&gt;: TP 5.7 mg&lt;/g&gt;&lt;g id="3"&gt;／&lt;/g&gt;&lt;g id="4"&gt;dL&lt;/g&gt;&lt;g id="5"&gt;，&lt;/g&gt;&lt;g id="6"&gt;Alb&lt;/g&gt;&lt;g id="7"&gt; &lt;/g&gt;&lt;g id="8"&gt;2.9 mg&lt;/g&gt;&lt;g id="9"&gt;／&lt;/g&gt;&lt;g id="10"&gt;dL&lt;/g&gt;&lt;g id="11"&gt; と低&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;入院時現症&lt;/g&gt;&lt;g id="2"&gt;:&lt;/g&gt;&lt;g id="3"&gt; 腹部に明らかな腫瘤は触れず，表在リン&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;既往歴&lt;/g&gt;&lt;g id="2"&gt;:&lt;/g&gt;&lt;g id="3"&gt; 糖尿病，脂質異常症。&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Fig. 2 &nbsp;&lt;/g&gt;&lt;g id="2"&gt;After&lt;g id="3"&gt; &lt;/g&gt;two cycle of R-CHOP chemotherapy&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Fig. 1 &nbsp;&lt;/g&gt;&lt;g id="2"&gt;Radiological findings on arrival&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;連絡先&lt;/g&gt;&lt;g id="2"&gt;:&lt;/g&gt;&lt;g id="3"&gt; 〒 &lt;/g&gt;&lt;g id="4"&gt;515-8566&lt;/g&gt;&lt;g id="5"&gt; 松阪市川井町字小望 &lt;/g&gt;&lt;g id="6"&gt;102&lt;/g&gt;&lt;g id="7"&gt; 松阪中央総合病院・内科 玉井 康将&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;＊1&lt;/g&gt;&lt;g id="2"&gt; 松阪中央病院・内科 &lt;/g&gt;&lt;g id="3"&gt;＊2&lt;/g&gt;&lt;g id="4"&gt; 三重大学医学系研究科・血液・腫瘍内科学&lt;/g&gt;##$_0A$##し 現病歴&lt;ex id="1"/&gt;&lt;g id="2"&gt;:&lt;/g&gt;&lt;g id="3"&gt; 胃部不快感にて近医受診し，上部消化管内視 かし，化学療法の効果により出血，穿孔，消化管閉塞と 鏡検査で体下部小弯から幽門輪に広がる巨大な不整潰瘍 いった重篤な合併症が生じた際は手術適応になる場合が を認め，精査加療目的で当院入院となった。&lt;/g&gt;##$_0A$##的切除に劣らない治療成績を示し，手術の危険性，&lt;ex id="5"/&gt;&lt;g id="6"&gt;QOL&lt;/g&gt;&lt;g id="7"&gt; 主訴&lt;/g&gt;&lt;g id="8"&gt;:&lt;/g&gt;&lt;g id="9"&gt; 胃部不快感。&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;（&lt;/g&gt;&lt;g id="2"&gt;DLBCL&lt;/g&gt;&lt;g id="3"&gt;）に対する治療法は，外科的切除に加えて術後 化学療法が行われていたが，最近では胃温存療法が外科 患者&lt;/g&gt;&lt;g id="4"&gt;: 59&lt;/g&gt;&lt;bx id="5"/&gt; 歳，男性。##$_0A$##&lt;g id="2"&gt;症&lt;/g&gt;&lt;g id="3"&gt; &nbsp; &lt;/g&gt;&lt;g id="4"&gt;例&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;限局期限局胃原発 &lt;/g&gt;&lt;g id="2"&gt;diffuse large B-cell lymphoma&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;果が得られた &lt;/g&gt;&lt;g id="2"&gt;1&lt;/g&gt;&lt;g id="3"&gt; 例を経験したので報告する。&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;胃空腸バイパス術を行い，&lt;/g&gt;&lt;g id="2"&gt;QOL&lt;/g&gt;&lt;g id="3"&gt; を損なわずに良好な結&lt;/g&gt;##$_0A$##化学療法により通過障害を来しても胃空腸バイパス術にて経口で栄養状態を維持し，&lt;ex id="27"/&gt;&lt;g id="28"&gt;QOL&lt;/g&gt;&lt;g id="29"&gt; を損なわずに化学療法を継続 することができた症例を経験したので報告する。&lt;/g&gt;##$_0A$##胃切除後の化学療法は &lt;ex id="25"/&gt;&lt;g id="26"&gt;QOL&lt;/g&gt;&lt;bx id="27"/&gt; を低下させ る。##$_0A$##評価の &lt;ex id="21"/&gt;&lt;g id="22"&gt;CT&lt;/g&gt;&lt;g id="23"&gt;，&lt;/g&gt;&lt;g id="24"&gt;PET-CT&lt;/g&gt;&lt;bx id="25"/&gt; では完全寛解であった。##$_0A$##手術後，&lt;ex id="19"/&gt;&lt;g id="20"&gt;Alb&lt;/g&gt;&lt;bx id="21"/&gt; と体重の改善を認めた。##$_0A$##その後，嘔吐はなく食事摂取が可能となり，&lt;ex id="15"/&gt;&lt;g id="16"&gt;R-CHOP&lt;/g&gt;&lt;g id="17"&gt; 療法 &lt;/g&gt;&lt;g id="18"&gt;6&lt;/g&gt;&lt;bx id="19"/&gt; コースを終了できた。##$_0A$##&lt;g id="10"&gt;R-CHOP&lt;/g&gt;&lt;g id="11"&gt; 療法 &lt;/g&gt;&lt;g id="12"&gt;2&lt;/g&gt;&lt;g id="13"&gt; コー ス後頻回に嘔吐を認め，&lt;/g&gt;&lt;g id="14"&gt;CT&lt;/g&gt;&lt;bx id="15"/&gt; および上部内視鏡検査を施行したところ，腫瘍は縮小していたが幽門部の狭窄を認めた。##$_0A$##&lt;g id="1"&gt;で病変部と胃周囲リンパ節に &lt;/g&gt;&lt;g id="2"&gt;FDG&lt;/g&gt;&lt;g id="3"&gt; 集積を認めたため，&lt;/g&gt;&lt;g id="4"&gt;Lugano&lt;/g&gt;&lt;g id="5"&gt; 分類Ⅱ&lt;/g&gt;&lt;g id="6"&gt;1&lt;/g&gt;&lt;g id="7"&gt;期胃原発 &lt;/g&gt;&lt;g id="8"&gt;DLBCL&lt;/g&gt;&lt;g id="9"&gt; と診断した。&lt;/g&gt;##$_0A$##胃生検で潰瘍部から &lt;ex id="3"/&gt;&lt;g id="4"&gt;diffuse large B-cell lymphoma&lt;/g&gt;&lt;g id="5"&gt;（&lt;/g&gt;&lt;g id="6"&gt;DLBCL&lt;/g&gt;&lt;g id="7"&gt;），&lt;/g&gt;&lt;g id="8"&gt;PET-CT&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;要旨 症例は &lt;/g&gt;&lt;g id="2"&gt;59&lt;/g&gt;&lt;bx id="3"/&gt; 歳，男性。##$_0A$##&lt;g id="13"&gt;13&lt;/g&gt;&lt;g id="14"&gt;,&lt;/g&gt;&lt;g id="15"&gt; &lt;/g&gt;&lt;g id="16"&gt;2011&lt;/g&gt;&lt;g id="17"&gt;)&lt;/g&gt;##$_0A$##&lt;g id="5"&gt;3&lt;/g&gt;&lt;g id="6"&gt;,&lt;/g&gt;&lt;g id="7"&gt; &lt;/g&gt;&lt;g id="8"&gt;2010&lt;/g&gt;&lt;g id="9"&gt;／&lt;/g&gt;&lt;g id="10"&gt;Accepted Jan&lt;/g&gt;&lt;g id="11"&gt;.&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;（&lt;/g&gt;&lt;g id="2"&gt;Received Dec&lt;/g&gt;&lt;g id="3"&gt;.&lt;/g&gt;##$_0A$##Key words: Gastric lymphoma, Diffuse large&lt;ex id="21"/&gt;&lt;g id="22"&gt;-&lt;/g&gt;&lt;g id="23"&gt;B&lt;/g&gt;&lt;g id="24"&gt;-&lt;/g&gt;&lt;g id="25"&gt;cell lymphoma, Stenosis, Gastrojejunal bypass&lt;/g&gt;##$_0A$##A computed tomography&lt;ex id="17"/&gt;&lt;g id="18"&gt;（&lt;/g&gt;&lt;g id="19"&gt;CT&lt;/g&gt;&lt;g id="20"&gt;）&lt;/g&gt;&lt;bx id="21"/&gt;examination and endoscopy showed that the tumor decreased, but a tight stenosis was located at the pylorus.##$_0A$##With a diagnosis of diffuse large B&lt;ex id="9"/&gt;&lt;g id="10"&gt;-&lt;/g&gt;&lt;g id="11"&gt;cell lymphoma&lt;/g&gt;&lt;g id="12"&gt;（&lt;/g&gt;&lt;g id="13"&gt;DLBCL&lt;/g&gt;&lt;g id="14"&gt;）&lt;/g&gt;&lt;g id="15"&gt;based on biopsy findings, the patient was treated with R&lt;/g&gt;&lt;g id="16"&gt;-&lt;/g&gt;&lt;bx id="17"/&gt;CHOP chemotherapy.##$_0A$##&lt;g id="1"&gt;A 59&lt;/g&gt;&lt;g id="2"&gt;-&lt;/g&gt;&lt;g id="3"&gt;year&lt;/g&gt;&lt;g id="4"&gt;-&lt;/g&gt;&lt;g id="5"&gt;old man presented to his general practitioner&lt;/g&gt;&lt;g id="6"&gt;（&lt;/g&gt;&lt;g id="7"&gt;GP&lt;/g&gt;&lt;g id="8"&gt;）&lt;/g&gt;&lt;bx id="9"/&gt;complaining of gastric discomfort.##$_0A$##&lt;g id="1"&gt;A Case of Gastric Stenosis Due to Primary Gastric Malignant Lymphoma during Administration of R&lt;/g&gt;&lt;g id="2"&gt;-&lt;/g&gt;&lt;g id="3"&gt;CHOP: Yasuyuki Tamai&lt;/g&gt;&lt;g id="4"&gt;＊1&lt;/g&gt;&lt;g id="5"&gt;, Eiko Murakami&lt;/g&gt;&lt;g id="6"&gt;＊1&lt;/g&gt;&lt;g id="7"&gt;, Yoshiki Nakamori&lt;/g&gt;&lt;g id="8"&gt;＊2&lt;/g&gt;&lt;g id="9"&gt;, Minoru Mizutani&lt;/g&gt;&lt;g id="10"&gt;＊1&lt;/g&gt;&lt;g id="11"&gt; and Takao Sekine&lt;/g&gt;&lt;g id="12"&gt;＊1&lt;/g&gt;&lt;g id="13"&gt;（&lt;g id="14"&gt;＊1&lt;/g&gt;&lt;/g&gt;&lt;g id="15"&gt;Dept&lt;/g&gt;&lt;g id="16"&gt;. &lt;g id="17"&gt;of Hematology&lt;/g&gt;, &lt;g id="18"&gt;Matsusaka&lt;/g&gt; &lt;g id="19"&gt;Chuo General Hospital&lt;/g&gt;,&lt;g id="20"&gt; &lt;/g&gt;&lt;/g&gt;&lt;g id="21"&gt;＊2&lt;/g&gt;&lt;g id="22"&gt;Dept&lt;/g&gt;&lt;g id="23"&gt;.&lt;g id="24"&gt; of Hematology and Oncology&lt;/g&gt;,&lt;g id="25"&gt; Mie University Graduate School of Medicine&lt;/g&gt;&lt;/g&gt;&lt;g id="26"&gt;)&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;〔&lt;/g&gt;&lt;g id="2"&gt;Jpn J Cancer Chemother&lt;/g&gt;&lt;g id="3"&gt; &lt;/g&gt;&lt;g id="4"&gt;38&lt;/g&gt;&lt;g id="5"&gt;º8¼: 1371-1373,&lt;/g&gt;&lt;g id="6"&gt; &lt;/g&gt;&lt;g id="7"&gt;August, 2011&lt;/g&gt;&lt;g id="8"&gt;〕&lt;/g&gt;##$_0A$##玉井 康将&lt;g id="2"&gt;＊1&lt;/g&gt; &nbsp; 村上 瑛子&lt;g id="3"&gt;＊1&lt;/g&gt; &nbsp; 中森 良樹&lt;g id="4"&gt;＊2&lt;/g&gt; &nbsp; 水 谷 &nbsp;実&lt;g id="5"&gt;＊1&lt;/g&gt; &nbsp; 関根 隆夫&lt;g id="6"&gt;＊1&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;R-CHOP &lt;/g&gt;&lt;g id="2"&gt;療法中に幽門部狭窄を来し胃空腸バイパス術を施行した&lt;/g&gt;&lt;g id="3"&gt; &lt;/g&gt;&lt;g id="4"&gt;胃原発 &lt;/g&gt;&lt;g id="5"&gt;Diffuse Large B-Cell Lymphoma&lt;/g&gt;&lt;g id="6"&gt; の &lt;/g&gt;&lt;g id="7"&gt;1&lt;/g&gt;&lt;g id="8"&gt; 例&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;● &lt;/g&gt;&lt;g id="2"&gt;症 例&lt;/g&gt;&lt;g id="3"&gt; ●&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;2011 &lt;/g&gt;&lt;g id="2"&gt;年&lt;/g&gt;&lt;g id="3"&gt; 8 &lt;/g&gt;&lt;g id="4"&gt;月&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;第 &lt;/g&gt;&lt;g id="2"&gt;38&lt;/g&gt;&lt;g id="3"&gt; 巻 第 &lt;/g&gt;&lt;g id="4"&gt;8&lt;/g&gt;&lt;g id="5"&gt; 号&lt;/g&gt;##$_0A$##Družba Exim Ex d.o.o., Letališka 27, Ljubljana sporoča, da je na svoji spletni strani &lt;g id="2"&gt;www.eximex.si&lt;/g&gt; pomotoma uporabila znamke družbe SCA Capital N V, Culliganlaan 1 D, Machelen (Brabant), Belgija, in sicer naslednje znamke: SMARTONE, lotus, lotus PROFESSIONAL, SmartOne Lotus PROFESSIONAL, SmartOne lotus PROFESSIONAL.##$_0A$##&lt;g id="1"&gt;30373-7 &lt;/g&gt;&lt;g id="2"&gt;Cordón de algodón marrón.&lt;/g&gt;&lt;g id="3"&gt;.&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;R.E.A. 288572 (FC) - Codice&lt;/g&gt;&lt;g id="2"&gt; Fiscale&lt;/g&gt;&lt;g id="3"&gt; e&lt;/g&gt;&lt;g id="4"&gt; Partita&lt;/g&gt;&lt;g id="5"&gt; IVA&lt;/g&gt;&lt;g id="6"&gt; 03154520401&lt;/g&gt;##$_0A$##info@irsternr, it - &lt;g id="2"&gt;www.irst.emr.it&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;T.&lt;/g&gt;&lt;g id="2"&gt; +39.0543.739100 -&lt;/g&gt;&lt;g id="3"&gt; F.&lt;/g&gt;&lt;g id="4"&gt; +39.0543.739123&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Istituto&lt;/g&gt;&lt;g id="2"&gt; Scientifico Romagnolo&lt;/g&gt;&lt;g id="3"&gt; per&lt;/g&gt;&lt;g id="4"&gt; lo Studio&lt;/g&gt;&lt;g id="5"&gt; e la Cura&lt;/g&gt;&lt;g id="6"&gt; dei Tumori (IRST) S.r.l. IRCCS&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;PEC:&lt;/g&gt;&lt;g id="2"&gt; &lt;g id="3"&gt;direzione.generale@irstiegalmail.it&lt;/g&gt;##$_0A$##&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;direzione.generale@irst.emr.it&lt;/g&gt;&lt;g id="2"&gt; -&lt;/g&gt;&lt;g id="3"&gt; &lt;g id="4"&gt;www.irst.emr.it&lt;/g&gt;##$_0A$##&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;T. +39.0543.739412/9415 -&lt;/g&gt;&lt;g id="2"&gt; F.&lt;/g&gt;&lt;g id="3"&gt; +39.0543.739123&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Via P. Maroncelli, 40&lt;/g&gt;&lt;g id="2"&gt; -&lt;/g&gt;&lt;g id="3"&gt; 47014&lt;/g&gt;&lt;g id="4"&gt; Meldola&lt;/g&gt;&lt;g id="5"&gt; (FC)&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Direzione&lt;/g&gt;&lt;g id="2"&gt; Sanitaria&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;R.E A. 288572&lt;/g&gt;&lt;g id="2"&gt; (FC) - Codice Fiscale&lt;/g&gt;&lt;g id="3"&gt; e Partita&lt;/g&gt;&lt;g id="4"&gt; IVA 03154520401&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;info@irst.emr.it&lt;/g&gt;&lt;g id="2"&gt; - &lt;g id="3"&gt;www.irst.emr.it&lt;/g&gt;&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;T. +39.0543.739100&lt;/g&gt;&lt;g id="2"&gt; -&lt;/g&gt;&lt;g id="3"&gt; F. +39.0543.739123&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Via&lt;/g&gt;&lt;g id="2"&gt; P.&lt;/g&gt;&lt;g id="3"&gt; Maroncelli, 40 - 47014 Meldola (FC)&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;Istituto Scientifico Romagnolo per&lt;/g&gt;&lt;g id="3"&gt; lo Studio&lt;/g&gt;&lt;g id="4"&gt; e&lt;/g&gt;&lt;g id="5"&gt; la Cura dei&lt;/g&gt;&lt;g id="6"&gt; Tumori (IRST) S.r.l. IRCCS&lt;/g&gt;##$_0A$##PEC: &lt;g id="2"&gt;direzione.generale@irst.legalmail.it&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;direzione.generale@irstemrit -&lt;/g&gt;&lt;g id="2"&gt; &lt;g id="3"&gt;www.irst.emr.it&lt;/g&gt;##$_0A$##&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Via P. Maroncelli, 40 - 47014&lt;/g&gt;&lt;g id="2"&gt; Meldola&lt;/g&gt;&lt;g id="3"&gt; (FC)&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Direzione&lt;/g&gt;&lt;g id="2"&gt; Sanitaria&lt;/g&gt;##$_0A$##OGGETTO: Protocollo &lt;g id="2"&gt;S-AVANT &lt;/g&gt;dal titolo "Follow-up dello studio AVANT a 8 e 10 anni (mediana del follow-up) nei pazienti con tumore al colon" di GERCOR: &lt;g id="3"&gt;AUTORIZZAZIONE &lt;/g&gt;##$_0A$##&lt;g id="2"&gt;Alla c.a. Dott.ssa &lt;/g&gt;&lt;g id="3"&gt;Kelly Lutchia GERCOR 151 rue du Faubourg St. Antoine 75011 Paris - Francia&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;codice interno: &lt;g id="2"&gt;L2P1212 &lt;/g&gt;&lt;/g&gt;&lt;bx id="3"/&gt; (Reg.##$_0A$##&lt;g id="1"&gt;Prot.: &lt;/g&gt;&lt;g id="2"&gt;/q6.5 &lt;/g&gt;&lt;g id="3"&gt;V,1/&lt;/g&gt;&lt;g id="4"&gt; /2e) f6&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;Meldola, &lt;/g&gt;&lt;g id="2"&gt;2&lt;/g&gt;&lt;g id="3"&gt;4&lt;/g&gt;&lt;g id="4"&gt;.7fr&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;:::•.:*&lt;/g&gt;&lt;g id="2"&gt; •&lt;/g&gt;&lt;g id="3"&gt; '&lt;/g&gt;&lt;g id="4"&gt; Istituto Scientifico Romagnolo per&lt;/g&gt;&lt;g id="5"&gt; lo&lt;/g&gt;&lt;g id="6"&gt; Studio e la Cura dei Tumori&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;•••••••••&lt;/g&gt;&lt;g id="2"&gt; EMILIA-ROMAGNA&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;'••&lt;/g&gt;&lt;g id="2"&gt; • • •&lt;/g&gt;&lt;g id="3"&gt; *** SERVIZIO SANITARIO REGIONALE&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;PER LO&lt;/g&gt;&lt;g id="2"&gt; STUDIO&lt;/g&gt;&lt;g id="3"&gt; E LA CURA DEI TUM RI&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;ISTITUTO &lt;/g&gt;&lt;g id="2"&gt;SCIENTIFC• ROMAGNOLO&lt;/g&gt;##$_0A$##8&lt;ex id="1"/&gt;&lt;g id="2"&gt; 800&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;I &nbsp; &lt;/g&gt;&lt;g id="3"&gt;820 &lt;/g&gt;&lt;g id="4"&gt;1 1 &nbsp; &lt;/g&gt;&lt;g id="5"&gt;822 &lt;/g&gt;&lt;g id="6"&gt;I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;-&lt;/g&gt;&lt;g id="2"&gt;810 &nbsp; &nbsp;&lt;/g&gt;&lt;g id="3"&gt;\ &lt;/g&gt;&lt;g id="4"&gt;/&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;INTERFACE &lt;/g&gt;&lt;g id="2"&gt;808&lt;/g&gt;&lt;g id="3"&gt; 806&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;COMMUNICATION &lt;/g&gt;&lt;g id="2"&gt;812 &nbsp; 802 804&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;DISPLAY &nbsp; &lt;/g&gt;&lt;g id="2"&gt;PROCESSOR MEMORY MODULE&lt;/g&gt;##$_0A$##0 &nbsp; 0.2 0.4 0.6 0.8 &lt;g id="1"&gt;1&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;I I I I &lt;/g&gt;&lt;g id="2"&gt;I &lt;/g&gt;&lt;g id="3"&gt;I &lt;/g&gt;&lt;g id="4"&gt;I &lt;/g&gt;&lt;g id="5"&gt;I &lt;/g&gt;&lt;g id="6"&gt;I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;_ _ _ _ _ _ _ J&lt;/g&gt;&lt;g id="2"&gt;I&lt;/g&gt;##$_0A$##0.02&lt;g id="1"&gt; &nbsp;I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;I &lt;/g&gt;&lt;g id="2"&gt;I &lt;/g&gt;&lt;g id="3"&gt;I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;tP &nbsp; &nbsp;&lt;/g&gt;&lt;g id="2"&gt;0.04&lt;/g&gt;&lt;g id="3"&gt; &nbsp; &nbsp;I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;- - - - - - - l &nbsp; &lt;/g&gt;&lt;g id="2"&gt;L 2&lt;/g&gt;&lt;g id="3"&gt;----&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;I&lt;/g&gt;&lt;g id="2"&gt; &nbsp; I&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;I &lt;/g&gt;&lt;g id="3"&gt;---------------!&lt;/g&gt;&lt;g id="4"&gt;........... 1&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;I I I I I I &lt;/g&gt;&lt;g id="2"&gt;I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;I &lt;/g&gt;&lt;g id="2"&gt;....&lt;/g&gt;&lt;g id="3"&gt;.....&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;I &lt;/g&gt;&lt;g id="2"&gt;....&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;I &lt;/g&gt;&lt;g id="2"&gt;....&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;....&lt;/g&gt;&lt;g id="3"&gt;....&lt;/g&gt;&lt;g id="4"&gt;..r....&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;D(mm) &nbsp; &lt;/g&gt;&lt;g id="2"&gt;1.5&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;f(z) &nbsp;&lt;/g&gt;&lt;g id="2"&gt;0.5&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;......&lt;/g&gt;&lt;g id="2"&gt;...... &nbsp; &nbsp;&lt;/g&gt;&lt;g id="3"&gt;I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;z&lt;/g&gt;&lt;g id="2"&gt;0 &lt;/g&gt;&lt;g id="3"&gt;ALONG THE HORIZONTAL LENGTH OF&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;304 &nbsp; &lt;/g&gt;&lt;g id="2"&gt;WELLBORES AND FRONT PROPAGATION&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;302 &nbsp; &lt;/g&gt;&lt;g id="3"&gt;INITIALIZE ALGORITHM WITH UNIFORM FLOW CONTROL DEVICE PLACEMENT &lt;/g&gt;&lt;g id="4"&gt;f(z) &lt;/g&gt;&lt;g id="5"&gt;= 1&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;y/d &nbsp; &lt;/g&gt;&lt;g id="2"&gt;0.85&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;--=--=--=--=--=--=--=--=--=--=--=--=--::- &lt;/g&gt;&lt;g id="3"&gt;=&lt;/g&gt;&lt;g id="4"&gt;--=&lt;/g&gt;&lt;g id="5"&gt;. &nbsp; &nbsp; &nbsp;·&lt;/g&gt;&lt;g id="6"&gt;--=--:-..:::--&lt;/g&gt;&lt;g id="7"&gt;-c;..::,&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;n &lt;/g&gt;&lt;g id="3"&gt;=&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;1 &lt;/g&gt;&lt;g id="2"&gt;16&lt;/g&gt;##$_0A$##&lt;g id="3"&gt;d &lt;/g&gt;&lt;g id="4"&gt;.I&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;lip = &lt;/g&gt;&lt;g id="2"&gt;0 : &nbsp; &lt;/g&gt;&lt;g id="3"&gt;: &lt;g id="4"&gt;lip = &lt;/g&gt;&lt;/g&gt;&lt;g id="5"&gt;0&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;c:::::&gt;&lt;/g&gt;&lt;g id="2"&gt; &nbsp;200&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;\ &lt;/g&gt;&lt;g id="2"&gt;FRONT&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;v· &nbsp; &nbsp;&lt;/g&gt;&lt;g id="3"&gt;• . .&lt;/g&gt;##$_0A$##&lt;g id="2"&gt;"· &lt;/g&gt;&lt;g id="3"&gt;. &lt;/g&gt;&lt;g id="4"&gt;. &lt;/g&gt;&lt;g id="5"&gt;.&lt;/g&gt;&lt;g id="6"&gt;. "&lt;/g&gt;&lt;g id="7"&gt;. &lt;/g&gt;&lt;g id="8"&gt;. ·fl' &nbsp;.&lt;/g&gt;##$_0A$##&lt;g id="1"&gt;: &lt;/g&gt;&lt;g id="2"&gt;. &lt;g id="3"&gt;v&lt;/g&gt;&lt;/g&gt;##$_0A$##&lt;g id="3"&gt;..&lt;/g&gt;&lt;g id="4"&gt;-·. &nbsp; &lt;g id="5"&gt;:v .&lt;/g&gt;&lt;/g&gt;##$_0A$##1 &nbsp; &nbsp; &lt;g id="2"&gt;1&lt;/g&gt;##$_0A$##1 &nbsp; &nbsp; &lt;g id="3"&gt;1 &nbsp; &nbsp; &lt;/g&gt;1##$_0A$##I &nbsp; &lt;g id="3"&gt;1 &nbsp; &nbsp; &lt;/g&gt;1##$_0A$##1 &nbsp; &nbsp; 1 &nbsp; &nbsp; &lt;g id="2"&gt;1&lt;/g&gt;##$_0A$##1 &nbsp; &lt;g id="2"&gt;1&lt;/g&gt;
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::rawxliff2view($this->source_segment));
    }


}


