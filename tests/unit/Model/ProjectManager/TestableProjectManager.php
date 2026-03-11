<?php

namespace unit\Model\ProjectManager;

use ArrayObject;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\ProjectManager\ProjectManager;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use Model\Xliff\DTO\XliffRulesModel;
use ReflectionClass;
use ReflectionException;
use Utils\Collections\RecursiveArrayObject;
use Utils\Logger\MatecatLogger;

/**
 * A testable subclass of ProjectManager that bypasses the heavy constructor
 * and allows injection of dependencies needed by _extractSegments().
 *
 * This is needed because ProjectManager's constructor initializes DB connections,
 * TMX services, and other infrastructure that is not relevant for unit-testing
 * the segment extraction logic.
 */
class TestableProjectManager extends ProjectManager
{
    /**
     * Bypass the parent constructor entirely.
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
        // intentionally empty — we inject dependencies manually
    }

    /**
     * Initialize the testable instance with mocked/stubbed dependencies.
     */
    public function initForTest(
        MateCatFilter $filter,
        FeatureSet $features,
        MetadataDao $filesMetadataDao,
        MatecatLogger $logger,
        ?XliffRulesModel $xliffParameters = null,
    ): void {
        $this->filter = $filter;
        $this->features = $features;
        $this->filesMetadataDao = $filesMetadataDao;

        // Use reflection to set the private logger
        $ref = new ReflectionClass(ProjectManager::class);
        $loggerProp = $ref->getProperty('logger');
        $loggerProp->setValue($this, $logger);

        // Initialize the projectStructure with all keys needed by _extractSegments
        $this->projectStructure = new RecursiveArrayObject([
            'id_project' => 999,
            'source_language' => 'en-US',
            'target_language' => new RecursiveArrayObject(['it-IT']),
            'segments' => new ArrayObject(),
            'segments-original-data' => new ArrayObject(),
            'segments-meta-data' => new ArrayObject(),
            'file-part-id' => new ArrayObject(),
            'file-metadata' => new ArrayObject(),
            'translations' => new ArrayObject(),
            'notes' => new ArrayObject(),
            'context-group' => new ArrayObject(),
            'current-xliff-info' => [],
            'xliff_parameters' => $xliffParameters ?? new XliffRulesModel(),
            'result' => ['errors' => []],
        ]);
    }

    /**
     * Public wrapper to invoke the protected _extractSegments.
     * @throws Exception
     */
    public function callExtractSegments(int $fid, array $file_info): void
    {
        $this->extractSegments($fid, $file_info);
    }

    /**
     * Expose projectStructure for assertions.
     */
    public function getTestProjectStructure(): RecursiveArrayObject|ArrayObject
    {
        return $this->projectStructure;
    }

    /**
     * Expose counters for assertions.
     */
    public function getFilesWordCount(): int
    {
        return $this->files_word_count;
    }

    public function getShowInCattoolSegsCounter(): int
    {
        return $this->show_in_cattool_segs_counter;
    }

    public function getTotalSegments(): int
    {
        return $this->total_segments;
    }

    /**
     * Public wrapper to invoke the protected _validateUploadToken.
     * @throws Exception
     */
    public function callValidateUploadToken(): void
    {
        $this->_validateUploadToken();
    }

    /**
     * Public wrapper to invoke the protected _validateXliffParameters.
     * @throws Exception
     */
    public function callValidateXliffParameters(): void
    {
        $this->_validateXliffParameters();
    }

    /**
     * Set a specific key in projectStructure for testing.
     */
    public function setProjectStructureValue(string $key, mixed $value): void
    {
        $this->projectStructure[$key] = $value;
    }

    // ── saveMetadata() testing support ──────────────────────────────

    private ?ProjectsMetadataDao $projectsMetadataDaoOverride = null;

    /**
     * Inject a mock/stub ProjectsMetadataDao for saveMetadata() tests.
     */
    public function setProjectsMetadataDao(ProjectsMetadataDao $dao): void
    {
        $this->projectsMetadataDaoOverride = $dao;
    }

    protected function createProjectsMetadataDao(): ProjectsMetadataDao
    {
        return $this->projectsMetadataDaoOverride ?? parent::createProjectsMetadataDao();
    }

    /**
     * Public wrapper to invoke the protected saveMetadata().
     * @throws Exception
     */
    public function callSaveMetadata(): void
    {
        $this->saveMetadata();
    }

    // ── saveJobsMetadata() testing support ──────────────────────────

    private ?JobsMetadataDao $jobsMetadataDaoOverride = null;

    /**
     * Inject a mock/stub JobsMetadataDao for saveJobsMetadata() tests.
     */
    public function setJobsMetadataDao(JobsMetadataDao $dao): void
    {
        $this->jobsMetadataDaoOverride = $dao;
    }

    protected function createJobsMetadataDao(): JobsMetadataDao
    {
        return $this->jobsMetadataDaoOverride ?? parent::createJobsMetadataDao();
    }

    /**
     * Public wrapper to invoke the protected saveJobsMetadata().
     * @throws ReflectionException
     */
    public function callSaveJobsMetadata(JobStruct $newJob, ArrayObject $projectStructure): void
    {
        $this->saveJobsMetadata($newJob, $projectStructure);
    }

    // ── Step 11b: setters / getters / config methods testing support ──

    /**
     * Public wrapper to invoke the protected _getRequestedFeatures().
     * @return BasicFeatureStruct[]
     */
    public function callGetRequestedFeatures(): array
    {
        return $this->_getRequestedFeatures();
    }

    /**
     * Public wrapper to invoke the protected saveFeaturesInMetadata().
     * @throws ReflectionException
     */
    public function callSaveFeaturesInMetadata(): void
    {
        $this->saveFeaturesInMetadata();
    }

    /**
     * Override reloadFeatures() to avoid DB hit in tests.
     * Does nothing — the features property is already injected via initForTest().
     */
    protected function reloadFeatures(): void
    {
        // no-op: features already set by test
    }

    /**
     * Expose the project property for assertions.
     */
    public function getProject(): ?ProjectStruct
    {
        return $this->project;
    }

    // ── Step 11c: _insertInstructions / __checkForProjectAssignment ──

    /**
     * Public wrapper to invoke the protected _insertInstructions().
     */
    public function callInsertInstructions(int $fid, string $value): void
    {
        $this->insertInstructions($fid, $value);
    }

    /**
     * Public wrapper to invoke the protected __checkForProjectAssignment().
     */
    public function callCheckForProjectAssignment(): void
    {
        $this->checkForProjectAssignment();
    }

    private ?TeamDao $teamDaoOverride = null;

    /**
     * Inject a mock/stub TeamDao for __checkForProjectAssignment() tests.
     */
    public function setTeamDao(TeamDao $dao): void
    {
        $this->teamDaoOverride = $dao;
    }

    protected function createTeamDao(): TeamDao
    {
        return $this->teamDaoOverride ?? parent::createTeamDao();
    }
}

