<?php

declare(strict_types=1);

namespace Matecat\Core\Plugins\Features;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\MetadataStruct;
use Model\Projects\ProjectStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Plugins\Features\AbstractRevisionFeature;
use Plugins\Features\ReviewExtended;
use Plugins\Features\ReviewExtended\ChunkReviewModel;
use Plugins\Features\RevisionFactory;
use Plugins\Features\SecondPassReview;
use Utils\Registry\AppConfig;

class RevisionFactoryTest extends AbstractTest
{
    private IDatabase&Stub $dbStub;
    private PDO&Stub $pdoStub;
    private PDOStatement&Stub $stmtStub;
    private static bool $originalSkipCache;

    protected function setUp(): void
    {
        parent::setUp();

        self::$originalSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();

        $reflection = new \ReflectionProperty(RevisionFactory::class, 'INSTANCE');
        $reflection->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionProperty(RevisionFactory::class, 'INSTANCE');
        $reflection->setValue(null, null);
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = self::$originalSkipCache;
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
        $metadataRow = new MetadataStruct();
        $metadataRow->key = 'features';
        $metadataRow->value = 'review_extended';
        $metadataRow->id_project = 1;

        $this->stmtStub->method('fetchAll')->willReturn([$metadataRow]);

        $project = $this->createStub(ProjectStruct::class);
        $project->id = 1;

        $factory = RevisionFactory::initFromProject($project, $this->dbStub);

        $this->assertInstanceOf(RevisionFactory::class, $factory);
        $this->assertInstanceOf(AbstractRevisionFeature::class, $factory->getRevisionFeature());
    }

    #[Test]
    public function InitFromProjectFallsBackToSecondPassReview(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $project = $this->createStub(ProjectStruct::class);
        $project->id = 1;

        $factory = RevisionFactory::initFromProject($project, $this->dbStub);

        $this->assertInstanceOf(RevisionFactory::class, $factory);
        $this->assertInstanceOf(SecondPassReview::class, $factory->getRevisionFeature());
    }
}
