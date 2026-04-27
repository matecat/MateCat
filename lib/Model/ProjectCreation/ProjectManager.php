<?php

namespace Model\ProjectCreation;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\ActivityLog\ActivityLogStruct;
use Model\Concerns\LogsMessages;
use Model\ConnectedServices\GDrive\Session;
use Model\Conversion\ZipArchiveHandler;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Files\FileDao;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FilesStorageFactory;
use Model\FilesStorage\S3FilesStorage;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use Model\Xliff\DTO\XliffRulesModel;
use Plugins\Features\SecondPassReview;
use ReflectionException;
use Throwable;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\ActivityLogWorker;
use Utils\Constants\ProjectStatus;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TMS\TMSService;
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;

class ProjectManager
{
    use LogsMessages;

    /**
     * Counter from the total number of segments in the project with the flag (show_in_cattool == true)
     */
    protected int $show_in_cattool_segs_counter = 0;
    protected int $files_word_count = 0;
    protected int $total_segments = 0;
    /** @var array<string, int> */
    protected array $min_max_segments_id = [];

    protected ProjectStructure $projectStructure;

    protected TMSService $tmxServiceWrapper;

    protected string $uploadDir;

    /*
       flag used to indicate TMX check status:
       0-not to check, or check passed
       1-still checking, but no useful TM for this project have been found, so far (no one matches this project langpair)
     */

    protected ProjectStruct $project;

    protected ?Session $gdriveSession = null;

    protected FeatureSet $features;

    protected IDatabase $dbHandler;

    protected MateCatFilter $filter;

    protected MetadataDao $filesMetadataDao;

    /**
     * Lazily created extractor for segment extraction.
     */
    protected ?SegmentExtractor $segmentExtractor = null;

    protected ?TmKeyService $tmKeyService = null;

    protected ?SegmentStorageService $segmentStorageService = null;

    protected ?ProjectManagerModel $projectManagerModel = null;

    protected ?JobCreationService $jobCreationService = null;

    protected ?FileInsertionService $fileInsertionService = null;

    protected ?QAProcessor $qaProcessor = null;

    /**
     * ProjectManager constructor.
     *
     * @param ProjectStructure $projectStructure
     * @throws ReflectionException
     * @throws Exception
     */
    public function __construct(ProjectStructure $projectStructure)
    {
        $this->logger = LoggerFactory::getLogger('project_manager');

        $this->projectStructure = $projectStructure;

        //get the TMX management component from the factory
        $this->tmxServiceWrapper = new TMSService();

        $this->dbHandler = Database::obtain();

        $this->features = new FeatureSet($this->getRequestedFeatures());

        if (!empty($this->projectStructure->id_customer)) {
            $this->features->loadAutoActivableOwnerFeatures($this->projectStructure->id_customer);
        }

        /** @var MateCatFilter $filter */
        $filter = MateCatFilter::getInstance(
            $this->features,
            $this->projectStructure->source_language,
            (string)json_encode($this->projectStructure->target_language),
            [],
            json_decode($this->projectStructure->subfiltering_handlers ?? 'null')
        );
        $this->filter = $filter;

        // sync array_files_meta
        $array_files_meta = [];
        foreach ($this->projectStructure->array_files_meta as $fileMeta) {
            if (in_array($fileMeta['basename'], $this->projectStructure->array_files)) {
                $array_files_meta[] = $fileMeta;
            }
        }

        $this->projectStructure->array_files_meta = $array_files_meta;

        $this->filesMetadataDao = new MetadataDao();
    }

    /**
     * Get or lazily create the SegmentExtractor instance.
     * The same instance is reused across all files so counters accumulate.
     */
    protected function getSegmentExtractor(): SegmentExtractor
    {
        return $this->segmentExtractor ??= new SegmentExtractor(
            $this->projectStructure,
            $this->filter,
            $this->features,
            $this->filesMetadataDao,
            $this->logger,
        );
    }

    /**
     * Get or lazily create the TmKeyService instance.
     */
    protected function getTmKeyService(): TmKeyService
    {
        return $this->tmKeyService ??= new TmKeyService(
            $this->tmxServiceWrapper,
            $this->dbHandler,
            $this->logger,
            fn(string $fileName) => $this->getSingleS3QueueFile($fileName),
        );
    }


    /**
     * Get or lazily create the SegmentStorageService instance.
     * The same instance is reused across all files so min/max IDs accumulate.
     */
    protected function getSegmentStorageService(): SegmentStorageService
    {
        return $this->segmentStorageService ??= new SegmentStorageService(
            $this->dbHandler,
            $this->features,
            $this->logger,
            $this->getProjectManagerModel(),
        );
    }

    /**
     * Get or lazily create the ProjectManagerModel instance.
     */
    protected function getProjectManagerModel(): ProjectManagerModel
    {
        return $this->projectManagerModel ??= new ProjectManagerModel(
            $this->dbHandler,
            $this->logger,
        );
    }

    /**
     * Get or lazily create the JobCreationService instance.
     */
    protected function getJobCreationService(): JobCreationService
    {
        return $this->jobCreationService ??= new JobCreationService(
            $this->features,
            $this->logger,
        );
    }

    /**
     * Get or lazily create the QAProcessor instance.
     */
    protected function getQAProcessor(): QAProcessor
    {
        return $this->qaProcessor ??= new QAProcessor(
            $this->filter,
            $this->features,
            (bool) ($this->projectStructure->metadata[ProjectsMetadataMarshaller::ICU_ENABLED->value] ?? false),
        );
    }

    /**
     * Get or lazily create the FileInsertionService instance.
     */
    protected function getFileInsertionService(): FileInsertionService
    {
        return $this->fileInsertionService ??= new FileInsertionService(
            $this->getProjectManagerModel(),
            $this->filesMetadataDao,
            $this->gdriveSession,
            fn(string $fileName) => $this->getSingleS3QueueFile($fileName),
            $this->logger,
        );
    }

    /**
     * @return list<BasicFeatureStruct>
     */
    protected function getRequestedFeatures(): array
    {
        $features = [];
        $projectFeatures = $this->projectStructure->project_features;
        foreach ($projectFeatures as $feature) {
            if ($feature instanceof BasicFeatureStruct) {
                $features[] = $feature;
            } else {
                $features[] = new BasicFeatureStruct((array)$feature);
            }
        }

        return $features;
    }

    public function setTeam(TeamStruct $team): void
    {
        $this->projectStructure->team = $team;
        $this->projectStructure->id_team = $team->id;
    }


    /**
     * Save features in project metadata
     * @throws ReflectionException
     */
    protected function saveFeaturesInMetadata(): void
    {
        $dao = $this->getProjectsMetadataDao();

        $featureCodes = $this->features->getCodes();
        if (!empty($featureCodes)) {
            $dao->set(
                (int)$this->projectStructure->id_project,
                ProjectsMetadataMarshaller::FEATURES_KEY->value,
                implode(',', $featureCodes)
            );
        }
    }

    /**
     * Persist project-level metadata options via ProjectMetadataService.
     *
     * @throws Exception
     */
    protected function saveMetadata(): void
    {
        $service = $this->getProjectMetadataService();
        $service->save($this->projectStructure, $this->features);
    }

    /**
     * Get a ProjectMetadataService instance — overridable in tests.
     */
    protected function getProjectMetadataService(): ProjectMetadataService
    {
        return new ProjectMetadataService($this->getProjectsMetadataDao(), $this->logger);
    }

    /**
     * Get a ProjectsMetadataDao instance — overridable in tests.
     */
    protected function getProjectsMetadataDao(): ProjectsMetadataDao
    {
        return new ProjectsMetadataDao();
    }

    /**
     * Creates a record in the projects table and instantiates the project struct
     * internally.
     *
     * @throws ReflectionException|Exception
     */
    private function createProjectRecord(): void
    {
        $this->project = $this->getProjectManagerModel()->createProjectRecord(
            $this->projectStructure,
            $this->projectStructure->id_team,
            $this->projectStructure->status ?? ProjectStatus::STATUS_NEW,
            $this->projectStructure->id_assignee,
        );
    }

    /**
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    protected function checkForProjectAssignment(): void
    {
        if (!empty($this->projectStructure->uid)) {
            //if this is a logged user, set the user as project assignee
            $this->projectStructure->id_assignee = $this->projectStructure->uid;

            /**
             * Normalize a team (array or TeamStruct) into TeamStruct
             */
            $teamData = $this->projectStructure->team instanceof TeamStruct
                ? $this->projectStructure->team->getArrayCopy()
                : (array)$this->projectStructure->team;
            $this->projectStructure->team = new TeamStruct(
                $this->features->filter('filter_team_for_project_creation', $teamData)
            );

            //clean the cache for the team member list of assigned projects
            $teamDao = $this->getTeamDao();
            $teamDao->destroyCacheAssignee($this->projectStructure->team);
        }
    }

    /**
     * Get a TeamDao instance — overridable in tests.
     */
    protected function getTeamDao(): TeamDao
    {
        return new TeamDao();
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function createProject(): void
    {
        // Normalize xliff_parameters: after queue deserialization this arrives as
        // a plain array because XliffRulesModel::$ruleSets is private and
        // RecursiveArrayCopy::toArray() only sees public properties.
        if (!$this->projectStructure->xliff_parameters instanceof XliffRulesModel) {
            $this->projectStructure->xliff_parameters = XliffRulesModel::fromArray(
                is_array($this->projectStructure->xliff_parameters) ? $this->projectStructure->xliff_parameters : []
            );
        }

        $fs = FilesStorageFactory::create();

        try {
            $this->initGdriveSession();
            $this->validateBeforeCreation();

            $firstTMXFileName = $this->sortFilesWithTmxFirst();
            $this->setPrivateTmKeysOrFail($firstTMXFileName);

            $linkFiles = $this->resolveUploadDirAndGetHashes($fs);
            $this->pushTmxToMemory();

            $this->getFileInsertionService()->registerNativeXliffsAsConverted(
                $fs, $this->projectStructure, $this->uploadDir, $linkFiles
            );
            $this->handleZipFiles($linkFiles);

            $this->resolveFilesExtractSegmentsAndStoreData($fs, $linkFiles);
            $this->finalizeProjectInTransaction();
        } finally {
            // Ensure the upload directory is cleaned up even when an exception
            // interrupts project creation, preventing orphaned temp files.
            if (isset($this->uploadDir)) {
                $this->cleanupUploadDirectory($fs);
            }
        }
    }

    /**
     * Initialize a Google Drive session if a UID is present in the session data.
     * @throws Exception
     */
    private function initGdriveSession(): void
    {
        if (!empty($this->projectStructure->session['uid'])) {
            $this->projectStructure->session['user'] = new UserStruct($this->projectStructure->session['user']);
            $this->gdriveSession = Session::getInstanceForCLI($this->projectStructure->session);
        }
    }

    /**
     * Run all pre-creation validations (assignment, quality framework, feature hooks).
     * Aborts with EndQueueException if any errors are found.
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    private function validateBeforeCreation(): void
    {
        // set creation date now
        $this->projectStructure->create_date = date('Y-m-d H:i:s');

        $this->checkForProjectAssignment();

        SecondPassReview::loadAndValidateQualityFramework($this->projectStructure);
        $this->features->run('validateProjectCreation', $this->projectStructure);

        if (count($this->projectStructure->result['errors']) > 0) {
            $this->log($this->projectStructure->result['errors']);

            throw new EndQueueException("Invalid Project found.");
        }
    }

    /**
     * Sort array_files so TMX/glossary files come first. Returns the first TMX filename (or empty string).
     */
    private function sortFilesWithTmxFirst(): string
    {
        $sortedFiles = [];
        $sortedMeta = [];
        $firstTMXFileName = "";

        foreach ($this->projectStructure->array_files as $pos => $fileName) {
            $meta = $this->projectStructure->array_files_meta[$pos] ?? [];

            if (!empty($meta['getMemoryType'])) {
                if (!empty($meta['isTMX'])) {
                    $firstTMXFileName = (empty($firstTMXFileName) ? $fileName : null);
                }

                array_unshift($sortedFiles, $fileName);
                array_unshift($sortedMeta, $meta);
            } else {
                $sortedFiles[] = $fileName;
                $sortedMeta[] = $meta;
            }
        }

        $this->projectStructure->array_files = $sortedFiles;
        $this->projectStructure->array_files_meta = $sortedMeta;

        return $firstTMXFileName ?? '';
    }

    /**
     * Validate and insert private TM keys. Aborts if validation errors occur.
     *
     * @throws EndQueueException
     */
    private function setPrivateTmKeysOrFail(string $firstTMXFileName): void
    {
        if (count($this->projectStructure->private_tm_key)) {
            $this->getTmKeyService()->setPrivateTMKeys($this->projectStructure, $firstTMXFileName);

            if (count($this->projectStructure->result['errors']) > 0) {
                throw new EndQueueException("Invalid Project found.");
            }
        }
    }

    /**
     * Resolve the upload directory from config + upload token, and retrieve file hashes from storage.
     *
     * @return array<string, mixed> The link files structure with conversion hashes and zip hashes.
     */
    private function resolveUploadDirAndGetHashes(AbstractFilesStorage $fs): array
    {
        $this->uploadDir = AppConfig::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure->uploadToken;
        $this->log($this->uploadDir);

        $linkFiles = $fs->getHashesFromDir($this->uploadDir);
        $this->log($linkFiles);

        return $linkFiles;
    }

    /**
     * Push TMX files to MyMemory via TmKeyService.
     *
     * @throws EndQueueException
     */
    private function pushTmxToMemory(): void
    {
        try {
            $this->getTmKeyService()->pushTMXToMyMemory($this->projectStructure, $this->uploadDir);
        } catch (Exception $e) {
            $this->log($e->getMessage(), $e);
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Link zip file hashes to the project. Aborts on failure.
     *
     * @param array<string, mixed> $linkFiles
     *
     * @throws EndQueueException
     */
    private function handleZipFiles(array $linkFiles): void
    {
        try {
            $this->zipFileHandling($linkFiles);
        } catch (Exception $e) {
            $this->log($e->getMessage(), $e);
            $this->projectStructure->addError($e->getCode(), $e->getMessage());
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Resolve and insert files, extract segments, create project record, store segments,
     * create jobs, insert pre-translations, and write analysis data.
     * Tolerates individual file extraction failures in multi-file projects.
     * On any failure, cleans up the project and file records before aborting.
     *
     * @param array<string, mixed> $linkFiles
     *
     * @throws EndQueueException
     */
    private function resolveFilesExtractSegmentsAndStoreData(
        AbstractFilesStorage $fs,
        array $linkFiles
    ): void {
        // $linkFile is needed in the error handler for hash cleanup
        $linkFile = '';
        if (isset($linkFiles['conversionHashes']['sha'])) {
            $shaSum = $linkFiles['conversionHashes']['sha'];
            $linkFile = end($shaSum) ?: '';
        }

        try {
            $totalFilesStructure = $this->getFileInsertionService()->resolveAndInsertFiles(
                $fs, $this->projectStructure, $linkFiles
            );

            $this->extractSegmentsFromFiles($totalFilesStructure);

            if ($this->total_segments === 0) {
                throw new Exception(
                    "No translatable content found in any uploaded file. The project cannot be created.",
                    ProjectCreationError::NO_TRANSLATABLE_TEXT->value
                );
            }

            if ($this->files_word_count > AppConfig::$MAX_SOURCE_WORDS) {
                throw new Exception(
                    "Matecat is unable to create your project. Please contact us at " . AppConfig::$SUPPORT_MAIL . ", we will be happy to help you!",
                    ProjectCreationError::MAX_WORDS_EXCEEDED->value
                );
            }

            $this->features->run("beforeProjectCreation", $this->projectStructure, [
                    'total_project_segments' => $this->total_segments,
                    'files_raw_wc' => $this->files_word_count
                ]
            );

            $this->createProjectRecord();
            $this->saveFeaturesInMetadata();
            $this->saveMetadata();

            foreach ($totalFilesStructure as $fid => $empty) {
                $this->storeSegments($fid);
            }

            $jobs = $this->createJobs();
            $this->linkFilesAndInsertPreTranslations($jobs);

            if (!empty($this->projectStructure->notes)) {
                $this->insertSegmentNotesForFile();
            }

            if (!empty($this->projectStructure->context_group)) {
                $this->insertContextsForFile();
            }

            $this->projectStructure->translations = [];

            $this->writeFastAnalysisData();

            $this->determineStatusAndPopulateResult();
            $this->insertFileInstructions($totalFilesStructure);
        } catch (Throwable $e) {
            $this->clearFailedProject($e);
            $this->mapSegmentExtractionError($e, $fs, $linkFile);
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Iterate over the files structure, extracting segments from each file.
     *
     * Files that contain no translatable text are silently removed from the
     * structure (by reference) as long as at least one other file remains.
     * If the last file also fails, the exception is re-thrown so the caller
     * can handle it as a project-level error.
     *
     * @param array<int, array<string, mixed>> $totalFilesStructure
     *
     * @throws Exception
     */
    private function extractSegmentsFromFiles(array &$totalFilesStructure): void
    {
        $exceptionsFound = 0;
        foreach ($totalFilesStructure as $fid => $file_info) {
            try {
                $this->extractSegments($fid, $file_info);
            } catch (Exception $e) {
                $this->log($totalFilesStructure);
                $this->log("Count fileSt.: " . count($totalFilesStructure));
                $this->log("Exceptions: " . $exceptionsFound);
                $this->log("Failed to parse " . $file_info['original_filename'], $e);

                if ($e->getCode() == ProjectCreationError::NO_TRANSLATABLE_TEXT->value && count($totalFilesStructure) > 1 && $exceptionsFound < count($totalFilesStructure)) {
                    $this->log("No text to translate in the file {$e->getMessage()}.");
                    $exceptionsFound += 1;
                    unset($totalFilesStructure[$fid]);
                    continue;
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Map segment-extraction/project-creation error codes to user-friendly project errors.
     */
    private function mapSegmentExtractionError(Throwable $e, AbstractFilesStorage $fs, string $linkFile): void
    {
        $code = $e->getCode();

        match ($code) {
            ProjectCreationError::NO_TRANSLATABLE_TEXT->value => $this->projectStructure->addError(
                ProjectCreationError::NO_TRANSLATABLE_TEXT->value,
                "No text to translate in the file " . ZipArchiveHandler::getFileName($e->getMessage()) . "."
            ),
            ProjectCreationError::XLIFF_PARSE_FAILURE->value => $this->projectStructure->addError(ProjectCreationError::XLIFF_IMPORT_ERROR->value, "Xliff Import Error: {$e->getMessage()}"),
            ProjectCreationError::INVALID_XLIFF_PARAMETERS->value => $this->projectStructure->addError(
                $code,
                (null !== $e->getPrevious()) ? $e->getPrevious()->getMessage() . " in {$e->getMessage()}" : $e->getMessage()
            ),
            default => $this->projectStructure->addError($code, $e->getMessage()),
        };

        if ($code == ProjectCreationError::NO_TRANSLATABLE_TEXT->value && AppConfig::$FILE_STORAGE_METHOD != 's3') {
            $fs->deleteHashFromUploadDir($this->uploadDir, $linkFile);
        }

        $this->log("Exception", $e);
    }

    /**
     * Determine project status and populate the result structure with success data.
     */
    private function determineStatusAndPopulateResult(): void
    {
        $this->projectStructure->status = (AppConfig::$VOLUME_ANALYSIS_ENABLED) ? ProjectStatus::STATUS_NEW : ProjectStatus::STATUS_NOT_TO_ANALYZE;

        if ($this->show_in_cattool_segs_counter == 0) {
            $this->log("Segment Search: No segments in this project - \n");
            $this->projectStructure->status = ProjectStatus::STATUS_EMPTY;
        }

        $this->projectStructure->result['code'] = 1;
        $this->projectStructure->result['data'] = "OK";
        $this->projectStructure->result['ppassword'] = $this->projectStructure->ppassword;
        $this->projectStructure->result['password'] = $this->projectStructure->array_jobs['job_pass'];
        $this->projectStructure->result['id_job'] = $this->projectStructure->array_jobs['job_list'];
        $this->projectStructure->result['job_segments'] = $this->projectStructure->array_jobs['job_segments'];
        $this->projectStructure->result['id_project'] = $this->projectStructure->id_project;
        $this->projectStructure->result['project_name'] = $this->projectStructure->project_name;
        $this->projectStructure->result['source_language'] = $this->projectStructure->source_language;
        $this->projectStructure->result['target_language'] = $this->projectStructure->target_language;
        $this->projectStructure->result['status'] = $this->projectStructure->status;
    }

    /**
     * Insert file-level instruction notes, matching files back to their original order.
     *
     * @param array<int, array<string, mixed>> $totalFilesStructure
     *
     * @throws Exception
     *
     */
    private function insertFileInstructions(array $totalFilesStructure): void
    {
        $array_files = $this->projectStructure->array_files;

        foreach ($totalFilesStructure as $fid => $file_info) {
            foreach ($array_files as $index => $filename) {
                if ($file_info['original_filename'] === $filename) {
                    if (isset($this->projectStructure->instructions[$index]) && !empty($this->projectStructure->instructions[$index])) {
                        $this->insertInstructions($fid, $this->projectStructure->instructions[$index]);
                    }
                }
            }
        }
    }

    /**
     * Finalize the project: warm caches, run post-create hooks, update analysis status, commit transaction.
     * @throws Exception
     */
    private function finalizeProjectInTransaction(): void
    {
        if (AppConfig::$VOLUME_ANALYSIS_ENABLED) {
            $this->projectStructure->result['analyze_url'] = $this->getAnalyzeURL();
        }

        $db = Database::obtain();
        $db->begin();

        try {
            (new ProjectDao())->destroyCacheForProjectData((int)$this->projectStructure->id_project, $this->projectStructure->ppassword);
            (new ProjectDao())->setCacheTTL(60 * 60 * 24)->getProjectData((int)$this->projectStructure->id_project, $this->projectStructure->ppassword);

            $this->features->run('postProjectCreate', $this->projectStructure);

            ProjectDao::updateAnalysisStatus(
                $this->projectStructure->id_project,
                $this->projectStructure->status,
                $this->files_word_count * count($this->projectStructure->array_jobs['job_languages'])
            );

            $this->pushActivityLog();

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }

        $this->features->run('postProjectCommit', $this->projectStructure);
    }

    /**
     * Delete the upload directory via the storage abstraction.
     */
    private function cleanupUploadDirectory(AbstractFilesStorage $fs): void
    {
        try {
            $this->log('Deleting upload directory: ' . $this->uploadDir);
            $fs->deleteQueue($this->uploadDir);
        } catch (Exception $e) {
            $output = "Exception: " . $e->getMessage() . "\n";
            $output .= "REQUEST URI: " . ($_SERVER['REQUEST_URI'] ?? '(unavailable)') . "\n";
            $output .= "REQUEST: " . print_r($_REQUEST, true) . "\n";
            $output .= "Trace:\n" . $e->getTraceAsString() . "\n";

            $this->log($output, $e);

            try {
                Utils::sendErrMailReport($output, $e->getMessage());
            } catch (Exception) {
            }
        }
    }

    /**
     * @param string $fileName
     *
     * @throws Exception
     */
    public function getSingleS3QueueFile(string $fileName): void
    {
        $fs = FilesStorageFactory::create();

        if (false === is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755);
        }

        /** @var S3FilesStorage $fs */
        $client = $fs::getStaticS3Client();
        $params['bucket'] = AppConfig::$AWS_STORAGE_BASE_BUCKET;
        $params['key'] = $fs::QUEUE_FOLDER . DIRECTORY_SEPARATOR . $fs::getUploadSessionSafeName((string)$fs->getTheLastPartOfKey($this->uploadDir)) . DIRECTORY_SEPARATOR . $fileName;
        $params['save_as'] = "$this->uploadDir/$fileName";
        $client->downloadItem($params);
    }

    private function clearFailedProject(Throwable $e): void
    {
        $this->log($e->getMessage(), $e);
        $this->log("Deleting Records.");

        if (isset($this->project)) {
            (new ProjectDao())->deleteFailedProject($this->projectStructure->id_project);
            $this->log("Deleted Project ID: " . $this->projectStructure->id_project);
        }

        (new FileDao())->deleteFailedProjectFiles($this->projectStructure->file_id_list);
        $this->log("Deleted Files ID: " . json_encode($this->projectStructure->file_id_list));
    }

    /**
     * @throws Exception
     */
    private function writeFastAnalysisData(): void
    {
        $job_id_passes = ltrim(
            array_reduce(
                array_keys($this->projectStructure->array_jobs['job_segments']),
                function ($acc, $value) {
                    $acc .= "," . strtr((string)$value, '-', ':');

                    return $acc;
                },
                ''
            ),
            ","
        );

        foreach ($this->projectStructure->segments_metadata as &$segmentElement) {
            unset($segmentElement['internal_id']);
            unset($segmentElement['xliff_mrk_id']);
            unset($segmentElement['show_in_cattool']);

            $segmentElement['jsid'] = $segmentElement['id'] . "-" . $job_id_passes;
            $segmentElement['source'] = $this->projectStructure->source_language;
            $segmentElement['target'] = implode(",", $this->projectStructure->array_jobs['job_languages']);
            $segmentElement['payable_rates'] = $this->projectStructure->array_jobs['payable_rates'];
            $segmentElement['segment'] = $this->filter->fromLayer0ToLayer1($segmentElement['segment']);
        }
        unset($segmentElement); // break the reference to the last array element to avoid accidental overwrites

        $fs = FilesStorageFactory::create();
        $fs::storeFastAnalysisFile((string)$this->project->id, $this->projectStructure->segments_metadata);

        $this->logger->debug("Stored fast analysis data for project {$this->project->id} with " . count($this->projectStructure->segments_metadata) . " segments.");

        //free memory
        $this->projectStructure->segments_metadata = [];
    }

    private function pushActivityLog(): void
    {
        $activity = new ActivityLogStruct();
        $activity->id_project = $this->projectStructure->id_project;
        $activity->action = ActivityLogStruct::PROJECT_CREATED;
        $activity->ip = $this->projectStructure->user_ip ?? '';
        $activity->uid = $this->projectStructure->uid;
        $activity->event_date = date('Y-m-d H:i:s');

        try {
            WorkerClient::enqueueWithClient(
                AMQHandler::getNewInstanceForDaemons(),
                'ACTIVITYLOG',
                ActivityLogWorker::class,
                $activity->toArray(),
                ['persistent' => WorkerClient::$_HANDLER->persistent]
            );
        } catch (Exception $e) {
            # Handle the error, logging, ...
            $output = "**** Activity Log failed. AMQ Connection Error. **** ";
            $output .= var_export($activity, true);
            $this->log($output, $e);
        }
    }

    /**
     * @throws Exception
     */
    public function getAnalyzeURL(): string
    {
        return CanonicalRoutes::analyze(
            [
                'project_name' => $this->projectStructure->project_name,
                'id_project' => $this->projectStructure->id_project,
                'password' => $this->projectStructure->ppassword
            ],
            [
                'http_host' => (is_null($this->projectStructure->HTTP_HOST) ?
                    AppConfig::$HTTPHOST :
                    $this->projectStructure->HTTP_HOST
                ),
            ]
        );
    }

    /**
     * @param array<string, mixed> $linkFiles
     *
     * @throws Exception
     */
    protected function zipFileHandling(array $linkFiles): void
    {
        $fs = FilesStorageFactory::create();

        //begin with zip hashes manipulation
        foreach ($linkFiles['zipHashes'] as $zipHash) {
            $result = $fs->linkZipToProject(
                $this->projectStructure->create_date,
                $zipHash,
                (string)$this->projectStructure->id_project
            );

            if (!$result) {
                $this->log("Failed to store the Zip file $zipHash - \n");
                throw new Exception("Failed to store the original Zip $zipHash ", ProjectCreationError::ZIP_STORE_FAILED->value);
                //Exit
            }
        } //end zip hashes manipulation

    }

    /**
     * @return list<JobStruct>
     * @throws Exception
     */
    protected function createJobs(): array
    {
        return $this->getJobCreationService()->createJobsForTargetLanguages(
            $this->projectStructure,
            $this->min_max_segments_id,
            $this->files_word_count,
        );
    }

    /**
     * For each created job, link project files and insert any pre-translations.
     *
     * @param list<JobStruct> $jobs
     * @throws Exception
     */
    private function linkFilesAndInsertPreTranslations(array $jobs): void
    {
        $this->getJobCreationService()->linkFilesAndInsertPreTranslations(
            $jobs,
            $this->projectStructure,
            $this->gdriveSession,
            $this->getSegmentStorageService(),
            $this->getQAProcessor(),
        );
    }

    /**
     * Extract sources and pre-translations from a xliff file and put them in Database.
     *
     * @param array<string, mixed> $file_info
     *
     * @throws Exception
     */
    protected function extractSegments(int $fid, array $file_info): void
    {
        $this->getSegmentExtractor()->extract($fid, $file_info, $this->projectStructure);
        $this->syncCountersFromExtractor();
    }

    /**
     * Copy the accumulated counters from the SegmentExtractor back to instance properties.
     */
    private function syncCountersFromExtractor(): void
    {
        $extractor = $this->getSegmentExtractor();
        $this->files_word_count = $extractor->getFilesWordCount();
        $this->total_segments = $extractor->getTotalSegments();
        $this->show_in_cattool_segs_counter = $extractor->getShowInCattoolSegsCounter();
    }

    /**
     * @param array<string, mixed>|string $value
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    protected function insertInstructions(int $fid, array|string $value): void
    {
        $value = $this->features->filter('decodeInstructions', $value);

        $this->filesMetadataDao->insert((int)$this->projectStructure->id_project, $fid, 'instructions', (string)$value);
    }

    /**
     * Store segments for a file — delegates to SegmentStorageService.
     * @throws Exception
     */
    protected function storeSegments(int $fid): void
    {
        $this->getSegmentStorageService()->storeSegments($fid, $this->projectStructure);
        $this->min_max_segments_id = $this->getSegmentStorageService()->getMinMaxSegmentsId();
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function insertSegmentNotesForFile(): void
    {
        $this->projectStructure = $this->features->filter('handleJsonNotesBeforeInsert', $this->projectStructure);
        $this->getProjectManagerModel()->bulkInsertSegmentNotesAndMetadata($this->projectStructure->notes);
    }

    /**
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws Exception
     */
    private function insertContextsForFile(): void
    {
        $this->features->filter('handleTUContextGroups', $this->projectStructure);
        $this->getProjectManagerModel()->bulkInsertContextsGroups(
            (int)$this->projectStructure->id_project,
            $this->projectStructure->context_group,
        );
    }

}