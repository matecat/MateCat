<?php

namespace Matecat\Core\Model;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;

class FeatureSetTest extends AbstractTest
{
    #[Test]
    public function getSortedFeatures(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,project_completion");

        $this->assertEquals(
            "translated,mmt,translation_versions,review_extended,second_pass_review,aligner,project_completion",
            implode(',', $featureSet->sortFeatures()->getCodes())
        );
    }

    #[Test]
    public function getFeaturesStructsReturnsLoadedFeatures(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $structs = $featureSet->getFeaturesStructs();

        self::assertCount(1, $structs);
        self::assertSame('test_featureset_stub_a', $structs['test_featureset_stub_a']->feature_code);
    }

    #[Test]
    public function loadProjectDependenciesFromProjectMetadataIsNoOp(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
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
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('setCacheTTL')->willReturnSelf();
        $metadataDao->method('getValue')->willReturn('');

        $project = new ProjectStruct();
        $project->id = 1;

        $featureSet->loadForProject($project, $metadataDao);

        $codes = $featureSet->getCodes();
        self::assertNotContains('test_featureset_stub_a', $codes);
    }

    #[Test]
    public function loadForProjectLoadsMetadataFeatures(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('setCacheTTL')->willReturnSelf();
        $metadataDao->method('getValue')->willReturn('translation_versions');

        $project = new ProjectStruct();
        $project->id = 1;

        $featureSet->loadForProject($project, $metadataDao);

        $codes = $featureSet->getCodes();
        self::assertContains('translation_versions', $codes);
    }

    #[Test]
    public function mergeThrowsOnConflictingDependencies(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/conflicting/i');

        new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_feature_conflict_declarer']),
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);
    }
}

namespace Plugins\Features;

class TestFeaturesetStubA extends BaseFeature
{
    public const string FEATURE_CODE = 'test_featureset_stub_a';
}

class TestFeatureConflictDeclarer extends BaseFeature
{
    public const string FEATURE_CODE = 'test_feature_conflict_declarer';

    protected static array $conflictingDependencies = ['test_featureset_stub_a'];
}
