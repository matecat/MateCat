<?php

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

    /**
     * @group regression
     * @covers CatUtils::placeholdamp
     */
    public function test_placeholdamp_base()
    {
        $source_segment = <<<'LAB'
<&>
LAB;
        $expected_segment = <<<'LAB'
<##AMPPLACEHOLDER##>
LAB;
        $this->assertEquals($expected_segment, CatUtils::placeholdamp($source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::placeholdamp
     */
    public function test_placeholdamp_null()
    {
        $source_segment = null;
        $expected_segment = "";
        $this->assertEquals($expected_segment, CatUtils::placeholdamp($source_segment));
    }

    /**
     * @group regression
     * @covers CatUtils::placeholdamp
     */
    public function test_placeholdamp_nomatches()
    {
        $source_segment = <<<'LAB'
<|\asòhg\\òsaldh<<<<<<<<<<f>
LAB;
        $expected_segment = <<<'LAB'
<|\asòhg\\òsaldh<<<<<<<<<<f>
LAB;
        $this->assertEquals($expected_segment, CatUtils::placeholdamp($source_segment));
    }


}