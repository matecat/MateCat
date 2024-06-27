<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers CatUtils::fastUnicode2ord
 * this battery of tests sends one character, that is chosen between a range of particular symbols
 * that have their representation in unicode that starts with an hexadecimal value included in "F0" and "F7",
 * in input as $source_segment to CatUtils::fastUnicode2ord method and
 * verifies that the output is a numeric value understandable by the utf-8 representation
 * and that it matches with the value in $expected_segment.
 * User: dinies
 * Date: 01/04/16
 * Time: 12.12
 */
class FastUnicode2ordTest extends AbstractTest
{

    protected $source_segment;
    protected $int_expected;


    /**
     * @group regression
     * @covers CatUtils::fastUnicode2ord
     */
    public function test_fastUnicode2ord_1()
    {

        $this->source_segment = <<<'LAB'
🛠
LAB;
        $this->int_expected = 128736;
        $this->assertEquals($this->int_expected, CatUtils::fastUnicode2ord($this->source_segment));

    }

    /**
     * @group regression
     * @covers CatUtils::fastUnicode2ord
     */
    public function test_fastUnicode2ord_2()
    {
        $this->source_segment = <<<'LAB'
😴
LAB;
        $this->int_expected = 128564;
        $this->assertEquals($this->int_expected, CatUtils::fastUnicode2ord($this->source_segment));

    }

    /**
     * @group regression
     * @covers CatUtils::fastUnicode2ord
     */
    public function test_fastUnicode2ord_3()
    {
        $this->source_segment = <<<'LAB'
😆
LAB;
        $this->int_expected = 128518;
        $this->assertEquals($this->int_expected, CatUtils::fastUnicode2ord($this->source_segment));

    }

    /**
     * @group regression
     * @covers CatUtils::fastUnicode2ord
     */
    public function test_fastUnicode2ord_4()
    {
        $this->source_segment = <<<'LAB'
𐎆
LAB;
        $this->int_expected = 66438;
        $this->assertEquals($this->int_expected, CatUtils::fastUnicode2ord($this->source_segment));

    }

    /**
     * @group regression
     * @covers CatUtils::fastUnicode2ord
     */
    public function test_fastUnicode2ord_anomalyimput_swichcase1()
    {
        $this->source_segment = <<<'LAB'
@
LAB;
        $this->int_expected = 64;
        $this->assertEquals($this->int_expected, CatUtils::fastUnicode2ord($this->source_segment));


    }

    /**
     * @group regression
     * @covers CatUtils::fastUnicode2ord
     */
    public function test_fastUnicode2ord_anomalyimput_swichcase2()
    {
        $this->source_segment = <<<'LAB'
گ
LAB;
        $this->int_expected = 1711;
        $this->assertEquals($this->int_expected, CatUtils::fastUnicode2ord($this->source_segment));

    }

    /**
     * @group regression
     * @covers CatUtils::fastUnicode2ord
     */
    public function test_fastUnicode2ord_anomalyimput_swichcase3()
    {
        $this->source_segment = <<<'LAB'
◕
LAB;
        $this->int_expected = 9685;
        $this->assertEquals($this->int_expected, CatUtils::fastUnicode2ord($this->source_segment));

    }
}