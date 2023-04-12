<?php

class CatUtilsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @throws Exception
     */
    public function testSegmentRawWordCount()
    {
        $data = [
            'it-IT' => [
                '<ph id="source1" dataRef="source1"/>Ciao <ph id="source2" dataRef="source2"/>,<ph id="source3" dataRef="source3"/>' => 1,
                'La casa è bianca' => 4,
                'La casa %%placeholder%% è bianca' => 5,
                'La casa %{placeholder} è bianca' => 5,
                'La casa {%placeholder%} è bianca' => 5,
                'La casa @@placeholder@@ è bianca' => 5,
                'La casa {placeholder} è bianca' => 5,
                'La casa {{placeholder}} è bianca' => 5,
                'La casa {{ placeholder }} è bianca' => 5,
                'La casa %s è bianca' => 5,
                'La casa %u è bianca' => 5,
                'La casa %1$s è bianca' => 5,
                'La casa %2$d è bianca' => 5,
                'La casa %d è bianca' => 5,
                'La casa %@ è bianca' => 5,
                'La casa %1$i è bianca' => 5,
                'La casa %1$.2f è bianca' => 5,
                'La casa %.0f è bianca' => 5,
                'La casa %c è bianca' => 5,
                'La casa %2$@ è bianca' => 5,
                'La casa %x è bianca' => 5,
                'La casa %1%@ è bianca' => 5,
                'La casa %1$#@file@ è bianca' => 5,
                'La casa %#@file@ è bianca' => 5,
                'La casa $%1$.2f è bianca' => 5,
                'La casa %.0f% è bianca' => 5,
                'La casa %ld è bianca' => 5,
                'La casa %hi è bianca' => 5,
                'La casa %lu è bianca' => 5,
                'La casa %1 è bianca' => 5,
                'La casa %2 è bianca' => 5,
            ],
        ];

        foreach ($data as $language => $phrases){
            foreach ($phrases as $phrase => $count){
                $this->assertEquals($count, CatUtils::segment_raw_word_count($phrase, $language), $phrase . ': test failed');
            }
        }
    }
}