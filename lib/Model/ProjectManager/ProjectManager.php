<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 22/10/13
 * Time: 17.25
 *
 */

namespace Model\ProjectManager;

use ArrayObject;
use Controller\API\Commons\Exceptions\AuthenticationError;
use DomainException;
use Exception;
use Matecat\Locales\Languages;
use Matecat\SubFiltering\MateCatFilter;
use Model\ActivityLog\ActivityLogStruct;
use Model\Analysis\PayableRates;
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
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Xliff\DTO\XliffRulesModel;
use Model\Xliff\XliffConfigTemplateStruct;
use Plugins\Features\SecondPassReview;
use ReflectionException;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\ActivityLogWorker;
use Utils\Collections\RecursiveArrayObject;
use Utils\Constants\EngineConstants;
use Utils\Constants\ProjectStatus;
use Utils\Engines\MyMemory;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TMS\TMSService;
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;

class ProjectManager
{
    use LogsMessages;

    /**
     * Counter from the total number of segments in the project with the flag (show_in_cattool == true)
     *
     * @var int
     */
    protected int $show_in_cattool_segs_counter = 0;
    protected int $files_word_count = 0;
    protected int $total_segments = 0;
    protected array $min_max_segments_id = [];

    /**
     * @var ArrayObject
     */
    protected ArrayObject $projectStructure;

    protected TMSService $tmxServiceWrapper;

    protected string $uploadDir;

    /*
       flag used to indicate TMX check status:
       0-not to check, or check passed
       1-still checking, but no useful TM for this project have been found, so far (no one matches this project langpair)
     */

    protected Languages $langService;

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /**
     * @var ?Session
     */
    protected ?Session $gdriveSession = null;

    /**
     * @var FeatureSet
     */
    protected FeatureSet $features;

    const string TRANSLATED_USER = 'translated_user';

    /**
     * @var IDatabase
     */
    protected IDatabase $dbHandler;

    /**
     * @var MateCatFilter
     */
    protected MateCatFilter $filter;

    /**
     * @var MetadataDao
     */
    protected MetadataDao $filesMetadataDao;

    /**
     * Lazily created extractor for segment extraction.
     * @var SegmentExtractor|null
     */
    protected ?SegmentExtractor $segmentExtractor = null;

    /**
     * @var TmKeyService|null
     */
    protected ?TmKeyService $tmKeyService = null;

    /**
     * @var JobSplitMergeService|null
     */
    protected ?JobSplitMergeService $jobSplitMergeService = null;

    /**
     * @var SegmentStorageService|null
     */
    protected ?SegmentStorageService $segmentStorageService = null;

    /**
     * @var ProjectManagerModel|null
     */
    protected ?ProjectManagerModel $projectManagerModel = null;

    /**
     * ProjectManager constructor.
     *
     * @param ArrayObject|null $projectStructure
     *
     * @throws Exception
     * @throws NotFoundException
     * @throws AuthenticationError
     * @throws ValidationError
     */
    public function __construct(ArrayObject $projectStructure = null)
    {
        $this->logger = LoggerFactory::getLogger('project_manager');

        if ($projectStructure == null) {
            $projectStructure = new RecursiveArrayObject(
                [
                    'HTTP_HOST' => null,
                    'id_project' => null,
                    'create_date' => date("Y-m-d H:i:s"),
                    'id_customer' => self::TRANSLATED_USER,
                    'project_features' => [],
                    'user_ip' => null,
                    'project_name' => null,
                    'result' => ["errors" => [], "data" => []],
                    'private_tm_key' => 0,
                    'uploadToken' => null,
                    'array_files' => [], //list of file names
                    'array_files_meta' => [], //list of file metadata
                    'file_id_list' => [],
                    'source_language' => null,
                    'target_language' => null,
                    'job_subject' => 'general',
                    'mt_engine' => null,
                    'tms_engine' => null,
                    'ppassword' => null,
                    'array_jobs' => [
                        'job_list' => [],
                        'job_pass' => [],
                        'job_segments' => [],
                        'job_languages' => [],
                        'payable_rates' => [],
                    ],
                    'job_segments' => [], //array of job_id => [  min_seg, max_seg  ]
                    'segments' => [], //array of files_id => segments[  ]
                    'segments-original-data' => [], //array of files_id => segments-original-data[  ]
                    'segments_metadata' => [], //array of segments_metadata
                    'segments-meta-data' => [], //array of files_id => segments-meta-data[  ]
                    'file-part-id' => [], //array of files_id => segments-meta-data[  ]
                    'file-metadata' => [], //array of files metadata
                    'translations' => [],
                    'notes' => [],
                    'context-group' => [],
                    //one translation for every file because translations are files related
                    'status' => ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
                    'job_to_split' => null,
                    'job_to_split_pass' => null,
                    'split_result' => null,
                    'job_to_merge' => null,
                    'tm_keys' => [],
                    'userIsLogged' => false,
                    'uid' => null,
                    'pretranslate_100' => 0,
                    'pretranslate_101' => 1,
                    'only_private' => 0,
                    'owner' => '',
                    ProjectsMetadataDao::WORD_COUNT_TYPE_KEY => ProjectsMetadataDao::WORD_COUNT_RAW,
                    'metadata' => [],
                    'id_assignee' => null,
                    'session' => ($_SESSION ?? false),
                    'instance_id' => (!is_null(AppConfig::$INSTANCE_ID) ? AppConfig::$INSTANCE_ID : 0),
                    'id_team' => null,
                    'team' => null,
                    'sanitize_project_options' => true,
                    'file_segments_count' => [],
                    'due_date' => null,
                    'qa_model' => null,
                    'target_language_mt_engine_association' => [],
                    'standard_word_count' => 0,
                    'mmt_glossaries' => null,
                    'deepl_formality' => null,
                    'deepl_id_glossary' => null,
                    'dictation' => null,
                    'show_whitespace' => null,
                    'character_counter' => null,
                    'character_counter_mode' => null,
                    'character_counter_count_tags' => null,
                    'ai_assistant' => null,
                    'filters_extraction_parameters' => new RecursiveArrayObject(),
                    'xliff_parameters' => new RecursiveArrayObject(),
                    'tm_prioritization' => null,
                    'mt_qe_workflow_payable_rate' => null,
                    'enable_mt_analysis' => null,
                    'mmt_activate_context_analyzer' => null,
                    'lara_glossaries' => null,
                    'lara_style' => null,
                    'deepl_engine_type' => null,
                    'intento_routing' => null,
                    'intento_provider' => null,
                ]
            );
        }

        $this->projectStructure = $projectStructure;

        //get the TMX management component from the factory
        $this->tmxServiceWrapper = new TMSService();

        $this->langService = Languages::getInstance();

        $this->dbHandler = Database::obtain();

        $this->features = new FeatureSet($this->_getRequestedFeatures());

        if (!empty($this->projectStructure['id_customer'])) {
            $this->features->loadAutoActivableOwnerFeatures($this->projectStructure['id_customer']);
        }

        /** @var MateCatFilter $filter */
        $filter = MateCatFilter::getInstance(
            $this->features,
            $this->projectStructure['source_language'],
            $this->projectStructure['target_language'],
            [],
            json_decode($this->projectStructure[JobsMetadataDao::SUBFILTERING_HANDLERS] ?? 'null')
        );
        $this->filter = $filter;

        $this->projectStructure['array_files'] = $this->features->filter(
            'filter_project_manager_array_files',
            $this->projectStructure['array_files'],
            $this->projectStructure
        );

        // sync array_files_meta
        $array_files_meta = [];
        foreach ($this->projectStructure['array_files_meta'] as $fileMeta) {
            if (in_array($fileMeta['basename'], (array)$this->projectStructure['array_files'])) {
                $array_files_meta[] = $fileMeta;
            }
        }

        $this->projectStructure['array_files_meta'] = $array_files_meta;

        $this->filesMetadataDao = new MetadataDao();
    }

    /**
     * Factory method to create a SegmentExtractor.
     * Protected so test subclasses can override for injection.
     */
    protected function createSegmentExtractor(): SegmentExtractor
    {
        return new SegmentExtractor(
            $this->filter,
            $this->features,
            $this->filesMetadataDao,
            $this->logger,
        );
    }

    /**
     * Get or lazily create the SegmentExtractor instance.
     * The same instance is reused across all files so counters accumulate.
     */
    protected function getSegmentExtractor(): SegmentExtractor
    {
        if ($this->segmentExtractor === null) {
            $this->segmentExtractor = $this->createSegmentExtractor();
        }

        return $this->segmentExtractor;
    }

    /**
     * Factory method to create a TmKeyService.
     * Protected so test subclasses can override for injection.
     */
    protected function createTmKeyService(): TmKeyService
    {
        return new TmKeyService(
            $this->tmxServiceWrapper,
            $this->dbHandler,
            $this->logger,
            fn(string $fileName) => $this->getSingleS3QueueFile($fileName),
        );
    }

    /**
     * Get or lazily create the TmKeyService instance.
     */
    protected function getTmKeyService(): TmKeyService
    {
        if ($this->tmKeyService === null) {
            $this->tmKeyService = $this->createTmKeyService();
        }

        return $this->tmKeyService;
    }

    /**
     * Factory method for creating a JobSplitMergeService instance.
     * Override in tests to inject a mock/stub.
     */
    protected function createJobSplitMergeService(): JobSplitMergeService
    {
        return new JobSplitMergeService(
            $this->dbHandler,
            $this->features,
            $this->logger,
        );
    }

    /**
     * Get or lazily create the JobSplitMergeService instance.
     */
    protected function getJobSplitMergeService(): JobSplitMergeService
    {
        if ($this->jobSplitMergeService === null) {
            $this->jobSplitMergeService = $this->createJobSplitMergeService();
        }

        return $this->jobSplitMergeService;
    }

    /**
     * Factory method for creating a SegmentStorageService instance.
     * Override in tests to inject a mock/stub.
     */
    protected function createSegmentStorageService(): SegmentStorageService
    {
        return new SegmentStorageService(
            $this->dbHandler,
            $this->features,
            $this->logger,
            $this->filter,
            $this->getProjectManagerModel(),
        );
    }

    /**
     * Get or lazily create the SegmentStorageService instance.
     * The same instance is reused across all files so min/max IDs accumulate.
     */
    protected function getSegmentStorageService(): SegmentStorageService
    {
        if ($this->segmentStorageService === null) {
            $this->segmentStorageService = $this->createSegmentStorageService();
        }

        return $this->segmentStorageService;
    }

    /**
     * Factory method for creating a ProjectManagerModel instance.
     * Override in tests to inject a mock/stub.
     */
    protected function createProjectManagerModel(): ProjectManagerModel
    {
        return new ProjectManagerModel(
            $this->dbHandler,
            $this->logger,
        );
    }

    /**
     * Get or lazily create the ProjectManagerModel instance.
     */
    protected function getProjectManagerModel(): ProjectManagerModel
    {
        if ($this->projectManagerModel === null) {
            $this->projectManagerModel = $this->createProjectManagerModel();
        }

        return $this->projectManagerModel;
    }

    /**
     * @return array
     */
    protected function _getRequestedFeatures(): array
    {
        $features = [];
        if (count($this->projectStructure['project_features']) != 0) {
            foreach ($this->projectStructure['project_features'] as $key => $feature) {
                /**
                 * @var $feature RecursiveArrayObject
                 */
                $this->projectStructure['project_features'][$key] = new BasicFeatureStruct($feature->getArrayCopy());
            }

            $features = $this->projectStructure['project_features']->getArrayCopy();
        }

        return $features;
    }

    /**
     * @throws Exception
     */
    protected function _validateUploadToken(): void
    {
        if (!isset($this->projectStructure['uploadToken']) || !Utils::isTokenValid($this->projectStructure['uploadToken'])) {
            $this->addProjectError(-19, "Invalid Upload Token.");
            throw new Exception("Invalid Upload Token.", -19);
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function _validateXliffParameters(): void
    {
        try {
            // when the request comes from api or ajax
            if (!$this->projectStructure['xliff_parameters'] instanceof ArrayObject) {
                if (is_array($this->projectStructure['xliff_parameters'])) {
                    $this->projectStructure['xliff_parameters'] = new RecursiveArrayObject($this->projectStructure['xliff_parameters']);
                } else {
                    throw new DomainException("Invalid xliff_parameters value found.", 400);
                }
            }

            // when the request comes from the ProjectCreation daemon, it is already an ArrayObject
            $this->projectStructure['xliff_parameters'] = XliffRulesModel::fromArrayObject($this->projectStructure['xliff_parameters']);
        } catch (DomainException $ex) {
            $this->addProjectError($ex->getCode(), $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @param TeamStruct $team
     */
    public function setTeam(TeamStruct $team): void
    {
        $this->projectStructure['team'] = $team;
        $this->projectStructure['id_team'] = $team->id;
    }

    /**
     * @throws Exception
     */
    public function setProjectAndReLoadFeatures(ProjectStruct $pStruct): void
    {
        $this->project = $pStruct;
        $this->projectStructure['id_project'] = $this->project->id;
        $this->projectStructure['id_customer'] = $this->project->id_customer;
        $this->reloadFeatures();
    }

    /**
     * @throws Exception
     */
    protected function reloadFeatures(): void
    {
        $this->features = new FeatureSet();
        $this->features->loadForProject($this->project);
    }

    public function getProjectStructure(): RecursiveArrayObject|ArrayObject
    {
        return $this->projectStructure;
    }

    /**
     * Save features in project metadata
     * @throws ReflectionException
     */
    protected function saveFeaturesInMetadata(): void
    {
        $dao = $this->createProjectsMetadataDao();

        $featureCodes = $this->features->getCodes();
        if (!empty($featureCodes)) {
            $dao->set(
                $this->projectStructure['id_project'],
                ProjectsMetadataDao::FEATURES_KEY,
                implode(',', $featureCodes)
            );
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function saveJobsMetadata(JobStruct $newJob, ArrayObject $projectStructure): void
    {
        $jobsMetadataDao = $this->createJobsMetadataDao();

        // Simple key-value metadata with optional transformation
        $simpleKeys = [
            'public_tm_penalty'            => null,
            'character_counter_count_tags'  => fn($v) => $v ? 1 : 0,
            'character_counter_mode'        => null,
            'tm_prioritization'            => fn($v) => $v ? 1 : 0,
        ];

        foreach ($simpleKeys as $key => $transformer) {
            if (isset($projectStructure[$key])) {
                $value = $transformer ? $transformer($projectStructure[$key]) : $projectStructure[$key];
                $jobsMetadataDao->set($newJob->id, $newJob->password, $key, $value);
            }
        }

        // dialect_strict — per-language matching logic
        if (isset($projectStructure['dialect_strict'])) {
            $dialectStrictObj = json_decode($projectStructure['dialect_strict'], true);

            foreach ($dialectStrictObj as $lang => $value) {
                if (trim($lang) === trim($newJob->target)) {
                    $jobsMetadataDao->set($newJob->id, $newJob->password, 'dialect_strict', $value);
                }
            }
        }

        /**
         * Save the subfiltering handlers in the JobsMetadataDao.
         * Configuration about handlers can be changed later in the job settings.
         * But the analysis must everytime be performed with the current configuration.
         */
        $jobsMetadataDao->set(
            $newJob->id,
            $newJob->password,
            JobsMetadataDao::SUBFILTERING_HANDLERS,
            $projectStructure[JobsMetadataDao::SUBFILTERING_HANDLERS]
        );
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
        $options = $this->projectStructure['metadata'];
        $dao = $this->createProjectsMetadataDao();

        // "From API" flag
        if (isset($this->projectStructure[ProjectsMetadataDao::FROM_API]) and $this->projectStructure[ProjectsMetadataDao::FROM_API]) {
            $options[ProjectsMetadataDao::FROM_API] = true;
        }

        // xliff_parameters
        if (isset($this->projectStructure[ProjectsMetadataDao::XLIFF_PARAMETERS]) and $this->projectStructure[ProjectsMetadataDao::XLIFF_PARAMETERS] instanceof XliffConfigTemplateStruct) {
            $configModel = $this->projectStructure[ProjectsMetadataDao::XLIFF_PARAMETERS];
            $options[ProjectsMetadataDao::XLIFF_PARAMETERS] = json_encode($configModel);
        }

        // pretranslate_101
        if (isset($this->projectStructure[ProjectsMetadataDao::PRETRANSLATE_101])) {
            $options[ProjectsMetadataDao::PRETRANSLATE_101] = $this->projectStructure[ProjectsMetadataDao::PRETRANSLATE_101];
        }

        // mt evaluation => ice_mt already in metadata
        // adds JSON parameters to the project metadata as JSON string
        if ($options[ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED] ?? false) {
            $options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS] = json_encode($options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS]);
        }

        /**
         * Here we have the opportunity to add other features as dependencies of the ones
         * which are already explicitly set.
         */
        $this->features->loadProjectDependenciesFromProjectMetadata($options);

        if (isset($this->projectStructure[ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS]) && $this->projectStructure[ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS]) {
            $options[ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS] = json_encode($this->projectStructure[ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS]);
        }

        if ($this->projectStructure['sanitize_project_options']) {
            $options = $this->sanitizeProjectOptions($options);
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
            if (!empty($this->projectStructure[$extraKey]) && $this->projectStructure[$extraKey]) {
                $options[$extraKey] = $this->projectStructure[$extraKey];
            }
        }

        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $dao->set(
                    $this->projectStructure['id_project'],
                    $key,
                    $value
                );
            }
        }

        /** Duplicate the JobsMetadataDao::SUBFILTERING_HANDLERS in project metadata for easier retrieval.
         * During the analysis of the project, there is no need to query the JobsMetadataDao.
         * Configuration about handlers can be changed later in the job settings.
         * But the analysis must everytime be performed with the current configuration.
         * @see ProjectManager::saveJobsMetadata()
         */
        $dao->set(
            $this->projectStructure['id_project'],
            JobsMetadataDao::SUBFILTERING_HANDLERS,
            $this->projectStructure[JobsMetadataDao::SUBFILTERING_HANDLERS]
        );
    }

    /**
     * Factory method for ProjectsMetadataDao — overridable in tests.
     */
    protected function createProjectsMetadataDao(): ProjectsMetadataDao
    {
        return new ProjectsMetadataDao();
    }

    /**
     * Factory method for JobsMetadataDao — overridable in tests.
     */
    protected function createJobsMetadataDao(): JobsMetadataDao
    {
        return new JobsMetadataDao();
    }

    /**
     * @param ArrayObject $options
     *
     * @return array
     * @throws Exception
     */
    private function sanitizeProjectOptions(ArrayObject $options): array
    {
        $sanitizer = new ProjectOptionsSanitizer($options->getArrayCopy());

        /** @var $langs RecursiveArrayObject */
        $langs = $this->projectStructure['target_language'];

        $sanitizer->setLanguages(
            $this->projectStructure['source_language'],
            $langs->getArrayCopy()
        );

        return $sanitizer->sanitize();
    }

    /**
     * Perform sanitization of the projectStructure and assign errors.
     * Resets the error array to avoid further calls to pile up errors.
     *
     * Please NOTE: this method is called by controllers and by ProjectManager itself, in the latter case
     *  'project_features' field already contains a RecursiveArrayObject,
     *   so use an ArrayObject, it accepts both array and RecursiveArrayObject
     *
     * @throws Exception
     */
    public function sanitizeProjectStructure(): void
    {
        $this->projectStructure['result']['errors'] = new ArrayObject();

        $this->_validateUploadToken();
        $this->_validateXliffParameters();

        $this->projectStructure['project_features'] = new ArrayObject($this->projectStructure['project_features']);
    }

    /**
     * Append an error entry to projectStructure['result']['errors'].
     *
     * Centralises the ~19 occurrences of the duplicated append pattern.
     */
    protected function addProjectError(int $code, string $message): void
    {
        $this->projectStructure['result']['errors'][] = [
            "code" => $code,
            "message" => $message,
        ];
    }

    /**
     * Creates record in projects tabele and instantiates the project struct
     * internally.
     *
     * @throws ReflectionException
     */
    private function __createProjectRecord(): void
    {
        $this->project = $this->getProjectManagerModel()->createProjectRecord($this->projectStructure);
    }

    /**
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    protected function __checkForProjectAssignment(): void
    {
        if (!empty($this->projectStructure['uid'])) {
            //if this is a logged user, set the user as project assignee
            $this->projectStructure['id_assignee'] = $this->projectStructure['uid'];

            /**
             * Normalize ArrayObject team in TeamStruct
             */
            $this->projectStructure['team'] = new TeamStruct(
                $this->features->filter('filter_team_for_project_creation', $this->projectStructure['team']->getArrayCopy())
            );

            //clean the cache for the team member list of assigned projects
            $teamDao = $this->createTeamDao();
            $teamDao->destroyCacheAssignee($this->projectStructure['team']);
        }
    }

    /**
     * Factory method for TeamDao — overridable in tests.
     */
    protected function createTeamDao(): TeamDao
    {
        return new TeamDao();
    }

    /**
     * @return void
     * @throws Exception
     * @throws Throwable
     */
    public function createProject(): void
    {
        $this->sanitizeProjectStructure();

        $fs = FilesStorageFactory::create();

        if (!empty($this->projectStructure['session']['uid'])) {
            $this->gdriveSession = Session::getInstanceForCLI($this->projectStructure['session']->getArrayCopy());
        }

        $this->__checkForProjectAssignment();

        /**
         * This is the last chance to perform the validation before the project is created
         * in the database.
         * Validations should populate the projectStructure with errors and codes.
         */
        SecondPassReview::loadAndValidateQualityFramework($this->projectStructure);
        $this->features->run('validateProjectCreation', $this->projectStructure);

        /**
         * @var ArrayObject $this ->projectStructure['result']['errors']
         */
        if ($this->projectStructure['result']['errors']->count()) {
            $this->log($this->projectStructure['result']['errors']);

            throw new EndQueueException("Invalid Project found.");
        }

        //sort files to process TMX first
        $sortedFiles = [];
        $sortedMeta = [];
        $firstTMXFileName = "";

        foreach ($this->projectStructure['array_files'] as $pos => $fileName) {
            // get metadata
            $meta = $this->projectStructure['array_files_meta'][$pos];

            //check for glossary files and tmx and put them in front of the list
            if ($meta['getMemoryType']) {
                //found TMX, enable language checking routines
                if ($meta['isTMX']) {
                    //export the name of the first TMX Files for latter use
                    $firstTMXFileName = (empty($firstTMXFileName) ? $fileName : null);
                }

                //prepend in front of the list
                array_unshift($sortedFiles, $fileName);
                array_unshift($sortedMeta, $meta);
            } else {
                //append at the end of the list
                $sortedFiles[] = $fileName;
                $sortedMeta[] = $meta;
            }
        }

        $this->projectStructure['array_files'] = $sortedFiles;
        $this->projectStructure['array_files_meta'] = $sortedMeta;
        unset($sortedFiles);
        unset($sortedMeta);

        if (count($this->projectStructure['private_tm_key'])) {
            $this->getTmKeyService()->setPrivateTMKeys($this->projectStructure, $firstTMXFileName);

            if (count($this->projectStructure['result']['errors']) > 0) {
                // This return value was introduced after a refactoring
                throw new EndQueueException("Invalid Project found.");
            }
        }

        $uploadDir = $this->uploadDir = AppConfig::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure['uploadToken'];

        $this->log($uploadDir);

        //we are going to access the storage, get a model object to manipulate it
        $linkFiles = $fs->getHashesFromDir($this->uploadDir);

        $this->log($linkFiles);

        /*
            loop through all input files to
            1) upload INSERT INTMX and Glossaries
        */
        try {
            $this->getTmKeyService()->pushTMXToMyMemory($this->projectStructure, $this->uploadDir);
        } catch (Exception $e) {
            $this->log($e->getMessage(), $e);

            //exit project creation
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }
        //TMX Management

        /*
            loop through all input files to
            2)convert, in case, non-standard XLIFF files to a format that Matecat understands

            Note that XLIFF that don't need conversion are moved anyway as they are to cache in order not to alter the workflow
         */

        foreach ($this->projectStructure['array_files'] as $pos => $fileName) {
            // get corresponding meta
            $meta = $this->projectStructure['array_files_meta'][$pos];
            $mustBeConverted = $meta['mustBeConverted'];

            //if it's one of the listed formats, or conversion is not enabled in the first place
            if (!$mustBeConverted) {
                /*
                   the filename is already a xliff, and it's in the upload directory
                   we have to make a cache package from it to avoid altering the original path
                 */
                //get the file
                $filePathName = "$this->uploadDir/$fileName";

                // NOTE: 12 Aug 2019
                // I am not sure that the queue file exists,
                // so I check it, and in negative case I force the download of the file to the file system from S3
                $isFsOnS3 = AbstractFilesStorage::isOnS3();

                if ($isFsOnS3 and false === file_exists($filePathName)) {
                    $this->getSingleS3QueueFile($fileName);
                }

                // calculate hash and add the fileName, if I load 3 equal files with the same content,
                // they will be squashed to the last one
                $sha1 = sha1_file($filePathName);

                // make a cache package (with work/ only, empty orig/)
                try {
                    $fs->makeCachePackage($sha1, $this->projectStructure['source_language'], null, $filePathName);
                    $this->logger->debug("File $fileName converted to cache");
                } catch (Exception $e) {
                    $this->addProjectError(-230, $e->getMessage());
                }

                // put reference to cache in the upload dir to link cache to session
                $fs->linkSessionToCacheForAlreadyConvertedFiles(
                    $sha1,
                    $this->projectStructure['uploadToken'],
                    $fileName
                );

                //add a newly created link to the list
                $linkFiles['conversionHashes']['sha'][] = $sha1 . AbstractFilesStorage::OBJECTS_SAFE_DELIMITER . $this->projectStructure['source_language'];

                $linkFiles['conversionHashes']['fileName'][$sha1 . AbstractFilesStorage::OBJECTS_SAFE_DELIMITER . $this->projectStructure['source_language']][] = $fileName;

                //when the same sdlxliff is uploaded more than once with different names
                $linkFiles['conversionHashes']['sha'] = array_unique($linkFiles['conversionHashes']['sha']);
                unset($sha1);
            }
        }


        try {
            $this->_zipFileHandling($linkFiles);
        } catch (Exception $e) {
            $this->log($e->getMessage(), $e);
            //Zip file Handling
            $this->addProjectError($e->getCode(), $e->getMessage());
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }

        //now, upload dir contains only hash-links
        //we start copying files to "file" dir, inserting metadata in db and extracting segments
        $totalFilesStructure = [];
        if (isset($linkFiles['conversionHashes']) and isset($linkFiles['conversionHashes']['sha'])) {
            foreach ($linkFiles['conversionHashes']['sha'] as $linkFile) {
                //converted file is inside cache directory
                //get hash from file name inside UUID dir
                $hashFile = AbstractFilesStorage::basename_fix($linkFile);
                $hashFile = explode(AbstractFilesStorage::OBJECTS_SAFE_DELIMITER, $hashFile);

                // Example:
                // $hashFile[ 0 ] = 917f7b03c8f54350fb65387bda25fbada43ff7d8
                // $hashFile[ 1 ] = it-it
                $sha1_original = $hashFile[0];
                $lang = $hashFile[1] ?? '';

                if (empty($lang)) {
                    continue; //in some cases, the hash is not linked to a language, skip the file
                }

                //use hash and lang to fetch the file from the package
                $cachedXliffFilePathName = $fs->getXliffFromCache($sha1_original, $lang);

                //associate the hash with the right file in upload directory
                //get original file name, to insert into DB and cp in storage
                //PLEASE NOTE; this can be an array when the same file added more
                // than once and with different names
                $_originalFileNames = $linkFiles['conversionHashes']['fileName'][$linkFile];

                unset($hashFile);

                try {
                    if (count($_originalFileNames ?: []) === 0) {
                        $this->logger->error('No hash files found', [$linkFiles['conversionHashes']]);
                        throw new Exception('No hash files found', -6);
                    }

                    if (AbstractFilesStorage::isOnS3()) {
                        if (!$cachedXliffFilePathName) {
                            throw new Exception(sprintf('Key not found on S3 cache bucket for file %s.', implode(',', $_originalFileNames)), -6);
                        }
                    } elseif (!file_exists($cachedXliffFilePathName)) {
                        throw new Exception(sprintf('File %s not found on server after upload.', $cachedXliffFilePathName), -6);
                    }

                    $info = AbstractFilesStorage::pathinfo_fix($cachedXliffFilePathName);

                    if (!in_array($info['extension'], ['xliff', 'sdlxliff', 'xlf'])) {
                        throw new Exception("Failed to find converted Xliff", -3);
                    }


                    $filesStructure = $this->_insertFiles($_originalFileNames, $sha1_original, $cachedXliffFilePathName);

                    if (count($filesStructure ?: []) === 0) {
                        $this->logger->error('No files inserted in DB', [$_originalFileNames, $sha1_original, $cachedXliffFilePathName]);
                        throw new Exception('Files could not be saved in database.', -6);
                    }

                    // pdfAnalysis
                    foreach ($filesStructure as $fid => $fileStructure) {
                        $pos = array_search($fileStructure['original_filename'], $this->projectStructure['array_files']);
                        $meta = isset($this->projectStructure['array_files_meta'][$pos]) ? $this->projectStructure['array_files_meta'][$pos] : null;

                        if ($meta !== null and isset($meta['pdfAnalysis'])) {
                            $this->filesMetadataDao->insert($this->projectStructure['id_project'], $fid, 'pdfAnalysis', json_encode($meta['pdfAnalysis']));
                        }
                    }
                } catch (Throwable $e) {
                    if ($e->getCode() == -10) {
                        //Failed to store the original Zip
                        $this->addProjectError(-10, $e->getMessage());
                    } elseif ($e->getCode() == -11) {
                        $this->addProjectError($e->getCode(), "Failed to store reference files on disk. Permission denied");
                    } elseif ($e->getCode() == -12) {
                        $this->addProjectError($e->getCode(), "Failed to store reference files in database");
                    } // SEVERE EXCEPTIONS HERE
                    elseif ($e->getCode() == -6) {
                        //"File isn't found on server after upload."
                        $this->addProjectError($e->getCode(), $e->getMessage());
                    } elseif ($e->getCode() == -3) {
                        $this->addProjectError(-16, "File not found. Failed to save XLIFF conversion on disk.");
                    } elseif ($e->getCode() == -13) {
                        $this->addProjectError($e->getCode(), $e->getMessage());
                        //we cannot write to disk!! Break project creation
                    } // S3 EXCEPTIONS HERE
                    elseif ($e->getCode() == -200) {
                        $this->addProjectError(-200, $e->getMessage());
                    } elseif ($e->getCode() == 0) {
                        // check for 'Invalid copy source encoding' error
                        $copyErrorMsg = "<Message>Invalid copy source encoding.</Message>";

                        if (str_contains($e->getMessage(), $copyErrorMsg)) {
                            $this->addProjectError(
                                -200,
                                'There was a problem during the upload of your file(s). Please, ' .
                                'try to rename your file(s) avoiding non-standard characters'
                            );
                        }
                    }
                    $this->__clearFailedProject($e);

                    //EXIT
                    throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
                }

                //this is an "array append" like array_merge, but it does not renumber the numeric keys, so we can preserve the files id
                $totalFilesStructure += $filesStructure;
            } //end of conversion hash-link loop
        }

        //Throws exception
        try {
            //Try to extract segments after all checks
            $exceptionsFound = 0;
            foreach ($totalFilesStructure as $fid => $file_info) {
                try {
                    $this->_extractSegments($fid, $file_info);
                } catch (Exception $e) {
                    $this->log($totalFilesStructure);
                    $this->log("Count fileSt.: " . count($totalFilesStructure));
                    $this->log("Exceptions: " . $exceptionsFound);
                    $this->log("Failed to parse " . $file_info['original_filename'], $e);

                    if ($e->getCode() == -1 && count($totalFilesStructure) > 1 && $exceptionsFound < count($totalFilesStructure)) {
                        $this->log("No text to translate in the file {$e->getMessage()}.");
                        $exceptionsFound += 1;
                        unset($totalFilesStructure[$fid]);
                        continue;
                    } else {
                        throw $e;
                    }
                }
            }

            //Allow projects with less than 250.000 words or characters (for cjk languages)
            if ($this->files_word_count > AppConfig::$MAX_SOURCE_WORDS) {
                throw new Exception("Matecat is unable to create your project. Please contact us at " . AppConfig::$SUPPORT_MAIL . ", we will be happy to help you!", 128);
            }

            // check for project Creation before wasting disk space
            $this->features->run("beforeProjectCreation", $this->projectStructure, [
                    'total_project_segments' => $this->total_segments,
                    'files_raw_wc' => $this->files_word_count
                ]
            );

            $this->__createProjectRecord();
            $this->saveFeaturesInMetadata();
            $this->saveMetadata();

            foreach ($totalFilesStructure as $fid => $empty) {
                $this->storeSegments($fid);
            }

            $this->_createJobs($this->projectStructure);
            $this->writeFastAnalysisData();
        } catch (Throwable $e) {
            if ($e->getCode() == -1) {
                $this->addProjectError(-1, "No text to translate in the file " . ZipArchiveHandler::getFileName($e->getMessage()) . ".");
                if (AppConfig::$FILE_STORAGE_METHOD != 's3') {
                    $fs->deleteHashFromUploadDir($this->uploadDir, $linkFile ?? '');
                }
            } elseif ($e->getCode() == -4) {
                $this->addProjectError(-7, "Xliff Import Error: {$e->getMessage()}");
            } elseif ($e->getCode() == 400) {
                $message = (null !== $e->getPrevious()) ? $e->getPrevious()->getMessage() . " in {$e->getMessage()}" : $e->getMessage();

                //invalid Trans-unit value found empty ID
                $this->addProjectError($e->getCode(), $message);
            } else {
                //Generic error
                $this->addProjectError($e->getCode(), $e->getMessage());
            }

            $this->log("Exception", $e);

            //EXIT
            throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
        }

        $this->projectStructure['status'] = (AppConfig::$VOLUME_ANALYSIS_ENABLED) ? ProjectStatus::STATUS_NEW : ProjectStatus::STATUS_NOT_TO_ANALYZE;

        if ($this->show_in_cattool_segs_counter == 0) {
            $this->log("Segment Search: No segments in this project - \n");
            $this->projectStructure['status'] = ProjectStatus::STATUS_EMPTY;
        }

        $this->projectStructure['result']['code'] = 1;
        $this->projectStructure['result']['data'] = "OK";
        $this->projectStructure['result']['ppassword'] = $this->projectStructure['ppassword'];
        $this->projectStructure['result']['password'] = $this->projectStructure['array_jobs']['job_pass'];
        $this->projectStructure['result']['id_job'] = $this->projectStructure['array_jobs']['job_list'];
        $this->projectStructure['result']['job_segments'] = $this->projectStructure['array_jobs']['job_segments'];
        $this->projectStructure['result']['id_project'] = $this->projectStructure['id_project'];
        $this->projectStructure['result']['project_name'] = $this->projectStructure['project_name'];
        $this->projectStructure['result']['source_language'] = $this->projectStructure['source_language'];
        $this->projectStructure['result']['target_language'] = $this->projectStructure['target_language'];
        $this->projectStructure['result']['status'] = $this->projectStructure['status'];


        foreach ($totalFilesStructure as $fid => $file_info) {
            //
            // ==============================================
            // NOTE 2022-03-01
            // ==============================================
            //
            // Save the instruction notes in the same order of files
            //
            // `array_files` array contains the original file list (with correct file order).
            // The file order in $totalFilesStructure instead (which comes from a conversion process) may not correspond.
            //
            $array_files = $this->getProjectStructure()['array_files'];
            foreach ($array_files as $index => $filename) {
                if ($file_info['original_filename'] === $filename) {
                    if (isset($this->projectStructure['instructions'][$index]) && !empty($this->projectStructure['instructions'][$index])) {
                        $this->_insertInstructions($fid, $this->projectStructure['instructions'][$index]);
                    }
                }
            }
        }

        if (AppConfig::$VOLUME_ANALYSIS_ENABLED) {
            $this->projectStructure['result']['analyze_url'] = $this->getAnalyzeURL();
        }

        Database::obtain()->begin();

        //pre-fetch Analysis page in transaction and store in cache
        (new ProjectDao())->destroyCacheForProjectData($this->projectStructure['id_project'], $this->projectStructure['ppassword']);
        (new ProjectDao())->setCacheTTL(60 * 60 * 24)->getProjectData($this->projectStructure['id_project'], $this->projectStructure['ppassword']);

        $this->features->run('postProjectCreate', $this->projectStructure);

        ProjectDao::updateAnalysisStatus(
            $this->projectStructure['id_project'],
            $this->projectStructure['status'],
            $this->files_word_count * count($this->projectStructure['array_jobs']['job_languages'])
        );

        $this->pushActivityLog();

        Database::obtain()->commit();

        $this->features->run('postProjectCommit', $this->projectStructure);

        try {
            if (AbstractFilesStorage::isOnS3()) {
                $this->log('Deleting folder' . $this->uploadDir . ' from S3');
                /** @var $fs S3FilesStorage */
                $fs->deleteQueue($this->uploadDir);
            } else {
                $this->log('Deleting folder' . $this->uploadDir . ' from filesystem');
                Utils::deleteDir($this->uploadDir);
                if (is_dir($this->uploadDir . '_converted')) {
                    Utils::deleteDir($this->uploadDir . '_converted');
                }
            }
        } catch (Exception $e) {
            $output = "<pre>\n";
            $output .= " - Exception: " . print_r($e->getMessage(), true) . "\n";
            $output .= " - REQUEST URI: " . print_r(@$_SERVER['REQUEST_URI'], true) . "\n";
            $output .= " - REQUEST Message: " . print_r($_REQUEST, true) . "\n";
            $output .= " - Trace: \n" . print_r($e->getTraceAsString(), true) . "\n";
            $output .= "\n\t";
            $output .= "Aborting...\n";
            $output .= "</pre>";

            $this->log($output, $e);

            Utils::sendErrMailReport($output, $e->getMessage());
        }
    }

    /**
     * @param $fileName
     *
     * @throws Exception
     */
    public function getSingleS3QueueFile($fileName): void
    {
        $fs = FilesStorageFactory::create();

        if (false === is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755);
        }

        /** @var $fs S3FilesStorage */
        $client = $fs::getStaticS3Client();
        $params['bucket'] = AppConfig::$AWS_STORAGE_BASE_BUCKET;
        $params['key'] = $fs::QUEUE_FOLDER . DIRECTORY_SEPARATOR . $fs::getUploadSessionSafeName($fs->getTheLastPartOfKey($this->uploadDir)) . DIRECTORY_SEPARATOR . $fileName;
        $params['save_as'] = "$this->uploadDir/$fileName";
        $client->downloadItem($params);
    }

    private function __clearFailedProject(Exception $e): void
    {
        $this->log($e->getMessage(), $e);
        $this->log("Deleting Records.");
        (new ProjectDao())->deleteFailedProject($this->projectStructure['id_project']);
        (new FileDao())->deleteFailedProjectFiles($this->projectStructure['file_id_list']->getArrayCopy());
        $this->log("Deleted Project ID: " . $this->projectStructure['id_project']);
        $this->log("Deleted Files ID: " . json_encode($this->projectStructure['file_id_list']->getArrayCopy()));
    }

    /**
     * @throws Exception
     */
    private function writeFastAnalysisData(): void
    {
        $job_id_passes = ltrim(
            array_reduce(
                array_keys($this->projectStructure['array_jobs']['job_segments']->getArrayCopy()),
                function ($acc, $value) {
                    $acc .= "," . strtr($value, '-', ':');

                    return $acc;
                }
            ),
            ","
        );

        foreach ($this->projectStructure['segments_metadata'] as &$segmentElement) {
            unset($segmentElement['internal_id']);
            unset($segmentElement['xliff_mrk_id']);
            unset($segmentElement['show_in_cattool']);

            $segmentElement['jsid'] = $segmentElement['id'] . "-" . $job_id_passes;
            $segmentElement['source'] = $this->projectStructure['source_language'];
            $segmentElement['target'] = implode(",", $this->projectStructure['array_jobs']['job_languages']->getArrayCopy());
            $segmentElement['payable_rates'] = $this->projectStructure['array_jobs']['payable_rates']->getArrayCopy();
            $segmentElement['segment'] = $this->filter->fromLayer0ToLayer1($segmentElement['segment']);
        }

        $fs = FilesStorageFactory::create();
        $fs::storeFastAnalysisFile($this->project->id, $this->projectStructure['segments_metadata']->getArrayCopy());

        //free memory
        unset($this->projectStructure['segments_metadata']);
    }

    private function pushActivityLog(): void
    {
        $activity = new ActivityLogStruct();
        $activity->id_project = $this->projectStructure['id_project'];
        $activity->action = ActivityLogStruct::PROJECT_CREATED;
        $activity->ip = $this->projectStructure['user_ip'];
        $activity->uid = $this->projectStructure['uid'];
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
     * @return string
     * @throws Exception
     */
    public function getAnalyzeURL(): string
    {
        return CanonicalRoutes::analyze(
            [
                'project_name' => $this->projectStructure['project_name'],
                'id_project' => $this->projectStructure['id_project'],
                'password' => $this->projectStructure['ppassword']
            ],
            [
                'http_host' => (is_null($this->projectStructure['HTTP_HOST']) ?
                    AppConfig::$HTTPHOST :
                    $this->projectStructure['HTTP_HOST']
                ),
            ]
        );
    }

    /**
     * @throws Exception
     */
    protected function _zipFileHandling($linkFiles): void
    {
        $fs = FilesStorageFactory::create();

        //begin with zip hashes manipulation
        foreach ($linkFiles['zipHashes'] as $zipHash) {
            $result = $fs->linkZipToProject(
                $this->projectStructure['create_date'],
                $zipHash,
                $this->projectStructure['id_project']
            );

            if (!$result) {
                $this->log("Failed to store the Zip file $zipHash - \n");
                throw new Exception("Failed to store the original Zip $zipHash ", -10);
                //Exit
            }
        } //end zip hashes manipulation

    }

    /**
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws Exception
     */
    protected function _createJobs(ArrayObject $projectStructure): void
    {
        foreach ($projectStructure['target_language'] as $target) {
            // get payable rates from mt_qe_workflow, this takes the priority over the other payable rates
            if ($projectStructure['mt_qe_workflow_payable_rate']) {
                $payableRatesTemplate = null;
                $payableRates = json_encode($projectStructure['mt_qe_workflow_payable_rate']);
            } elseif (isset($projectStructure['payable_rate_model']) and !empty($projectStructure['payable_rate_model'])) {
                // get payable rates
                $payableRatesTemplate = new CustomPayableRateStruct();
                $payableRatesTemplate->hydrateFromJSON(json_encode($projectStructure['payable_rate_model']));
                $payableRates = $payableRatesTemplate->getPayableRates($projectStructure['source_language'], $target);
                $payableRates = json_encode($payableRates);
            } elseif (isset($projectStructure['payable_rate_model_id']) and !empty($projectStructure['payable_rate_model_id'])) {
                // get payable rates
                $payableRatesTemplate = CustomPayableRateDao::getById($projectStructure['payable_rate_model_id']);
                $payableRates = $payableRatesTemplate->getPayableRates($projectStructure['source_language'], $target);
                $payableRates = json_encode($payableRates);
            } else {
                $payableRatesTemplate = null;
                $payableRates = PayableRates::getPayableRates($projectStructure['source_language'], $target);
                $payableRates = json_encode($this->features->filter("filterPayableRates", $payableRates, $projectStructure['source_language'], $target));
            }

            $password = Utils::randomString();

            $tm_key = [];

            if (!empty($projectStructure['private_tm_key'])) {
                foreach ($projectStructure['private_tm_key'] as $tmKeyObj) {
                    $newTmKey = TmKeyManager::getTmKeyStructure();
                    $newTmKey->complete_format = true;
                    $newTmKey->tm = true;
                    $newTmKey->glos = true;
                    $newTmKey->owner = true;
                    $newTmKey->penalty = $tmKeyObj['penalty'] ?? 0;
                    $newTmKey->name = $tmKeyObj['name'];
                    $newTmKey->key = $tmKeyObj['key'];
                    $newTmKey->r = $tmKeyObj['r'];
                    $newTmKey->w = $tmKeyObj['w'];

                    $tm_key[] = $newTmKey;
                }
            }

            // check for job_first_segment and job_last_segment existence
            if (!isset($this->min_max_segments_id['job_first_segment']) or !isset($this->min_max_segments_id['job_last_segment'])) {
                throw new Exception('Job cannot be created. No segments found!');
            }

            $this->log($projectStructure['private_tm_key']);

            $projectStructure['tm_keys'] = json_encode($tm_key);

            // Replace {{pid}} with project ID for new keys created with an empty name
            $projectStructure['tm_keys'] = str_replace("{{pid}}", $projectStructure['id_project'], $projectStructure['tm_keys']);

            $newJob = new JobStruct();
            $newJob->password = $password;
            $newJob->id_project = $projectStructure['id_project'];
            $newJob->source = $projectStructure['source_language'];
            $newJob->target = $target;
            $newJob->id_tms = $projectStructure['tms_engine'] ?? 1;
            $newJob->id_mt_engine = $projectStructure['target_language_mt_engine_association'][$target];
            $newJob->create_date = date("Y-m-d H:i:s");
            $newJob->last_update = date("Y-m-d H:i:s");
            $newJob->subject = $projectStructure['job_subject'];
            $newJob->owner = $projectStructure['owner'];
            $newJob->job_first_segment = $this->min_max_segments_id['job_first_segment'];
            $newJob->job_last_segment = $this->min_max_segments_id['job_last_segment'];
            $newJob->tm_keys = $projectStructure['tm_keys'];
            $newJob->payable_rates = $payableRates;
            $newJob->total_raw_wc = $this->files_word_count;
            $newJob->only_private_tm = (int)$projectStructure['only_private'];

            $this->features->run('validateJobCreation', $newJob, $projectStructure);
            $newJob = JobDao::createFromStruct($newJob);

            $projectStructure['array_jobs']['job_list']->append($newJob->id);
            $projectStructure['array_jobs']['job_pass']->append($newJob->password);
            $projectStructure['array_jobs']['job_segments']->offsetSet($newJob->id . "-" . $newJob->password, $this->min_max_segments_id);
            $projectStructure['array_jobs']['job_languages']->offsetSet($newJob->id, $newJob->id . ":" . $target);
            $projectStructure['array_jobs']['payable_rates']->offsetSet($newJob->id, $payableRates);

            $this->saveJobsMetadata($newJob, $projectStructure);

            try {
                if (isset($projectStructure['payable_rate_model_id']) and !empty($projectStructure['payable_rate_model_id']) and $payableRatesTemplate !== null) {
                    CustomPayableRateDao::assocModelToJob(
                        $projectStructure['payable_rate_model_id'],
                        $newJob->id,
                        $payableRatesTemplate->version,
                        $payableRatesTemplate->name
                    );
                }

                //prepare pre-translated segments queries
                if (!empty($projectStructure['translations'])) {
                    $this->getSegmentStorageService()->insertPreTranslations($newJob, $projectStructure);
                }
            } catch (Exception $e) {
                $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export($e->getMessage(), true);
                Utils::sendErrMailReport($msg);
            }

            foreach ($projectStructure['file_id_list'] as $fid) {
                FileDao::insertFilesJob($newJob->id, $fid);

                if ($this->gdriveSession && $this->gdriveSession->hasFiles()) {
                    $client = GoogleProvider::getClient(AppConfig::$HTTPHOST . "/gdrive/oauth/response");
                    $this->gdriveSession->createRemoteCopiesWhereToSaveTranslation($fid, $newJob->id, $client);
                }
            }
        }

        if (!empty($this->projectStructure['notes'])) {
            $this->insertSegmentNotesForFile();
        }

        if (!empty($this->projectStructure['context-group'])) {
            $this->insertContextsForFile();
        }

        //Clean Translation array
        $this->projectStructure['translations']->exchangeArray([]);
    }

    /**
     * Build a job split structure, minimum split value are 2 chunks.
     *
     * Delegates to {@see JobSplitMergeService::getSplitData()}.
     *
     * @param ArrayObject $projectStructure
     * @param int $num_split
     * @param array $requestedWordsPerSplit Matecat Equivalent Words (Only valid for Pro Version)
     * @param string $count_type
     *
     * @return ArrayObject
     *
     * @throws Exception
     */
    public function getSplitData(
        ArrayObject $projectStructure,
        int $num_split = 2,
        array $requestedWordsPerSplit = [],
        string $count_type = ProjectsMetadataDao::SPLIT_EQUIVALENT_WORD_TYPE
    ): ArrayObject {
        return $this->getJobSplitMergeService()->getSplitData($projectStructure, $num_split, $requestedWordsPerSplit, $count_type);
    }

    /**
     * Apply the new job structure.
     *
     * Delegates to {@see JobSplitMergeService::applySplit()}.
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    public function applySplit(ArrayObject $projectStructure): void
    {
        $uid = $this->projectStructure['uid'] ?? null;
        $this->getJobSplitMergeService()->applySplit($projectStructure, $uid);
    }

    /**
     * Merge all job chunks back into a single job.
     *
     * Delegates to {@see JobSplitMergeService::mergeALL()}.
     *
     * @param ArrayObject $projectStructure
     * @param JobStruct[] $jobStructs
     *
     * @throws Exception
     */
    public function mergeALL(ArrayObject $projectStructure, array $jobStructs): void
    {
        $this->getJobSplitMergeService()->mergeALL($projectStructure, $jobStructs);
    }

    /**
     * Extract sources and pre-translations from an xliff file and put them in Database
     *
     * @param int $fid
     * @param array $file_info
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     *
     */
    protected function _extractSegments(int $fid, array $file_info): void
    {
        $this->getSegmentExtractor()->extract($fid, $file_info, $this->projectStructure);
        $this->syncCountersFromExtractor();
    }

    /**
     * Copy the accumulated counters from the SegmentExtractor back to instance properties.
     */
    private function syncCountersFromExtractor(): void
    {
        $extractor                       = $this->getSegmentExtractor();
        $this->files_word_count          = $extractor->getFilesWordCount();
        $this->total_segments            = $extractor->getTotalSegments();
        $this->show_in_cattool_segs_counter = $extractor->getShowInCattoolSegsCounter();
    }

    /**
     * @param $_originalFileNames
     * @param $sha1_original (example: 917f7b03c8f54350fb65387bda25fbada43ff7d8)
     * @param $cachedXliffFilePathName (example: 91/7f/7b03c8f54350fb65387bda25fbada43ff7d8!!it-it/work/test_2.txt.sdlxliff)
     *
     * @return array
     * @throws Exception
     */
    protected function _insertFiles($_originalFileNames, $sha1_original, $cachedXliffFilePathName): array
    {
        $fs = FilesStorageFactory::create();

        $yearMonthPath = date_create($this->projectStructure['create_date'])->format('Ymd');
        $fileDateSha1Path = $yearMonthPath . DIRECTORY_SEPARATOR . $sha1_original;

        //return structure
        $fileStructures = [];

        foreach ($_originalFileNames as $pos => $originalFileName) {
            // avoid blank filenames
            if (!empty($originalFileName)) {
                // get metadata
                $meta = isset($this->projectStructure['array_files_meta'][$pos]) ? $this->projectStructure['array_files_meta'][$pos] : null;
                $mimeType = AbstractFilesStorage::pathinfo_fix($originalFileName, PATHINFO_EXTENSION);
                $fid = $this->getProjectManagerModel()->insertFile($this->projectStructure, $originalFileName, $mimeType, $fileDateSha1Path);

                if ($this->gdriveSession) {
                    $gdriveFileId = $this->gdriveSession->findFileIdByName($originalFileName);
                    if ($gdriveFileId) {
                        $client = GoogleProvider::getClient(AppConfig::$HTTPHOST . "/gdrive/oauth/response");
                        $this->gdriveSession->createRemoteFile($fid, $gdriveFileId, $client);
                    }
                }

                $moved = $fs->moveFromCacheToFileDir(
                    $fileDateSha1Path,
                    $this->projectStructure['source_language'],
                    $fid,
                    $originalFileName
                );

                // check if the files were moved
                if (true !== $moved) {
                    throw new Exception('Project creation failed. Please refresh page and retry.', -200);
                }

                $this->projectStructure['file_id_list']->append($fid);

                // pdfAnalysis
                if (!empty($meta['pdfAnalysis'])) {
                    $this->filesMetadataDao->insert($this->projectStructure['id_project'], $fid, 'pdfAnalysis', json_encode($meta['pdfAnalysis']));
                }

                $fileStructures[$fid] = [
                    'fid' => $fid,
                    'original_filename' => $originalFileName,
                    'path_cached_xliff' => $cachedXliffFilePathName,
                    'mime_type' => $mimeType
                ];
            }
        }

        return $fileStructures;
    }

    /**
     * @param $fid
     * @param $value
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    protected function _insertInstructions($fid, $value): void
    {
        $value = $this->features->filter('decodeInstructions', $value);

        $this->filesMetadataDao->insert($this->projectStructure['id_project'], $fid, 'instructions', $value);
    }

    /**
     * Store segments for a file — delegates to SegmentStorageService.
     * @throws Exception
     */
    protected function storeSegments($fid): void
    {
        $this->getSegmentStorageService()->storeSegments($fid, $this->projectStructure);
        $this->min_max_segments_id = $this->getSegmentStorageService()->getMinMaxSegmentsId();
    }

    /**
     * setSegmentIdForNotes
     *
     * Adds notes to segment, taking into account that a same note may be assigned to
     * more than one Matecat segment, due to the <mrk> tags.
     *
     * Example:
     * ['notes'][ $internal_id] => array( 'aaa' );
     * ['notes'][ $internal_id] => array( 'aaa', 'yyy' ); // in case of mrk tags
     *
     */
    /**
     * @throws Exception
     */
    private function insertSegmentNotesForFile(): void
    {
        $this->projectStructure = $this->features->filter('handleJsonNotesBeforeInsert', $this->projectStructure);
        $this->getProjectManagerModel()->bulkInsertSegmentNotes($this->projectStructure['notes']);
        $this->getProjectManagerModel()->bulkInsertSegmentMetaDataFromAttributes($this->projectStructure['notes']);
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
        $this->getProjectManagerModel()->bulkInsertContextsGroups($this->projectStructure);
    }

}