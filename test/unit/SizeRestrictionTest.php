<?php

namespace unit;

use LQA\SizeRestriction;
use PHPUnit_Framework_TestCase;

class SizeRestrictionTest extends PHPUnit_Framework_TestCase {
    /**
     * @test
     */
    public function test_with_too_long_string() {
        $string = '##$_0A$####$_09$####$_09$####$_09$####$_09$####$_09$##&lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;Age (if exact date is not available&lt;ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;##$_0A$####$_09$####$_09$####$_09$####$_09$####$_09$##&lt;ph id="source2_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UyIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTIiJmd0Ow==" dataRef="source2" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6ckZvbnRzJmd0OyZsdDsvdzpyRm9udHMmZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIxIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjEiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDt3OmhpZ2hsaWdodCB3OnZhbD0id2hpdGUiJmd0OyZsdDsvdzpoaWdobGlnaHQmZ3Q7Jmx0Oy93OnJQciZndDsmbHQ7dzp0Jmd0OyZsdDsvdzp0Jmd0OyZsdDsvdzpyJmd0Ow=="/&gt; &lt;day,month,year&gt;&nbsp; &lt;ph id="source2_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source2" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6ckZvbnRzJmd0OyZsdDsvdzpyRm9udHMmZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIxIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjEiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDt3OmhpZ2hsaWdodCB3OnZhbD0id2hpdGUiJmd0OyZsdDsvdzpoaWdobGlnaHQmZ3Q7Jmx0Oy93OnJQciZndDsmbHQ7dzp0Jmd0OyZsdDsvdzp0Jmd0OyZsdDsvdzpyJmd0Ow=="/&gt;##$_0A$####$_09$####$_09$####$_09$####$_09$####$_09$##&lt;ph id="source3_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UzIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTMiJmd0Ow==" dataRef="source3" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;or we have work/education history to prove the age difference)&lt;ph id="source3_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source3" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;##$_0A$####$_09$####$_09$####$_09$####$_09$##';

        $sizeRestriction = new SizeRestriction( $string, 55 );

        $this->assertFalse( $sizeRestriction->checkLimit() );
        $this->assertEquals( -84, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_limit_string() {
        $string = 'dsadsads asd dsaddsadsadsadsa dsadsads asd dsaddsadsad ';

        $sizeRestriction = new SizeRestriction( $string, 55 );

        $this->assertTrue( $sizeRestriction->checkLimit() );
        $this->assertEquals( 0, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_a_short_string() {
        $string = 'ciao &lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;';

        $sizeRestriction = new SizeRestriction( $string, 55 );

        $this->assertTrue( $sizeRestriction->checkLimit() );
        $this->assertEquals( 50, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_a_russian_string() {
        $string = 'Лорем ипсум долор сит амет, еи нец цонгуе граеце примис';

        $sizeRestriction = new SizeRestriction( $string, 55 );

        $this->assertTrue( $sizeRestriction->checkLimit() );
        $this->assertEquals( 0, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_a_japanese_string() {
        $string = '9検オフユハ報37覚安チヘリヨ稿漢前のたむほ面今めンざれ握面みレ';

        $sizeRestriction = new SizeRestriction( $string, 32 );

        $this->assertTrue( $sizeRestriction->checkLimit() );
        $this->assertEquals( 0, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_a_chinese_string() {
        $string = '在結個發我者從改區及老亮：許了聲來而演下感收不臺的港';

        $sizeRestriction = new SizeRestriction( $string, 26 );

        $this->assertTrue( $sizeRestriction->checkLimit() );
        $this->assertEquals( 0, $sizeRestriction->getCharactersRemaining() );
    }
}