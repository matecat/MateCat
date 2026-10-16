<?php

namespace Matecat\Core\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\PostProjectCreateEvent;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use RuntimeException;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::finalizeProjectInTransaction()}.
 *
 * The PROJECT_CREATED activity log is enqueued only once the finalize transaction has committed:
 * a finalize that rolls back must not report a project that never became ready.
 */
class FinalizeProjectInTransactionTest extends AbstractTest
{
    /** An id no fixture uses, so updateAnalysisStatus() matches no row. */
    private const int MISSING_PROJECT_ID = 2_147_000_001;

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
        $pm->setProjectStructureValue('status', ProjectStatus::STATUS_NEW);
        $pm->setProjectStructureValue('array_jobs', ['job_languages' => ['it-IT']]);

        return $pm;
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function theActivityLogIsPushedAfterTheCommit(): void
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('dispatch')->willReturnArgument(0);

        $pm = $this->projectManager($features);
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
}
