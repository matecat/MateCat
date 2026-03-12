<?php

namespace unit\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Collections\RecursiveArrayObject;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::determineStatusAndPopulateResult()}.
 *
 * Verifies:
 * - Status is set to NEW when VOLUME_ANALYSIS_ENABLED and segments > 0
 * - Status is set to NOT_TO_ANALYZE when VOLUME_ANALYSIS_ENABLED is false
 * - Status is set to EMPTY when show_in_cattool_segs_counter is 0
 * - Result structure is fully populated with correct keys
 */
class DetermineStatusAndPopulateResultTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private bool $originalVolumeAnalysis;

    protected function setUp(): void
    {
        $this->originalVolumeAnalysis = AppConfig::$VOLUME_ANALYSIS_ENABLED;

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );

        // Set required project structure values
        $this->pm->setProjectStructureValue('ppassword', 'proj_pass');
        $this->pm->setProjectStructureValue('project_name', 'Test Project');
        $this->pm->setProjectStructureValue('source_language', 'en-US');
        $this->pm->setProjectStructureValue('target_language', new RecursiveArrayObject(['it-IT']));
        $this->pm->setProjectStructureValue('array_jobs', new RecursiveArrayObject([
            'job_pass'     => new RecursiveArrayObject(['pass1']),
            'job_list'     => new RecursiveArrayObject([101]),
            'job_segments' => new RecursiveArrayObject(['101-pass1' => ['job_first_segment' => 1, 'job_last_segment' => 10]]),
            'job_languages' => new RecursiveArrayObject(),
            'payable_rates' => new RecursiveArrayObject(),
        ]));
    }

    protected function tearDown(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = $this->originalVolumeAnalysis;
        parent::tearDown();
    }

    // ── Status determination ────────────────────────────────────────

    #[Test]
    public function statusIsNewWhenVolumeAnalysisEnabledAndSegmentsExist(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = true;
        $this->pm->setShowInCattoolSegsCounter(5);

        $this->pm->callDetermineStatusAndPopulateResult();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame(ProjectStatus::STATUS_NEW, $ps['status']);
    }

    #[Test]
    public function statusIsNotToAnalyzeWhenVolumeAnalysisDisabled(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = false;
        $this->pm->setShowInCattoolSegsCounter(5);

        $this->pm->callDetermineStatusAndPopulateResult();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame(ProjectStatus::STATUS_NOT_TO_ANALYZE, $ps['status']);
    }

    #[Test]
    public function statusIsEmptyWhenNoShowInCattoolSegments(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = true;
        $this->pm->setShowInCattoolSegsCounter(0);

        $this->pm->callDetermineStatusAndPopulateResult();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame(ProjectStatus::STATUS_EMPTY, $ps['status']);
    }

    #[Test]
    public function statusIsEmptyOverridesNotToAnalyze(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = false;
        $this->pm->setShowInCattoolSegsCounter(0);

        $this->pm->callDetermineStatusAndPopulateResult();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame(ProjectStatus::STATUS_EMPTY, $ps['status']);
    }

    // ── Result structure population ─────────────────────────────────

    #[Test]
    public function resultCodeIsSetToOne(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertSame(1, $result['code']);
    }

    #[Test]
    public function resultDataIsOK(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertSame('OK', $result['data']);
    }

    #[Test]
    public function resultContainsProjectPassword(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertSame('proj_pass', $result['ppassword']);
    }

    #[Test]
    public function resultContainsJobPasswords(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $jobPass = $result['password'];
        $this->assertCount(1, $jobPass);
    }

    #[Test]
    public function resultContainsJobIds(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $jobList = $result['id_job'];
        $this->assertCount(1, $jobList);
    }

    #[Test]
    public function resultContainsProjectId(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertSame(999, $result['id_project']);
    }

    #[Test]
    public function resultContainsProjectName(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertSame('Test Project', $result['project_name']);
    }

    #[Test]
    public function resultContainsSourceLanguage(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertSame('en-US', $result['source_language']);
    }

    #[Test]
    public function resultContainsTargetLanguage(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertNotNull($result['target_language']);
    }

    #[Test]
    public function resultStatusMatchesProjectStructureStatus(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = true;
        $this->pm->setShowInCattoolSegsCounter(10);

        $this->pm->callDetermineStatusAndPopulateResult();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame($ps['status'], $ps['result']['status']);
    }

    #[Test]
    public function resultContainsJobSegments(): void
    {
        $this->pm->setShowInCattoolSegsCounter(1);
        $this->pm->callDetermineStatusAndPopulateResult();

        $result = $this->pm->getTestProjectStructure()['result'];
        $this->assertArrayHasKey('job_segments', $result);
    }
}
