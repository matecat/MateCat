<?php

use Model\Jobs\ChunkOptionsSanitizer;
use TestHelpers\AbstractTest;


/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/10/2016
 * Time: 11:43
 */
class ProjectOptionSanitizerTest extends AbstractTest
{

    function testSpeech2TextIsTrue()
    {
        $sanitizer = new ChunkOptionsSanitizer(['speech2text' => true]);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['speech2text' => 1], $result);
    }

    function testSpeech2TextIsNotTrue()
    {
        $sanitizer = new ChunkOptionsSanitizer(['speech2text' => false]);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['speech2text' => 0], $result);
    }

    function testLexiQaIsLeftTrueForValidLanguageCombinations()
    {
        $sanitizer = new ChunkOptionsSanitizer(['lexiqa' => true]);
        $sanitizer->setLanguages('en-US', ['en-GB']);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['lexiqa' => 1], $result);
    }

    function testLexiQaIsSanitizedForOddLanguages()
    {
        $sanitizer = new ChunkOptionsSanitizer(['lexiqa' => true]);
        $sanitizer->setLanguages('en-US', ['es-MX']);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['lexiqa' => 1], $result);
    }

    function testTagProjecttionIsTrueForValidLanguages()
    {
        $sanitizer = new ChunkOptionsSanitizer(['tag_projection' => true]);
        $sanitizer->setLanguages('en-US', ['es-MX']);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['tag_projection' => 1], $result);
    }

    function testTagProjecttionIsSanitizedForOddLanguages()
    {
        $sanitizer = new ChunkOptionsSanitizer(['tag_projection' => true]);
        $sanitizer->setLanguages('en-US', ['ru-RU']);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['tag_projection' => 1], $result);
    }

    function testSegmentationRuleIsSetWhenValid()
    {
        $sanitizer = new ChunkOptionsSanitizer(['segmentation_rule' => 'patent']);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['segmentation_rule' => 'patent'], $result);
    }

    function testSegmentationRuleIsSanitized()
    {
        $sanitizer = new ChunkOptionsSanitizer(['segmentation_rule' => 'invalid']);
        $result = $sanitizer->sanitize();
        $this->assertEquals([], $result);
    }

    function testUnknownKeysPassThrough()
    {
        $sanitizer = new ChunkOptionsSanitizer([
            'tag_projection' => true,
            'lexiqa' => false,
            'another_key_set_by_plugin' => 42
        ]);
        $sanitizer->setLanguages('en-US', ['es-MX']);

        $this->assertEquals([
            'tag_projection' => 1,
            'lexiqa' => 0,
            'another_key_set_by_plugin' => 42
        ], $sanitizer->sanitize());
    }

    function testLexiQaWorksWithBooleanTrue()
    {
        $sanitizer = new ChunkOptionsSanitizer(['lexiqa' => true]);
        $sanitizer->setLanguages('en-US', ['en-GB']);
        $result = $sanitizer->sanitize();
        $this->assertEquals(['lexiqa' => 1], $result);
    }


}