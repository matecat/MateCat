<?php
namespace Tests\unit\Features\Hook;

use Model\FeaturesBase\FeatureSet;
use Matecat\SubFiltering\Commons\Pipeline;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class FeatureSetPipelineCustomizationTest extends AbstractTest
{
    #[Test]
    public function customizeFromLayer0ToLayer1DispatchesAndReturnsPipeline(): void
    {
        $featureSet = new FeatureSet();
        $pipeline = new Pipeline('en-US', 'it-IT');
        $result = $featureSet->customizeFromLayer0ToLayer1($pipeline);
        $this->assertInstanceOf(Pipeline::class, $result);
    }

    #[Test]
    public function customizeNoOpMethodsReturnPipelineUnchanged(): void
    {
        $featureSet = new FeatureSet();
        $pipeline = new Pipeline('en-US', 'it-IT');

        $this->assertSame($pipeline, $featureSet->customizeFromLayer1ToLayer2($pipeline));
        $this->assertSame($pipeline, $featureSet->customizeFromLayer2ToLayer1($pipeline));
        $this->assertSame($pipeline, $featureSet->customizeFromRawXliffToLayer0($pipeline));
        $this->assertSame($pipeline, $featureSet->customizeFromLayer0ToRawXliff($pipeline));
        $this->assertSame($pipeline, $featureSet->customizeFromLayer1ToLayer0($pipeline));
    }
}
