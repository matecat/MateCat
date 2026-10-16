<?php

namespace Matecat\Core\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\PostProjectCreateEvent;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use RuntimeException;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::finalizeProjectInTransaction()}.
 *
 * The finalize sets the project status, and enqueues the PROJECT_CREATED activity log only once its
 * transaction has committed: a finalize that rolls back must not report a project that never became ready.
 */
class FinalizeProjectInTransactionTest extends AbstractTest
{
    /** An id no fixture uses, so updateAnalysisStatus() matches no row. */
    private const int MISSING_PROJECT_ID = 2_147_000_001;

    private bool $originalVolumeAnalysis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalVolumeAnalysis = AppConfig::$VOLUME_ANALYSIS_ENABLED;
    }

    protected function tearDown(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = $this->originalVolumeAnalysis;
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function projectManager(FeatureSet $features): TestableProjectManager
    {
        $pm = new TestableProjectManager();
        $pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );

        $pm->setProjectStructureValue('id_project', self::MISSING_PROJECT_ID);
        $pm->setProjectStructureValue('ppassword', 'projectpw');
        $pm->setProjectStructureValue('project_name', 'finalize-test');
        $pm->setProjectStructureValue('array_jobs', ['job_languages' => ['it-IT']]);

        return $pm;
    }

    private function passThroughFeatures(): FeatureSet
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('dispatch')->willReturnArgument(0);

        return $features;
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function theActivityLogIsPushedAfterTheCommit(): void
    {
        $pm = $this->projectManager($this->passThroughFeatures());
        $pm->callFinalizeProjectInTransaction();

        $this->assertSame([false], $pm->activityLogPushedInsideScope);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function aFinalizeThatRollsBackPushesNoActivityLog(): void
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof PostProjectCreateEvent) {
                throw new RuntimeException('Hook failed');
            }

            return $event;
        });

        $pm = $this->projectManager($features);

        try {
            $pm->callFinalizeProjectInTransaction();
            $this->fail('The hook failure must abort the finalize');
        } catch (RuntimeException $e) {
            $this->assertSame('Hook failed', $e->getMessage());
        }

        $this->assertSame([], $pm->activityLogPushedInsideScope);
    }

    /**
     * @return array<string, array{bool, int, string}>
     */
    public static function statusCases(): array
    {
        return [
            'volume analysis on, segments'   => [true, 5, ProjectStatus::STATUS_NEW],
            'volume analysis off, segments'  => [false, 5, ProjectStatus::STATUS_NOT_TO_ANALYZE],
            'volume analysis on, no segment' => [true, 0, ProjectStatus::STATUS_EMPTY],
            'empty overrides not to analyze' => [false, 0, ProjectStatus::STATUS_EMPTY],
        ];
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('statusCases')]
    public function theStatusFollowsVolumeAnalysisAndSegmentCount(bool $volumeAnalysis, int $segments, string $expected): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = $volumeAnalysis;

        $pm = $this->projectManager($this->passThroughFeatures());
        $pm->setShowInCattoolSegsCounter($segments);
        $pm->callFinalizeProjectInTransaction();

        $this->assertSame($expected, $pm->getTestProjectStructure()->status);
    }
}
