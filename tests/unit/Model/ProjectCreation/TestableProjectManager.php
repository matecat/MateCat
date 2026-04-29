<?php

namespace unit\Model\ProjectCreation;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\ProjectCreation\FileInsertionService;
use Model\ProjectCreation\JobCreationService;
use Model\ProjectCreation\ProjectManager;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use Model\Xliff\DTO\XliffRulesModel;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

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
     * @param MateCatFilter $filter
     * @param FeatureSet $features
     * @param MetadataDao $filesMetadataDao
     * @param MatecatLogger $logger
     * @param XliffRulesModel|null $xliffParameters
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
        $this->projectStructure = new ProjectStructure([
            'id_project' => 999,
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'private_tm_key' => [],
            'segments' => [],
            'segments_original_data' => [],
            'segments_meta_data' => [],
            'translations' => [],
            'notes' => [],
            'context_group' => [],
            'current_xliff_info' => [],
            ProjectsMetadataMarshaller::XLIFF_PARAMETERS->value => $xliffParameters ?? new XliffRulesModel(),
            'result' => ['errors' => []],
            // Mutable pipeline keys needed by createProjectRecord() caller
            'status' => ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
            'id_team' => null,
            'id_assignee' => null,
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
    public function getTestProjectStructure(): ProjectStructure
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
     * Set a specific key in the projectStructure for testing.
     */
    public function setProjectStructureValue(string $key, mixed $value): void
    {
        $this->projectStructure->$key = $value;
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

    protected function getProjectsMetadataDao(): ProjectsMetadataDao
    {
        return $this->projectsMetadataDaoOverride ?? parent::getProjectsMetadataDao();
    }

    // ── JobCreationService override ──────────────────────────────

    private ?JobCreationService $jobCreationServiceOverride = null;

    public function setJobCreationService(JobCreationService $service): void
    {
        $this->jobCreationServiceOverride = $service;
    }

    protected function getJobCreationService(): JobCreationService
    {
        return $this->jobCreationServiceOverride ?? parent::getJobCreationService();
    }

    // ── FileInsertionService override ──────────────────────────────

    private ?FileInsertionService $fileInsertionServiceOverride = null;

    public function setFileInsertionService(FileInsertionService $service): void
    {
        $this->fileInsertionServiceOverride = $service;
    }

    protected function getFileInsertionService(): FileInsertionService
    {
        return $this->fileInsertionServiceOverride ?? parent::getFileInsertionService();
    }

    // ── Step 11b: setters / getters / config methods testing support ──

    /**
     * Public wrapper to invoke the protected _getRequestedFeatures().
     * @return BasicFeatureStruct[]
     */
    public function callGetRequestedFeatures(): array
    {
        return $this->getRequestedFeatures();
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
     * Expose the project property for assertions.
     */
    public function getProject(): ?ProjectStruct
    {
        return $this->project;
    }

    // ── Step 11c: _insertInstructions / __checkForProjectAssignment ──

    /**
     * Public wrapper to invoke the protected _insertInstructions().
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    public function callInsertInstructions(int $fid, string $value): void
    {
        $this->insertInstructions($fid, $value);
    }

    /**
     * Public wrapper to invoke the protected __checkForProjectAssignment().
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
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

    protected function getTeamDao(): TeamDao
    {
        return $this->teamDaoOverride ?? parent::getTeamDao();
    }

    // ── Step 12: additional private/protected methods testing support ──

    /**
     * Public wrapper to invoke the private sortFilesWithTmxFirst().
     * @throws ReflectionException
     */
    public function callSortFilesWithTmxFirst(): string
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('sortFilesWithTmxFirst');

        return $method->invoke($this);
    }

    /**
     * Public wrapper to invoke the private determineStatusAndPopulateResult().
     * @throws ReflectionException
     */
    public function callDetermineStatusAndPopulateResult(): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('determineStatusAndPopulateResult');
        $method->invoke($this);
    }

    /**
     * Set the show_in_cattool_segs_counter for testing.
     */
    public function setShowInCattoolSegsCounter(int $value): void
    {
        $this->show_in_cattool_segs_counter = $value;
    }

    /**
     * Public wrapper to invoke the private mapSegmentExtractionError().
     * @throws ReflectionException
     */
    public function callMapSegmentExtractionError(Throwable $e, AbstractFilesStorage $fs, string $linkFile): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('mapSegmentExtractionError');
        $method->invoke($this, $e, $fs, $linkFile);
    }

    /**
     * Set the uploadDir for testing.
     */
    public function setUploadDir(string $dir): void
    {
        $this->uploadDir = $dir;
    }

    /**
     * Public wrapper to invoke the private validateBeforeCreation().
     * @throws ReflectionException
     */
    public function callValidateBeforeCreation(): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('validateBeforeCreation');
        $method->invoke($this);
    }

    /**
     * Public wrapper to invoke the private insertFileInstructions().
     * @throws ReflectionException
     * @throws Exception
     */
    public function callInsertFileInstructions(array $totalFilesStructure): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('insertFileInstructions');
        $method->invoke($this, $totalFilesStructure);
    }

    /**
     * Set files_word_count for testing.
     */
    public function setFilesWordCount(int $value): void
    {
        $this->files_word_count = $value;
    }

    /**
     * Set min_max_segments_id for testing.
     */
    public function setMinMaxSegmentsId(array $value): void
    {
        $this->min_max_segments_id = $value;
    }

    // ── ProjectManagerModel override ──

    public function setProjectManagerModel(ProjectManagerModel $model): void
    {
        $this->projectManagerModel = $model;
    }

    /**
     * Public wrapper to invoke the private clearFailedProject().
     * @throws ReflectionException
     */
    public function callClearFailedProject(Throwable $e): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('clearFailedProject');
        $method->invoke($this, $e);
    }

    // ── Step 13: additional private methods testing support ──

    /**
     * Public wrapper to invoke the private createProjectRecord().
     * @throws ReflectionException
     * @throws Exception
     */
    public function callCreateProjectRecord(): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('createProjectRecord');
        $method->invoke($this);
    }

    /**
     * Public wrapper to invoke the private resolveUploadDirAndGetHashes().
     * @throws ReflectionException
     */
    public function callResolveUploadDirAndGetHashes(AbstractFilesStorage $fs): array
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('resolveUploadDirAndGetHashes');

        return $method->invoke($this, $fs);
    }

    /**
     * Public wrapper to invoke the private handleZipFiles().
     * @throws ReflectionException
     */
    public function callHandleZipFiles(array $linkFiles): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('handleZipFiles');
        $method->invoke($this, $linkFiles);
    }

    /**
     * Override _zipFileHandling so handleZipFiles() tests can control behavior.
     * By default, does nothing. Set a callback via setZipFileHandlingCallback().
     *
     * @var callable|null
     */
    private $zipFileHandlingCallback = null;

    public function setZipFileHandlingCallback(?callable $callback): void
    {
        $this->zipFileHandlingCallback = $callback;
    }

    protected function zipFileHandling($linkFiles): void
    {
        if ($this->zipFileHandlingCallback !== null) {
            ($this->zipFileHandlingCallback)($linkFiles);
        }
    }

    /**
     * Public wrapper to invoke the private cleanupUploadDirectory().
     * @throws ReflectionException
     */
    public function callCleanupUploadDirectory(AbstractFilesStorage $fs): void
    {
        $ref = new ReflectionClass(ProjectManager::class);
        $method = $ref->getMethod('cleanupUploadDirectory');
        $method->invoke($this, $fs);
    }

    /**
     * Get the uploadDir value for assertions.
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }
}

