<?php

namespace Matecat\Core\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use ReflectionException;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::populateResult()}.
 *
 * Verifies the result structure is fully populated with correct keys.
 */
class PopulateResultTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private bool $originalVolumeAnalysis;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
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
        $this->pm->setProjectStructureValue('target_language', ['it-IT']);
        $this->pm->setProjectStructureValue('array_jobs', [
            'job_pass'     => ['pass1'],
            'job_list'     => [101],
            'job_segments' => ['101-pass1' => ['job_first_segment' => 1, 'job_last_segment' => 10]],
            'job_languages' => [],
            'payable_rates' => [],
        ]);
    }

    protected function tearDown(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = $this->originalVolumeAnalysis;
        parent::tearDown();
    }

    // ── Result structure population ─────────────────────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultCodeIsSetToOne(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertSame(1, $result['code']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultDataIsOK(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertSame('OK', $result['data']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsProjectPassword(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertSame('proj_pass', $result['ppassword']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsJobPasswords(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $jobPass = $result['password'];
        $this->assertCount(1, $jobPass);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsJobIds(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $jobList = $result['id_job'];
        $this->assertCount(1, $jobList);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsProjectId(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertSame(999, $result['id_project']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsProjectName(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertSame('Test Project', $result['project_name']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsSourceLanguage(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertSame('en-US', $result['source_language']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsTargetLanguage(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertNotNull($result['target_language']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultStatusMatchesProjectStructureStatus(): void
    {
        $this->pm->setProjectStructureValue('status', ProjectStatus::STATUS_NEW);

        $this->pm->callPopulateResult();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame($ps->status, $ps->result['status']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsJobSegments(): void
    {
        $this->pm->callPopulateResult();

        $result = $this->pm->getTestProjectStructure()->result;
        $this->assertArrayHasKey('job_segments', $result);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function resultContainsAnalyzeUrlOnlyWhenVolumeAnalysisIsEnabled(): void
    {
        AppConfig::$VOLUME_ANALYSIS_ENABLED = false;
        $this->pm->callPopulateResult();
        $this->assertArrayNotHasKey('analyze_url', $this->pm->getTestProjectStructure()->result);

        AppConfig::$VOLUME_ANALYSIS_ENABLED = true;
        $this->pm->callPopulateResult();
        $this->assertNotEmpty($this->pm->getTestProjectStructure()->result['analyze_url']);
    }
}
