<?php

declare(strict_types=1);

namespace unit\Plugins\Features;

use Exception;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Plugins\Features\AbstractRevisionFeature;
use Plugins\Features\ReviewExtended;
use Plugins\Features\ReviewExtended\ChunkReviewModel;
use Plugins\Features\RevisionFactory;
use Plugins\Features\SecondPassReview;

class RevisionFactoryTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new \ReflectionProperty(RevisionFactory::class, 'INSTANCE');
        $reflection->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionProperty(RevisionFactory::class, 'INSTANCE');
        $reflection->setValue(null, null);
        parent::tearDown();
    }

    #[Test]
    public function GetInstanceThrowsWhenNoRevisionAndNoInstance(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Revision not defined');

        RevisionFactory::getInstance(null);
    }

    #[Test]
    public function GetInstanceCreatesNewInstanceWithRevisionFeature(): void
    {
        $revision = $this->createStub(AbstractRevisionFeature::class);
        $factory = RevisionFactory::getInstance($revision);

        $this->assertInstanceOf(RevisionFactory::class, $factory);
    }

    #[Test]
    public function GetInstanceReturnsSingletonOnSubsequentCalls(): void
    {
        $revision = $this->createStub(AbstractRevisionFeature::class);
        $first = RevisionFactory::getInstance($revision);
        $second = RevisionFactory::getInstance();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function GetInstanceIgnoresNewRevisionAfterInit(): void
    {
        $revision1 = $this->createStub(AbstractRevisionFeature::class);
        $revision2 = $this->createStub(AbstractRevisionFeature::class);

        $first = RevisionFactory::getInstance($revision1);
        $second = RevisionFactory::getInstance($revision2);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function SetFeatureSetReturnsSelf(): void
    {
        $revision = $this->createStub(AbstractRevisionFeature::class);
        $factory = RevisionFactory::getInstance($revision);
        $featureSet = $this->createStub(FeatureSet::class);

        $result = $factory->setFeatureSet($featureSet);

        $this->assertSame($factory, $result);
    }

    #[Test]
    public function GetRevisionFeatureReturnsInjectedFeature(): void
    {
        $revision = $this->createStub(AbstractRevisionFeature::class);
        $factory = RevisionFactory::getInstance($revision);

        $this->assertSame($revision, $factory->getRevisionFeature());
    }

    #[Test]
    public function GetChunkReviewModelDelegatesToRevisionFeature(): void
    {
        $expectedModel = $this->createStub(ChunkReviewModel::class);

        $revision = $this->createStub(AbstractRevisionFeature::class);
        $revision->method('getChunkReviewModel')->willReturn($expectedModel);

        $factory = RevisionFactory::getInstance($revision);
        $chunkReview = new ChunkReviewStruct();

        $this->assertSame($expectedModel, $factory->getChunkReviewModel($chunkReview));
    }

    #[Test]
    public function InitFromProjectWithRevisionFeature(): void
    {
        $revision = new SecondPassReview(
            new BasicFeatureStruct(['feature_code' => ReviewExtended::FEATURE_CODE])
        );

        $featureSet = $this->createStub(FeatureSet::class);
        $featureSet->method('getFeaturesStructs')->willReturn([
            new BasicFeatureStruct(['feature_code' => ReviewExtended::FEATURE_CODE]),
        ]);

        $project = $this->createStub(ProjectStruct::class);
        $project->method('getFeaturesSet')->willReturn($featureSet);

        $factory = RevisionFactory::initFromProject($project);

        $this->assertInstanceOf(RevisionFactory::class, $factory);
        $this->assertInstanceOf(AbstractRevisionFeature::class, $factory->getRevisionFeature());
    }

    #[Test]
    public function InitFromProjectFallsBackToSecondPassReview(): void
    {
        $featureSet = $this->createStub(FeatureSet::class);
        $featureSet->method('getFeaturesStructs')->willReturn([]);

        $project = $this->createStub(ProjectStruct::class);
        $project->method('getFeaturesSet')->willReturn($featureSet);

        $factory = RevisionFactory::initFromProject($project);

        $this->assertInstanceOf(RevisionFactory::class, $factory);
        $this->assertInstanceOf(SecondPassReview::class, $factory->getRevisionFeature());
    }
}
