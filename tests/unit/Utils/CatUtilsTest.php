<?php

use TestHelpers\AbstractTest;

class CatUtilsTest extends AbstractTest {
    /**
     * @throws Exception
     */
    public function testSegmentRawWordCount() {
        $data = [
                'zh-CN' => [
                        '​你叫什么名字%%placeholder%%'              => 7,
                        '​你叫什么名字%{placeholder}'               => 7,
                        '​你叫什么名字{{placeholder}}'              => 7,
                        '​你叫什么名字{{ placeholder }}'            => 7,
                        '​你叫什么名字@@placeholder@@'              => 7,
                        '​你叫什么名字{%placeholder%}'              => 7,
                        '​你叫什么名字%s'                           => 7,
                        '​你叫什么名字%u'                           => 7,
                        '​你叫什么名字%d'                           => 7,
                        '​你叫什么名字%@'                           => 7,
                        '​你叫什么名字%1$i'                         => 7,
                        '​你叫什么名字%1$.2f'                       => 7,
                        '​你叫什么名字%.0f'                         => 7,
                        '​你叫什么名字%c'                           => 7,
                        '​你叫什么名字%2$@'                         => 7,
                        '​你叫什么名字%x'                           => 7,
                        '​你叫什么名字 %1%@'                        => 8,
                    // '​你叫什么名字%#@file@' => 7,
                        '​你叫什么名字$%1$.2f'                      => 8,
                        '​你叫什么名字%.0f%'                        => 7,
                        '​你叫什么名字%ld'                          => 7,
                        '​你叫什么名字%hi'                          => 7,
                        '​你叫什么名字%lu'                          => 7,
                        '​你叫什么名字%1'                           => 7,
                        '​你叫什么名字%2'                           => 7,
                        '​你叫什么名字'                             => 6,
                        '​你叫什么名字<a href="#">你叫什么名字</a>' => 12,
                ],
                'it-IT' => [
                        '​La casa è <object_color>​​<object> bianca'                                                                         => 4,
                        '​La casa è <object_color>​​ <object> bianca'                                                                        => 4,
                        '{{place holder1}} {{place holder2}}'                                                                                => 2,
                        '<ph id="source1" dataRef="source1"/>Ciao <ph id="source2" dataRef="source2"/>,<ph id="source3" dataRef="source3"/>' => 1,
                        'La casa è <a href="#">bianca</a>'                                                                                   => 4,
                        'La casa è &lt;a href="#"&gt;bianca&lt;/a&gt;'                                                                       => 4,
                        'La casa è bianca'                                                                                                   => 4,
                        'La casa %%placeholder%% è bianca'                                                                                   => 5,
                        'La casa %{placeholder} è bianca'                                                                                    => 5,
                        'La casa {%placeholder%} è bianca'                                                                                   => 5,
                        'La casa @@placeholder@@ è bianca'                                                                                   => 5,
                        'La casa {placeholder} è bianca'                                                                                     => 5,
                        'La casa {{placeholder}} è bianca'                                                                                   => 5,
                        'La casa {{ placeholder }} è bianca'                                                                                 => 5,
                        'La casa %s è bianca'                                                                                                => 5,
                        'La casa %u è bianca'                                                                                                => 5,
                        'La casa %1$s è bianca'                                                                                              => 5,
                        'La casa %2$d è bianca'                                                                                              => 5,
                        'La casa %d è bianca'                                                                                                => 5,
                        'La casa %@ è bianca'                                                                                                => 5,
                        'La casa %1$i è bianca'                                                                                              => 5,
                        'La casa %1$.2f è bianca'                                                                                            => 5,
                        'La casa %.0f è bianca'                                                                                              => 5,
                        'La casa %c è bianca'                                                                                                => 5,
                        'La casa %2$@ è bianca'                                                                                              => 5,
                        'La casa %x è bianca'                                                                                                => 5,
                        'La casa %1%@ è bianca'                                                                                              => 5,
                        'La casa %#@file@ è bianca'                                                                                          => 5,
                        'La casa $%1$.2f è bianca'                                                                                           => 5,
                        'La casa %.0f% è bianca'                                                                                             => 5,
                        'La casa %ld è bianca'                                                                                               => 5,
                        'La casa %hi è bianca'                                                                                               => 5,
                        'La casa %lu è bianca'                                                                                               => 5,
                        'La casa %1 è bianca'                                                                                                => 5,
                        'La casa %2 è bianca'                                                                                                => 5,
                ],
        ];

        foreach ( $data as $language => $phrases ) {
            foreach ( $phrases as $phrase => $count ) {
                $this->assertEquals( $count, CatUtils::segment_raw_word_count( $phrase, $language ), $phrase . ': test failed' );
            }
        }
    }
}