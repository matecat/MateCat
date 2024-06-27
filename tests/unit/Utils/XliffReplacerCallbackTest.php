<?php

use TestHelpers\AbstractTest;
use XliffReplacer\XliffReplacerCallback;

class XliffReplacerCallbackTest extends AbstractTest
{
    /**
     * @throws Exception
     */
    public function testSegmentsWithTagG()
    {
        $segment = '<g id="1">Hello</g>';
        $target = '<g id="3">Hola</g>';

        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet(), 'en-EN', 'es-ES');

        $this->assertTrue($xliffReplacerCallback->thereAreErrors(1, $segment, $target));
    }

    /**
     * @throws Exception
     */
    public function testSegmentsWithTagPh()
    {
        $segment = '<ph id="1"/> Hello';
        $target = '<ph id="3"/> Hola';

        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet(), 'en-EN', 'es-ES');

        $this->assertTrue($xliffReplacerCallback->thereAreErrors(1, $segment, $target));
    }
}