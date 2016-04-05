<?php

/**
 * @group regression
 * @covers CatUtils::htmlentitiesFromUnicode
 * this battery of tests sends one character, that is chosen between a range of particular symbols
 * that have their representation in unicode that starts with an hexadecimal value included in "F0" and "F7",
 * in input as $source_segment to CatUtils::htmlentitiesFromUnicode method and
 * verifies that the output is a numeric value understandable by the utf-8 representation
 * concatenated with "&#" at the start and ";" at the end and that it matches with the value in $expected_segment.
 * User: dinies
 * Date: 31/03/16
 * Time: 16.36
 */
class HtmlentitiesFromUnicodeTest extends AbstractTest{

    /**
     * @group regression
     * @covers CatUtils::htmlentitiesFromUnicode
     */
    public function testhtmlentitiesFromUnicode1(){

        $source_segment = <<<'LAB'
𐍇
LAB;
        $expected_segment = <<<'LAB'
&#66375;
LAB;
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $source_segment );

        $this->assertEquals($expected_segment,$segment);
    }

    /**
     * @group regression
     * @covers CatUtils::htmlentitiesFromUnicode
     * original_segment=  <g 𐎆 𐏉</g>
     */
    public function testhtmlentitiesFromUnicode2(){
        //"<g 𐎆 𐏉</g>" real imput string
        $source_segment = <<<'LAB'
<g 𐎆 𐏉##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $expected_segment = <<<'LAB'
<g &#66438; &#66505;##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $source_segment );

        $this->assertEquals($expected_segment,$segment);
    }

    /**
     * @group regression
     * @covers CatUtils::htmlentitiesFromUnicode
     * original_segment= <g id="1">ψ</g>😴<g 😆id="2">🛠λ</g>
     */
    public function testhtmlentitiesFromUnicode3(){
//<g id="1">ψ</g>😴<g 😆id="2">🛠λ</g> initial string in input
        $source_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##ψ##LESSTHAN##L2c=##GREATERTHAN##😴<g 😆id="2">🛠λ##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $expected_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##ψ##LESSTHAN##L2c=##GREATERTHAN##&#128564;<g &#128518;id="2">&#128736;λ##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $source_segment );

        $this->assertEquals($expected_segment,$segment);
    }

    /**
     * @group regression
     * @covers CatUtils::htmlentitiesFromUnicode
     */
    public function testhtmlentitiesFromUnicode4(){
        $source_array=array();
        $source_alfa="😴";
        $source_array[0]=$source_alfa;
        $source_array[1]=$source_alfa;

        $expected_segment = <<<'LAB'
&#128564;
LAB;
        //$segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $source_segment );

        $this->assertEquals($expected_segment,CatUtils::htmlentitiesFromUnicode($source_array));
    }

    /**
     * @group regression
     * @covers CatUtils::htmlentitiesFromUnicode
     */
    public function testhtmlentitiesFromUnicode_artificial_feeding_of_parameters(){
        $source_array_1=array();
        $source_array_2=array();

        $source_alfa="😴";
        $source_beta="🛠";


        $source_array_1[0]=$source_alfa;
        $source_array_1[1]=$source_alfa;
        $source_array_2[0]=$source_beta;
        $source_array_2[1]=$source_beta;

        $expected_segment = <<<'LAB'
&#128564;&#128736;
LAB;
        //$segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $source_segment );
        $current_segment=CatUtils::htmlentitiesFromUnicode($source_array_1).CatUtils::htmlentitiesFromUnicode($source_array_2);
        $this->assertEquals($expected_segment,$current_segment);
    }


}
