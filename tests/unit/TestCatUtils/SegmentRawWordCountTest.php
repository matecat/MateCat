<?php

namespace unit\TestCatUtils;

use Exception;
use TestHelpers\AbstractTest;
use Utils\CatUtils;

class SegmentRawWordCountTest extends AbstractTest {
    /**
     * @throws Exception
     */
    public function testSegmentRawWordCount() {

        $links = <<<H
http://foo.com/blah_blah http://foo.com/blah_blah/ http://foo.com/blah_blah_(wikipedia) http://foo.com/blah_blah_(wikipedia)_(again) 
http://www.example.com/wpstyle/?p=364 https://www.example.com/foo/?bar=baz&inga=42&quux http://✪df.ws/123 
http://userid:password@example.com:8080 http://userid:password@example.com:8080/%20测试测试测试 http://userid@example.com 
http://userid@example.com/ http://userid@example.com:8080 http://userid@example.com:8080/ http://userid:password@example.com 
http://userid:password@example.com/ http://142.42.1.1/ http://142.42.1.1:8080/ http://➡.ws/䨹 http://⌘.ws http://⌘.ws/ 
http://foo.com/blah_(wikipedia)#cite-1 http://foo.com/blah_(wikipedia)_blah#cite-1 http://foo.com/unicode_(✪)_in_parens 
http://foo.com/(something)?after=parens http://☺.damowmow.com/ http://code.google.com/events/#&product=browser 
http://j.mp ftp://foo.bar/baz http://foo.bar/?q=Test%20URL-encoded%20stuff http://例子.测试 http://उदाहरण.परीक्षा 
http://-.~_!$&'()*+,;=:%40:80%2f::::::@example.com http://1337.net http://a.b-c.de http://223.255.255.254 
socket://1.2.3.4 ws://1.2.3.4:7788?check/url/socket.io?myparams=1 ssh://pippo:pluto@domain.com http://foo.bar/foo(bar)baz 
ftps://foo.bar/ http://-error-.invalid/ http://a.b--c.de/ http://-a.b.co http://a.b-.co http://0.0.0.0 http://10.1.1.0 
http://10.1.1.255 http://224.1.1.1 http://1.1.1.1.1 http://123.123.123 http://.www.foo.bar/ http://www.foo.bar./ 
http://.www.foo.bar./ http://10.1.1.1 
H;


        $data = [
                'zh-CN' => [
                        '​你叫什么名字%%placeholder%%'                                         => 7,
                        '​你叫什么名字%{placeholder}'                                          => 7,
                        '​你叫什么名字{{placeholder}}'                                         => 7,
                        '​你叫什么名字{{ placeholder }}'                                       => 7,
                        '​你叫什么名字@@placeholder@@'                                         => 7,
                        '​你叫什么名字{%placeholder%}'                                         => 7,
                        '​你叫什么名字%s'                                                      => 7,
                        '​你叫什么名字%u'                                                      => 7,
                        '​你叫什么名字%d'                                                      => 7,
                        '​你叫什么名字%@'                                                      => 7,
                        '​你叫什么名字%1$i'                                                    => 7,
                        '​你叫什么名字%1$.2f'                                                  => 7,
                        '​你叫什么名字%.0f'                                                    => 7,
                        '​你叫什么名字%c'                                                      => 7,
                        '​你叫什么名字%2$@'                                                    => 7,
                        '​你叫什么名字%x'                                                      => 7,
                        '​你叫什么名字 %1%@'                                                   => 8, //the first part of sprintf group is invalid, so it remains a string %1
                    // '​你叫什么名字%#@file@' => 7,
                        '​你叫什么名字$%1$.2f'                                                 => 8,
                        '​你叫什么名字%.0f%'                                                   => 7,
                        '​你叫什么名字%ld'                                                     => 7,
                        '​你叫什么名字%hi'                                                     => 7,
                        '​你叫什么名字%lu'                                                     => 7,
                        '​你叫什么名字%1'                                                      => 7,
                        '​你叫什么名字%2'                                                      => 7,
                        '​你叫什么名字'                                                        => 6,
                        '​你叫什么名字<a href="#">你叫什么名字</a>'                            => 12,
                        "dev-docs-servicenow-it.okservice.io 你叫什mm-xx么名　字 la casa-matta" => 6,
//                        $links                                      => 133,  // disabled for now until new regexp will be better tested
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
                        'La casa %#@file@ è bianca'                                                                                          => 5,

                    // the first part of sprintf group is invalid, so it remains a string %1
                        'La casa %1%@ è bianca'                                                                                              => 6,

                    // the first dollar sign is not part of the sprintf placeholder, it counts as 1 word
                        'La casa $%1$.2f è bianca'                                                                                           => 6,

                        'La casa %.0f% è bianca' => 5,
                        'La casa %ld è bianca'   => 5,
                        'La casa %hi è bianca'   => 5,
                        'La casa %lu è bianca'   => 5,
                        'La casa %1 è bianca'    => 5,
                        'La casa %2 è bianca'    => 5,
                ],
                'en-US' => [
                        'Hyphenated words count as one, like pippo-dash and pippo_underscore, but isolated - -- and _ __ count as zero' => 15,
                        "The header's list"                                                                                             => 3,
//                        $links                                                                                                          => 74, // disabled for now until new regexp will be better tested
                        "dev-docs-servicenow.zoominsoftware.io"                                                                         => 1,
                        "dev-docs-servicenow-it.okservice.io 你叫什mm-xx么名　字 la casa-matta"                                          => 5,
                        "e pippo-ciccio foo-bar"                                                                                        => 3
                ]
        ];

        foreach ( $data as $language => $phrases ) {
            foreach ( $phrases as $phrase => $count ) {
                $this->assertEquals( $count, CatUtils::segment_raw_word_count( $phrase, $language ), $phrase . ': test failed' );
            }
        }
    }
}