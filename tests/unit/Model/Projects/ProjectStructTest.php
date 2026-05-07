<?php

namespace unit\Model\Projects;

use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataStruct;
use Model\Projects\ProjectStruct;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\RemoteFiles\RemoteFileServiceNameStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
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
    public function getJobsCountReturnsZeroWhenNoJobs(): void
    {
        $project = new ProjectStructTestDouble();
        $project->jobs = [];

        $this->assertSame(0, $project->getJobsCount());
    }

    #[Test]
    public function getJobsCountReturnsCountWhenJobsExist(): void
    {
        $jobOne = new JobStruct();
        $jobTwo = new JobStruct();

        $project = new ProjectStructTestDouble();
        $project->jobs = [$jobOne, $jobTwo];

        $this->assertSame(2, $project->getJobsCount());
    }

    #[Test]
    public function getFeaturesSetLoadsFeatureSetFromOverriddenMetadataAccessor(): void
    {
        $project = new ProjectStructFeaturesSetLoadDouble();
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

    #[Test]
    public function getWordCountTypeReturnsEquivalentWhenMetadataIsNull(): void
    {
        $project = new ProjectStructTestDouble();
        $project->metadataValues = [
            ProjectsMetadataMarshaller::WORD_COUNT_TYPE_KEY->value => null,
        ];

        $this->assertSame(ProjectsMetadataMarshaller::WORD_COUNT_EQUIVALENT->value, $project->getWordCountType());
    }

    #[Test]
    public function getWordCountTypeReturnsMetadataValueWhenPresent(): void
    {
        $project = new ProjectStructTestDouble();
        $project->metadataValues = [
            ProjectsMetadataMarshaller::WORD_COUNT_TYPE_KEY->value => ProjectsMetadataMarshaller::WORD_COUNT_RAW->value,
        ];

        $this->assertSame(ProjectsMetadataMarshaller::WORD_COUNT_RAW->value, $project->getWordCountType());
    }

    #[Test]
    public function getLqaModelReturnsNullWhenIdQaModelIsNull(): void
    {
        $project = new ProjectStruct();
        $project->id_qa_model = null;

        $this->assertNull($project->getLqaModel());
    }

    #[Test]
    public function getJobsReturnsArrayWhenProjectIdIsSet(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;

        $this->assertIsArray($project->getJobs());
    }

    #[Test]
    public function getMetadataValueReturnsNullOrStringWhenProjectIdIsSet(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;

        $value = $project->getMetadataValue('this_key_should_not_exist_for_project_struct_test');

        $this->assertTrue($value === null || is_string($value));
    }

    #[Test]
    public function getAllMetadataReturnsArrayWhenProjectIdIsSet(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;

        $this->assertIsArray($project->getAllMetadata());
    }

    #[Test]
    public function getChunksReturnsArrayWhenProjectIdIsSet(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;

        $this->assertIsArray($project->getChunks());
    }

    #[Test]
    public function getRemoteFileServiceNameReturnsStructOrNullWhenProjectIdIsSet(): void
    {
        $project = new ProjectStruct();
        $project->id = 1;

        $result = $project->getRemoteFileServiceName();

        $this->assertTrue($result === null || $result instanceof RemoteFileServiceNameStruct);
    }

    #[Test]
    public function getTeamReturnsStructOrNullWhenIdTeamIsSet(): void
    {
        $project = new ProjectStruct();
        $project->id_team = 1;

        $result = $project->getTeam();

        $this->assertTrue($result === null || is_object($result));
    }

    #[Test]
    public function getRemoteFileServiceNameReturnsStructFromCacheablePath(): void
    {
        $service = new RemoteFileServiceNameStruct();
        $service->id_project = 100;
        $service->service = 'gdrive';

        $project = new ProjectStructTestDouble();
        $project->setCachedResult('Model\\Projects\\ProjectStruct::getRemoteFileServiceName', $service);

        $this->assertSame($service, $project->getRemoteFileServiceName());
    }

    #[Test]
    public function getTeamReturnsNullWhenIdTeamIsNull(): void
    {
        $project = new ProjectStruct();
        $project->id_team = null;

        $this->assertNull($project->getTeam());
    }

    #[Test]
    public function getAllMetadataAsKeyValueReturnsKeyValueMap(): void
    {
        $recordOne = new MetadataStruct();
        $recordOne->id_project = 1;
        $recordOne->key = 'first_key';
        $recordOne->value = 'first_value';

        $recordTwo = new MetadataStruct();
        $recordTwo->id_project = 1;
        $recordTwo->key = 'second_key';
        $recordTwo->value = 'second_value';

        $project = new ProjectStructTestDouble();
        $project->allMetadata = [$recordOne, $recordTwo];

        $this->assertSame(
            [
                'first_key' => 'first_value',
                'second_key' => 'second_value',
            ],
            $project->getAllMetadataAsKeyValue()
        );
    }
}

class ProjectStructTestDouble extends ProjectStruct
{
    /** @var JobStruct[] */
    public array $jobs = [];

    /** @var array<string, mixed> */
    public array $metadataValues = [];

    /** @var MetadataStruct[] */
    public array $allMetadata = [];

    public function setCachedResult(string $key, mixed $value): void
    {
        $this->cached_results[$key] = $value;
    }

    public function getJobs(int $ttl = 0): array
    {
        return $this->jobs;
    }

    public function getMetadataValue(string $key): mixed
    {
        return $this->metadataValues[$key] ?? null;
    }

    public function getAllMetadata(): array
    {
        return $this->allMetadata;
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

class ProjectStructFeaturesSetLoadDouble extends ProjectStruct
{
    public function getMetadataValue(string $key): mixed
    {
        return '';
    }
}
