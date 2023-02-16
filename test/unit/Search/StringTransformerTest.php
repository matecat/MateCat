<?php

use Search\StringTransformer;

class StringTransformerTest extends AbstractTest {

    /**
     * @test
     */
    public function canTransformAStringWithGAndBxTags() {
        $source = '<bx id="23"/><g id="1">Ciao</g> questa è una stringa';
        $expected = '23 1 Ciao questa è una stringa';

        $this->assertEquals(StringTransformer::transform($source), $expected);
    }

    /**
     * @test
     */
    public function canTransformAStringWithDataRefMap() {
        $source = '<pc id="source1" dataRefStart="source1">Drop off at the door.</pc>';
        $map = '{"source1":"&lt;strong class=\"cmln__strong\"&gt;"}';
        $expected = '&lt;strong class="cmln__strong"&gt; Drop off at the door.';

        $this->assertEquals(StringTransformer::transform($source, $map), $expected);
    }
}