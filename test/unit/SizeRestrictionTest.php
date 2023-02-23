<?php

namespace unit;

use FeatureSet;
use LQA\SizeRestriction;
use PHPUnit_Framework_TestCase;

class SizeRestrictionTest extends PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function test_with_too_long_string() {
        $string = '##$_0A$####$_09$####$_09$####$_09$####$_09$####$_09$##&lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;Age (if exact date is not available&lt;ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;##$_0A$####$_09$####$_09$####$_09$####$_09$####$_09$##&lt;ph id="source2_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UyIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTIiJmd0Ow==" dataRef="source2" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6ckZvbnRzJmd0OyZsdDsvdzpyRm9udHMmZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIxIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjEiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDt3OmhpZ2hsaWdodCB3OnZhbD0id2hpdGUiJmd0OyZsdDsvdzpoaWdobGlnaHQmZ3Q7Jmx0Oy93OnJQciZndDsmbHQ7dzp0Jmd0OyZsdDsvdzp0Jmd0OyZsdDsvdzpyJmd0Ow=="/&gt; &lt;day,month,year&gt;&nbsp; &lt;ph id="source2_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source2" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6ckZvbnRzJmd0OyZsdDsvdzpyRm9udHMmZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIxIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjEiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDt3OmhpZ2hsaWdodCB3OnZhbD0id2hpdGUiJmd0OyZsdDsvdzpoaWdobGlnaHQmZ3Q7Jmx0Oy93OnJQciZndDsmbHQ7dzp0Jmd0OyZsdDsvdzp0Jmd0OyZsdDsvdzpyJmd0Ow=="/&gt;##$_0A$####$_09$####$_09$####$_09$####$_09$####$_09$##&lt;ph id="source3_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UzIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTMiJmd0Ow==" dataRef="source3" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;or we have work/education history to prove the age difference)&lt;ph id="source3_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source3" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;##$_0A$####$_09$####$_09$####$_09$####$_09$##';

        $sizeRestriction = new SizeRestriction( $string, new FeatureSet() );

        $this->assertFalse( $sizeRestriction->checkLimit(55) );
        $this->assertEquals( -84, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_limit_string() {
        $string = 'dsadsads asd dsaddsadsadsadsa dsadsads asd dsaddsadsad ';

        $sizeRestriction = new SizeRestriction( $string, new FeatureSet() );

        $this->assertTrue( $sizeRestriction->checkLimit(55) );
        $this->assertEquals( 0, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_a_short_string() {
        $string = 'ciao &lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:Jmx0O3c6ciZndDsmbHQ7dzpyUHImZ3Q7Jmx0O3c6c3ogdzp2YWw9IjIwIiZndDsmbHQ7L3c6c3omZ3Q7Jmx0O3c6c3pDcyB3OnZhbD0iMjAiJmd0OyZsdDsvdzpzekNzJmd0OyZsdDsvdzpyUHImZ3Q7Jmx0O3c6dCZndDsmbHQ7L3c6dCZndDsmbHQ7L3c6ciZndDs="/&gt;';

        $sizeRestriction = new SizeRestriction( $string, new FeatureSet() );

        $this->assertTrue( $sizeRestriction->checkLimit(55) );
        $this->assertEquals( 50, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_with_a_russian_string() {
        $string = 'Лорем ипсум долор сит амет, еи нец цонгуе граеце примис';

        $sizeRestriction = new SizeRestriction( $string, new FeatureSet() );

        $this->assertTrue( $sizeRestriction->checkLimit(55) );
        $this->assertEquals( 0, $sizeRestriction->getCharactersRemaining() );
    }

    /**
     * @test
     */
    public function test_CJK_string() {

        $strings = [
            'ch' => [
                '「AirCover」？' => 14,
                '？' => 2,
                '什么是「AirCover 四海无忧」？' => 29,
                '我們有關於你 Airbnb 帳號的重要消息。' => 36,
            ],
            'jp' => [
                'これを受け、今後Airbnbでは予約やホスティングを行えませんのでご了承ください。' => 76,
                'あなたのカテゴリ' => 16,
                'Airbnb Plusのリ​⁠​ス​⁠​テ​⁠​ィ​⁠​ン​⁠​グ​⁠​を管​⁠​理​⁠​す​⁠​る新​⁠​し​⁠​い​⁠​方​⁠​法' => 45,
                'AirCoverとは？' => 14,
                'Aon 在 FCA 的登记号为 310451。' => 30,
                '9検オフユハ報37覚安チヘリヨ稿漢前のたむほ面今めンざれ握面みレ' => 61,
                '皆さんこんにちは、トウフグのコウイチでございます。ハロー！' => 58,
            ],
            'ko' => [
                '​2023년 1월 24일 또는 그 후에 에어비앤비 계정을 만들었어요. ' => 59,
                '​2023년' => 6,
                '안녕하세요' => 10,
                '어떤 약관이 적용되나요?' => 23,
                ' (기본)' => 7,
                '내 카테고리' => 11,
            ],
            'others' => [
                'रिमाइंडर: कागदपत्र आता अपलोड करा' => 32,
                'ভেনিছতকৈ অধিক খালৰ সৈতে' => 23,
                'वेनिस की तुलना में अधिक नहरों के साथ' => 36,
                'ವೆನಿಸ್‌ಗಿಂತ ಹೆಚ್ಚಿನ ಕಾಲುವೆಗಳೊಂದಿಗೆ' => 33,
                'വെനീസിനേക്കാളും' => 15,
                'වැනීසියට වඩා වැඩි' => 17,
                'வெனிஸை' => 6,
                'ตัวอักษรไทย' => 11,
                'తెలుగు లిపి' => 11,
                'https://www.uber.com/blog/perficient-simplifying-business-travel/' => 65,
            ]
        ];

        foreach($strings as $lang => $langStrings){
            foreach ($langStrings as $string => $limit){
                $this->sizeRestrictionAsserts($string, $limit);
            }
        }
    }

    /**
     * @test
     * @throws \Exception
     */
    public function test_string_with_emoji() {

        $strings = [
           '😀 This is an emoji' => 19,
            '😁 This is an emoji' => 19,
            '😂 This is an emoji' => 19,
            '🤣 This is an emoji' => 19,
            '😃 This is an emoji' => 19,
            '😄 This is an emoji' => 19,
            '😅 This is an emoji' => 19,
            '😆 This is an emoji' => 19,
            '😉 This is an emoji' => 19,
            '😊 This is an emoji' => 19,
            '😋 This is an emoji' => 19,
            '😎 This is an emoji' => 19,
            '😍 This is an emoji' => 19,
            '😘 This is an emoji' => 19,
            '🥰 This is an emoji' => 19,
            '😗 This is an emoji' => 19,
            '😙 This is an emoji' => 19,
            '😚 This is an emoji' => 19,
            '🙂 This is an emoji' => 19,
            '🤗 This is an emoji' => 19,
            '🤩 This is an emoji' => 19,
            '🤔 This is an emoji' => 19,
            '🤨 This is an emoji' => 19,
            '😐 This is an emoji' => 19,
            '😑 This is an emoji' => 19,
            '😶 This is an emoji' => 19,
            '🙄 This is an emoji' => 19,
            '😏 This is an emoji' => 19,
            '😣 This is an emoji' => 19,
            '😥 This is an emoji' => 19,
            '😮 This is an emoji' => 19,
            '🤐 This is an emoji' => 19,
            '😯 This is an emoji' => 19,
            '😪 This is an emoji' => 19,
            '😫 This is an emoji' => 19,
            '😴 This is an emoji' => 19,
            '😌 This is an emoji' => 19,
            '😛 This is an emoji' => 19,
            '😜 This is an emoji' => 19,
            '😝 This is an emoji' => 19,
            '🤤 This is an emoji' => 19,
            '😒 This is an emoji' => 19,
            '😓 This is an emoji' => 19,
            '😔 This is an emoji' => 19,
            '😕 This is an emoji' => 19,
            '🙃 This is an emoji' => 19,
            '🤑 This is an emoji' => 19,
            '😲 This is an emoji' => 19,
            '🙁 This is an emoji' => 19,
            '😖 This is an emoji' => 19,
            '😞 This is an emoji' => 19,
            '😟 This is an emoji' => 19,
            '😤 This is an emoji' => 19,
            '😢 This is an emoji' => 19,
            '😭 This is an emoji' => 19,
            '😦 This is an emoji' => 19,
            '😧 This is an emoji' => 19,
            '😨 This is an emoji' => 19,
            '😩 This is an emoji' => 19,
            '🤯 This is an emoji' => 19,
            '😬 This is an emoji' => 19,
            '😰 This is an emoji' => 19,
            '😱 This is an emoji' => 19,
            '🥵 This is an emoji' => 19,
            '🥶 This is an emoji' => 19,
            '😳 This is an emoji' => 19,
            '🤪 This is an emoji' => 19,
            '😵 This is an emoji' => 19,
            '😡 This is an emoji' => 19,
            '😠 This is an emoji' => 19,
            '🤬 This is an emoji' => 19,
            '😷 This is an emoji' => 19,
            '🤒 This is an emoji' => 19,
            '🤕 This is an emoji' => 19,
            '🤢 This is an emoji' => 19,
            '🤮 This is an emoji' => 19,
            '🤧 This is an emoji' => 19,
            '😇 This is an emoji' => 19,
            '🤠 This is an emoji' => 19,
            '🤡 This is an emoji' => 19,
            '🥳 This is an emoji' => 19,
            '🥴 This is an emoji' => 19,
            '🥺 This is an emoji' => 19,
            '🤥 This is an emoji' => 19,
            '🤫 This is an emoji' => 19,
            '🤭 This is an emoji' => 19,
            '🧐 This is an emoji' => 19,
            '🤓 This is an emoji' => 19,
            '😈 This is an emoji' => 19,
            '👿 This is an emoji' => 19,
            '👹 This is an emoji' => 19,
            '👺 This is an emoji' => 19,
            '💀 This is an emoji' => 19,
            '👻 This is an emoji' => 19,
            '👽 This is an emoji' => 19,
            '🤖 This is an emoji' => 19,
            '💩 This is an emoji' => 19,
            '😺 This is an emoji' => 19,
            '😸 This is an emoji' => 19,
            '😹 This is an emoji' => 19,
            '😻 This is an emoji' => 19,
            '😼 This is an emoji' => 19,
            '😽 This is an emoji' => 19,
            '🙀 This is an emoji' => 19,
            '😿 This is an emoji' => 19,
            '😾 This is an emoji' => 19,
        ];

        foreach ($strings as $string => $limit){
            $this->sizeRestrictionAsserts($string, $limit);
        }
    }

    /**
     * @param $string
     * @param $limit
     * @throws \Exception
     */
    private function sizeRestrictionAsserts($string, $limit)
    {
        $sizeRestriction = new SizeRestriction( $string, new FeatureSet() );

        $this->assertTrue( $sizeRestriction->checkLimit($limit), "Failed: '" . $string . "'" );
        $this->assertEquals( 0, $sizeRestriction->getCharactersRemaining($limit), "Failed: '" . $string . "'" );
    }
}