<?php

use Matecat\SubFiltering\MateCatFilter;

/**
 * @group regression
 * @covers CatUtils::parseSegmentSplit
 * this battery of tests sends one string in input as $source_segment and the $separator
 * to CatUtils::parseSegmentSplit method and verifies that the output is an array
 * with the first element that is equal to the $expected_segment and the second is an array
 * equal to the array called $chunk_positions .
 * User: dinies
 * Date: 05/04/16
 * Time: 11.20
 */
class ParseSegmentSplitTest extends AbstractTest
{
    protected $source_segment;
    protected $separator;
    protected $expected_segment;
    protected $chunk_position;

    public function setUp()
    {
        parent::setUp();
        $this->separator = " ";
        $this->chunk_position = array();
    }

    /**
     * @group regression
     * @covers CatUtils::parseSegmentSplit
     */
    public function test_parseSegmentSplit_without_modifications()
    {
        $this->source_segment = <<<'LAB'
<g id="1">&#1048766;</g><g id="2"> </g><g id="3">Gâche à mortaiser;</g>
LAB;
        $this->expected_segment = <<<'LAB'
<g id="1">&#1048766;</g><g id="2"> </g><g id="3">Gâche à mortaiser;</g>
LAB;
        self::assertEquals(array($this->expected_segment, $this->chunk_position), CatUtils::parseSegmentSplit($this->source_segment, $this->separator, $Filter = MateCatFilter::getInstance(new \FeatureSet())
        ) );

    }


    /**
     * @group regression
     * @covers CatUtils::parseSegmentSplit
     * original_input_segment= <g id="1">􀂾</g><g id="2"> </g><bx id="3"/>Porte d'accès au bureau [1-1-13] d'entrée depuis le haut de l'escalier (P118 et P119)
     */
    public function test_parseSegmentSplit_without_modifications_with_special_char()
    {
        $this->source_segment = <<<'LAB'
<g id="1">&#1048766;</g><g id="2"> </g><bx id="3"/>Porte d'accès au bureau [1-1-13] d'entrée depuis le haut de l'escalier (P118 et P119)
LAB;
        $this->expected_segment = <<<'LAB'
<g id="1">&#1048766;</g><g id="2"> </g><bx id="3"/>Porte d'accès au bureau [1-1-13] d'entrée depuis le haut de l'escalier (P118 et P119)
LAB;
        self::assertEquals(array($this->expected_segment, $this->chunk_position), CatUtils::parseSegmentSplit($this->source_segment, $this->separator, $Filter = MateCatFilter::getInstance(new \FeatureSet()) ));

    }


    /**
     * @group regression
     * @covers CatUtils::parseSegmentSplit
     * original_input_segment= <g id="1">3.2.124   123 - E</g><g id="2">NSE##$_0A$##MBLE A   PPUI W##$_09$##C ET NICCHIA DE S##$_09$##OUTIEN DU RA    NGEMENT LUMINEUX</g>
     */
    public function test_parseSegmentSplit_with_spaces_tabulations_new_lines()
    {
        $this->source_segment = <<<'LAB'
<g id="1">3.2.124   123 - E</g><g id="2">NSE
MBLE A   PPUI W	C ET NICCHIA DE S	OUTIEN DU RA    NGEMENT LUMINEUX</g>
LAB;
        $this->expected_segment = <<<'LAB'
<g id="1">3.2.124   123 - E</g><g id="2">NSE
MBLE A   PPUI W	C ET NICCHIA DE S	OUTIEN DU RA    NGEMENT LUMINEUX</g>
LAB;
        self::assertEquals(array($this->expected_segment, $this->chunk_position), CatUtils::parseSegmentSplit($this->source_segment, $this->separator, $Filter = MateCatFilter::getInstance(new \FeatureSet()) ));

    }


}