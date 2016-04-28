<?php

/**
 * @group regression
 * @covers CatUtils::restore_xliff_tags
 * this battery of tests use the principle of reflection and sends one string in input as $source_segment
 * to CatUtils::restore_xliff_tags method and verifies that the output is  equal to the $expected_segment .
 * User: dinies
 * Date: 05/04/16
 * Time: 11.53
 */
class RestoreXliffTagsTest extends AbstractTest
{
protected $refelctor;
    protected $method;
    protected $source_segment;
    protected $expected_segment;

    public function setUp(){
        parent::setUp();
        $this->reflectedClass = new CatUtils();
        $this->refelctor = new ReflectionClass($this->reflectedClass);
        $this->method = $this->refelctor->getMethod('restore_xliff_tags');
        $this->method->setAccessible(true);
    }

    /**
     * @group regression
     * @covers CatUtils::restore_xliff_tags
     * original_input_segment= <g id="1">3.2.128 M127 - C</g><g id="2">HAMBRANLE DE FENETRE LUMINEUX </g><g id="3">- </g><g id="4">NEGOZIO </g><g id="5">"</g><g id="6">SUN SCREEN</g><g id="7">"</g>
     */
    public function test_restore_xliff_tags_basic()
    {
        $this->source_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##3.2.128 M127 - C##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##HAMBRANLE DE FENETRE LUMINEUX ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##- ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##NEGOZIO ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNSI=##GREATERTHAN##"##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNiI=##GREATERTHAN##SUN SCREEN##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNyI=##GREATERTHAN##"##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<'LAB'
<g id="1">3.2.128 M127 - C</g><g id="2">HAMBRANLE DE FENETRE LUMINEUX </g><g id="3">- </g><g id="4">NEGOZIO </g><g id="5">"</g><g id="6">SUN SCREEN</g><g id="7">"</g>
LAB;

        self::assertEquals($this->expected_segment, $this->method->invoke($this->reflectedClass, $this->source_segment));
    }


    /**
     * @group regression
     * @covers CatUtils::restore_xliff_tags
     * original_input_segment= <g id="1">􀂾</g><g id="2"> </g><g id="3">d'une toile d'occultazione en fibre de verre enduite et / ou composito PVC, de 330g / m² à 630 g / m²,</g>
     */
    public function test_restore_xliff_tags_special_char()
    {
        $this->source_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##&#1048766;##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN## ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##d'une toile d'occultazione en fibre de verre enduite et / ou composito PVC, de 330g / m² à 630 g / m²,##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<'LAB'
<g id="1">&#1048766;</g><g id="2"> </g><g id="3">d'une toile d'occultazione en fibre de verre enduite et / ou composito PVC, de 330g / m² à 630 g / m²,</g>
LAB;

        self::assertEquals($this->expected_segment, $this->method->invoke($this->reflectedClass, $this->source_segment));
    }


    /**
     * @group regression
     * @covers CatUtils::restore_xliff_tags
     * original_input_segment= <g id="1">3.2.122 M121 - B</g><g id="2">Loc atea panelak kabinete atea A </g><g id="3">- </g><g id="4">Margoak eta</g>
     */
    public function test_restore_xliff_tags_target_language_basque_with_tags()
    {
        $this->source_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##3.2.122 M121 - B##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN##Loc atea panelak kabinete atea A ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##- ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iNCI=##GREATERTHAN##Margoak eta##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<'LAB'
<g id="1">3.2.122 M121 - B</g><g id="2">Loc atea panelak kabinete atea A </g><g id="3">- </g><g id="4">Margoak eta</g>
LAB;

        self::assertEquals($this->expected_segment, $this->method->invoke($this->reflectedClass, $this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::restore_xliff_tags
     * original_input_segment= <g id="1">􀂾</g><g id="2"> </g><g id="3">音響音響以下の手順は弱め。</g>
     */
    public function test_restore_xliff_tags_target_language_japanese_with_special_char()
    {
        $this->source_segment = <<<'LAB'
##LESSTHAN##ZyBpZD0iMSI=##GREATERTHAN##&#1048766;##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMiI=##GREATERTHAN## ##LESSTHAN##L2c=##GREATERTHAN####LESSTHAN##ZyBpZD0iMyI=##GREATERTHAN##音響音響以下の手順は弱め。##LESSTHAN##L2c=##GREATERTHAN##
LAB;
        $this->expected_segment = <<<'LAB'
<g id="1">&#1048766;</g><g id="2"> </g><g id="3">音響音響以下の手順は弱め。</g>
LAB;

        self::assertEquals($this->expected_segment, $this->method->invoke($this->reflectedClass, $this->source_segment));
    }


}