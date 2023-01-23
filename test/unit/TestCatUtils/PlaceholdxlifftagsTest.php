<?php

use Matecat\SubFiltering\Filters\PlaceHoldXliffTags;

/**
 * @group regression
 * @covers PlaceHoldXliffTags::transform
 * this battery of tests sends one string in input as $source_segment to ( new PlaceHoldXliffTags() )->transform method and
 * verifies that the output is equal to the $expected_segment.
 * User: dinies
 * Date: 31/03/16
 * Time: 15.51
 */
class PlaceholdxlifftagsTest extends AbstractTest
{
    protected $source_segment;
    protected $expected_segment;
    /**
     * @group regression
     * @covers PlaceHoldXliffTags::transform
     */
    public function testplaceholdxlifftags_japanese_short()
    {
        $this->source_segment = <<<'LAB'
<g id="1">6) </g><g id="2">阪  眞</g><g id="3">: </g><bx id="4"/>胃術後の栄養障害と栄養補給法．
LAB;
        $this->expected_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##6) ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##阪  眞##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##: ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##YnggaWQ9IjQiLw==##GREATERTHAN##胃術後の栄養障害と栄養補給法．
LAB;
        $this->assertEquals($this->expected_segment, ( new PlaceHoldXliffTags() )->transform($this->source_segment));
    }

    /**
     * @group regression
     * @covers PlaceHoldXliffTags::transform
     */
    public function testplaceholdxlifftags_japanese_long()
    {
        $this->source_segment = <<<'LAB'
<g id="1">προσωπικού </g><g id="2">και<g id="3"> ιατρείο).</g></g>
<g id="1">ΤΕΥΧΟΣ ΔΕΥΤΕΡΟ</g><g id="2">  </g><g id="3">Αο, Φύλλου 2326</g>
<g id="1">2 </g><g id="2">.</g>
LAB;
        $this->expected_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##προσωπικού ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##και##LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN## ιατρείο).##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##ΤΕΥΧΟΣ ΔΕΥΤΕΡΟ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##  ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##Αο, Φύλλου 2326##LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##2 ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##.##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->assertEquals($this->expected_segment, ( new PlaceHoldXliffTags() )->transform($this->source_segment));
    }

    /**
     * @group regression
     * @covers PlaceHoldXliffTags::transform
     */
    public function testplaceholdxlifftags_english_short()
    {
        $this->source_segment = <<<'LAB'
<g id="1">12.3.</g><g id="2"> Upon termination of this Agreement for whatever reasons, the Franchisee shall at the request of the Franchisor promptly return all documentation in the possession or control of the Franchisee relating to the Pr</g><bx id="3"/>oducts, Services or business activities and affairs of the Franchisor.
LAB;
        $this->expected_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##12.3.##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN## Upon termination of this Agreement for whatever reasons, the Franchisee shall at the request of the Franchisor promptly return all documentation in the possession or control of the Franchisee relating to the Pr##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##YnggaWQ9IjMiLw==##GREATERTHAN##oducts, Services or business activities and affairs of the Franchisor.
LAB;
        $this->assertEquals($this->expected_segment, ( new PlaceHoldXliffTags() )->transform($this->source_segment));
    }

    /**
     * @group regression
     * @covers PlaceHoldXliffTags::transform
     */
    public function testplaceholdxlifftags_deustsch()
    {
        $this->source_segment = <<<'LAB'
<g id="2">derselbe soll dir den Kopf zertreten“</g>,  (Hinweis auf Satan) und du wirst ihn in die Ferse stechen (Hinweis auf den Tode des Messias, Ferse hat eine wichtige Bedeutung in der semitischen Kultur).
LAB;
        $this->expected_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##derselbe soll dir den Kopf zertreten“##LESSTHAN##L2c=##GREATERTHAN##,  (Hinweis auf Satan) und du wirst ihn in die Ferse stechen (Hinweis auf den Tode des Messias, Ferse hat eine wichtige Bedeutung in der semitischen Kultur).
LAB;
        $this->assertEquals($this->expected_segment, ( new PlaceHoldXliffTags() )->transform($this->source_segment));
    }

    /**
     * @group regression
     * @covers PlaceHoldXliffTags::transform
     */
    public function testplaceholdxlifftags_very_long()
    {
        $this->source_segment = <<<'LAB'
<g id="1">25    </g>would result in uniform displacement front and least risk of water breakthrough.
<g id="1">20    </g><g id="2">[0080] </g>Placement of FCDs with equal properties in such a situation would result in higher breakthrough risk in the beginning and end parts of the production well.
<g id="1">&lt;p∆S</g><g id="2">in</g><g id="3">µ</g><g id="4">in </g><g id="5">ay</g>
<g id="2">k</g><g id="3">0</g><g id="4">k</g><g id="5">'    </g><g id="6">ap</g>
<g id="2">[0079] </g><g id="3">V</g><g id="4">f </g><g id="5">= - </g>
LAB;
        $this->expected_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##25    ##LESSTHAN##L2c=##GREATERTHAN##would result in uniform displacement front and least risk of water breakthrough.
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##20    ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##[0080] ##LESSTHAN##L2c=##GREATERTHAN##Placement of FCDs with equal properties in such a situation would result in higher breakthrough risk in the beginning and end parts of the production well.
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##&lt;p∆S##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##in##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##µ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##in ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##ay##LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##k##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##0##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##k##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##'    ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNiI=##GREATERTHAN##ap##LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##[0079] ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##V##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##f ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##= - ##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->assertEquals($this->expected_segment, ( new PlaceHoldXliffTags() )->transform($this->source_segment));
    }
}