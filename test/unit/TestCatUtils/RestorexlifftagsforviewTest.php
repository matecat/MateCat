<?php

use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Filters\RestoreXliffTagsForView;

/**
 * @group regression
 * 
 * this battery of tests sends one string in input as $source_segment to ( new RestoreXliffTagsForView )->transform method and
 * verifies that the output is equal to the $expected_segment.
 * User: dinies
 * Date: 01/04/16
 * Time: 14.10
 */
class RestorexlifftagsforviewTest extends AbstractTest
{
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

        $this->filter = MateCatFilter::getInstance($this->featureSet, 'en-US','it-IT', [] );

    }

    
    /**
     * @group regression
     * 
     * original_input_segment= <g id="1">[AH1]</g><g id="2">Is fold &amp; crease the same??</g>
     */
    public function test_restorexlifftagsforview_1()
    {
        $this->source_segment = <<<LAB
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##[AH1]##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##Is fold & crease the same??##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;[AH1]&lt;/g&gt;&lt;g id="2"&gt;Is fold & crease the same??&lt;/g&gt;
LAB;
        self::assertEquals($this->expected_segment, ( new RestoreXliffTagsForView )->transform($this->source_segment));
    }

    /**
     * @group regression
     * 
     * original_input_segment= <g id="1">SIA “Bio2You”,</g><g id="2"> Reg. no</g><g id="3">40103243404, </g><g id="4">address: Ganibu Dambis 24A, Riga, Latvia  ("the Franchisor")  </g>
     */
    public function test_restorexlifftagsforview_2()
    {
        $this->source_segment = <<<LAB
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##SIA “Bio2You”,##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN## Reg. no##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##40103243404, ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##address: Ganibu Dambis 24A, Riga, Latvia &nbsp;("the Franchisor") &nbsp;##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;SIA “Bio2You”,&lt;/g&gt;&lt;g id="2"&gt; Reg. no&lt;/g&gt;&lt;g id="3"&gt;40103243404, &lt;/g&gt;&lt;g id="4"&gt;address: Ganibu Dambis 24A, Riga, Latvia &nbsp;("the Franchisor") &nbsp;&lt;/g&gt;
LAB;
        self::assertEquals($this->expected_segment, ( new RestoreXliffTagsForView )->transform($this->source_segment));
    }

    /**
     * @group regression
     * 
     * original_input_segment= <g id="1">USB </g><g id="2">(to wake to your USB music)</g><g id="1">DISC </g><g id="2">(to wake to your DISC music)</g><g id="1">BUZZER </g><g id="2">(to wake to a buzzer sound)</g>
     */
    public function test_restorexlifftagsforview_3()
    {
        $this->source_segment = <<<LAB
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##USB ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##(to wake to your USB music)##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##DISC ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##(to wake to your DISC music)##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##BUZZER ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##(to wake to a buzzer sound)##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;USB &lt;/g&gt;&lt;g id="2"&gt;(to wake to your USB music)&lt;/g&gt;&lt;g id="1"&gt;DISC &lt;/g&gt;&lt;g id="2"&gt;(to wake to your DISC music)&lt;/g&gt;&lt;g id="1"&gt;BUZZER &lt;/g&gt;&lt;g id="2"&gt;(to wake to a buzzer sound)&lt;/g&gt;
LAB;
        self::assertEquals($this->expected_segment, ( new RestoreXliffTagsForView )->transform($this->source_segment));
    }

    /**
     * @group regression
     * 
     * original_input_segment= <g id="1">入院時検査所見</g><g id="2">: TP 5.7 mg</g><g id="3">／</g><g id="4">dL</g><g id="5">，</g><g id="6">Alb</g><g id="7"> </g><g id="8">2.9 mg</g><g id="9">／</g><g id="10">dL</g><g id="11"> と低</g><g id="1">入院時現症</g><g id="2">:</g><g id="3"> 腹部に明らかな腫瘤は触れず，表在リン</g>
     */
    public function test_restorexlifftagsforview_4()
    {
        $this->source_segment = <<<LAB
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##入院時検査所見##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##: TP 5.7 mg##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##／##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##dL##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##，##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNiI=##GREATERTHAN##Alb##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNyI=##GREATERTHAN## ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iOCI=##GREATERTHAN##2.9 mg##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iOSI=##GREATERTHAN##／##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTAi##GREATERTHAN##dL##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTEi##GREATERTHAN## と低##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##入院時現症##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##:##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN## 腹部に明らかな腫瘤は触れず，表在リン##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;入院時検査所見&lt;/g&gt;&lt;g id="2"&gt;: TP 5.7 mg&lt;/g&gt;&lt;g id="3"&gt;／&lt;/g&gt;&lt;g id="4"&gt;dL&lt;/g&gt;&lt;g id="5"&gt;，&lt;/g&gt;&lt;g id="6"&gt;Alb&lt;/g&gt;&lt;g id="7"&gt; &lt;/g&gt;&lt;g id="8"&gt;2.9 mg&lt;/g&gt;&lt;g id="9"&gt;／&lt;/g&gt;&lt;g id="10"&gt;dL&lt;/g&gt;&lt;g id="11"&gt; と低&lt;/g&gt;&lt;g id="1"&gt;入院時現症&lt;/g&gt;&lt;g id="2"&gt;:&lt;/g&gt;&lt;g id="3"&gt; 腹部に明らかな腫瘤は触れず，表在リン&lt;/g&gt;
LAB;
        self::assertEquals($this->expected_segment, ( new RestoreXliffTagsForView )->transform($this->source_segment));
    }

    /**
     * @group regression
     * 
     * original_input_segment= <g id="1">併症や </g><g id="2">QOL</g><g id="3"> 低下の観点から外科切除は行わない傾向に</g><g id="1">胃悪性リンパ腫の治療は，これまで外科的切除が積極 的に行われてきたが，最近では胃温存療法が外科的切除 に劣らない治療成績を示し</g><g id="2">1)</g><g id="3">，外科的切除に伴う術後合</g><g id="2">考</g><g id="3">   </g><g id="4">察</g><g id="1">Antecolic gastrojejunostomy with a braun anastomosi</g><g id="2">8)</g><g id="3">.</g>
     */
    public function test_restorexlifftagsforview_5()
    {
        $this->source_segment = <<<LAB
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##併症や ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##QOL##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN## 低下の観点から外科切除は行わない傾向に##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##胃悪性リンパ腫の治療は，これまで外科的切除が積極 的に行われてきたが，最近では胃温存療法が外科的切除 に劣らない治療成績を示し##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##1)##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##，外科的切除に伴う術後合##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##考##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN## &nbsp; ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##察##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##Antecolic gastrojejunostomy with a braun anastomosi##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##8)##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##.##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;併症や &lt;/g&gt;&lt;g id="2"&gt;QOL&lt;/g&gt;&lt;g id="3"&gt; 低下の観点から外科切除は行わない傾向に&lt;/g&gt;&lt;g id="1"&gt;胃悪性リンパ腫の治療は，これまで外科的切除が積極 的に行われてきたが，最近では胃温存療法が外科的切除 に劣らない治療成績を示し&lt;/g&gt;&lt;g id="2"&gt;1)&lt;/g&gt;&lt;g id="3"&gt;，外科的切除に伴う術後合&lt;/g&gt;&lt;g id="2"&gt;考&lt;/g&gt;&lt;g id="3"&gt; &nbsp; &lt;/g&gt;&lt;g id="4"&gt;察&lt;/g&gt;&lt;g id="1"&gt;Antecolic gastrojejunostomy with a braun anastomosi&lt;/g&gt;&lt;g id="2"&gt;8)&lt;/g&gt;&lt;g id="3"&gt;.&lt;/g&gt;
LAB;
        self::assertEquals($this->expected_segment, ( new RestoreXliffTagsForView )->transform($this->source_segment));
    }

    /**
     * @group regression
     * 
     * original_input_segment= <g id="1">[0065] </g><g id="2">y</g><g id="3">1</g><g id="4">(</g><g id="5">z</g><g id="6">O</g><g id="7">, t</g><g id="8">m</g><g id="9">) </g><g id="10">= min</g><g id="11">[</g><g id="12">y</g><g id="13">1</g><g id="14">(</g><g id="15">z, t</g><g id="16">m</g><g id="17">)]</g><g id="18">;             </g><g id="19">0 : : : z ::: L                                              </g><g id="20">(Equation 16)</g>
     */
    public function test_restorexlifftagsforview_6()
    {
        $this->source_segment = <<<LAB
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##[0065] ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##y##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##1##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##(##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##z##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNiI=##GREATERTHAN##O##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNyI=##GREATERTHAN##, t##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iOCI=##GREATERTHAN##m##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iOSI=##GREATERTHAN##) ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTAi##GREATERTHAN##= min##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTEi##GREATERTHAN##[##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTIi##GREATERTHAN##y##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTMi##GREATERTHAN##1##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTQi##GREATERTHAN##(##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTUi##GREATERTHAN##z, t##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTYi##GREATERTHAN##m##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTci##GREATERTHAN##)]##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTgi##GREATERTHAN##; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTki##GREATERTHAN##0 : : : z ::: L &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMjAi##GREATERTHAN##(Equation 16)##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;[0065] &lt;/g&gt;&lt;g id="2"&gt;y&lt;/g&gt;&lt;g id="3"&gt;1&lt;/g&gt;&lt;g id="4"&gt;(&lt;/g&gt;&lt;g id="5"&gt;z&lt;/g&gt;&lt;g id="6"&gt;O&lt;/g&gt;&lt;g id="7"&gt;, t&lt;/g&gt;&lt;g id="8"&gt;m&lt;/g&gt;&lt;g id="9"&gt;) &lt;/g&gt;&lt;g id="10"&gt;= min&lt;/g&gt;&lt;g id="11"&gt;[&lt;/g&gt;&lt;g id="12"&gt;y&lt;/g&gt;&lt;g id="13"&gt;1&lt;/g&gt;&lt;g id="14"&gt;(&lt;/g&gt;&lt;g id="15"&gt;z, t&lt;/g&gt;&lt;g id="16"&gt;m&lt;/g&gt;&lt;g id="17"&gt;)]&lt;/g&gt;&lt;g id="18"&gt;; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &lt;/g&gt;&lt;g id="19"&gt;0 : : : z ::: L &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&lt;/g&gt;&lt;g id="20"&gt;(Equation 16)&lt;/g&gt;
LAB;
        self::assertEquals($this->expected_segment, ( new RestoreXliffTagsForView )->transform($this->source_segment));
    }

    /**
     * @group regression
     * 
     * original_input_segment= <g id="1">•••••••••</g><g id="2"> EMILIA-ROMAGNA</g>
     */
    public function test_restorexlifftagsforview_7()
    {
        $this->source_segment = <<<LAB
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##•••••••••##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN## EMILIA-ROMAGNA##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<LAB
&lt;g id="1"&gt;•••••••••&lt;/g&gt;&lt;g id="2"&gt; EMILIA-ROMAGNA&lt;/g&gt;
LAB;
        self::assertEquals($this->expected_segment, ( new RestoreXliffTagsForView )->transform($this->source_segment));
    }

    /**
     * @group regression
     * 
     */
    public function test_restorexlifftagsforview_nullvalue()
    {
        self::assertEquals("", ( new RestoreXliffTagsForView )->transform(null));
    }
}