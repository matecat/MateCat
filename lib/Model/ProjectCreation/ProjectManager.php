<?php

namespace Model\ProjectCreation;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\ActivityLog\ActivityLogStruct;
use Model\Concerns\LogsMessages;
use Model\ConnectedServices\GDrive\Session;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\Conversion\ZipArchiveHandler;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
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
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use Model\Xliff\DTO\XliffRulesModel;
use Plugins\Features\SecondPassReview;
use ReflectionException;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\ActivityLogWorker;
use Utils\Constants\EngineConstants;
use Utils\Constants\ProjectStatus;
use Utils\Engines\MyMemory;
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

    /**
     * ProjectManager constructor.
     *
     * @throws Exception
     * @throws NotFoundException
     * @throws AuthenticationError
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
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

        $this->projectStructure->array_files = $this->features->filter(
            'filter_project_manager_array_files',
            $this->projectStructure->array_files,
            $this->projectStructure
        );

        // sync array_files_meta
        $array_files_meta = [];
        foreach ($this->projectStructure->array_files_meta as $fileMeta) {
            if (in_array($fileMeta['basename'], (array)$this->projectStructure->array_files)) {
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
        if ($this->segmentExtractor === null) {
            $this->segmentExtractor = new SegmentExtractor(
                $this->projectStructure,
                $this->filter,
                $this->features,
                $this->filesMetadataDao,
                $this->logger,
            );
        }

        return $this->segmentExtractor;
    }

    /**
     * Get or lazily create the TmKeyService instance.
     */
    protected function getTmKeyService(): TmKeyService
    {
        if ($this->tmKeyService === null) {
            $this->tmKeyService = new TmKeyService(
                $this->tmxServiceWrapper,
                $this->dbHandler,
                $this->logger,
                fn(string $fileName) => $this->getSingleS3QueueFile($fileName),
            );
        }

        return $this->tmKeyService;
    }


    /**
     * Get or lazily create the SegmentStorageService instance.
     * The same instance is reused across all files so min/max IDs accumulate.
     */
    protected function getSegmentStorageService(): SegmentStorageService
    {
        if ($this->segmentStorageService === null) {
            $this->segmentStorageService = new SegmentStorageService(
                $this->dbHandler,
                $this->features,
                $this->logger,
                $this->filter,
                $this->getProjectManagerModel(),
            );
        }

        return $this->segmentStorageService;
    }

    /**
     * Get or lazily create the ProjectManagerModel instance.
     */
    protected function getProjectManagerModel(): ProjectManagerModel
    {
        if ($this->projectManagerModel === null) {
            $this->projectManagerModel = new ProjectManagerModel(
                $this->dbHandler,
                $this->logger,
            );
        }

        return $this->projectManagerModel;
    }

    /**
     * Get or lazily create the JobCreationService instance.
     */
    protected function getJobCreationService(): JobCreationService
    {
        if ($this->jobCreationService === null) {
            $this->jobCreationService = new JobCreationService(
                $this->features,
                $this->logger,
            );
        }

        return $this->jobCreationService;
    }

    /**
     * @return list<BasicFeatureStruct>
     */
    protected function getRequestedFeatures(): array
    {
        $features = [];
        $projectFeatures = $this->projectStructure->project_features;
        if (count($projectFeatures) != 0) {
            foreach ($projectFeatures as $feature) {
                if ($feature instanceof BasicFeatureStruct) {
                    $features[] = $feature;
                } else {
                    $features[] = new BasicFeatureStruct((array)$feature);
                }
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
                ProjectsMetadataDao::FEATURES_KEY,
                implode(',', $featureCodes)
            );
        }
    }

    /**
     *  Save custom project metadata
     *
     * This is where, among other things, we put project options.
     *
     * Project options may need to be sanitized so that we can silently ignore impossible combinations,
     * and we can apply defaults when those are missing.
     *
     * @throws Exception
     */
    protected function saveMetadata(): void
    {
        $options = $this->projectStructure->metadata;
        $dao = $this->getProjectsMetadataDao();

        // "From API" flag
        if ($this->projectStructure->from_api) {
            $options[ProjectsMetadataDao::FROM_API] = '1';
        }

        // xliff_parameters — only persist when the model contains actual rules.
        // Guard with instanceof: createProject() normalizes to XliffRulesModel,
        // but saveMetadata() is protected and may be called from other paths.
        if (
            $this->projectStructure->xliff_parameters instanceof XliffRulesModel
            && (
                !empty($this->projectStructure->xliff_parameters->getRulesForVersion(1))
                || !empty($this->projectStructure->xliff_parameters->getRulesForVersion(2))
            )
        ) {
            $options[ProjectsMetadataDao::XLIFF_PARAMETERS] = json_encode($this->projectStructure->xliff_parameters);
        }

        // pretranslate_101
        if (isset($this->projectStructure->pretranslate_101)) {
            $options[ProjectsMetadataDao::PRETRANSLATE_101] = (string)$this->projectStructure->pretranslate_101;
        }

        // mt evaluation => ice_mt already in metadata
        // adds JSON parameters to the project metadata as JSON string
        if ($options[ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED] ?? false) {
            $options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS] = json_encode($options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS]);
        } else {
            // When MT QE workflow is disabled, remove the raw array to prevent
            // passing a non-string value to MetadataDao::set()
            unset($options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS]);
        }

        /**
         * Here we have the opportunity to add other features as dependencies of the ones
         * which are already explicitly set.
         */
        $this->features->loadProjectDependenciesFromProjectMetadata($options);

        if ($this->projectStructure->filters_extraction_parameters) {
            $options[ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS] = json_encode($this->projectStructure->filters_extraction_parameters);
        }

        $extraKeys = [];
        // MT extra config parameters
        foreach (EngineConstants::getAvailableEnginesList() as $engineName) {
            $extraKeys = array_merge(
                $extraKeys,
                (new $engineName(
                    new EngineStruct([
                        'type' => $engineName == MyMemory::class ? EngineConstants::TM : EngineConstants::MT,
                    ])
                ))->getConfigurationParameters()
            );
        }

        foreach ($extraKeys as $extraKey) {
            $engineValue = $this->projectStructure->$extraKey;
            if (!empty($engineValue)) {
                $options[$extraKey] = $engineValue;
            }
        }

        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $dao->set(
                    (int)$this->projectStructure->id_project,
                    $key,
                    (string)$value
                );
            }
        }

        /** Duplicate the JobsMetadataDao::SUBFILTERING_HANDLERS in project metadata for easier retrieval.
         * During the analysis of the project, there is no need to query the JobsMetadataDao.
         * Configuration about handlers can be changed later in the job settings.
         * But the analysis must everytime be performed with the current configuration.
         * @see JobCreationService::saveJobsMetadata()
         */
        if (!empty($this->projectStructure->subfiltering_handlers)) {
            $dao->set(
                (int)$this->projectStructure->id_project,
                JobsMetadataDao::SUBFILTERING_HANDLERS,
                $this->projectStructure->subfiltering_handlers
            );
        }
    }

    /**
     * Get a ProjectsMetadataDao instance — overridable in tests.
     */
    protected function getProjectsMetadataDao(): ProjectsMetadataDao
    {
        return new ProjectsMetadataDao();
    }

    /**
     * Append an error entry to projectStructure->result['errors'].
     *
     * Centralizes the ~19 occurrences of the duplicated appended pattern.
     */
    protected function addProjectError(int $code, string $message): void
    {
        $this->projectStructure->result['errors'][] = [
            "code" => $code,
            "message" => $message,
        ];
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

            $this->cacheNonConvertedFiles($fs, $linkFiles);
            $this->handleZipFiles($linkFiles);

            $totalFilesStructure = $this->resolveAndInsertFiles($fs, $linkFiles);
            $this->extractSegmentsCreateProjectAndStoreData($fs, $totalFilesStructure, $linkFiles);

            $this->determineStatusAndPopulateResult();
            $this->insertFileInstructions($totalFilesStructure);
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
            $meta = $this->projectStructure->array_files_meta[$pos];

            if ($meta['getMemoryType']) {
                if ($meta['isTMX']) {
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
     * For files that don't need conversion, create cache packages and add them to conversionHashes.
     * Downloads from S3 if the file is missing locally.
     *
     * @param array<string, mixed> $linkFiles
     *
     * @throws Exception
     */
    private function cacheNonConvertedFiles(AbstractFilesStorage $fs, array &$linkFiles): void
    {
        foreach ($this->projectStructure->array_files as $pos => $fileName) {
            $meta = $this->projectStructure->array_files_meta[$pos];

            if ($meta['mustBeConverted']) {
                continue;
            }

            $filePathName = "$this->uploadDir/$fileName";

            if (AbstractFilesStorage::isOnS3() && false === file_exists($filePathName)) {
                $this->getSingleS3QueueFile($fileName);
            }

            $sha1 = sha1_file($filePathName);
            if ($sha1 === false) {
                $this->addProjectError(ProjectCreationError::FILE_HASH_FAILED->value, "Failed to compute hash for file $fileName");
                continue;
            }

            try {
                $fs->makeCachePackage($sha1, (string)$this->projectStructure->source_language, null, $filePathName);
                $this->logger->debug("File $fileName converted to cache");
            } catch (Exception $e) {
                $this->addProjectError(ProjectCreationError::FILE_HASH_FAILED->value, $e->getMessage());
            }

            $fs->linkSessionToCacheForAlreadyConvertedFiles(
                $sha1,
                (string)$this->projectStructure->uploadToken,
                $fileName
            );

            $hashKey = $sha1 . AbstractFilesStorage::OBJECTS_SAFE_DELIMITER . $this->projectStructure->source_language;
            $linkFiles['conversionHashes']['sha'][] = $hashKey;
            $linkFiles['conversionHashes']['fileName'][$hashKey][] = $fileName;
            $linkFiles['conversionHashes']['sha'] = array_unique($linkFiles['conversionHashes']['sha']);
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
            $this->addProjectError($e->getCode(), $e->getMessage());
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Resolve conversion hashes, validate cached XLIFF files, and insert file records into DB.
     *
     * @param array<string, mixed> $linkFiles
     *
     * @return array<int, array<string, mixed>> The accumulated file structures keyed by file ID.
     *
     * @throws EndQueueException
     */
    private function resolveAndInsertFiles(AbstractFilesStorage $fs, array $linkFiles): array
    {
        // Collect the DB/file structure created for all processed files.
        $totalFilesStructure = [];

        // Stop early if there are no converted file hashes to resolve.
        if (!isset($linkFiles['conversionHashes']) || !isset($linkFiles['conversionHashes']['sha'])) {
            return $totalFilesStructure;
        }

        // Process each converted-file reference produced during conversion.
        foreach ($linkFiles['conversionHashes']['sha'] as $linkFile) {
            // Extract the hash+lang token from the path/identifier.
            $hashFile = AbstractFilesStorage::basename_fix($linkFile);
            $hashFile = explode(AbstractFilesStorage::OBJECTS_SAFE_DELIMITER, $hashFile);

            // The first part is the original file hash, the second is the target language.
            $sha1_original = $hashFile[0];
            $lang = $hashFile[1] ?? '';

            // Skip malformed entries with no language suffix.
            if (empty($lang)) {
                continue;
            }

            // Locate the converted XLIFF in cache/storage for this hash+language.
            $cachedXliffFilePathName = $fs->getXliffFromCache($sha1_original, $lang) ?: null;

            // Get the original file names associated with this converted file.
            $_originalFileNames = $linkFiles['conversionHashes']['fileName'][$linkFile];

            try {
                // Ensure original names exist and the cached converted file is valid.
                $this->validateCachedXliff($cachedXliffFilePathName, $_originalFileNames, $linkFiles);

                // Insert file records using the original names and resolved XLIFF path.
                $filesStructure = $this->insertFiles($_originalFileNames, $sha1_original, (string)$cachedXliffFilePathName);

                // Treat "nothing inserted" as a hard failure.
                if (count($filesStructure ?: []) === 0) {
                    $this->logger->error('No files inserted in DB', [$_originalFileNames, $sha1_original, $cachedXliffFilePathName]);
                    throw new Exception('Files could not be saved in database.', ProjectCreationError::FILE_NOT_FOUND->value);
                }
            } catch (Throwable $e) {
                // Normalize the error, clean the partial project state, and rethrow as queue failure.
                $this->mapFileInsertionError($e);
                $this->clearFailedProject($e);
                throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
            }

            // Merge inserted file info into the overall result.
            // Note: += is intentional here — array_merge() would re-index the
            // numeric keys, losing the $fid mapping that downstream consumers
            // rely on. Key collisions cannot occur because $fid values are
            // database auto-increment IDs, guaranteed unique across all
            // insertFiles() calls within the same project.
            $totalFilesStructure += $filesStructure;
        }

        // Return the combined structure for all successfully inserted files.
        return $totalFilesStructure;
    }

    /**
     * Validate that a cached XLIFF file exists and has a valid extension.
     *
     * @param list<string> $_originalFileNames
     * @param array<string, mixed> $linkFiles
     *
     * @throws Exception
     */
    private function validateCachedXliff(?string $cachedXliffFilePathName, array $_originalFileNames, array $linkFiles): void
    {
        if (count($_originalFileNames ?: []) === 0) {
            $this->logger->error('No hash files found', [$linkFiles['conversionHashes']]);
            throw new Exception('No hash files found', ProjectCreationError::FILE_NOT_FOUND->value);
        }

        if (AbstractFilesStorage::isOnS3()) {
            if (!$cachedXliffFilePathName) {
                throw new Exception(sprintf('Key not found on S3 cache bucket for file %s.', implode(',', $_originalFileNames)), ProjectCreationError::FILE_NOT_FOUND->value);
            }
        } elseif ($cachedXliffFilePathName === null || !file_exists($cachedXliffFilePathName)) {
            throw new Exception(sprintf('File %s not found on server after upload.', $cachedXliffFilePathName), ProjectCreationError::FILE_NOT_FOUND->value);
        }

        $info = AbstractFilesStorage::pathinfo_fix($cachedXliffFilePathName);

        if (!in_array($info['extension'] ?? '', ['xliff', 'sdlxliff', 'xlf'])) {
            throw new Exception("Failed to find converted Xliff", ProjectCreationError::XLIFF_NOT_FOUND->value);
        }
    }

    /**
     * Map file-insertion error codes to user-friendly project errors.
     */
    private function mapFileInsertionError(Throwable $e): void
    {
        $code = $e->getCode();

        match (true) {
            $code == ProjectCreationError::REFERENCE_FILES_DISK_ERROR->value => $this->addProjectError($code, "Failed to store reference files on disk. Permission denied"),
            $code == ProjectCreationError::REFERENCE_FILES_DB_ERROR->value => $this->addProjectError($code, "Failed to store reference files in database"),
            $code == ProjectCreationError::XLIFF_NOT_FOUND->value => $this->addProjectError(
                ProjectCreationError::XLIFF_CONVERSION_NOT_FOUND->value,
                "File not found. Failed to save XLIFF conversion on disk."
            ),
            $code == ProjectCreationError::GENERIC_ERROR->value && str_contains($e->getMessage(), '<Message>Invalid copy source encoding.</Message>') => $this->addProjectError(
                ProjectCreationError::FILE_MOVE_FAILED->value,
                'There was a problem during the upload of your file(s). Please, ' .
                'try to rename your file(s) avoiding non-standard characters'
            ),
            in_array(
                $code,
                [
                    ProjectCreationError::ZIP_STORE_FAILED->value,
                    ProjectCreationError::FILE_NOT_FOUND->value,
                    ProjectCreationError::FILE_CACHE_ERROR->value,
                    ProjectCreationError::FILE_MOVE_FAILED->value,
                    ProjectCreationError::GENERIC_ERROR->value
                ],
                true
            ) => $this->addProjectError($code, $e->getMessage()),
            default => $this->addProjectError($code, 'An unexpected error occurred during file insertion: ' . $e->getMessage()),
        };
    }

    /**
     * Extract segments from all files, create project record, store segments, create jobs, and write analysis data.
     * Tolerates individual file extraction failures in multi-file projects.
     *
     * @param array<int, array<string, mixed>> $totalFilesStructure Modified by reference — failed files are removed.
     * @param array<string, mixed> $linkFiles
     *
     * @throws EndQueueException
     */
    private function extractSegmentsCreateProjectAndStoreData(
        AbstractFilesStorage $fs,
        array &$totalFilesStructure,
        array $linkFiles
    ): void {
        // $linkFile is needed in the error handler for hash cleanup
        $linkFile = '';
        if (isset($linkFiles['conversionHashes']['sha'])) {
            $shaSum = $linkFiles['conversionHashes']['sha'];
            $linkFile = end($shaSum) ?: '';
        }

        try {
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
        } catch (Throwable $e) {
            $this->mapSegmentExtractionError($e, $fs, $linkFile);
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Map segment-extraction/project-creation error codes to user-friendly project errors.
     */
    private function mapSegmentExtractionError(Throwable $e, AbstractFilesStorage $fs, string $linkFile): void
    {
        $code = $e->getCode();

        match ($code) {
            ProjectCreationError::NO_TRANSLATABLE_TEXT->value => $this->addProjectError(
                ProjectCreationError::NO_TRANSLATABLE_TEXT->value,
                "No text to translate in the file " . ZipArchiveHandler::getFileName($e->getMessage()) . "."
            ),
            ProjectCreationError::XLIFF_PARSE_FAILURE->value => $this->addProjectError(ProjectCreationError::XLIFF_IMPORT_ERROR->value, "Xliff Import Error: {$e->getMessage()}"),
            ProjectCreationError::INVALID_XLIFF_PARAMETERS->value => $this->addProjectError(
                $code,
                (null !== $e->getPrevious()) ? $e->getPrevious()->getMessage() . " in {$e->getMessage()}" : $e->getMessage()
            ),
            default => $this->addProjectError($code, $e->getMessage()),
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
        foreach ($jobs as $job) {
            $this->linkFilesToJob($job);
            $this->insertPreTranslations($job);
        }
    }

    /**
     * Link all project files to a job and create GDrive remote copies if applicable.
     * @throws Exception
     */
    private function linkFilesToJob(JobStruct $job): void
    {
        foreach ($this->projectStructure->file_id_list as $fid) {
            FileDao::insertFilesJob((int)$job->id, $fid);

            if ($this->gdriveSession && $this->gdriveSession->hasFiles()) {
                $client = GoogleProvider::getClient(AppConfig::$HTTPHOST . '/gdrive/oauth/response');
                $this->gdriveSession->createRemoteCopiesWhereToSaveTranslation($fid, (int)$job->id, $client);
            }
        }
    }

    /**
     * Insert pre-translations for a job. Errors are logged and recorded
     * but do not halt project creation.
     * @throws Exception
     */
    private function insertPreTranslations(JobStruct $job): void
    {
        if (empty($this->projectStructure->translations)) {
            return;
        }

        try {
            $this->getSegmentStorageService()->insertPreTranslations($job, $this->projectStructure);
        } catch (Exception $e) {
            $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export($e->getMessage(), true);
            Utils::sendErrMailReport($msg);
            $this->log("Pre-translation insertion failed for job $job->id", $e);
            $this->addProjectError(
                (int)$e->getCode(),
                "Pre-translations lost for job $job->id: " . $e->getMessage() . ". The project should be re-created."
            );
        }
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
     * Insert files into the database, moving them from the cache to the file directory.
     *
     * @param list<string> $_originalFileNames
     * @param string $sha1_original e.g. 917f7b03c8f54350fb65387bda25fbada43ff7d8
     * @param string $cachedXliffFilePathName e.g. 91/7f/...!!it-it/work/test_2.txt.sdlxliff
     *
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    protected function insertFiles(array $_originalFileNames, string $sha1_original, string $cachedXliffFilePathName): array
    {
        $fs = FilesStorageFactory::create();

        $createDate = date_create($this->projectStructure->create_date);
        if ($createDate === false) {
            throw new Exception('Invalid create_date for project');
        }
        $yearMonthPath = $createDate->format('Ymd');
        $fileDateSha1Path = $yearMonthPath . DIRECTORY_SEPARATOR . $sha1_original;

        //return structure
        $fileStructures = [];

        foreach ($_originalFileNames as $pos => $originalFileName) {
            // avoid blank filenames
            if (!empty($originalFileName)) {
                // get metadata
                $meta = $this->projectStructure->array_files_meta[$pos] ?? null;
                /** @var string $fileExtension */
                $fileExtension = AbstractFilesStorage::pathinfo_fix($originalFileName, PATHINFO_EXTENSION);
                $fidStr = $this->getProjectManagerModel()->insertFile(
                    (int)$this->projectStructure->id_project,
                    (string)$this->projectStructure->source_language,
                    $originalFileName,
                    $fileExtension,
                    $fileDateSha1Path
                );
                $fid = (int)$fidStr;

                if ($this->gdriveSession) {
                    $gdriveFileId = $this->gdriveSession->findFileIdByName($originalFileName);
                    if ($gdriveFileId) {
                        $client = GoogleProvider::getClient(AppConfig::$HTTPHOST . "/gdrive/oauth/response");
                        $this->gdriveSession->createRemoteFile($fid, $gdriveFileId, $client);
                    }
                }

                $moved = $fs->moveFromCacheToFileDir(
                    $fileDateSha1Path,
                    (string)$this->projectStructure->source_language,
                    $fidStr,
                    $originalFileName
                );

                // check if the files were moved
                if (true !== $moved) {
                    throw new Exception('Project creation failed. Please refresh page and retry.', ProjectCreationError::FILE_MOVE_FAILED->value);
                }

                $this->projectStructure->file_id_list[] = $fid;

                // pdfAnalysis
                if (!empty($meta['pdfAnalysis'])) {
                    $this->filesMetadataDao->insert((int)$this->projectStructure->id_project, $fid, 'pdfAnalysis', (string)json_encode($meta['pdfAnalysis']));
                }

                $fileStructures[$fid] = [
                    'fid' => $fid,
                    'original_filename' => $originalFileName,
                    'path_cached_xliff' => $cachedXliffFilePathName,
                    'mime_type' => $fileExtension
                ];
            }
        }

        return $fileStructures;
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
     */
    private function insertSegmentNotesForFile(): void
    {
        $this->projectStructure = $this->features->filter('handleJsonNotesBeforeInsert', $this->projectStructure);
        $this->getProjectManagerModel()->bulkInsertSegmentNotes($this->projectStructure->notes);
        $this->getProjectManagerModel()->bulkInsertSegmentMetaDataFromAttributes($this->projectStructure->notes);
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