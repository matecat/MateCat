<?php

namespace Matecat\Core\Model\Projects;

use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\ProjectStatus;

class ProjectStructTest extends AbstractTest
{
    #[Test]
    public function analysisCompleteReturnsTrueWhenStatusIsDone(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_DONE;

        $this->assertTrue($project->analysisComplete());
    }

    #[Test]
    public function analysisCompleteReturnsTrueWhenStatusIsNotToAnalyze(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_NOT_TO_ANALYZE;

        $this->assertTrue($project->analysisComplete());
    }

    #[Test]
    public function analysisCompleteReturnsFalseForOtherStatus(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_BUSY;

        $this->assertFalse($project->analysisComplete());
    }

    #[Test]
    public function getFeaturesSetLoadsFeatureSetFromOverriddenMetadataAccessor(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;

        $featureSet = $project->getFeaturesSet();

        $this->assertInstanceOf(FeatureSet::class, $featureSet);
    }

    #[Test]
    public function isFeatureEnabledReturnsTrueWhenFeatureIsPresent(): void
    {
        $featureSet = new FeatureSetTestDouble();
        $featureSet->codes = ['foo_feature'];

        $project = new ProjectStructTestDouble();
        $project->setCachedResult('Model\\Projects\\ProjectStruct::getFeaturesSet', $featureSet);

        $this->assertTrue($project->isFeatureEnabled('foo_feature'));
    }

    #[Test]
    public function isFeatureEnabledReturnsFalseWhenFeatureIsMissing(): void
    {
        $featureSet = new FeatureSetTestDouble();
        $featureSet->codes = ['foo_feature'];

        $project = new ProjectStructTestDouble();
        $project->setCachedResult('Model\\Projects\\ProjectStruct::getFeaturesSet', $featureSet);

        $this->assertFalse($project->isFeatureEnabled('bar_feature'));
    }

    #[Test]
    public function hasFeatureReturnsTrueWhenFeatureIsPresent(): void
    {
        $featureSet = new FeatureSetTestDouble();
        $featureSet->codes = ['foo_feature'];

        $project = new ProjectStructTestDouble();
        $project->setCachedResult('Model\\Projects\\ProjectStruct::getFeaturesSet', $featureSet);

        $this->assertTrue($project->hasFeature('foo_feature'));
    }

    #[Test]
    public function hasFeatureReturnsFalseWhenFeatureIsMissing(): void
    {
        $featureSet = new FeatureSetTestDouble();
        $featureSet->codes = ['foo_feature'];

        $project = new ProjectStructTestDouble();
        $project->setCachedResult('Model\\Projects\\ProjectStruct::getFeaturesSet', $featureSet);

        $this->assertFalse($project->hasFeature('bar_feature'));
    }


}

class ProjectStructTestDouble extends ProjectStruct
{
    public function setCachedResult(string $key, mixed $value): void
    {
        $this->cached_results[$key] = $value;
    }
}

class FeatureSetTestDouble extends FeatureSet
{
    /** @var string[] */
    public array $codes = [];

    public function __construct()
    {
    }

    public function getCodes(): array
    {
        return $this->codes;
    }
}

