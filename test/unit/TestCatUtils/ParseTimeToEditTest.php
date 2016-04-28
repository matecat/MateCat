<?php

/**
 * @group regression
 * @covers CatUtils::parse_time_to_edit
 * this battery of tests sends one integer in input as $source_time to CatUtils::parse_time_to_edit method and
 * verifies that the output is equal to the array contained in $expected_time.
 * User: dinies
 * Date: 04/04/16
 * Time: 16.47
 */
class ParseTimeToEditTest extends AbstractTest
{
    protected $source_time;
    protected $expected_time;

    /**
     * @group regression
     * @covers CatUtils::parse_time_to_edit
     */
    public function test_parse_time_to_edit_small_input()
    {
        $this->source_time = 2345;
        $this->expected_time = array("00", "00", "02", 345);
        $this->assertEquals($this->expected_time, CatUtils::parse_time_to_edit($this->source_time));
    }


    /**
     * @group regression
     * @covers CatUtils::parse_time_to_edit
     */
    public function test_parse_time_to_edit_bigger_than_maxInt_input()
    {
        $this->source_time = 346543847623214134341343498008990;
        $this->expected_time = array('-39', '-34', '-31', -392);
        $this->assertEquals($this->expected_time, CatUtils::parse_time_to_edit($this->source_time));
    }

    /**
     * @group regression
     * @covers CatUtils::parse_time_to_edit
     */
    public function test_parse_time_to_edit_string_number()
    {
        $this->source_time = "1234";
        $this->expected_time = array('00', '00', '01', 234);
        $this->assertEquals($this->expected_time, CatUtils::parse_time_to_edit($this->source_time));
    }

    /**
     * @group regression
     * @covers CatUtils::parse_time_to_edit
     */
    public function test_parse_time_to_edit_float_number()
    {
        $this->source_time = (9 / 2);
        $this->expected_time = array('00', '00', '00', 4);
        $this->assertEquals($this->expected_time, CatUtils::parse_time_to_edit($this->source_time));
    }


    /**
     * @group regression
     * @covers CatUtils::parse_time_to_edit
     */
    public function test_parse_time_to_edit_normal_input()
    {
        $this->source_time = 3469000976;
        $this->expected_time = array("03", "36", "40", 976);
        $this->assertEquals($this->expected_time, CatUtils::parse_time_to_edit($this->source_time));
    }


    /**
     * @group regression
     * @covers CatUtils::parse_time_to_edit
     */
    public function test_parse_time_to_edit_unexpected_string_input()
    {
        $this->source_time = "hello what's time is it ?";
        $this->expected_time = array("00", "00", "00", "00");
        $this->assertEquals($this->expected_time, CatUtils::parse_time_to_edit($this->source_time));
    }

    /**
     * @group regression
     * @covers CatUtils::parse_time_to_edit
     */
    public function test_parse_time_to_edit_unexpected_array_input()
    {
        $this->source_time = array("00", "00", "00", "00");
        $this->setExpectedException('\InvalidArgumentException');
        CatUtils::parse_time_to_edit($this->source_time);
    }
}