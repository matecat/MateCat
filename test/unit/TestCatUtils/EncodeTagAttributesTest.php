<?php

/**
 * @group regression
 * @covers CatUtils::__encode_tag_attributes
 * this battery of tests sends one string in input as $source_segment to CatUtils::__encode_tag_attributes method and
 * verifies that the output is equal to the $expected_segment.
 * User: dinies
 * Date: 05/04/16
 * Time: 12.30
 */
class EncodeTagAttributesTest extends AbstractTest
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
        $this->method = $this->reflector->getMethod('__encode_tag_attributes');
        $this->method->setAccessible(true);
    }

    /**
     * @group regression
     * @covers CatUtils::__encode_tag_attributes
     */
    public function test_encode_tag_attributes_japanese_short()
    {
        $this->input_param = <<<'LAB'
##LESSTHAN##g id="1"##GREATERTHAN##6) ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##阪  眞##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="3"##GREATERTHAN##: ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##bx id="4"/##GREATERTHAN##胃術後の栄養障害と栄養補給法．
LAB;
        $this->output_param = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##6) ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##阪  眞##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##: ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##YnggaWQ9IjQiLw==##GREATERTHAN##胃術後の栄養障害と栄養補給法．
LAB;
        $this->assertEquals($this->output_param, $this->method->invoke($this->reflectedClass, $this->input_param));

    }


    /**
     * @group regression
     * @covers CatUtils::__encode_tag_attributes
     */
    public function test_encode_tag_attributes_japanese_long()
    {
        $this->input_param = <<<'LAB'
##LESSTHAN##g id="1"##GREATERTHAN##προσωπικού ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##και##LESSTHAN##g id="3"##GREATERTHAN## ιατρείο).##LESSTHAN##/g##GREATERTHAN####LESSTHAN##/g##GREATERTHAN##
##LESSTHAN##g id="1"##GREATERTHAN##ΤΕΥΧΟΣ ΔΕΥΤΕΡΟ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##  ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="3"##GREATERTHAN##Αο, Φύλλου 2326##LESSTHAN##/g##GREATERTHAN##
##LESSTHAN##g id="1"##GREATERTHAN##2 ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##.##LESSTHAN##/g##GREATERTHAN##
LAB;
        $this->output_param = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##προσωπικού ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##και##LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN## ιατρείο).##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##ΤΕΥΧΟΣ ΔΕΥΤΕΡΟ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##  ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##Αο, Φύλλου 2326##LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##2 ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##.##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->assertEquals($this->output_param, $this->method->invoke($this->reflectedClass, $this->input_param));

    }


    /**
     * @group regression
     * @covers CatUtils::__encode_tag_attributes
     */
    public function test_encode_tag_attributes_deustsch()
    {
        $this->input_param = <<<'LAB'
##LESSTHAN##g id="2"##GREATERTHAN##derselbe soll dir den Kopf zertreten“##LESSTHAN##/g##GREATERTHAN##,  (Hinweis auf Satan) und du wirst ihn in die Ferse stechen (Hinweis auf den Tode des Messias, Ferse hat eine wichtige Bedeutung in der semitischen Kultur).
LAB;
        $this->output_param = <<<'LAB'
##LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##derselbe soll dir den Kopf zertreten“##LESSTHAN##L2c=##GREATERTHAN##,  (Hinweis auf Satan) und du wirst ihn in die Ferse stechen (Hinweis auf den Tode des Messias, Ferse hat eine wichtige Bedeutung in der semitischen Kultur).
LAB;
        $this->assertEquals($this->output_param, $this->method->invoke($this->reflectedClass, $this->input_param));

    }


    /**
     * @group regression
     * @covers CatUtils::__encode_tag_attributes
     */
    public function test_encode_tag_attributes_very_long()
    {
        $this->input_param = <<<'LAB'
##LESSTHAN##g id="1"##GREATERTHAN##25    ##LESSTHAN##/g##GREATERTHAN##would result in uniform displacement front and least risk of water breakthrough.
##LESSTHAN##g id="1"##GREATERTHAN##20    ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##[0080] ##LESSTHAN##/g##GREATERTHAN##Placement of FCDs with equal properties in such a situation would result in higher breakthrough risk in the beginning and end parts of the production well.
##LESSTHAN##g id="1"##GREATERTHAN##&lt;p∆S##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="2"##GREATERTHAN##in##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="3"##GREATERTHAN##µ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="4"##GREATERTHAN##in ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="5"##GREATERTHAN##ay##LESSTHAN##/g##GREATERTHAN##
##LESSTHAN##g id="2"##GREATERTHAN##k##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="3"##GREATERTHAN##0##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="4"##GREATERTHAN##k##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="5"##GREATERTHAN##'    ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="6"##GREATERTHAN##ap##LESSTHAN##/g##GREATERTHAN##
##LESSTHAN##g id="2"##GREATERTHAN##[0079] ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="3"##GREATERTHAN##V##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="4"##GREATERTHAN##f ##LESSTHAN##/g##GREATERTHAN####LESSTHAN##g id="5"##GREATERTHAN##= - ##LESSTHAN##/g##GREATERTHAN##
LAB;
        $this->output_param = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##25    ##LESSTHAN##L2c=##GREATERTHAN##would result in uniform displacement front and least risk of water breakthrough.
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##20    ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##[0080] ##LESSTHAN##L2c=##GREATERTHAN##Placement of FCDs with equal properties in such a situation would result in higher breakthrough risk in the beginning and end parts of the production well.
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##&lt;p∆S##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##in##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##µ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##in ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##ay##LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##k##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##0##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##k##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##'    ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNiI=##GREATERTHAN##ap##LESSTHAN##L2c=##GREATERTHAN##
##LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##[0079] ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##V##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##f ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##= - ##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->assertEquals($this->output_param, $this->method->invoke($this->reflectedClass, $this->input_param));

    }


}
