<?php

/**
 * Created by PhpStorm.
 * User: dinies
 * Date: 30/03/16
 * Time: 17.25
 */
class View2rawxliffTest extends AbstractTest{
    public function testview2rawxliff2()
    {
        $source_segment = <<<LAB
LAB;
        $expected_segment = <<<LAB

LAB;
        self::assertEquals($expected_segment, CatUtils::view2rawxliff($source_segment));
    }

}