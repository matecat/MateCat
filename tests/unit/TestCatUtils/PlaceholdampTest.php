<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers CatUtils::placeholdamp
 * this battery of tests sends one string in input as $source_segment to CatUtils::placeholdamp method and
 * verifies that the output is equal to the $expected_segment.
 * User: dinies
 * Date: 01/04/16
 * Time: 13.45
 */
class PlaceholdampTest extends AbstractTest
{
    protected $source_segment;
    protected $expected_segment;

    /**
     * @group regression
     * @covers CatUtils::placeholdamp
     */
    public function test_placeholdamp_base()
    {
        $this->source_segment = <<<'LAB'
<&>
LAB;
        $this->expected_segment = <<<'LAB'
<##AMPPLACEHOLDER##>
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::placeholdamp($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::placeholdamp
     */
    public function test_placeholdamp_null()
    {
        $this->source_segment = null;
        $this->expected_segment = "";
        $this->assertEquals($this->expected_segment, CatUtils::placeholdamp($this->source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::placeholdamp
     */
    public function test_placeholdamp_nomatches()
    {
        $this->source_segment = <<<'LAB'
<|\asòhg\\òsaldh<<<<<<<<<<f>
LAB;
        $this->expected_segment = <<<'LAB'
<|\asòhg\\òsaldh<<<<<<<<<<f>
LAB;
        $this->assertEquals($this->expected_segment, CatUtils::placeholdamp($this->source_segment));
    }


}