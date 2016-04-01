<?php

/**
 * Created by PhpStorm.
 * User: dinies
 * Date: 01/04/16
 * Time: 12.12
 */
class FastUnicode2ordTest extends AbstractTest{

    public function test_fastUnicode2ord_1(){

       $source_segment = <<<'LAB'
🛠
LAB;
        $int_expected= 128736;
        $this->assertEquals($int_expected, CatUtils::fastUnicode2ord($source_segment));
    }


    public function test_fastUnicode2ord_2(){
        $source_segment = <<<'LAB'
😴
LAB;
        $int_expected= 128564;
        $this->assertEquals($int_expected, CatUtils::fastUnicode2ord($source_segment));
    }


    public function test_fastUnicode2ord_3(){
        $source_segment = <<<'LAB'
😆
LAB;
        $int_expected= 128518;
        $this->assertEquals($int_expected, CatUtils::fastUnicode2ord($source_segment));
    }


    public function test_fastUnicode2ord_4(){
        $source_segment = <<<'LAB'
𐎆
LAB;
        $int_expected= 66438;
        $this->assertEquals($int_expected, CatUtils::fastUnicode2ord($source_segment));

    }


    public function test_fastUnicode2ord_anomalyimput_swichcase1(){
        $source_segment = <<<'LAB'
@
LAB;
        $int_expected= 64;
        $this->assertEquals($int_expected, CatUtils::fastUnicode2ord($source_segment));

    }


    public function test_fastUnicode2ord_anomalyimput_swichcase2(){
        $source_segment = <<<'LAB'
گ
LAB;
        $int_expected= 1711;
        $this->assertEquals($int_expected, CatUtils::fastUnicode2ord($source_segment));

    }


    public function test_fastUnicode2ord_anomalyimput_swichcase3(){
        $source_segment = <<<'LAB'
◕
LAB;
        $int_expected= 9685;
        $this->assertEquals($int_expected, CatUtils::fastUnicode2ord($source_segment));

    }
}