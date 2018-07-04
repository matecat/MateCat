<?php

/**
 * @group regression
 * @covers CatUtils::__decode_tag_attributes
 * this battery of tests sends one string in input as $source_segment to CatUtils::__decode_tag_attributes method and
 * verifies that the output is equal to the $expected_segment.
 * User: dinies
 * Date: 01/04/16
 * Time: 18.26
 */
class DecodeTagAttributesTest extends AbstractTest
{
    protected $reflector;
    protected $method;
    protected $input_param;
    protected $output_param;

    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = new CatUtils();
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod('__decode_tag_attributes');
        $this->method->setAccessible(true);
    }

    /**
     * @group regression
     * @covers CatUtils::__decode_tag_attributes
     * original_input_segment= <g id="1">[AH1]</g><g id="2">Is fold &amp; crease the same??</g>
     */
    public function test_decode_tag_attributes_1()
    {
        $this->input_param = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##[AH1]##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##Is fold & crease the same??##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->output_param = <<<'LAB'
##LESSTHAN##g id="1"##GREATERTHAN##[AH1]##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##Is fold & crease the same??##LESSTHAN##/g##GREATERTHAN##
LAB;

        $this->assertEquals($this->output_param, $this->method->invoke($this->reflectedClass, $this->input_param));

    }

    /**
     * @group regression
     * @covers CatUtils::__decode_tag_attributes
     * original_input_segment= <g id="1">总之，通过对</g><g id="2">2012-2015年间美企所中国军情研究的统计和特点分析，可以做出以下判断：美企所是保守主义思想浓</g><bx id="3"/>厚的智库，对中国军事力量的正常发展观点激进，态度偏激；美企所近年来中国军情研究主要聚焦在南海、东海等海洋领土争端问题上；美企所提出的诸如加强“航行自由”、联盟体系的建议在美国政府的政策举措上有所表现。<g id="2">从上文</g><g id="3">对26篇文章的内容简述，可以清晰地看出，美企所非常关注中国海空军力的发展，并以此作为加强美军在亚太地区军力部署、更新作战概念、增加军费预算的理由。</g>
     */
    public function test_decode_tag_attributes_2()
    {

        $this->input_param = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##总之，通过对##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##2012-2015年间美企所中国军情研究的统计和特点分析，可以做出以下判断：美企所是保守主义思想浓##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##YnggaWQ9IjMiLw==##GREATERTHAN##厚的智库，对中国军事力量的正常发展观点激进，态度偏激；美企所近年来中国军情研究主要聚焦在南海、东海等海洋领土争端问题上；美企所提出的诸如加强“航行自由”、联盟体系的建议在美国政府的政策举措上有所表现。##LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##从上文##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##对26篇文章的内容简述，可以清晰地看出，美企所非常关注中国海空军力的发展，并以此作为加强美军在亚太地区军力部署、更新作战概念、增加军费预算的理由。##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->output_param = <<<'LAB'
##LESSTHAN##g id="1"##GREATERTHAN##总之，通过对##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##2012-2015年间美企所中国军情研究的统计和特点分析，可以做出以下判断：美企所是保守主义思想浓##LESSTHAN##/g##GREATERTHAN####LESSTHAN##bx id="3"/##GREATERTHAN##厚的智库，对中国军事力量的正常发展观点激进，态度偏激；美企所近年来中国军情研究主要聚焦在南海、东海等海洋领土争端问题上；美企所提出的诸如加强“航行自由”、联盟体系的建议在美国政府的政策举措上有所表现。##LESSTHAN##g id="2"##GREATERTHAN##从上文##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="3"##GREATERTHAN##对26篇文章的内容简述，可以清晰地看出，美企所非常关注中国海空军力的发展，并以此作为加强美军在亚太地区军力部署、更新作战概念、增加军费预算的理由。##LESSTHAN##/g##GREATERTHAN##
LAB;

        $this->assertEquals($this->output_param, $this->method->invoke($this->reflectedClass, $this->input_param));

    }

    /**
     * @group regression
     * @covers CatUtils::__decode_tag_attributes
     * original_input_segment=  <g id="1">[0054] </g><g id="2">y<g id="3">(</g>z</g><g id="4">1</g><g id="5">, t</g><g id="6">m</g><g id="7">) </g><g id="8">= d - r</g><g id="9">O                                                                                                                      </g><g id="10">(Equation 11)</g>
     */
    public function test_decode_tag_attributes_3()
    {
        $this->input_param = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##[0054] ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##y##LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##(##LESSTHAN##L2c=##GREATERTHAN##z##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##1##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##, t##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNiI=##GREATERTHAN##m##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNyI=##GREATERTHAN##) ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iOCI=##GREATERTHAN##= d - r##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iOSI=##GREATERTHAN##O &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMTAi##GREATERTHAN##(Equation 11)##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->output_param = <<<'LAB'
##LESSTHAN##g id="1"##GREATERTHAN##[0054] ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##y##LESSTHAN##g id="3"##GREATERTHAN##(##LESSTHAN##/g##GREATERTHAN##z##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="4"##GREATERTHAN##1##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="5"##GREATERTHAN##, t##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="6"##GREATERTHAN##m##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="7"##GREATERTHAN##) ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="8"##GREATERTHAN##= d - r##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="9"##GREATERTHAN##O &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="10"##GREATERTHAN##(Equation 11)##LESSTHAN##/g##GREATERTHAN##
LAB;
        $this->assertEquals($this->output_param, $this->method->invoke($this->reflectedClass, $this->input_param));

    }

}