<?php

namespace Tests\Unit\Model;

use Exception;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class FeatureSetTest extends AbstractTest
{
    #[Test]
    public function getSortedFeatures(): void
    {
        $featureSet = new FeatureSet();
        $featureSet->loadFromString("translation_versions,project_completion");

        $this->assertEquals(
            "translated,mmt,translation_versions,review_extended,second_pass_review,aligner,project_completion",
            implode(',', $featureSet->sortFeatures()->getCodes())
        );
    }

    #[Test]
    public function getFeaturesStructsReturnsLoadedFeatures(): void
    {
        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $structs = $featureSet->getFeaturesStructs();

        self::assertCount(1, $structs);
        self::assertSame('test_featureset_stub_a', $structs['test_featureset_stub_a']->feature_code);
    }

    #[Test]
    public function loadProjectDependenciesFromProjectMetadataIsNoOp(): void
    {
        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $featureSet->loadProjectDependenciesFromProjectMetadata(['some_key' => 'some_value']);

        self::assertSame(
            ['test_featureset_stub_a'],
            $featureSet->getCodes()
        );
    }

    #[Test]
    public function loadForProjectClearsAndReloadsFeatures(): void
    {
        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $project = $this->createStub(ProjectStruct::class);
        $project->method('getMetadataValue')->willReturn('');

        $featureSet->loadForProject($project);

        $codes = $featureSet->getCodes();
        self::assertNotContains('test_featureset_stub_a', $codes);
    }

    #[Test]
    public function loadForProjectLoadsMetadataFeatures(): void
    {
        $featureSet = new FeatureSet();

        $project = $this->createStub(ProjectStruct::class);
        $project->method('getMetadataValue')->willReturn('translation_versions');

        $featureSet->loadForProject($project);

        $codes = $featureSet->getCodes();
        self::assertContains('translation_versions', $codes);
    }

    #[Test]
    public function mergeThrowsOnConflictingDependencies(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/conflicting/i');

        new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_feature_conflict_declarer']),
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);
    }
}

namespace Plugins\Features;

use Model\FeaturesBase\BasicFeatureStruct;

class TestFeaturesetStubA extends BaseFeature
{
    public const string FEATURE_CODE = 'test_featureset_stub_a';
}

class TestFeatureConflictDeclarer extends BaseFeature
{
    public const string FEATURE_CODE = 'test_feature_conflict_declarer';

    protected static array $conflictingDependencies = ['test_featureset_stub_a'];
}
