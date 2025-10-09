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
use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Utils\DataRefReplacer;
use Matecat\XliffParser\XliffParser;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use Model\ActivityLog\ActivityLogStruct;
use Model\Analysis\AnalysisDao;
use Model\Analysis\PayableRates;
use Model\ConnectedServices\GDrive\Session;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\Conversion\ZipArchiveHandler;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Files\FileDao;
use Model\Files\FilesPartsDao;
use Model\Files\FilesPartsStruct;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FilesStorageFactory;
use Model\FilesStorage\S3FilesStorage;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use Model\Segments\SegmentOriginalDataDao;
use Model\Segments\SegmentOriginalDataStruct;
use Model\Segments\SegmentStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Translators\TranslatorsModel;
use Model\Users\UserDao;
use Model\WordCount\CounterModel;
use Model\Xliff\DTO\XliffRulesModel;
use Model\Xliff\XliffConfigTemplateStruct;
use Plugins\Features\SecondPassReview;
use ReflectionException;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\ActivityLogWorker;
use Utils\AsyncTasks\Workers\JobsWorker;
use Utils\Collections\RecursiveArrayObject;
use Utils\Constants\ProjectStatus;
use Utils\Constants\XliffTranslationStatus;
use Utils\Engines\EnginesFactory;
use Utils\Langs\Languages;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\LQA\QA;
use Utils\Registry\AppConfig;
use Utils\Shop\Cart;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSFile;
use Utils\TMS\TMSService;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;
use View\API\Commons\Error;

class ProjectManager {

    /**
     * Configuration for segment notes handling
     */
    const SEGMENT_NOTES_LIMIT    = 10;
    const SEGMENT_NOTES_MAX_SIZE = 65535;

    /**
     * Counter from the total number of segments in the project with the flag (show_in_cattool == true)
     *
     * @var int
     */
    protected int   $show_in_cattool_segs_counter = 0;
    protected int   $files_word_count             = 0;
    protected int   $total_segments               = 0;
    protected array $min_max_segments_id          = [];

    /**
     * @var ArrayObject|RecursiveArrayObject
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

    const TRANSLATED_USER = 'translated_user';

    /**
     * @var Database|IDatabase
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
    private MatecatLogger $logger;

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
    public function __construct( ArrayObject $projectStructure = null ) {

        $this->logger = LoggerFactory::getLogger( 'project_manager' );

        if ( $projectStructure == null ) {
            $projectStructure = new RecursiveArrayObject(
                    [
                            'HTTP_HOST'                              => null,
                            'id_project'                             => null,
                            'create_date'                            => date( "Y-m-d H:i:s" ),
                            'id_customer'                            => self::TRANSLATED_USER,
                            'project_features'                       => [],
                            'user_ip'                                => null,
                            'project_name'                           => null,
                            'result'                                 => [ "errors" => [], "data" => [] ],
                            'private_tm_key'                         => 0,
                            'uploadToken'                            => null,
                            'array_files'                            => [], //list of file names
                            'array_files_meta'                       => [], //list of file metadata
                            'file_id_list'                           => [],
                            'source_language'                        => null,
                            'target_language'                        => null,
                            'job_subject'                            => 'general',
                            'mt_engine'                              => null,
                            'tms_engine'                             => null,
                            'ppassword'                              => null,
                            'array_jobs'                             => [
                                    'job_list'      => [],
                                    'job_pass'      => [],
                                    'job_segments'  => [],
                                    'job_languages' => [],
                                    'payable_rates' => [],
                            ],
                            'job_segments'                           => [], //array of job_id => [  min_seg, max_seg  ]
                            'segments'                               => [], //array of files_id => segments[  ]
                            'segments-original-data'                 => [], //array of files_id => segments-original-data[  ]
                            'segments_metadata'                      => [], //array of segments_metadata
                            'segments-meta-data'                     => [], //array of files_id => segments-meta-data[  ]
                            'file-part-id'                           => [], //array of files_id => segments-meta-data[  ]
                            'file-metadata'                          => [], //array of files metadata
                            'translations'                           => [],
                            'notes'                                  => [],
                            'context-group'                          => [],
                        //one translation for every file because translations are files related
                            'status'                                 => ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
                            'job_to_split'                           => null,
                            'job_to_split_pass'                      => null,
                            'split_result'                           => null,
                            'job_to_merge'                           => null,
                            'tm_keys'                                => [],
                            'userIsLogged'                           => false,
                            'uid'                                    => null,
                            'pretranslate_100'                       => 0,
                            'pretranslate_101'                       => 1,
                            'only_private'                           => 0,
                            'owner'                                  => '',
                            ProjectsMetadataDao::WORD_COUNT_TYPE_KEY => ProjectsMetadataDao::WORD_COUNT_RAW,
                            'metadata'                               => [],
                            'id_assignee'                            => null,
                            'session'                                => ( $_SESSION ?? false ),
                            'instance_id'                            => ( !is_null( AppConfig::$INSTANCE_ID ) ? AppConfig::$INSTANCE_ID : 0 ),
                            'id_team'                                => null,
                            'team'                                   => null,
                            'sanitize_project_options'               => true,
                            'file_segments_count'                    => [],
                            'due_date'                               => null,
                            'qa_model'                               => null,
                            'target_language_mt_engine_association'  => [],
                            'standard_word_count'                    => 0,
                            'mmt_glossaries'                         => null,
                            'deepl_formality'                        => null,
                            'deepl_id_glossary'                      => null,
                            'dictation'                              => null,
                            'show_whitespace'                        => null,
                            'character_counter'                      => null,
                            'character_counter_mode'                 => null,
                            'character_counter_count_tags'           => null,
                            'ai_assistant'                           => null,
                            'filters_extraction_parameters'          => new RecursiveArrayObject(),
                            'xliff_parameters'                       => new RecursiveArrayObject(),
                            'tm_prioritization'                      => null,
                            'mt_qe_workflow_payable_rate'            => null,
                            'pre_translate_files'                    => null,
                            'mmt_pre_import_tm'                      => null,
                            'mmt_activate_context_analyzer'          => null,
                            'mmt_glossaries_case_sensitive_matching' => null,
                            'lara_glossaries'                        => null,
                            'deepl_engine_type'                      => null,
                            'intento_routing'                        => null,
                            'intento_provider'                       => null,
                    ] );
        }

        $this->projectStructure = $projectStructure;

        //get the TMX management component from the factory
        $this->tmxServiceWrapper = new TMSService();

        $this->langService = Languages::getInstance();

        $this->dbHandler = Database::obtain();

        $this->features = new FeatureSet( $this->_getRequestedFeatures() );

        if ( !empty( $this->projectStructure[ 'id_customer' ] ) ) {
            $this->features->loadAutoActivableOwnerFeatures( $this->projectStructure[ 'id_customer' ] );
        }

        /** @var MateCatFilter $filter */
        $filter       = MateCatFilter::getInstance( $this->features, $this->projectStructure[ 'source_language' ], $this->projectStructure[ 'target_language' ] );
        $this->filter = $filter;

        $this->projectStructure[ 'array_files' ] = $this->features->filter(
                'filter_project_manager_array_files',
                $this->projectStructure[ 'array_files' ],
                $this->projectStructure
        );

        // sync array_files_meta
        $array_files_meta = [];
        foreach ( $this->projectStructure[ 'array_files_meta' ] as $fileMeta ) {
            if ( in_array( $fileMeta[ 'basename' ], (array)$this->projectStructure[ 'array_files' ] ) ) {
                $array_files_meta[] = $fileMeta;
            }
        }

        $this->projectStructure[ 'array_files_meta' ] = $array_files_meta;

        $this->filesMetadataDao = new MetadataDao();
    }

    protected function _log( $_msg, ?Throwable $exception = null ) {
        $this->logger->debug( $_msg );
        if ( $exception ) {
            $this->logger->debug( ( new Error( $exception ) )->render( true ) );
        }
    }

    /**
     * @return array
     */
    protected function _getRequestedFeatures(): array {
        $features = [];
        if ( count( $this->projectStructure[ 'project_features' ] ) != 0 ) {
            foreach ( $this->projectStructure[ 'project_features' ] as $key => $feature ) {
                /**
                 * @var $feature RecursiveArrayObject
                 */
                $this->projectStructure[ 'project_features' ][ $key ] = new BasicFeatureStruct( $feature->getArrayCopy() );
            }

            $features = $this->projectStructure[ 'project_features' ]->getArrayCopy();
        }

        return $features;
    }

    /**
     * Project name is required to build the analysis URL.
     * The project name is memoized in an instance variable
     * so to perform the check only the first time on $projectStructure['project_name'].
     *
     * @throws Exception
     */
    protected function _sanitizeProjectName() {
        $newName = Utils::sanitizeName( $this->projectStructure[ 'project_name' ] );

        if ( !$newName ) {
            $this->projectStructure[ 'result' ][ 'errors' ][] = [
                    "code"    => -5,
                    "message" => "Invalid Project Name " . $this->projectStructure[ 'project_name' ] . ": it should only contain numbers and letters!"
            ];
            throw new Exception( "Invalid Project Name " . $this->projectStructure[ 'project_name' ] . ": it should only contain numbers and letters!", -5 );
        }

        $this->projectStructure[ 'project_name' ] = $newName;
    }

    /**
     * @throws Exception
     */
    protected function _validateUploadToken() {
        if ( !isset( $this->projectStructure[ 'uploadToken' ] ) || !Utils::isTokenValid( $this->projectStructure[ 'uploadToken' ] ) ) {
            $this->projectStructure[ 'result' ][ 'errors' ][] = [
                    "code"    => -19,
                    "message" => "Invalid Upload Token."
            ];
            throw new Exception( "Invalid Upload Token.", -19 );
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function _validateXliffParameters() {
        try {

            // when the request comes from api or ajax
            if ( !$this->projectStructure[ 'xliff_parameters' ] instanceof ArrayObject ) {
                if ( is_array( $this->projectStructure[ 'xliff_parameters' ] ) ) {
                    $this->projectStructure[ 'xliff_parameters' ] = new RecursiveArrayObject( $this->projectStructure[ 'xliff_parameters' ] );
                } else {
                    throw new DomainException( "Invalid xliff_parameters value found.", 400 );
                }
            }

            // when the request comes from the ProjectCreation daemon, it is already an ArrayObject
            $this->projectStructure[ 'xliff_parameters' ] = XliffRulesModel::fromArrayObject( $this->projectStructure[ 'xliff_parameters' ] );

        } catch ( DomainException $ex ) {
            $this->projectStructure[ 'result' ][ 'errors' ][] = [
                    "code" => $ex->getCode(), "message" => $ex->getMessage()
            ];
            throw $ex;
        }
    }

    /**
     * @param TeamStruct $team
     */
    public function setTeam( TeamStruct $team ) {
        $this->projectStructure[ 'team' ]    = $team;
        $this->projectStructure[ 'id_team' ] = $team->id;
    }

    /**
     * @throws Exception
     */
    public function setProjectAndReLoadFeatures( ProjectStruct $pStruct ) {
        $this->project                           = $pStruct;
        $this->projectStructure[ 'id_project' ]  = $this->project->id;
        $this->projectStructure[ 'id_customer' ] = $this->project->id_customer;
        $this->reloadFeatures();
    }

    /**
     * @throws Exception
     */
    private function reloadFeatures() {
        $this->features = new FeatureSet();
        $this->features->loadForProject( $this->project );
    }

    public function getProjectStructure() {
        return $this->projectStructure;
    }

    /**
     * Save features in project metadata
     * @throws ReflectionException
     */
    private function saveFeaturesInMetadata() {

        $dao = new ProjectsMetadataDao();

        $featureCodes = $this->features->getCodes();
        if ( !empty( $featureCodes ) ) {
            $dao->set( $this->projectStructure[ 'id_project' ],
                    ProjectsMetadataDao::FEATURES_KEY,
                    implode( ',', $featureCodes )
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
    private function saveMetadata() {

        $options = $this->projectStructure[ 'metadata' ];
        $dao     = new ProjectsMetadataDao();

        // "From API" flag
        if ( isset( $this->projectStructure[ 'from_api' ] ) and $this->projectStructure[ 'from_api' ] ) {
            $options[ 'from_api' ] = 1;
        }

        // xliff_parameters
        if ( isset( $this->projectStructure[ 'xliff_parameters' ] ) and $this->projectStructure[ 'xliff_parameters' ] instanceof XliffConfigTemplateStruct ) {
            $configModel                   = $this->projectStructure[ 'xliff_parameters' ];
            $options[ 'xliff_parameters' ] = json_encode( $configModel );
        }

        // pretranslate_101
        if ( isset( $this->projectStructure[ 'pretranslate_101' ] ) ) {
            $options[ 'pretranslate_101' ] = $this->projectStructure[ 'pretranslate_101' ];
        }

        // mt evaluation => ice_mt already in metadata
        // adds JSON parameters to the project metadata as JSON string
        if ( $options[ 'mt_qe_workflow_enabled' ] ?? false ) {
            $options[ 'mt_qe_workflow_parameters' ] = json_encode( $options[ 'mt_qe_workflow_parameters' ] );
        }

        /**
         * Here we have the opportunity to add other features as dependencies of the ones
         * which are already explicitly set.
         */
        $this->features->loadProjectDependenciesFromProjectMetadata( $options );

        if ( isset( $this->projectStructure[ 'filters_extraction_parameters' ] ) && $this->projectStructure[ 'filters_extraction_parameters' ] ) {
            $options[ 'filters_extraction_parameters' ] = json_encode( $this->projectStructure[ 'filters_extraction_parameters' ] );
        }

        if ( $this->projectStructure[ 'sanitize_project_options' ] ) {
            $options = $this->sanitizeProjectOptions( $options );
        }

        // MT extra config parameters
        $extraKeys = [
                'pre_translate_files',
                'mmt_glossaries',
                'mmt_pre_import_tm',
                'mmt_activate_context_analyzer',
                'mmt_glossaries_case_sensitive_matching',
                'lara_glossaries',
                'deepl_formality',
                'deepl_id_glossary',
                'deepl_engine_type',
                'intento_provider',
                'intento_routing',
        ];

        foreach ( $extraKeys as $extraKey ) {
            if ( !empty( $this->projectStructure[ $extraKey ] ) && $this->projectStructure[ $extraKey ] ) {
                $options[ $extraKey ] = $this->projectStructure[ $extraKey ];
            }
        }

        if ( !empty( $options ) ) {
            foreach ( $options as $key => $value ) {
                $dao->set(
                        $this->projectStructure[ 'id_project' ],
                        $key,
                        $value
                );
            }
        }
    }

    /**
     * @param ArrayObject $options
     *
     * @return array
     * @throws Exception
     */
    private function sanitizeProjectOptions( ArrayObject $options ): array {
        $sanitizer = new ProjectOptionsSanitizer( $options->getArrayCopy() );

        /** @var $langs RecursiveArrayObject */
        $langs = $this->projectStructure[ 'target_language' ];

        $sanitizer->setLanguages(
                $this->projectStructure[ 'source_language' ],
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
    public function sanitizeProjectStructure() {

        $this->projectStructure[ 'result' ][ 'errors' ] = new ArrayObject();

        $this->_sanitizeProjectName();
        $this->_validateUploadToken();
        $this->_validateXliffParameters();

        $this->projectStructure[ 'project_features' ] = new ArrayObject( $this->projectStructure[ 'project_features' ] );

        $features = new FeatureSet( $this->_getRequestedFeatures() );
        $features->run( 'sanitizeProjectStructureInCreation', $this->projectStructure );

    }

    /**
     * Creates record in projects tabele and instantiates the project struct
     * internally.
     *
     * @throws ReflectionException
     */
    private function __createProjectRecord() {
        $this->project = ProjectManagerModel::createProjectRecord( $this->projectStructure );
    }

    /**
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    private function __checkForProjectAssignment() {

        if ( !empty( $this->projectStructure[ 'uid' ] ) ) {

            //if this is a logged user, set the user as project assignee
            $this->projectStructure[ 'id_assignee' ] = $this->projectStructure[ 'uid' ];

            /**
             * Normalize ArrayObject team in TeamStruct
             */
            $this->projectStructure[ 'team' ] = new TeamStruct(
                    $this->features->filter( 'filter_team_for_project_creation', $this->projectStructure[ 'team' ]->getArrayCopy() )
            );

            //clean the cache for the team member list of assigned projects
            $teamDao = new TeamDao();
            $teamDao->destroyCacheAssignee( $this->projectStructure[ 'team' ] );

        }

    }

    /**
     * @return bool|void
     * @throws Exception
     */
    public function createProject() {

        $this->sanitizeProjectStructure();

        $fs = FilesStorageFactory::create();

        if ( !empty( $this->projectStructure[ 'session' ][ 'uid' ] ) ) {
            $this->gdriveSession = Session::getInstanceForCLI( $this->projectStructure[ 'session' ]->getArrayCopy() );
        }

        $this->__checkForProjectAssignment();

        /**
         * This is the last chance to perform the validation before the project is created
         * in the database.
         * Validations should populate the projectStructure with errors and codes.
         */
        SecondPassReview::loadAndValidateQualityFramework( $this->projectStructure );
        $this->features->run( 'validateProjectCreation', $this->projectStructure );

        /**
         * @var ArrayObject $this ->projectStructure['result']['errors']
         */
        if ( $this->projectStructure[ 'result' ][ 'errors' ]->count() ) {
            $this->_log( $this->projectStructure[ 'result' ][ 'errors' ] );

            return false;
        }

        //sort files to process TMX first
        $sortedFiles      = [];
        $sortedMeta       = [];
        $firstTMXFileName = "";

        foreach ( $this->projectStructure[ 'array_files' ] as $pos => $fileName ) {

            // get metadata
            $meta = $this->projectStructure[ 'array_files_meta' ][ $pos ];

            //check for glossary files and tmx and put them in front of the list
            if ( $meta[ 'getMemoryType' ] ) {

                //found TMX, enable language checking routines
                if ( $meta[ 'isTMX' ] ) {
                    //export the name of the first TMX Files for latter use
                    $firstTMXFileName = ( empty( $firstTMXFileName ) ? $fileName : null );
                }

                //prepend in front of the list
                array_unshift( $sortedFiles, $fileName );
                array_unshift( $sortedMeta, $meta );

            } else {

                //append at the end of the list
                $sortedFiles[] = $fileName;
                $sortedMeta[]  = $meta;
            }
        }

        $this->projectStructure[ 'array_files' ]      = $sortedFiles;
        $this->projectStructure[ 'array_files_meta' ] = $sortedMeta;
        unset( $sortedFiles );
        unset( $sortedMeta );

        if ( count( $this->projectStructure[ 'private_tm_key' ] ) ) {
            $this->setPrivateTMKeys( $firstTMXFileName );

            if ( count( $this->projectStructure[ 'result' ][ 'errors' ] ) > 0 ) {
                // This return value was introduced after a refactoring
                return;
            }
        }

        $uploadDir = $this->uploadDir = AppConfig::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure[ 'uploadToken' ];

        $this->_log( $uploadDir );

        //we are going to access the storage, get a model object to manipulate it
        $linkFiles = $fs->getHashesFromDir( $this->uploadDir );

        $this->_log( $linkFiles );

        /*
            loop through all input files to
            1) upload INSERT INTMX and Glossaries
        */
        try {
            $this->_pushTMXToMyMemory();
        } catch ( Exception $e ) {
            $this->_log( $e->getMessage(), $e );

            //exit project creation
            return false;
        }
        //TMX Management

        /*
            loop through all input files to
            2)convert, in case, non-standard XLIFF files to a format that Matecat understands

            Note that XLIFF that don't need conversion are moved anyway as they are to cache in order not to alter the workflow
         */

        foreach ( $this->projectStructure[ 'array_files' ] as $pos => $fileName ) {

            // get corresponding meta
            $meta            = $this->projectStructure[ 'array_files_meta' ][ $pos ];
            $mustBeConverted = $meta[ 'mustBeConverted' ];

            //if it's one of the listed formats, or conversion is not enabled in the first place
            if ( !$mustBeConverted ) {
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

                if ( $isFsOnS3 and false === file_exists( $filePathName ) ) {
                    $this->getSingleS3QueueFile( $fileName );
                }

                // calculate hash and add the fileName, if I load 3 equal files with the same content,
                // they will be squashed to the last one
                $sha1 = sha1_file( $filePathName );

                // make a cache package (with work/ only, empty orig/)
                try {
                    $fs->makeCachePackage( $sha1, $this->projectStructure[ 'source_language' ], null, $filePathName );
                    $this->logger->debug( "File $fileName converted to cache" );
                } catch ( Exception $e ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code"    => -230,
                            "message" => $e->getMessage()
                    ];
                }

                // put reference to cache in the upload dir to link cache to session
                $fs->linkSessionToCacheForAlreadyConvertedFiles(
                        $sha1,
                        $this->projectStructure[ 'uploadToken' ],
                        $fileName
                );

                //add a newly created link to the list
                $linkFiles[ 'conversionHashes' ][ 'sha' ][] = $sha1 . AbstractFilesStorage::OBJECTS_SAFE_DELIMITER . $this->projectStructure[ 'source_language' ];

                $linkFiles[ 'conversionHashes' ][ 'fileName' ][ $sha1 . AbstractFilesStorage::OBJECTS_SAFE_DELIMITER . $this->projectStructure[ 'source_language' ] ][] = $fileName;

                //when the same sdlxliff is uploaded more than once with different names
                $linkFiles[ 'conversionHashes' ][ 'sha' ] = array_unique( $linkFiles[ 'conversionHashes' ][ 'sha' ] );
                unset( $sha1 );
            }
        }


        try {
            $this->_zipFileHandling( $linkFiles );
        } catch ( Exception $e ) {
            $this->_log( $e->getMessage(), $e );
            //Zip file Handling
            $this->projectStructure[ 'result' ][ 'errors' ][] = [
                    "code"    => $e->getCode(),
                    "message" => $e->getMessage()
            ];
        }

        //now, upload dir contains only hash-links
        //we start copying files to "file" dir, inserting metadata in db and extracting segments
        $totalFilesStructure = [];
        if ( isset( $linkFiles[ 'conversionHashes' ] ) and isset( $linkFiles[ 'conversionHashes' ][ 'sha' ] ) ) {
            foreach ( $linkFiles[ 'conversionHashes' ][ 'sha' ] as $linkFile ) {

                //converted file is inside cache directory
                //get hash from file name inside UUID dir
                $hashFile = AbstractFilesStorage::basename_fix( $linkFile );
                $hashFile = explode( AbstractFilesStorage::OBJECTS_SAFE_DELIMITER, $hashFile );

                // Example:
                // $hashFile[ 0 ] = 917f7b03c8f54350fb65387bda25fbada43ff7d8
                // $hashFile[ 1 ] = it-it
                $sha1_original = $hashFile[ 0 ];
                $lang          = $hashFile[ 1 ];

                //use hash and lang to fetch the file from the package
                $cachedXliffFilePathName = $fs->getXliffFromCache( $sha1_original, $lang );

                //associate the hash with the right file in upload directory
                //get original file name, to insert into DB and cp in storage
                //PLEASE NOTE; this can be an array when the same file added more
                // than once and with different names
                $_originalFileNames = $linkFiles[ 'conversionHashes' ][ 'fileName' ][ $linkFile ];

                unset( $hashFile );

                try {

                    if ( count( $_originalFileNames ) === 0 ) {
                        throw new Exception( 'No hash files found', -6 );
                    }

                    if ( AbstractFilesStorage::isOnS3() ) {
                        if ( null === $cachedXliffFilePathName ) {
                            throw new Exception( sprintf( 'Key not found on S3 cache bucket for file %s.', implode( ',', $_originalFileNames ) ), -6 );
                        }
                    } else {
                        if ( !file_exists( $cachedXliffFilePathName ) ) {
                            throw new Exception( sprintf( 'File %s not found on server after upload.', $cachedXliffFilePathName ), -6 );
                        }
                    }

                    $info = AbstractFilesStorage::pathinfo_fix( $cachedXliffFilePathName );

                    if ( !in_array( $info[ 'extension' ], [ 'xliff', 'sdlxliff', 'xlf' ] ) ) {
                        throw new Exception( "Failed to find converted Xliff", -3 );
                    }


                    $filesStructure = $this->_insertFiles( $_originalFileNames, $sha1_original, $cachedXliffFilePathName );

                    if ( count( $filesStructure ) === 0 ) {
                        throw new Exception( 'Files could not be saved in database.', -6 );
                    }

                    // pdfAnalysis
                    foreach ($filesStructure as $fid => $fileStructure){
                        $pos  = array_search($fileStructure['original_filename'], $this->projectStructure[ 'array_files' ]);
                        $meta = isset( $this->projectStructure[ 'array_files_meta' ][ $pos ] ) ? $this->projectStructure[ 'array_files_meta' ][ $pos ] : null;

                        if ( $meta !== null and isset( $meta[ 'pdfAnalysis' ] ) ) {
                            $this->filesMetadataDao->insert( $this->projectStructure[ 'id_project' ], $fid, 'pdfAnalysis', json_encode( $meta[ 'pdfAnalysis' ] ) );
                        }
                    }

                } catch ( Exception $e ) {

                    if ( $e->getCode() == -10 ) {

                        //Failed to store the original Zip
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code" => -10, "message" => $e->getMessage()
                        ];

                    } elseif ( $e->getCode() == -11 ) {
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code" => $e->getCode(), "message" => "Failed to store reference files on disk. Permission denied"
                        ];
                    } elseif ( $e->getCode() == -12 ) {
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code" => $e->getCode(), "message" => "Failed to store reference files in database"
                        ];
                    } // SEVERE EXCEPTIONS HERE
                    elseif ( $e->getCode() == -6 ) {
                        //"File isn't found on server after upload."
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code"    => $e->getCode(),
                                "message" => $e->getMessage()
                        ];
                    } elseif ( $e->getCode() == -3 ) {
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code"    => -16,
                                "message" => "File not found. Failed to save XLIFF conversion on disk."
                        ];
                    } elseif ( $e->getCode() == -13 ) {
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code" => $e->getCode(), "message" => $e->getMessage()
                        ];
                        //we cannot write to disk!! Break project creation
                    } // S3 EXCEPTIONS HERE
                    elseif ( $e->getCode() == -200 ) {
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code"    => -200,
                                "message" => $e->getMessage()
                        ];
                    } else {
                        if ( $e->getCode() == 0 ) {

                            // check for 'Invalid copy source encoding' error
                            $copyErrorMsg = "<Message>Invalid copy source encoding.</Message>";

                            if ( strpos( $e->getMessage(), $copyErrorMsg ) !== false ) {
                                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                        "code"    => -200,
                                        "message" => 'There was a problem during the upload of your file(s). Please, try to rename your file(s) avoiding non-standard characters'
                                ];
                            }
                        }
                    }
                    $this->__clearFailedProject( $e );

                    //EXIT
                    return false;

                }

                //this is an "array append" like array_merge, but it does not renumber the numeric keys, so we can preserve the files id
                $totalFilesStructure += $filesStructure;

            } //end of conversion hash-link loop
        }

        //Throws exception
        try {

            //Try to extract segments after all checks
            $exceptionsFound = 0;
            foreach ( $totalFilesStructure as $fid => $file_info ) {

                try {
                    $this->_extractSegments( $fid, $file_info );
                } catch ( Exception $e ) {

                    $this->_log( $totalFilesStructure );
                    $this->_log( "Count fileSt.: " . count( $totalFilesStructure ) );
                    $this->_log( "Exceptions: " . $exceptionsFound );
                    $this->_log( "Failed to parse " . $file_info[ 'original_filename' ], $e );

                    if ( $e->getCode() == -1 && count( $totalFilesStructure ) > 1 && $exceptionsFound < count( $totalFilesStructure ) ) {
                        $this->_log( "No text to translate in the file {$e->getMessage()}." );
                        $exceptionsFound += 1;
                        unset( $totalFilesStructure[ $fid ] );
                        continue;
                    } else {
                        throw $e;
                    }
                }
            }

            //Allow projects with less than 250.000 words or characters (for cjk languages)
            if ( $this->files_word_count > AppConfig::$MAX_SOURCE_WORDS ) {
                throw new Exception( "Matecat is unable to create your project. Please contact us at " . AppConfig::$SUPPORT_MAIL . ", we will be happy to help you!", 128 );
            }

            // check for project Creation before wasting disk space
            $this->features->run( "beforeProjectCreation", $this->projectStructure, [
                            'total_project_segments' => $this->total_segments,
                            'files_raw_wc'           => $this->files_word_count
                    ]
            );

            $this->__createProjectRecord();
            $this->saveFeaturesInMetadata();
            $this->saveMetadata();

            foreach ( $totalFilesStructure as $fid => $empty ) {
                $this->_storeSegments( $fid );
            }

            $this->_createJobs( $this->projectStructure );
            $this->writeFastAnalysisData();

        } catch ( Exception $e ) {

            if ( $e->getCode() == -1 ) {
                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code"    => -1,
                        "message" => "No text to translate in the file " . ZipArchiveHandler::getFileName( $e->getMessage() ) . "."
                ];
                if ( AppConfig::$FILE_STORAGE_METHOD != 's3' ) {
                    $fs->deleteHashFromUploadDir( $this->uploadDir, $linkFile ?? '' );
                }
            } elseif ( $e->getCode() == -4 ) {
                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code"    => -7,
                        "message" => "Xliff Import Error: {$e->getMessage()}"
                ];
            } elseif ( $e->getCode() == 400 ) {

                $message = ( null !== $e->getPrevious() ) ? $e->getPrevious()->getMessage() . " in {$e->getMessage()}" : $e->getMessage();

                //invalid Trans-unit value found empty ID
                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code"    => $e->getCode(),
                        "message" => $message,
                ];
            } else {
                //Generic error
                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code"    => $e->getCode(),
                        "message" => $e->getMessage()
                ];
            }

            $this->_log( "Exception", $e );

            //EXIT
            return false;
        }

        $this->projectStructure[ 'status' ] = ( AppConfig::$VOLUME_ANALYSIS_ENABLED ) ? ProjectStatus::STATUS_NEW : ProjectStatus::STATUS_NOT_TO_ANALYZE;

        if ( $this->show_in_cattool_segs_counter == 0 ) {
            $this->_log( "Segment Search: No segments in this project - \n" );
            $this->projectStructure[ 'status' ] = ProjectStatus::STATUS_EMPTY;
        }

        $this->projectStructure[ 'result' ][ 'code' ]            = 1;
        $this->projectStructure[ 'result' ][ 'data' ]            = "OK";
        $this->projectStructure[ 'result' ][ 'ppassword' ]       = $this->projectStructure[ 'ppassword' ];
        $this->projectStructure[ 'result' ][ 'password' ]        = $this->projectStructure[ 'array_jobs' ][ 'job_pass' ];
        $this->projectStructure[ 'result' ][ 'id_job' ]          = $this->projectStructure[ 'array_jobs' ][ 'job_list' ];
        $this->projectStructure[ 'result' ][ 'job_segments' ]    = $this->projectStructure[ 'array_jobs' ][ 'job_segments' ];
        $this->projectStructure[ 'result' ][ 'id_project' ]      = $this->projectStructure[ 'id_project' ];
        $this->projectStructure[ 'result' ][ 'project_name' ]    = $this->projectStructure[ 'project_name' ];
        $this->projectStructure[ 'result' ][ 'source_language' ] = $this->projectStructure[ 'source_language' ];
        $this->projectStructure[ 'result' ][ 'target_language' ] = $this->projectStructure[ 'target_language' ];
        $this->projectStructure[ 'result' ][ 'status' ]          = $this->projectStructure[ 'status' ];


        foreach ( $totalFilesStructure as $fid => $file_info ) {

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
            $array_files = $this->getProjectStructure()[ 'array_files' ];
            foreach ( $array_files as $index => $filename ) {
                if ( $file_info[ 'original_filename' ] === $filename ) {
                    if ( isset( $this->projectStructure[ 'instructions' ][ $index ] ) && !empty( $this->projectStructure[ 'instructions' ][ $index ] ) ) {
                        $instructions = Utils::stripTagsPreservingHrefs( $this->projectStructure[ 'instructions' ][ $index ] );
                        $this->_insertInstructions( $fid, $instructions );
                    }

                }
            }
        }

        if ( AppConfig::$VOLUME_ANALYSIS_ENABLED ) {
            $this->projectStructure[ 'result' ][ 'analyze_url' ] = $this->getAnalyzeURL();
        }

        Database::obtain()->begin();

        //pre-fetch Analysis page in transaction and store in cache
        ( new ProjectDao() )->destroyCacheForProjectData( $this->projectStructure[ 'id_project' ], $this->projectStructure[ 'ppassword' ] );
        ( new ProjectDao() )->setCacheTTL( 60 * 60 * 24 )->getProjectData( $this->projectStructure[ 'id_project' ], $this->projectStructure[ 'ppassword' ] );

        $this->features->run( 'postProjectCreate', $this->projectStructure );

        ProjectDao::updateAnalysisStatus(
                $this->projectStructure[ 'id_project' ],
                $this->projectStructure[ 'status' ],
                $this->files_word_count * count( $this->projectStructure[ 'array_jobs' ][ 'job_languages' ] )
        );

        $this->pushActivityLog();

        Database::obtain()->commit();

        $this->features->run( 'postProjectCommit', $this->projectStructure );

        try {

            if ( AbstractFilesStorage::isOnS3() ) {
                $this->_log( 'Deleting folder' . $this->uploadDir . ' from S3' );
                /** @var $fs S3FilesStorage */
                $fs->deleteQueue( $this->uploadDir );
            } else {
                $this->_log( 'Deleting folder' . $this->uploadDir . ' from filesystem' );
                Utils::deleteDir( $this->uploadDir );
                if ( is_dir( $this->uploadDir . '_converted' ) ) {
                    Utils::deleteDir( $this->uploadDir . '_converted' );
                }
            }

        } catch ( Exception $e ) {

            $output = "<pre>\n";
            $output .= " - Exception: " . print_r( $e->getMessage(), true ) . "\n";
            $output .= " - REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
            $output .= " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $output .= " - Trace: \n" . print_r( $e->getTraceAsString(), true ) . "\n";
            $output .= "\n\t";
            $output .= "Aborting...\n";
            $output .= "</pre>";

            $this->_log( $output, $e );

            Utils::sendErrMailReport( $output, $e->getMessage() );

        }


    }

    /**
     * @param $fileName
     *
     * @throws Exception
     */
    public function getSingleS3QueueFile( $fileName ) {
        $fs = FilesStorageFactory::create();

        if ( false === is_dir( $this->uploadDir ) ) {
            mkdir( $this->uploadDir, 0755 );
        }

        /** @var $fs S3FilesStorage */
        $client              = $fs::getStaticS3Client();
        $params[ 'bucket' ]  = AppConfig::$AWS_STORAGE_BASE_BUCKET;
        $params[ 'key' ]     = $fs::QUEUE_FOLDER . DIRECTORY_SEPARATOR . $fs::getUploadSessionSafeName( $fs->getTheLastPartOfKey( $this->uploadDir ) ) . DIRECTORY_SEPARATOR . $fileName;
        $params[ 'save_as' ] = "$this->uploadDir/$fileName";
        $client->downloadItem( $params );
    }

    private function __clearFailedProject( Exception $e ) {
        $this->_log( $e->getMessage(), $e );
        $this->_log( "Deleting Records." );
        ( new ProjectDao() )->deleteFailedProject( $this->projectStructure[ 'id_project' ] );
        ( new FileDao() )->deleteFailedProjectFiles( $this->projectStructure[ 'file_id_list' ]->getArrayCopy() );
        $this->_log( "Deleted Project ID: " . $this->projectStructure[ 'id_project' ] );
        $this->_log( "Deleted Files ID: " . json_encode( $this->projectStructure[ 'file_id_list' ]->getArrayCopy() ) );
    }

    /**
     * @throws Exception
     */
    private function writeFastAnalysisData() {

        $job_id_passes = ltrim(
                array_reduce(
                        array_keys( $this->projectStructure[ 'array_jobs' ][ 'job_segments' ]->getArrayCopy() ),
                        function ( $acc, $value ) {
                            $acc .= "," . strtr( $value, '-', ':' );

                            return $acc;
                        }
                ), "," );

        foreach ( $this->projectStructure[ 'segments_metadata' ] as &$segmentElement ) {

            unset( $segmentElement[ 'internal_id' ] );
            unset( $segmentElement[ 'xliff_mrk_id' ] );
            unset( $segmentElement[ 'show_in_cattool' ] );

            $segmentElement[ 'jsid' ]          = $segmentElement[ 'id' ] . "-" . $job_id_passes;
            $segmentElement[ 'source' ]        = $this->projectStructure[ 'source_language' ];
            $segmentElement[ 'target' ]        = implode( ",", $this->projectStructure[ 'array_jobs' ][ 'job_languages' ]->getArrayCopy() );
            $segmentElement[ 'payable_rates' ] = $this->projectStructure[ 'array_jobs' ][ 'payable_rates' ]->getArrayCopy();
            $segmentElement[ 'segment' ]       = $this->filter->fromLayer0ToLayer1( $segmentElement[ 'segment' ] );

        }

        $fs = FilesStorageFactory::create();
        $fs::storeFastAnalysisFile( $this->project->id, $this->projectStructure[ 'segments_metadata' ]->getArrayCopy() );

        //free memory
        unset( $this->projectStructure[ 'segments_metadata' ] );

    }

    private function pushActivityLog() {

        $activity             = new ActivityLogStruct();
        $activity->id_project = $this->projectStructure[ 'id_project' ];
        $activity->action     = ActivityLogStruct::PROJECT_CREATED;
        $activity->ip         = $this->projectStructure[ 'user_ip' ];
        $activity->uid        = $this->projectStructure[ 'uid' ];
        $activity->event_date = date( 'Y-m-d H:i:s' );

        try {
            WorkerClient::enqueueWithClient(
                    AMQHandler::getNewInstanceForDaemons(),
                    'ACTIVITYLOG',
                    ActivityLogWorker::class,
                    $activity->toArray(),
                    [ 'persistent' => WorkerClient::$_HANDLER->persistent ]
            );
        } catch ( Exception $e ) {

            # Handle the error, logging, ...
            $output = "**** Activity Log failed. AMQ Connection Error. **** ";
            $output .= var_export( $activity, true );
            $this->_log( $output, $e );

        }

    }

    /**
     * @return string
     * @throws Exception
     */
    public function getAnalyzeURL(): string {
        return CanonicalRoutes::analyze(
                [
                        'project_name' => $this->projectStructure[ 'project_name' ],
                        'id_project'   => $this->projectStructure[ 'id_project' ],
                        'password'     => $this->projectStructure[ 'ppassword' ]
                ],
                [
                        'http_host' => ( is_null( $this->projectStructure[ 'HTTP_HOST' ] ) ?
                                AppConfig::$HTTPHOST :
                                $this->projectStructure[ 'HTTP_HOST' ]
                        ),
                ]
        );
    }

    /**
     * @throws Exception
     */
    protected function _pushTMXToMyMemory() {

        $memoryFiles = [];

        //TMX Management
        foreach ( $this->projectStructure[ 'array_files' ] as $pos => $fileName ) {

            // get corresponding meta
            $meta = $this->projectStructure[ 'array_files_meta' ][ $pos ];

            $ext = $meta[ 'extension' ];

            try {

                if ( 'tmx' == $ext ) {

                    $file = new TMSFile(
                            "$this->uploadDir/$fileName",
                            $this->projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ],
                            $fileName,
                            $pos
                    );

                    $memoryFiles[] = $file;

                    if ( AppConfig::$FILE_STORAGE_METHOD == 's3' ) {
                        $this->getSingleS3QueueFile( $fileName );
                    }

                    $userStruct = ( new UserDao() )->setCacheTTL( 60 * 60 )->getByUid( $this->projectStructure[ 'uid' ] );
                    $this->tmxServiceWrapper->addTmxInMyMemory( $file, $userStruct );

                } else {
                    //don't call the postPushTMX for normal files
                    continue;
                }

            } catch ( Exception $e ) {

                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code"    => $e->getCode(),
                        "message" => $e->getMessage()
                ];

                throw new Exception( $e );
            }
        }

        /**
         * @throws Exception
         */
        $this->_loopForTMXLoadStatus( $memoryFiles );

    }

    /**
     * @param $memoryFiles TMSFile[]
     *
     * @throws Exception
     */
    protected function _loopForTMXLoadStatus( array $memoryFiles ) {

        $time = strtotime( '+30 minutes' );

        //TMX Management
        /****************/
        //loop again through files to check for TMX loading
        foreach ( $memoryFiles as $file ) {

            //is the TM loaded?
            //wait until the current TMX is loaded
            while ( true ) {

                try {

                    $result = $this->tmxServiceWrapper->tmxUploadStatus( $file->getUuid() );

                    if ( $result[ 'completed' ] || strtotime( 'now' ) > $time ) {

                        //"$fileName" has been loaded into Match"
                        // OR the indexer is down or stopped for maintenance
                        // exit the loop, the import will be executed at a later time
                        break;

                    }

                    //waiting for "$fileName" to be loaded into Match
                    sleep( 3 );

                } catch ( Exception $e ) {

                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code" => $e->getCode(), "message" => $e->getMessage()
                    ];

                    $this->_log( $e->getMessage(), $e );

                    //exit project creation
                    throw new Exception( $e );

                }
            }

            unset( $this->projectStructure[ 'array_files' ][ $file->getPosition() ] );
            unset( $this->projectStructure[ 'array_files_meta' ][ $file->getPosition() ] );
        }
    }

    /**
     * @throws Exception
     */
    protected function _zipFileHandling( $linkFiles ) {

        $fs = FilesStorageFactory::create();

        //begin with zip hashes manipulation
        foreach ( $linkFiles[ 'zipHashes' ] as $zipHash ) {

            $result = $fs->linkZipToProject(
                    $this->projectStructure[ 'create_date' ],
                    $zipHash,
                    $this->projectStructure[ 'id_project' ]
            );

            if ( !$result ) {

                $this->_log( "Failed to store the Zip file $zipHash - \n" );
                throw new Exception( "Failed to store the original Zip $zipHash ", -10 );
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
    protected function _createJobs( ArrayObject $projectStructure ) {

        foreach ( $projectStructure[ 'target_language' ] as $target ) {

            // get payable rates from mt_qe_workflow, this takes the priority over the other payable rates
            if ( $projectStructure[ 'mt_qe_workflow_payable_rate' ] ) {
                $payableRatesTemplate = null;
                $payableRates         = json_encode( $projectStructure[ 'mt_qe_workflow_payable_rate' ] );
            } elseif ( isset( $projectStructure[ 'payable_rate_model' ] ) and !empty( $projectStructure[ 'payable_rate_model' ] ) ) {

                // get payable rates
                $payableRatesTemplate = new CustomPayableRateStruct();
                $payableRatesTemplate->hydrateFromJSON( json_encode( $projectStructure[ 'payable_rate_model' ] ) );
                $payableRates = $payableRatesTemplate->getPayableRates( $projectStructure[ 'source_language' ], $target );
                $payableRates = json_encode( $payableRates );

            } elseif ( isset( $projectStructure[ 'payable_rate_model_id' ] ) and !empty( $projectStructure[ 'payable_rate_model_id' ] ) ) {
                // get payable rates
                $payableRatesTemplate = CustomPayableRateDao::getById( $projectStructure[ 'payable_rate_model_id' ] );
                $payableRates         = $payableRatesTemplate->getPayableRates( $projectStructure[ 'source_language' ], $target );
                $payableRates         = json_encode( $payableRates );
            } else {
                $payableRatesTemplate = null;
                $payableRates         = PayableRates::getPayableRates( $projectStructure[ 'source_language' ], $target );
                $payableRates         = json_encode( $this->features->filter( "filterPayableRates", $payableRates, $projectStructure[ 'source_language' ], $target ) );
            }

            $password = $this->generatePassword();

            $tm_key = [];

            if ( !empty( $projectStructure[ 'private_tm_key' ] ) ) {
                foreach ( $projectStructure[ 'private_tm_key' ] as $tmKeyObj ) {
                    $newTmKey                  = TmKeyManager::getTmKeyStructure();
                    $newTmKey->complete_format = true;
                    $newTmKey->tm              = true;
                    $newTmKey->glos            = true;
                    $newTmKey->owner           = true;
                    $newTmKey->penalty         = $tmKeyObj[ 'penalty' ] ?? 0;
                    $newTmKey->name            = $tmKeyObj[ 'name' ];
                    $newTmKey->key             = $tmKeyObj[ 'key' ];
                    $newTmKey->r               = $tmKeyObj[ 'r' ];
                    $newTmKey->w               = $tmKeyObj[ 'w' ];

                    $tm_key[] = $newTmKey;
                }

            }

            // check for job_first_segment and job_last_segment existence
            if ( !isset( $this->min_max_segments_id[ 'job_first_segment' ] ) or !isset( $this->min_max_segments_id[ 'job_last_segment' ] ) ) {
                throw new Exception( 'Job cannot be created. No segments found!' );
            }

            $this->_log( $projectStructure[ 'private_tm_key' ] );

            $projectStructure[ 'tm_keys' ] = json_encode( $tm_key );

            // Replace {{pid}} with project ID for new keys created with an empty name
            $projectStructure[ 'tm_keys' ] = str_replace( "{{pid}}", $projectStructure[ 'id_project' ], $projectStructure[ 'tm_keys' ] );

            $newJob                    = new JobStruct();
            $newJob->password          = $password;
            $newJob->id_project        = $projectStructure[ 'id_project' ];
            $newJob->source            = $projectStructure[ 'source_language' ];
            $newJob->target            = $target;
            $newJob->id_tms            = $projectStructure[ 'tms_engine' ] ?? 1;
            $newJob->id_mt_engine      = $projectStructure[ 'target_language_mt_engine_association' ][ $target ];
            $newJob->create_date       = date( "Y-m-d H:i:s" );
            $newJob->last_update       = date( "Y-m-d H:i:s" );
            $newJob->subject           = $projectStructure[ 'job_subject' ];
            $newJob->owner             = $projectStructure[ 'owner' ];
            $newJob->job_first_segment = $this->min_max_segments_id[ 'job_first_segment' ];
            $newJob->job_last_segment  = $this->min_max_segments_id[ 'job_last_segment' ];
            $newJob->tm_keys           = $projectStructure[ 'tm_keys' ];
            $newJob->payable_rates     = $payableRates;
            $newJob->total_raw_wc      = $this->files_word_count;
            $newJob->only_private_tm   = (int)$projectStructure[ 'only_private' ];

            $this->features->run( 'validateJobCreation', $newJob, $projectStructure );
            $newJob = JobDao::createFromStruct( $newJob );

            $projectStructure[ 'array_jobs' ][ 'job_list' ]->append( $newJob->id );
            $projectStructure[ 'array_jobs' ][ 'job_pass' ]->append( $newJob->password );
            $projectStructure[ 'array_jobs' ][ 'job_segments' ]->offsetSet( $newJob->id . "-" . $newJob->password, $this->min_max_segments_id );
            $projectStructure[ 'array_jobs' ][ 'job_languages' ]->offsetSet( $newJob->id, $newJob->id . ":" . $target );
            $projectStructure[ 'array_jobs' ][ 'payable_rates' ]->offsetSet( $newJob->id, $payableRates );

            $jobsMetadataDao = new \Model\Jobs\MetadataDao();

            // public_tm_penalty
            if ( isset ( $projectStructure[ 'public_tm_penalty' ] ) ) {
                $jobsMetadataDao->set( $newJob->id, $newJob->password, 'public_tm_penalty', $projectStructure[ 'public_tm_penalty' ] );
            }

            // character_counter_count_tags
            if ( isset( $projectStructure[ 'character_counter_count_tags' ] ) ) {
                $jobsMetadataDao->set( $newJob->id, $newJob->password, 'character_counter_count_tags', ( $projectStructure[ 'character_counter_count_tags' ] ? "1" : "0" ) );
            }

            // character_counter_mode
            if ( isset( $projectStructure[ 'character_counter_mode' ] ) ) {
                $jobsMetadataDao->set( $newJob->id, $newJob->password, 'character_counter_mode', $projectStructure[ 'character_counter_mode' ] );
            }

            // tm_prioritization
            if ( isset( $projectStructure[ 'tm_prioritization' ] ) ) {
                $jobsMetadataDao->set( $newJob->id, $newJob->password, 'tm_prioritization', $projectStructure[ 'tm_prioritization' ] ? 1 : 0 );
            }

            // dialect_strict
            if ( isset( $projectStructure[ 'dialect_strict' ] ) ) {
                $dialectStrictObj = json_decode( $projectStructure[ 'dialect_strict' ], true );

                foreach ( $dialectStrictObj as $lang => $value ) {
                    if ( trim( $lang ) === trim( $newJob->target ) ) {
                        $jobsMetadataDao->set( $newJob->id, $newJob->password, 'dialect_strict', $value );
                    }
                }
            }

            try {
                if ( isset( $projectStructure[ 'payable_rate_model_id' ] ) and !empty( $projectStructure[ 'payable_rate_model_id' ] ) and $payableRatesTemplate !== null ) {
                    CustomPayableRateDao::assocModelToJob(
                            $projectStructure[ 'payable_rate_model_id' ],
                            $newJob->id,
                            $payableRatesTemplate->version,
                            $payableRatesTemplate->name
                    );
                }

                //prepare pre-translated segments queries
                if ( !empty( $projectStructure[ 'translations' ] ) ) {
                    $this->_insertPreTranslations( $newJob, $projectStructure );
                }
            } catch ( Exception $e ) {
                $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export( $e->getMessage(), true );
                Utils::sendErrMailReport( $msg );
            }

            foreach ( $projectStructure[ 'file_id_list' ] as $fid ) {

                FileDao::insertFilesJob( $newJob->id, $fid );

                if ( $this->gdriveSession && $this->gdriveSession->hasFiles() ) {
                    $client = GoogleProvider::getClient( AppConfig::$HTTPHOST . "/gdrive/oauth/response" );
                    $this->gdriveSession->createRemoteCopiesWhereToSaveTranslation( $fid, $newJob->id, $client );
                }
            }

        }

        if ( !empty( $this->projectStructure[ 'notes' ] ) ) {
            $this->insertSegmentNotesForFile();
        }

        if ( !empty( $this->projectStructure[ 'context-group' ] ) ) {
            $this->insertContextsForFile();
        }

        //Clean Translation array
        $this->projectStructure[ 'translations' ]->exchangeArray( [] );

    }

    /**
     *
     * Build a job split structure, minimum split value are 2 chunks
     *
     * @param ArrayObject $projectStructure
     * @param int         $num_split
     * @param array       $requestedWordsPerSplit Matecat Equivalent Words (Only valid for Pro Version)
     * @param string      $count_type
     *
     * @return ArrayObject
     *
     * @throws Exception
     */
    public function getSplitData( ArrayObject $projectStructure, int $num_split = 2, array $requestedWordsPerSplit = [], string $count_type = ProjectsMetadataDao::SPLIT_EQUIVALENT_WORD_TYPE ): ArrayObject {

        if ( $num_split < 2 ) {
            throw new Exception( 'Minimum Chunk number for split is 2.', -2 );
        }

        if ( !empty( $requestedWordsPerSplit ) && count( $requestedWordsPerSplit ) != $num_split ) {
            throw new Exception( "Requested words per chunk and Number of chunks not consistent.", -3 );
        }

        if ( !empty( $requestedWordsPerSplit ) && !AppConfig::$VOLUME_ANALYSIS_ENABLED ) {
            throw new Exception( "Requested words per chunk available only for Matecat PRO version", -4 );
        }

        $rows = ( new JobDao() )->getSplitData( $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );

        if ( empty( $rows ) ) {
            throw new Exception( 'No segments found for job ' . $projectStructure[ 'job_to_split' ], -5 );
        }

        $row_totals = array_pop( $rows ); //get the last row (ROLLUP)
        unset( $row_totals[ 'id' ] );

        if ( empty( $row_totals[ 'job_first_segment' ] ) || empty( $row_totals[ 'job_last_segment' ] ) ) {
            throw new Exception( 'Wrong job id or password. Job segment range not found.', -6 );
        }

        $total_words = $row_totals[ $count_type ];

        // if the requested $count_type is empty (for example, equivalent raw count = 0),
        // switch to the other one
        if ( $total_words < $num_split ) {
            $new_count_type = ( $count_type === ProjectsMetadataDao::SPLIT_EQUIVALENT_WORD_TYPE ) ? ProjectsMetadataDao::SPLIT_RAW_WORD_TYPE : ProjectsMetadataDao::SPLIT_EQUIVALENT_WORD_TYPE;
            $total_words    = $row_totals[ $new_count_type ];
            $count_type     = $new_count_type;
        }

        // if the total number of words is < the number of chunks, throw an exception
        if ( $total_words < $num_split ) {
            throw new Exception( 'The number of words is insufficient for the requested amount of chunks.', -6 );
        }

        if ( empty( $requestedWordsPerSplit ) ) {
            /*
             * Simple Split with a pretty equivalent number of words per chunk
             */
            $words_per_job = array_fill( 0, $num_split, round( $total_words / $num_split ) );
        } else {
            /*
             * User defined words per chunk, needs some checks and control structures
             */
            $words_per_job = $requestedWordsPerSplit;
        }

        $counter = [];
        $chunk   = 0;

        $reverse_count = [ 'standard_word_count' => 0, 'eq_word_count' => 0, 'raw_word_count' => 0 ];

        foreach ( $rows as $row ) {

            if ( !array_key_exists( $chunk, $counter ) ) {
                $counter[ $chunk ] = [
                        'standard_word_count' => 0,
                        'eq_word_count'       => 0,
                        'raw_word_count'      => 0,
                        'segment_start'       => $row[ 'id' ],
                        'segment_end'         => 0,
                        'last_opened_segment' => 0,
                ];
            }

            $counter[ $chunk ][ 'standard_word_count' ] += $row[ 'standard_word_count' ];
            $counter[ $chunk ][ 'eq_word_count' ]       += $row[ 'eq_word_count' ];
            $counter[ $chunk ][ 'raw_word_count' ]      += $row[ 'raw_word_count' ];
            $counter[ $chunk ][ 'segment_end' ]         = $row[ 'id' ];

            //if the last_opened segment is not set and if that segment can be shown in cattool
            //set that segment as the default last visited
            ( $counter[ $chunk ][ 'last_opened_segment' ] == 0 && $row[ 'show_in_cattool' ] == 1 ? $counter[ $chunk ][ 'last_opened_segment' ] = $row[ 'id' ] : null );

            //check for wanted words per job.
            //create a chunk when we reach the requested number of words,
            //and we are below the requested number of splits.
            //in this manner, we add to the last chunk all rests
            if ( $counter[ $chunk ][ $count_type ] >= $words_per_job[ $chunk ] && $chunk < $num_split - 1 /* chunk is zero-based */ ) {
                $counter[ $chunk ][ 'standard_word_count' ] = (int)$counter[ $chunk ][ 'standard_word_count' ];
                $counter[ $chunk ][ 'eq_word_count' ]       = (int)$counter[ $chunk ][ 'eq_word_count' ];
                $counter[ $chunk ][ 'raw_word_count' ]      = (int)$counter[ $chunk ][ 'raw_word_count' ];

                $reverse_count[ 'standard_word_count' ] += (int)$counter[ $chunk ][ 'standard_word_count' ];
                $reverse_count[ 'eq_word_count' ]       += (int)$counter[ $chunk ][ 'eq_word_count' ];
                $reverse_count[ 'raw_word_count' ]      += (int)$counter[ $chunk ][ 'raw_word_count' ];

                $chunk++;
            }
        }

        if ( $total_words > $reverse_count[ $count_type ] ) {
            if ( !empty( $counter[ $chunk ] ) ) {
                $counter[ $chunk ][ 'standard_word_count' ] = round( $row_totals[ 'standard_word_count' ] - $reverse_count[ 'standard_word_count' ] );
                $counter[ $chunk ][ 'eq_word_count' ]       = round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
                $counter[ $chunk ][ 'raw_word_count' ]      = round( $row_totals[ 'raw_word_count' ] - $reverse_count[ 'raw_word_count' ] );
            } else {
                $counter[ $chunk - 1 ][ 'standard_word_count' ] += round( $row_totals[ 'standard_word_count' ] - $reverse_count[ 'standard_word_count' ] );
                $counter[ $chunk - 1 ][ 'eq_word_count' ]       += round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
                $counter[ $chunk - 1 ][ 'raw_word_count' ]      += round( $row_totals[ 'raw_word_count' ] - $reverse_count[ 'raw_word_count' ] );
            }
        }

        if ( count( $counter ) < 2 ) {
            throw new Exception( 'The requested number of words for the first chunk is too large. I cannot create 2 chunks.', -7 );
        }

        $chunk                                   = JobDao::getByIdAndPassword( $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );
        $row_totals[ 'standard_analysis_count' ] = $chunk->standard_analysis_wc;

        $result = array_merge( $row_totals->getArrayCopy(), [ 'chunks' => $counter ] );

        $projectStructure[ 'split_result' ] = new ArrayObject( $result );

        return $projectStructure[ 'split_result' ];
    }

    /**
     * Do the split based on previous getSplitData analysis
     * It clones the original job in the right number of chunks and fill these rows with:
     * first/last segments of every chunk, last opened segment as the first segment of the new job
     * and the timestamp of creation
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    protected function _splitJob( ArrayObject $projectStructure ) {

        // init JobDao
        $jobDao = new JobDao();

        // job to split
        $jobToSplit = JobDao::getByIdAndPassword( $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );

        $translatorModel   = new TranslatorsModel( $jobToSplit );
        $jTranslatorStruct = $translatorModel->getTranslator( 0 ); // no cache
        if ( !empty( $jTranslatorStruct ) && !empty( $this->projectStructure[ 'uid' ] ) ) {

            $translatorModel
                    ->setUserInvite( ( new UserDao() )->setCacheTTL( 60 * 60 )->getByUid( $this->projectStructure[ 'uid' ] ) )
                    ->setDeliveryDate( $jTranslatorStruct->delivery_date )
                    ->setJobOwnerTimezone( $jTranslatorStruct->job_owner_timezone )
                    ->setEmail( $jTranslatorStruct->email )
                    ->setNewJobPassword( Utils::randomString() );

            $translatorModel->update();
        }

        $chunks = $projectStructure[ 'split_result' ][ 'chunks' ];

        // update the first chunk of the job to split
        $jobDao->updateStdWcAndTotalWc( $jobToSplit->id, $chunks[ 0 ][ 'standard_word_count' ], $chunks[ 0 ][ 'raw_word_count' ] );

        $newJobList = [];

        // create the other chunks of the job to split
        foreach ( $chunks as $contents ) {

            $newJob = clone $jobToSplit;

            //IF THIS IS NOT the original job, UPDATE relevant fields
            if ( $contents[ 'segment_start' ] != $projectStructure[ 'split_result' ][ 'job_first_segment' ] ) {
                //next insert
                $newJob[ 'password' ]                = $this->generatePassword();
                $newJob[ 'create_date' ]             = date( 'Y-m-d H:i:s' );
                $newJob[ 'avg_post_editing_effort' ] = 0;
                $newJob[ 'total_time_to_edit' ]      = 0;
            }

            $newJob[ 'last_opened_segment' ]  = $contents[ 'last_opened_segment' ];
            $newJob[ 'job_first_segment' ]    = $contents[ 'segment_start' ];
            $newJob[ 'job_last_segment' ]     = $contents[ 'segment_end' ];
            $newJob[ 'standard_analysis_wc' ] = $contents[ 'standard_word_count' ];
            $newJob[ 'total_raw_wc' ]         = $contents[ 'raw_word_count' ];

            $stmt = $jobDao->getSplitJobPreparedStatement( $newJob );
            $stmt->execute();

            $wCountManager = new CounterModel();
            $wCountManager->initializeJobWordCount( $newJob->id, $newJob->password );

            if ( $this->dbHandler->affected_rows == 0 ) {
                $msg = "Failed to split job into " . count( $projectStructure[ 'split_result' ][ 'chunks' ] ) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r( $stmt->queryString, true ) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r( $newJob, true ) . "\n";
                $this->_log( $msg );
                throw new Exception( 'Failed to insert job chunk, project damaged.', -8 );
            }

            $newJobList[] = $newJob;

            $stmt->closeCursor();
            unset( $stmt );

            //add here the job id to list
            $projectStructure[ 'array_jobs' ][ 'job_list' ]->append( $projectStructure[ 'job_to_split' ] );
            //add here passwords to list
            $projectStructure[ 'array_jobs' ][ 'job_pass' ]->append( $newJob[ 'password' ] );

            $projectStructure[ 'array_jobs' ][ 'job_segments' ]->offsetSet( $projectStructure[ 'job_to_split' ] . "-" . $newJob[ 'password' ], new ArrayObject( [
                    $contents[ 'segment_start' ], $contents[ 'segment_end' ]
            ] ) );

        }

        foreach ( $newJobList as $job ) {
            /**
             * Async worker to re-count avg-PEE and total-TTE for split jobs
             */
            try {
                WorkerClient::enqueue( 'JOBS', JobsWorker::class, $job->getArrayCopy(), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
            } catch ( Exception $e ) {
                # Handle the error, logging, ...
                $output = "**** Job Split PEE recount request failed. AMQ Connection Error. ****\n\t";
                $output .= "{$e->getMessage()}";
                $output .= var_export( $job, true );
                $this->_log( $output, $e );
            }
        }

        ( new JobDao() )->destroyCacheByProjectId( $projectStructure[ 'id_project' ] );

        $projectStruct = $jobToSplit->getProject( 60 * 10 );
        ( new ProjectDao() )->destroyCacheForProjectData( $projectStruct->id, $projectStruct->password );
        AnalysisDao::destroyCacheByProjectId( $projectStructure[ 'id_project' ] );

        Cart::getInstance( 'outsource_to_external_cache' )->deleteCart();

        $this->features->run( 'postJobSplitted', $projectStructure );

    }

    /**
     * Apply new structure of the job
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    public function applySplit( ArrayObject $projectStructure ) {
        Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();

        Database::obtain()->begin();
        $this->_splitJob( $projectStructure );
        $this->dbHandler->getConnection()->commit();

    }

    /**
     * @param ArrayObject $projectStructure
     * @param JobStruct[] $jobStructs
     *
     * @throws Exception
     */
    public function mergeALL( ArrayObject $projectStructure, array $jobStructs ) {

        $metadata_dao = new ProjectsMetadataDao();
        $metadata_dao->cleanupChunksOptions( $jobStructs );

        //get the min and
        $first_job         = reset( $jobStructs );
        $job_first_segment = $first_job[ 'job_first_segment' ];

        //the max segment from the job list
        $last_job         = end( $jobStructs );
        $job_last_segment = $last_job[ 'job_last_segment' ];

        //change values of the first job
        $first_job[ 'job_first_segment' ] = $job_first_segment; // redundant
        $first_job[ 'job_last_segment' ]  = $job_last_segment;

        //get the min and
        $total_raw_wc        = 0;
        $standard_word_count = 0;

        //merge TM keys: preserve only owner's keys
        $tm_keys = [];
        foreach ( $jobStructs as $chunk_info ) {
            $tm_keys[]           = $chunk_info[ 'tm_keys' ];
            $total_raw_wc        = $total_raw_wc + $chunk_info[ 'total_raw_wc' ];
            $standard_word_count = $standard_word_count + $chunk_info[ 'standard_analysis_wc' ];
        }

        try {
            $owner_tm_keys = TmKeyManager::getOwnerKeys( $tm_keys );

            foreach ( $owner_tm_keys as $i => $owner_key ) {
                $owner_key->complete_format = true;
                $owner_tm_keys[ $i ]        = $owner_key->toArray();
            }

            $first_job[ 'tm_keys' ] = json_encode( $owner_tm_keys );
        } catch ( Exception $e ) {
            $this->_log( __METHOD__ . " -> Merge Jobs error - TM key problem", $e );
        }

        $totalAvgPee     = 0;
        $totalTimeToEdit = 0;
        foreach ( $jobStructs as $_jStruct ) {
            $totalAvgPee     += $_jStruct->avg_post_editing_effort;
            $totalTimeToEdit += $_jStruct->total_time_to_edit;
        }
        $first_job[ 'avg_post_editing_effort' ] = $totalAvgPee;
        $first_job[ 'total_time_to_edit' ]      = $totalTimeToEdit;

        Database::obtain()->begin();

        if ( $first_job->getTranslator() ) {
            //Update the password in the struct and in the database for the first job
            JobDao::updateForMerge( $first_job, self::generatePassword() );
            Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();
        } else {
            JobDao::updateForMerge( $first_job, false );
        }

        JobDao::deleteOnMerge( $first_job );

        $wCountManager = new CounterModel();
        $wCountManager->initializeJobWordCount( $first_job[ 'id' ], $first_job[ 'password' ] );

        $chunk = new JobStruct( $first_job->toArray() );
        $this->features->run( 'postJobMerged', $projectStructure, $chunk );

        $jobDao = new JobDao();

        $jobDao->updateStdWcAndTotalWc( $first_job[ 'id' ], $standard_word_count, $total_raw_wc );

        $this->dbHandler->getConnection()->commit();

        $jobDao->destroyCacheByProjectId( $projectStructure[ 'id_project' ] );
        AnalysisDao::destroyCacheByProjectId( $projectStructure[ 'id_project' ] );

        $projectStruct = $jobStructs[ 0 ]->getProject( 60 * 10 );
        ( new ProjectDao() )->destroyCacheForProjectData( $projectStruct->id, $projectStruct->password );

    }

    /**
     * Extract sources and pre-translations from an xliff file and put them in Database
     *
     * @param int   $fid
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
    protected function _extractSegments( int $fid, array $file_info ) {

        $xliff_file_content = $this->getXliffFileContent( $file_info[ 'path_cached_xliff' ] );

        // create Structure for multiple files
        $this->projectStructure[ 'segments' ]->offsetSet( $fid, new ArrayObject( [] ) );
        $this->projectStructure[ 'segments-original-data' ]->offsetSet( $fid, new ArrayObject( [] ) );
        $this->projectStructure[ 'file-part-id' ]->offsetSet( $fid, new ArrayObject( [] ) );
        $this->projectStructure[ 'segments-meta-data' ]->offsetSet( $fid, new ArrayObject( [] ) );

        $xliffParser = new XliffParser();

        try {
            $xliff                                                  = $xliffParser->xliffToArray( $xliff_file_content );
            $xliffInfo                                              = XliffProprietaryDetect::getInfoByStringData( $xliff_file_content );
            $this->projectStructure[ 'current-xliff-info' ][ $fid ] = $xliffInfo;
        } catch ( Exception $e ) {
            throw new Exception( "Failed to parse " . $file_info[ 'original_filename' ], ( $e->getCode() != 0 ? $e->getCode() : -4 ), $e );
        }

        // Checking that parsing went well
        if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
            $this->_log( "Failed to parse " . $file_info[ 'original_filename' ] . join( "\n", $xliff[ 'parser-errors' ] ) );
            throw new Exception( "Failed to parse " . $file_info[ 'original_filename' ], -4 );
        }

        //needed to check if a file has only one segment
        //for correctness: we could have more tag files in the xliff
        $_fileCounter_Show_In_Cattool = 0;

        // Creating the Query
        foreach ( $xliff[ 'files' ] as $xliff_file ) {

            // save external-file attribute
            if ( isset( $xliff_file[ 'attr' ][ 'external-file' ] ) ) {
                $externalFile = $xliff_file[ 'attr' ][ 'external-file' ];
                $this->filesMetadataDao->insert( $this->projectStructure[ 'id_project' ], $fid, 'mtc:references', $externalFile );
            }

            // save x-jsont* datatype
            if ( isset( $xliff_file[ 'attr' ][ 'data-type' ] ) ) {
                $dataType = $xliff_file[ 'attr' ][ 'data-type' ];

                if ( strpos( $dataType, 'x-jsont' ) !== false ) {
                    $this->filesMetadataDao->insert( $this->projectStructure[ 'id_project' ], $fid, 'data-type', $dataType );
                }
            }

            if ( !array_key_exists( 'trans-units', $xliff_file ) ) {
                continue;
            }

            // files-part
            if ( isset( $xliff_file[ 'attr' ][ 'original' ] ) ) {
                $filesPartsStruct            = new FilesPartsStruct();
                $filesPartsStruct->id_file   = $fid;
                $filesPartsStruct->tag_key   = 'original';
                $filesPartsStruct->tag_value = $xliff_file[ 'attr' ][ 'original' ];

                $filePartsId = ( new FilesPartsDao() )->insert( $filesPartsStruct );

                // save `custom` meta data
                if ( isset( $xliff_file[ 'attr' ][ 'custom' ] ) and !empty( $xliff_file[ 'attr' ][ 'custom' ] ) ) {
                    $this->filesMetadataDao->bulkInsert( $this->projectStructure[ 'id_project' ], $fid, $xliff_file[ 'attr' ][ 'custom' ], $filePartsId );
                }
            }

            foreach ( $xliff_file[ 'trans-units' ] as $xliff_trans_unit ) {

                //initialize flag
                $show_in_cattool = 1;

                if ( !isset( $xliff_trans_unit[ 'attr' ][ 'translate' ] ) ) {
                    $xliff_trans_unit[ 'attr' ][ 'translate' ] = 'yes';
                }

                if ( $xliff_trans_unit[ 'attr' ][ 'translate' ] == "no" ) {
                    //No segments to translate
                    //don't increment global counter '$this->fileCounter_Show_In_Cattool'
                    // $show_in_cattool = 0;
                } else {

                    $this->_manageAlternativeTranslations( $xliff_trans_unit, $xliff_file[ 'attr' ] );

                    $trans_unit_reference = self::sanitizedUnitId( $xliff_trans_unit[ 'attr' ][ 'id' ], $fid );

                    // check if there is original data
                    $segmentOriginalData = [];
                    $dataRefMap          = [];

                    if ( isset( $xliff_trans_unit[ 'original-data' ] ) and !empty( $xliff_trans_unit[ 'original-data' ] ) ) {
                        $segmentOriginalData = $xliff_trans_unit[ 'original-data' ];
                        foreach ( $segmentOriginalData as $datum ) {
                            if ( isset( $datum[ 'attr' ][ 'id' ] ) ) {
                                $dataRefMap[ $datum[ 'attr' ][ 'id' ] ] = $datum[ 'raw-content' ];
                            }
                        }
                    }

                    // If the XLIFF is already segmented (has <seg-source>)
                    if ( isset( $xliff_trans_unit[ 'seg-source' ] ) ) {
                        foreach ( $xliff_trans_unit[ 'seg-source' ] as $position => $seg_source ) {

                            //rest flag because if the first mrk of the seg-source is not translatable the rest of
                            //mrk in the list will not be too!!!
                            $show_in_cattool = 1;

                            $wordCount = CatUtils::segment_raw_word_count( $seg_source[ 'raw-content' ], $this->projectStructure[ 'source_language' ], $this->filter );
                            $wordCount = $this->features->filter( 'wordCount', $wordCount );

                            //init tags
                            $seg_source[ 'mrk-ext-prec-tags' ] = '';
                            $seg_source[ 'mrk-ext-succ-tags' ] = '';

                            if ( empty( $wordCount ) ) {
                                $show_in_cattool = 0;
                            } else {

                                $extract_external                  = $this->_strip_external( $seg_source[ 'raw-content' ] );
                                $seg_source[ 'mrk-ext-prec-tags' ] = $extract_external[ 'prec' ];
                                $seg_source[ 'mrk-ext-succ-tags' ] = $extract_external[ 'succ' ];
                                $seg_source[ 'raw-content' ]       = $extract_external[ 'seg' ];

                                if ( isset( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ] ) ) {

                                    if ( $this->features->filter( 'populatePreTranslations', true ) ) {

                                        $stateValues = $this->getTargetStatesFromTransUnit( $xliff_trans_unit, $position );

                                        $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ] );

                                        //
                                        // -----------------------------------------------
                                        // NOTE 2020-06-16
                                        // -----------------------------------------------
                                        //
                                        // before calling html_entity_decode function we convert
                                        // all unicode entities with no corresponding HTML entity
                                        //
                                        $extract_external[ 'seg' ]        = CatUtils::restoreUnicodeEntitiesToOriginalValues( $extract_external[ 'seg' ] );
                                        $target_extract_external[ 'seg' ] = CatUtils::restoreUnicodeEntitiesToOriginalValues( $target_extract_external[ 'seg' ] );

                                        // we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                        // AND IF IT IS ONLY A CHAR? like "*" ?
                                        // we can't distinguish if it is translated or not
                                        // this means that we lose the tags id inside the target if different from source
                                        $src = CatUtils::trimAndStripFromAnHtmlEntityDecoded( $extract_external[ 'seg' ] );
                                        $trg = CatUtils::trimAndStripFromAnHtmlEntityDecoded( $target_extract_external[ 'seg' ] );

                                        if ( $this->__isTranslated(
                                                        $src,
                                                        $trg,
                                                        $fid,
                                                        $stateValues[ 'state' ],
                                                        $stateValues[ 'state-qualifier' ] ) && !empty( $trg )
                                        ) { //treat 0,1,2... as translated content!

                                            $target = $this->filter->fromRawXliffToLayer0( $target_extract_external[ 'seg' ] );

                                            //add an empty string to avoid casting to int: 0001 -> 1
                                            //useful for idiom internal xliff id
                                            if ( !$this->projectStructure[ 'translations' ]->offsetExists( $trans_unit_reference ) ) {
                                                $this->projectStructure[ 'translations' ]->offsetSet( $trans_unit_reference, new ArrayObject() );
                                            }

                                            /**
                                             * Trans-Unit
                                             * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                                             */
                                            $this->projectStructure[ 'translations' ][ $trans_unit_reference ]->offsetSet(
                                                    $seg_source[ 'mid' ],
                                                    new ArrayObject( [
                                                            2 => $target,
                                                            4 => $xliff_trans_unit,
                                                            6 => $position, // this value is the mrk positional order
                                                    ] )
                                            );

                                            //seg-source and target translation can have different mrk id
                                            //override the seg-source surrounding mrk-id with them of target
                                            $seg_source[ 'mrk-ext-prec-tags' ] = $target_extract_external[ 'prec' ];
                                            $seg_source[ 'mrk-ext-succ-tags' ] = $target_extract_external[ 'succ' ];

                                        }
                                    }
                                }
                            }

                            //
                            // -------------------------------------
                            // START SEGMENTS META
                            // -------------------------------------
                            //

                            $metadataStruct = new SegmentMetadataStruct();

                            // check if there is sizeRestriction
                            if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                                $metadataStruct->meta_key   = 'sizeRestriction';
                                $metadataStruct->meta_value = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                            }

                            $this->projectStructure[ 'segments-meta-data' ][ $fid ]->append( $metadataStruct );

                            //
                            // -------------------------------------
                            // END SEGMENTS META
                            // -------------------------------------
                            //

                            //
                            // -------------------------------------
                            // START SEGMENTS ORIGINAL DATA
                            // -------------------------------------
                            //

                            // if its empty pass create a SegmentOriginalDataStruct with no data
                            $segmentOriginalDataStruct = ( new SegmentOriginalDataStruct )->setMap( $dataRefMap ?? [] );
                            $this->projectStructure[ 'segments-original-data' ][ $fid ]->append( $segmentOriginalDataStruct );

                            //
                            // -------------------------------------
                            // END SEGMENTS ORIGINAL DATA
                            // -------------------------------------
                            //

                            $sizeRestriction = null;
                            if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                                $sizeRestriction = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                            }

                            $segmentHash = $this->createSegmentHash( $seg_source[ 'raw-content' ], $dataRefMap, $sizeRestriction );

                            // segment struct
                            $segStruct = new SegmentStruct( [
                                    'id_file'                 => $fid,
                                    'id_file_part'            => ( isset( $filePartsId ) ) ? $filePartsId : null,
                                    'id_project'              => $this->projectStructure[ 'id_project' ],
                                    'internal_id'             => $xliff_trans_unit[ 'attr' ][ 'id' ],
                                    'xliff_mrk_id'            => $seg_source[ 'mid' ],
                                    'xliff_ext_prec_tags'     => $seg_source[ 'ext-prec-tags' ],
                                    'xliff_mrk_ext_prec_tags' => $seg_source[ 'mrk-ext-prec-tags' ],
                                    'segment'                 => $this->filter->fromRawXliffToLayer0( $seg_source[ 'raw-content' ] ),
                                    'segment_hash'            => $segmentHash,
                                    'xliff_mrk_ext_succ_tags' => $seg_source[ 'mrk-ext-succ-tags' ],
                                    'xliff_ext_succ_tags'     => $seg_source[ 'ext-succ-tags' ],
                                    'raw_word_count'          => $wordCount,
                                    'show_in_cattool'         => $show_in_cattool
                            ] );

                            $this->projectStructure[ 'segments' ][ $fid ]->append( $segStruct );

                            //increment counter for word count
                            $this->files_word_count += $wordCount;

                            //increment the counter for not empty segments
                            $_fileCounter_Show_In_Cattool += $show_in_cattool;

                        } // end foreach seg-source

                        try {
                            $this->__addNotesToProjectStructure( $xliff_trans_unit, $fid );
                            $this->__addTUnitContextsToProjectStructure( $xliff_trans_unit, $fid );
                        } catch ( Exception $exception ) {
                            throw new Exception( $exception->getMessage(), -1 );
                        }

                    } else {

                        $wordCount = CatUtils::segment_raw_word_count( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $this->projectStructure[ 'source_language' ], $this->filter );

                        $prec_tags = null;
                        $succ_tags = null;
                        if ( empty( $wordCount ) ) {
                            $show_in_cattool = 0;
                        } else {
                            $extract_external                              = $this->_strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );
                            $prec_tags                                     = empty( $extract_external[ 'prec' ] ) ? null : $extract_external[ 'prec' ];
                            $succ_tags                                     = empty( $extract_external[ 'succ' ] ) ? null : $extract_external[ 'succ' ];
                            $xliff_trans_unit[ 'source' ][ 'raw-content' ] = $extract_external[ 'seg' ];

                            if ( isset( $xliff_trans_unit[ 'target' ][ 'raw-content' ] ) ) {

                                $stateValues = $this->getTargetStatesFromTransUnit( $xliff_trans_unit );

                                $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ] );

                                if ( $this->__isTranslated(
                                                $xliff_trans_unit[ 'source' ][ 'raw-content' ],
                                                $target_extract_external[ 'seg' ],
                                                $fid,
                                                $stateValues[ 'state' ],
                                                $stateValues[ 'state-qualifier' ] ) && !empty( $target_extract_external[ 'seg' ] )
                                ) {

                                    $target = $this->filter->fromRawXliffToLayer0( $target_extract_external[ 'seg' ] );

                                    //add an empty string to avoid casting to int: 0001 -> 1
                                    //useful for idiom internal xliff id
                                    if ( !$this->projectStructure[ 'translations' ]->offsetExists( $trans_unit_reference ) ) {
                                        $this->projectStructure[ 'translations' ]->offsetSet( $trans_unit_reference, new ArrayObject() );
                                    }

                                    /**
                                     * Trans-Unit
                                     * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                                     */
                                    $this->projectStructure[ 'translations' ][ $trans_unit_reference ]->append(
                                            new ArrayObject( [
                                                    2 => $target,
                                                    4 => $xliff_trans_unit,
                                            ] )
                                    );
                                }
                            }
                        }

                        try {
                            $this->__addNotesToProjectStructure( $xliff_trans_unit, $fid );
                            $this->__addTUnitContextsToProjectStructure( $xliff_trans_unit, $fid );
                        } catch ( Exception $exception ) {
                            throw new Exception(
                                    $exception->getMessage(),
                                    $exception->getCode() ?? -1
                            );
                        }

                        //
                        // -------------------------------------
                        // START SEGMENTS META
                        // -------------------------------------
                        //
                        $metadataStruct = new SegmentMetadataStruct();

                        // check if there is sizeRestriction
                        if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                            $metadataStruct->meta_key   = 'sizeRestriction';
                            $metadataStruct->meta_value = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                        }

                        $this->projectStructure[ 'segments-meta-data' ][ $fid ]->append( $metadataStruct );

                        //
                        // -------------------------------------
                        // END SEGMENTS META
                        // -------------------------------------
                        //


                        // segment original data
                        if ( !empty( $segmentOriginalData ) ) {

                            // this seems not to be used, SegmentOriginalDataStruct do not have a 'data' key.
                            $segmentOriginalDataStruct = new SegmentOriginalDataStruct( [
                                    'data' => $segmentOriginalData,
                            ] );

                            $this->projectStructure[ 'segments-original-data' ][ $fid ]->append( $segmentOriginalDataStruct );
                        }

                        $sizeRestriction = null;
                        if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                            $sizeRestriction = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                        }

                        $segmentHash = $this->createSegmentHash( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $segmentOriginalData, $sizeRestriction );

                        $segStruct = new SegmentStruct( [
                                'id_file'             => $fid,
                                'id_file_part'        => ( isset( $filePartsId ) ) ? $filePartsId : null,
                                'id_project'          => $this->projectStructure[ 'id_project' ],
                                'internal_id'         => $xliff_trans_unit[ 'attr' ][ 'id' ],
                                'xliff_ext_prec_tags' => ( !is_null( $prec_tags ) ? $prec_tags : null ),
                                'segment'             => $this->filter->fromRawXliffToLayer0( $xliff_trans_unit[ 'source' ][ 'raw-content' ] ),
                                'segment_hash'        => $segmentHash,
                                'xliff_ext_succ_tags' => ( !is_null( $succ_tags ) ? $succ_tags : null ),
                                'raw_word_count'      => $wordCount,
                                'show_in_cattool'     => $show_in_cattool
                        ] );

                        $this->projectStructure[ 'segments' ][ $fid ]->append( $segStruct );

                        //increment counter for word count
                        $this->files_word_count += $wordCount;

                        //increment the counter for not empty segments
                        $_fileCounter_Show_In_Cattool += $show_in_cattool;
                    }
                }
            }

            $this->total_segments += count( $xliff_file[ 'trans-units' ] );

        }

        //use generic
        if ( count( $this->projectStructure[ 'segments' ][ $fid ] ) == 0 || $_fileCounter_Show_In_Cattool == 0 ) {
            $this->_log( "Segment import - no segments found in {$file_info[ 'original_filename' ]}\n" );
            throw new Exception( $file_info[ 'original_filename' ], -1 );
        } else {
            //increment global counter
            $this->show_in_cattool_segs_counter += $_fileCounter_Show_In_Cattool;
        }

    }

    /**
     * -------------------------------------
     * SEGMENT HASH
     * -------------------------------------
     *
     * When there is an 'original-data' map, save segment_hash of REPLACED string
     * in order to distinguish it in UI avoiding possible collisions
     * (same text, different 'original-data' maps).
     * Example:
     *
     * $mapA = '{"source1":"%@"}';
     * $mapB = '{"source1":"%s"}';
     *
     * $segmentA = 'If you find the content to be inappropriate or offensive, we recommend contacting <ph id="source1" dataRef="source1"/>.';
     * $segmentB = 'If you find the content to be inappropriate or offensive, we recommend contacting <ph id="source1" dataRef="source1"/>.';
     *
     * The same thing happens when the segment has a char size restriction.
     *
     *
     * @param      $rawContent
     * @param null $dataRefMap
     * @param null $sizeRestriction
     *
     * @return string
     */
    private function createSegmentHash( $rawContent, $dataRefMap = null, $sizeRestriction = null ): string {

        $segmentToBeHashed = $rawContent;

        if ( !empty( $dataRefMap ) ) {
            $dataRefReplacer   = new DataRefReplacer( $dataRefMap );
            $segmentToBeHashed = $dataRefReplacer->replace( $rawContent );
        }

        if ( !empty( $sizeRestriction ) ) {
            $segmentToBeHashed .= $segmentToBeHashed . '{"sizeRestriction": ' . $sizeRestriction . '}';
        }

        return md5( $segmentToBeHashed );
    }

    /**
     * @param $xliff_file_content
     *
     * @return false|string
     * @throws Exception
     */
    private function getXliffFileContent( $xliff_file_content ) {
        if ( AbstractFilesStorage::isOnS3() ) {
            $s3Client = S3FilesStorage::getStaticS3Client();

            if ( $s3Client->hasEncoder() ) {
                $xliff_file_content = $s3Client->getEncoder()->decode( $xliff_file_content );
            }

            return $s3Client->openItem( [ 'bucket' => S3FilesStorage::getFilesStorageBucket(), 'key' => $xliff_file_content ] );
        }

        return file_get_contents( $xliff_file_content );
    }

    /**
     * @param $_originalFileNames
     * @param $sha1_original           (example: 917f7b03c8f54350fb65387bda25fbada43ff7d8)
     * @param $cachedXliffFilePathName (example: 91/7f/7b03c8f54350fb65387bda25fbada43ff7d8!!it-it/work/test_2.txt.sdlxliff)
     *
     * @return array
     * @throws Exception
     */
    protected function _insertFiles( $_originalFileNames, $sha1_original, $cachedXliffFilePathName ): array {
        $fs = FilesStorageFactory::create();

        $yearMonthPath    = date_create( $this->projectStructure[ 'create_date' ] )->format( 'Ymd' );
        $fileDateSha1Path = $yearMonthPath . DIRECTORY_SEPARATOR . $sha1_original;

        //return structure
        $fileStructures = [];

        foreach ( $_originalFileNames as $originalFileName ) {

            // avoid blank filenames
            if ( !empty( $originalFileName ) ) {

                // get metadata
                $mimeType = AbstractFilesStorage::pathinfo_fix( $originalFileName, PATHINFO_EXTENSION );
                $fid      = ProjectManagerModel::insertFile( $this->projectStructure, $originalFileName, $mimeType, $fileDateSha1Path, $meta );

                if ( $this->gdriveSession ) {
                    $gdriveFileId = $this->gdriveSession->findFileIdByName( $originalFileName );
                    if ( $gdriveFileId ) {
                        $client = GoogleProvider::getClient( AppConfig::$HTTPHOST . "/gdrive/oauth/response" );
                        $this->gdriveSession->createRemoteFile( $fid, $gdriveFileId, $client );
                    }
                }

                $moved = $fs->moveFromCacheToFileDir(
                        $fileDateSha1Path,
                        $this->projectStructure[ 'source_language' ],
                        $fid,
                        $originalFileName
                );

                // check if the files were moved
                if ( true !== $moved ) {
                    throw new Exception( 'Project creation failed. Please refresh page and retry.', -200 );
                }

                $this->projectStructure[ 'file_id_list' ]->append( $fid );

                $fileStructures[ $fid ] = [
                        'fid'               => $fid,
                        'original_filename' => $originalFileName,
                        'path_cached_xliff' => $cachedXliffFilePathName,
                        'mime_type'         => $mimeType
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
    protected function _insertInstructions( $fid, $value ) {

        $value = $this->features->filter( 'decodeInstructions', $value );

        $this->filesMetadataDao->insert( $this->projectStructure[ 'id_project' ], $fid, 'instructions', $value );
    }

    /**
     * @throws ReQueueException
     * @throws ValidationError
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws AuthenticationError
     * @throws Exception
     */
    protected function _storeSegments( $fid ) {

        if ( count( $this->projectStructure[ 'segments' ][ $fid ] ) == 0 ) {
            return;
        }

        $this->_log( "Segments: Total Rows to insert: " . count( $this->projectStructure[ 'segments' ][ $fid ] ) );
        $sequenceIds = $this->dbHandler->nextSequence( Database::SEQ_ID_SEGMENT, count( $this->projectStructure[ 'segments' ][ $fid ] ) );
        $this->_log( "Id sequence reserved." );

        //Update/Initialize the min-max sequences id
        if ( !isset( $this->min_max_segments_id[ 'job_first_segment' ] ) ) {
            $this->min_max_segments_id[ 'job_first_segment' ] = reset( $sequenceIds );
        }

        //update the last id, if there is another cycle update this value
        $this->min_max_segments_id[ 'job_last_segment' ] = end( $sequenceIds );


        $segments_metadata = [];
        foreach ( $sequenceIds as $position => $id_segment ) {

            /**
             * @var $this ->projectStructure[ 'segments' ][ $fid ][ $position ] SegmentStruct
             */
            $this->projectStructure[ 'segments' ][ $fid ][ $position ]->id = $id_segment;

            /** @var ?SegmentOriginalDataStruct $segmentOriginalDataStruct */
            $segmentOriginalDataStruct = $this->projectStructure[ 'segments-original-data' ][ $fid ][ $position ] ?? new SegmentOriginalDataStruct(); // If not set, create an empty struct to be safe. Avoid 'Call to a member function getMap() on null'

            if ( !empty( $segmentOriginalDataStruct->getMap() ) ) {

                // We add two filters here (sanitizeOriginalDataMap and correctTagErrors)
                // to allow the correct tag handling by the plugins
                $map = $this->features->filter( 'sanitizeOriginalDataMap', $segmentOriginalDataStruct->getMap() );

                // persist original data map if present
                SegmentOriginalDataDao::insertRecord( $id_segment, $map );

                $this->projectStructure[ 'segments' ][ $fid ][ $position ]->segment = $this->features->filter(
                        'correctTagErrors',
                        $this->projectStructure[ 'segments' ][ $fid ][ $position ]->segment,
                        $map
                );
            }

            /** @var  SegmentMetadataStruct $segmentMetadataStruct */
            $segmentMetadataStruct = @$this->projectStructure[ 'segments-meta-data' ][ $fid ][ $position ];

            if ( isset( $segmentMetadataStruct ) and !empty( $segmentMetadataStruct ) ) {
                $this->_saveSegmentMetadata( $id_segment, $segmentMetadataStruct );
            }

            if ( !isset( $this->projectStructure[ 'file_segments_count' ] [ $fid ] ) ) {
                $this->projectStructure[ 'file_segments_count' ] [ $fid ] = 0;
            }
            $this->projectStructure[ 'file_segments_count' ] [ $fid ]++;

            $_metadata = [
                    'id'                => $id_segment,
                    'internal_id'       => self::sanitizedUnitId( $this->projectStructure[ 'segments' ][ $fid ][ $position ]->internal_id, $fid ),
                    'segment'           => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->segment,
                    'segment_hash'      => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->segment_hash,
                    'raw_word_count'    => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->raw_word_count,
                    'xliff_mrk_id'      => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->xliff_mrk_id,
                    'show_in_cattool'   => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->show_in_cattool,
                    'additional_params' => null,
                    'file_id'           => $fid,
            ];

            /*
             *This hook allows plugins to manipulate data analysis content, should be not allowed to change existing data but only to eventually add new fields
             */
            $_metadata = $this->features->filter( 'appendFieldToAnalysisObject', $_metadata, $this->projectStructure );

            $segments_metadata[] = $_metadata;

        }

        $segmentsDao = new SegmentDao();
        //split the query in to chunks if there are too much segments
        $segmentsDao->createList( $this->projectStructure[ 'segments' ][ $fid ]->getArrayCopy() );

        //free memory
        $this->projectStructure[ 'segments' ][ $fid ]->exchangeArray( [] );

        // Here we make a query for the last inserted segments. This is the point where we
        // can read the id of the segments table to reference it in other inserts in other tables.
        //
        if ( !(
                empty( $this->projectStructure[ 'notes' ] ) &&
                empty( $this->projectStructure[ 'translations' ] )
        )
        ) {

            //internal counter for the segmented translations ( mrk in target )
            $array_internal_segmentation_counter = [];

            foreach ( $segments_metadata as $row ) {

                // The following call is to save `id_segment` for notes,
                // to be used later to insert the record in notes table.
                $this->__setSegmentIdForNotes( $row );
                $this->__setSegmentIdForContexts( $row );

                // The following block of code is for translations
                if ( $this->projectStructure[ 'translations' ]->offsetExists( $row[ 'internal_id' ] ) ) {

                    if ( !array_key_exists( $row[ 'internal_id' ], $array_internal_segmentation_counter ) ) {

                        //if we don't have segmentation, we have not mrk ID,
                        // so work with positional indexes ( should be only one row )
                        if ( empty( $row[ 'xliff_mrk_id' ] ) ) {
                            $array_internal_segmentation_counter[ $row[ 'internal_id' ] ] = 0;
                        } else {
                            //we have the mark id use them
                            $array_internal_segmentation_counter[ $row[ 'internal_id' ] ] = $row[ 'xliff_mrk_id' ];
                        }

                    } else {

                        //if we don't have segmentation, we have not mrk ID,
                        // so work with positional indexes
                        // ( should be only one row but if we are here increment it )
                        if ( empty( $row[ 'xliff_mrk_id' ] ) ) {
                            $array_internal_segmentation_counter[ $row[ 'internal_id' ] ]++;
                        } else {
                            //we have the mark id use them
                            $array_internal_segmentation_counter[ $row[ 'internal_id' ] ] = $row[ 'xliff_mrk_id' ];
                        }

                    }

                    //set this var only for easy reading
                    $short_var_counter = $array_internal_segmentation_counter[ $row[ 'internal_id' ] ];

                    if ( !$this->projectStructure[ 'translations' ][ $row[ 'internal_id' ] ]->offsetExists( $short_var_counter ) ) {
                        continue;
                    }

                    $this->projectStructure[ 'translations' ][ $row[ 'internal_id' ] ][ $short_var_counter ]->offsetSet( 0, $row[ 'id' ] );
                    $this->projectStructure[ 'translations' ][ $row[ 'internal_id' ] ][ $short_var_counter ]->offsetSet( 1, $row[ 'internal_id' ] );
                    /**
                     * WARNING offset 2 is the target translation
                     */
                    $this->projectStructure[ 'translations' ][ $row[ 'internal_id' ] ][ $short_var_counter ]->offsetSet( 3, $row[ 'segment_hash' ] );
                    /**
                     * WARNING offset 4 is the Trans-Unit
                     * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                     */
                    $this->projectStructure[ 'translations' ][ $row[ 'internal_id' ] ][ $short_var_counter ]->offsetSet( 5, $row[ 'file_id' ] );
                    /**
                     * WARNING Offset 6 is possibly the MRK order position.
                     */

                    // Remove an existent translation, we won't send these segment to the analysis because it is marked as locked
                    /*
                     * Commented because of
                     *
                     * https://app.asana.com/0/1134617950425092/1202822242420298
                     */
                    // unset( $segments_metadata[ $k ] );

                }

            }

        }

        //merge segments_metadata for every file in the project
        $this->projectStructure[ 'segments_metadata' ]->exchangeArray( array_merge( $this->projectStructure[ 'segments_metadata' ]->getArrayCopy(), $segments_metadata ) );

    }

    protected function _cleanSegmentsMetadata() {
        //More cleaning on the segments, remove show_in_cattool == false
        $this->projectStructure[ 'segments_metadata' ]->exchangeArray(
                array_filter( $this->projectStructure[ 'segments_metadata' ]->getArrayCopy(), function ( $value ) {
                    return $value[ 'show_in_cattool' ] == 1;
                } )
        );
    }

    /**
     * Save segment metadata
     *
     * @param int                        $id_segment
     * @param SegmentMetadataStruct|null $metadataStruct
     */
    protected function _saveSegmentMetadata( int $id_segment, SegmentMetadataStruct $metadataStruct = null ) {

        if ( $metadataStruct !== null and
                isset( $metadataStruct->meta_key ) and $metadataStruct->meta_key !== '' and
                isset( $metadataStruct->meta_value ) and $metadataStruct->meta_value !== ''
        ) {
            $metadataStruct->id_segment = $id_segment;
            SegmentMetadataDao::save( $metadataStruct );
        }
    }

    /**
     * @param array      $xliff_trans_unit
     *
     * @param array|null $xliff_file_attributes
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws Exception
     */
    protected function _manageAlternativeTranslations( array $xliff_trans_unit, ?array $xliff_file_attributes ) {

        //Source and target language are mandatory, moreover do not set matches on public area
        if (
                !isset( $xliff_trans_unit[ 'alt-trans' ] ) ||
                empty( $xliff_file_attributes[ 'source-language' ] ) ||
                empty( $xliff_file_attributes[ 'target-language' ] ) ||
                count( $this->projectStructure[ 'private_tm_key' ] ) == 0 ||
                $this->features->filter( 'doNotManageAlternativeTranslations', true, $xliff_trans_unit, $xliff_file_attributes )
        ) {
            return;
        }

        // set the contribution for every key in the job belonging to the user
        $engine = EnginesFactory::getInstance( 1 );
        $config = $engine->getConfigStruct();

        if ( count( $this->projectStructure[ 'private_tm_key' ] ) != 0 ) {

            foreach ( $this->projectStructure[ 'private_tm_key' ] as $tm_info ) {
                if ( $tm_info[ 'w' ] == 1 ) {
                    $config[ 'id_user' ][] = $tm_info[ 'key' ];
                }
            }

        }

        $config[ 'source' ] = $xliff_file_attributes[ 'source-language' ];
        $config[ 'target' ] = $xliff_file_attributes[ 'target-language' ];
        $config[ 'email' ]  = AppConfig::$MYMEMORY_API_KEY;

        foreach ( $xliff_trans_unit[ 'alt-trans' ] as $altTrans ) {

            if ( !empty( $altTrans[ 'attr' ][ 'match-quality' ] ) && $altTrans[ 'attr' ][ 'match-quality' ] < '50' ) {
                continue;
            }

            $source_extract_external = '';

            //Wrong alt-trans tag
            if ( ( empty( $xliff_trans_unit[ 'source' ] /* theoretically impossible empty source */ ) && empty( $altTrans[ 'source' ] ) ) || empty( $altTrans[ 'target' ] ) ) {
                continue;
            }

            if ( !empty( $xliff_trans_unit[ 'source' ] ) ) {
                $source_extract_external = $this->_strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ] ); //WIP to remove function
            }

            //Override with the alt-trans source value
            if ( !empty( $altTrans[ 'source' ] ) ) {
                $source_extract_external = $this->_strip_external( $altTrans[ 'source' ] ); //WIP to remove function
            }

            $target_extract_external = $this->_strip_external( $altTrans[ 'target' ] ); //WIP to remove function

            //wrong alt-trans content: source == target
            if ( $source_extract_external[ 'seg' ] == $target_extract_external[ 'seg' ] ) {
                continue;
            }

            $config[ 'segment' ]        = $this->filter->fromRawXliffToLayer0( $this->filter->fromLayer0ToLayer1( $source_extract_external[ 'seg' ] ) );
            $config[ 'translation' ]    = $this->filter->fromRawXliffToLayer0( $this->filter->fromLayer0ToLayer1( $target_extract_external[ 'seg' ] ) );
            $config[ 'context_after' ]  = null;
            $config[ 'context_before' ] = null;

            if ( !empty( $altTrans[ 'attr' ][ 'match-quality' ] ) ) {

                //get the Props
                $config[ 'prop' ] = json_encode( [
                        "match-quality" => $altTrans[ 'attr' ][ 'match-quality' ]
                ] );

            }

            $engine->set( $config );

        }

    }

    /**
     * @param JobStruct   $job
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    protected function _insertPreTranslations( JobStruct $job, ArrayObject $projectStructure ) {

        $jid = $job->id;
        $this->_cleanSegmentsMetadata();
        $createSecondPassReview = false;

        $query_translations_values = [];
        foreach ( $this->projectStructure[ 'translations' ] as $struct ) {

            if ( empty( $struct ) ) {
                continue;
            }

            // array of segmented translations
            foreach ( $struct as $translation_row ) {

                $position = ( isset( $translation_row[ 6 ] ) ) ? $translation_row[ 6 ] : null;
                $segment  = ( new SegmentDao() )->getById( $translation_row [ 0 ] );

                //XXX This condition is meant to debug an issue with the segment id that returns false from dao.
                // SegmentDao::getById returns false if the id is not found in the database
                // Skip the segment and lose the translation if the segment id is not found in the database
                if( !$segment ) {
                    continue;
                }

                if ( is_string( $this->projectStructure[ 'array_jobs' ][ 'payable_rates' ][ $jid ] ) ) {
                    $payable_rates = json_decode( $this->projectStructure[ 'array_jobs' ][ 'payable_rates' ][ $jid ], true );
                } else {
                    $payable_rates = $this->projectStructure[ 'array_jobs' ][ 'payable_rates' ][ $jid ];
                }

                /**
                 * @var $configModel XliffRulesModel
                 */
                $configModel = $this->projectStructure[ 'xliff_parameters' ];
                $stateValues = $this->getTargetStatesFromTransUnit( $translation_row[ 4 ], $position );

                $rule = $configModel->getMatchingRule(
                        $this->projectStructure[ 'current-xliff-info' ][ $translation_row[ 5 ] /* file_id */ ][ 'version' ],
                        $stateValues[ 'state' ],
                        $stateValues[ 'state-qualifier' ]
                );

                if ( XliffTranslationStatus::isFinalState( $stateValues[ 'state' ] ) ) {
                    $createSecondPassReview = true;
                }

                // Use QA to get target segment
                $chunk  = ChunkDao::getByJobID( $jid )[ 0 ];
                $source = $segment->segment;
                $target = $translation_row [ 2 ];

                /** @var $filter MateCatFilter filter */
                $filter = MateCatFilter::getInstance( $this->features, $chunk->source, $chunk->target, SegmentOriginalDataDao::getSegmentDataRefMap( $translation_row [ 0 ] ) );
                $source = $filter->fromLayer0ToLayer1( $source );
                $target = $filter->fromLayer0ToLayer1( $target );

                $check = new QA( $source, $target );
                $check->setFeatureSet( $this->features );
                $check->setSourceSegLang( $chunk->source );
                $check->setTargetSegLang( $chunk->target );
                $check->setIdSegment( $translation_row [ 0 ] );
                $check->performConsistencyCheck();

                if ( !$check->thereAreErrors() ) {
                    $translation = $check->getTrgNormalized();
                } else {
                    $translation = $check->getTargetSeg();
                }

                /* WARNING: do not change the order of the keys */
                $sql_values = [
                        'id_segment'             => $translation_row [ 0 ],
                        'id_job'                 => $jid,
                        'segment_hash'           => $translation_row [ 3 ],
                        'status'                 => $rule->asEditorStatus(),
                        'translation'            => $filter->fromLayer1ToLayer0( $translation ),
                        'suggestion'             => $filter->fromLayer1ToLayer0( $translation ),
                        'locked'                 => 0, // not allowed to change locked status for pre-translations
                        'match_type'             => $rule->asMatchType(),
                        'eq_word_count'          => $rule->asEquivalentWordCount( $segment->raw_word_count, $payable_rates ),
                        'serialized_errors_list' => ( $check->thereAreErrors() ) ? $check->getErrorsJSON() : '',
                        'warning'                => ( $check->thereAreErrors() ) ? 1 : 0,
                        'suggestion_match'       => null,
                        'standard_word_count'    => $rule->asStandardWordCount( $segment->raw_word_count, $payable_rates ),
                        'version_number'         => 0,
                ];

                $query_translations_values[] = $sql_values;
            }
        }

        // Executing the Query
        if ( !empty( $query_translations_values ) ) {
            ProjectManagerModel::insertPreTranslations( $query_translations_values );
        }

        // We do not create Chunk reviews since this is a task for postProjectCreate
        // Create a R2 for the job is state is 'final',
        if ( $createSecondPassReview ) {
            $projectStructure[ 'create_2_pass_review' ] = true;
        }

        //clean translations and queries
        unset( $query_translations_values );

    }

    /**
     * @param array    $trans_unit
     * @param int|null $position
     *
     * @return array
     */
    protected function getTargetStatesFromTransUnit( array $trans_unit, ?int $position = null ): array {

        // state handling
        $state          = null;
        $stateQualifier = null;

        if ( isset( $trans_unit[ 'seg-target' ][ $position ][ 'attr' ] ) and isset( $trans_unit[ 'seg-target' ][ $position ][ 'attr' ][ 'state' ] ) ) {
            $state = $trans_unit[ 'seg-target' ][ $position ][ 'attr' ][ 'state' ];
        } elseif ( isset( $trans_unit[ 'target' ][ 'attr' ] ) and isset( $trans_unit[ 'target' ][ 'attr' ][ 'state' ] ) ) {
            $state = $trans_unit[ 'target' ][ 'attr' ][ 'state' ];
        }

        if ( isset( $trans_unit[ 'seg-target' ][ $position ][ 'attr' ] ) and isset( $trans_unit[ 'seg-target' ][ $position ][ 'attr' ][ 'state-qualifier' ] ) ) {
            $stateQualifier = $trans_unit[ 'seg-target' ][ $position ][ 'attr' ][ 'state-qualifier' ];
        } elseif ( isset( $trans_unit[ 'target' ][ 'attr' ] ) and isset( $trans_unit[ 'target' ][ 'attr' ][ 'state-qualifier' ] ) ) {
            $stateQualifier = $trans_unit[ 'target' ][ 'attr' ][ 'state-qualifier' ];
        }

        return [ 'state' => $state, 'state-qualifier' => $stateQualifier ];

    }

    /**
     * @param string $segment
     *
     * @return array
     */
    protected function _strip_external( string $segment ): array {

        // Definitely DISABLED
        return [ 'prec' => null, 'seg' => $segment, 'succ' => null ];

    }

    public function generatePassword( $length = 12 ) {
        return Utils::randomString( $length );
    }

    /**
     * addNotesToProjectStructure
     *
     * Notes structure is the following:
     *
     *  ... ['notes'][ $internal_id ] = array(
     *      'entries' => array( // one item per comment in the trans unit ),
     *      'id_segment' => (int) to be populated later for the database insert
     *
     * @param $trans_unit
     * @param $fid
     *
     * @throws Exception
     */
    private function __addNotesToProjectStructure( $trans_unit, $fid ) {

        $internal_id = self::sanitizedUnitId( $trans_unit[ 'attr' ][ 'id' ], $fid );
        if ( isset( $trans_unit[ 'notes' ] ) ) {

            if ( count( $trans_unit[ 'notes' ] ) > self::SEGMENT_NOTES_LIMIT ) {
                throw new Exception( 'File upload failed: a segment can have a maximum of ' . self::SEGMENT_NOTES_LIMIT . ' notes.', -44 );
            }

            foreach ( $trans_unit[ 'notes' ] as $note ) {
                $this->initArrayObject( 'notes', $internal_id );

                $noteKey     = null;
                $noteContent = null;

                if ( isset( $note[ 'json' ] ) ) {
                    $noteContent = $note[ 'json' ];
                    $noteKey     = 'json';
                } elseif ( isset( $note[ 'raw-content' ] ) ) {
                    $noteContent = $note[ 'raw-content' ];
                    $noteKey     = 'entries';
                }

                if ( strlen( $noteContent ) > self::SEGMENT_NOTES_MAX_SIZE ) {
                    throw new Exception( ' you reached the maximum size for a single segment note (' . self::SEGMENT_NOTES_MAX_SIZE . ' bytes)' );
                }

                if ( !$this->projectStructure[ 'notes' ][ $internal_id ]->offsetExists( 'entries' ) ) {
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'from', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ][ 'from' ]->offsetSet( 'entries', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ][ 'from' ]->offsetSet( 'json', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'entries', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'json', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'json_segment_ids', [] );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'segment_ids', [] );
                }

                $this->projectStructure[ 'notes' ][ $internal_id ][ $noteKey ]->append( $noteContent );

                // import segments metadata from the `from` attribute
                if ( isset( $note[ 'from' ] ) ) {
                    $this->projectStructure[ 'notes' ][ $internal_id ][ 'from' ][ $noteKey ]->append( $note[ 'from' ] );
                } else {
                    $this->projectStructure[ 'notes' ][ $internal_id ][ 'from' ][ $noteKey ]->append( 'NO_FROM' );
                }

            }

        }

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
    private function __setSegmentIdForNotes( $row ) {

        $internal_id = $row[ 'internal_id' ];

        if ( $this->projectStructure[ 'notes' ]->offsetExists( $internal_id ) ) {

            if ( count( $this->projectStructure[ 'notes' ][ $internal_id ][ 'json' ] ) != 0 ) {
                $this->projectStructure[ 'notes' ][ $internal_id ][ 'json_segment_ids' ][] = $row[ 'id' ];
            } else {
                $this->projectStructure[ 'notes' ][ $internal_id ][ 'segment_ids' ][] = $row[ 'id' ];
            }

        }

    }

    /**
     * @throws Exception
     */
    private function insertSegmentNotesForFile() {

        $this->projectStructure = $this->features->filter( 'handleJsonNotesBeforeInsert', $this->projectStructure );
        ProjectManagerModel::bulkInsertSegmentNotes( $this->projectStructure[ 'notes' ] );
        ProjectManagerModel::bulkInsertSegmentMetaDataFromAttributes( $this->projectStructure[ 'notes' ] );
    }

    /**
     * addNotesToProjectStructure
     *
     * ContextGroup structure is the following:
     *
     *  ... ['context-group']
     *        [ $internal_id ] = array(
     *          'context_json' => [], //context-group-xml-structure,
     *          'context_json_segment_ids' => [ ] //a list to be populated later for the database insert
     *        )
     *
     * @param $trans_unit
     * @param $fid
     */
    private function __addTUnitContextsToProjectStructure( $trans_unit, $fid ) {

        $internal_id = self::sanitizedUnitId( $trans_unit[ 'attr' ][ 'id' ], $fid );
        if ( isset( $trans_unit[ 'context-group' ] ) ) {

            $this->initArrayObject( 'context-group', $internal_id );

            if ( !$this->projectStructure[ 'context-group' ][ $internal_id ]->offsetExists( 'context_json' ) ) {
                $this->projectStructure[ 'context-group' ][ $internal_id ]->offsetSet( 'context_json', $trans_unit[ 'context-group' ] );
                $this->projectStructure[ 'context-group' ][ $internal_id ]->offsetSet( 'context_json_segment_ids', [] ); // because of mrk tags, same context can be owned by different segments
            }

        }

    }

    private function __setSegmentIdForContexts( $row ) {

        $internal_id = $row[ 'internal_id' ];

        if ( $this->projectStructure[ 'context-group' ]->offsetExists( $internal_id ) ) {
            $this->projectStructure[ 'context-group' ][ $internal_id ][ 'context_json_segment_ids' ][] = $row[ 'id' ];
        }

    }

    /**
     *
     * @throws Exception
     */
    private function insertContextsForFile() {
        $this->features->filter( 'handleTUContextGroups', $this->projectStructure );
        ProjectManagerModel::bulkInsertContextsGroups( $this->projectStructure );
    }

    private function initArrayObject( $key, $id ) {
        if ( !$this->projectStructure[ $key ]->offsetExists( $id ) ) {
            $this->projectStructure[ $key ]->offsetSet( $id, new ArrayObject() );
        }
    }

    private static function sanitizedUnitId( $trans_unitID, $fid ): string {
        return $fid . "|" . $trans_unitID;
    }

    /**
     *
     * What this function does:
     *
     * 1. validate the input private keys
     * 2. set the primary key into the engine object
     * 3. check if the user is logged and if so add the new keys to his keyring
     * 4. ensure tm_user and tm_pass are populated even if missing
     * 5. insert translator
     * 6. run a callback to plugins to filter the private_tm_key value
     *
     * @param $firstTMXFileName
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private function setPrivateTMKeys( $firstTMXFileName ) {

        foreach ( $this->projectStructure[ 'private_tm_key' ] as $_tmKey ) {

            try {

                $keyExists = $this->tmxServiceWrapper->checkCorrectKey( $_tmKey[ 'key' ] );

                if ( !isset( $keyExists ) || $keyExists === false ) {
                    $this->_log( __METHOD__ . " -> TM key is not valid." );

                    throw new Exception( "TM key is not valid: " . $_tmKey[ 'key' ], -4 );
                }

            } catch ( Exception $e ) {

                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code" => $e->getCode(), "message" => $e->getMessage()
                ];

                return;
            }

        }


        //check if the Match keys provided by the user are already associated to him.


        $mkDao = new MemoryKeyDao( $this->dbHandler );

        $searchMemoryKey      = new MemoryKeyStruct();
        $searchMemoryKey->uid = $this->projectStructure[ 'uid' ];

        $userMemoryKeys = $mkDao->read( $searchMemoryKey );

        $userTmKeys             = [];
        $memoryKeysToBeInserted = [];

        //extract user tm keys
        foreach ( $userMemoryKeys as $_memoKey ) {
            $userTmKeys[] = $_memoKey->tm_key->key;
        }


        foreach ( $this->projectStructure[ 'private_tm_key' ] as $_tmKey ) {

            if ( !in_array( $_tmKey[ 'key' ], $userTmKeys ) ) {
                $newMemoryKey   = new MemoryKeyStruct();
                $newTmKey       = new TmKeyStruct();
                $newTmKey->key  = $_tmKey[ 'key' ];
                $newTmKey->tm   = true;
                $newTmKey->glos = true;

                // THIS IS A NEW KEY and must be inserted into the user keyring
                // So, if a TMX file is present in the list of uploaded files, and the Key name provided is empty
                // assign TMX name to the key

                // NOTE 2025-05-08: Replace {{pid}} with project ID for new keys created with empty name
                $newTmKey->name = ( !empty( $_tmKey[ 'name' ] ) ? str_replace( "{{pid}}", $this->projectStructure[ 'id_project' ], $_tmKey[ 'name' ] ) : $firstTMXFileName );

                $newMemoryKey->tm_key = $newTmKey;
                $newMemoryKey->uid    = $this->projectStructure[ 'uid' ];

                $memoryKeysToBeInserted[] = $newMemoryKey;
            } else {
                $this->_log( 'skip insertion' );
            }

        }
        try {
            $mkDao->createList( $memoryKeysToBeInserted );

            $featuresSet = new FeatureSet();
            $featuresSet->run( 'postTMKeyCreation', $memoryKeysToBeInserted, $this->projectStructure[ 'uid' ] );

        } catch ( Exception $e ) {
            $this->_log( $e->getMessage(), $e );
        }

    }

    /**
     * Decide if the pair of source and target should be considered translated.
     * This function returns true or false based on user-defined rules, or XLIFF states as the default behavior.
     * This function is used to filter out segments from the analysis queue and to allow working directly on their states and payable rates.
     *
     * @param string|null $source
     * @param string|null $target
     * @param int|null    $file_id
     * @param string|null $state
     * @param string|null $stateQualifier
     *
     * @return bool
     * @throws Exception
     */
    private function __isTranslated( string $source = null, string $target = null, int $file_id = null, string $state = null, string $stateQualifier = null ): bool {

        /**
         * @var $configModel XliffRulesModel
         */
        $configModel = $this->projectStructure[ 'xliff_parameters' ];
        $rule        = $configModel->getMatchingRule(
                $this->projectStructure[ 'current-xliff-info' ][ $file_id ][ 'version' ],
                $state,
                $stateQualifier
        );

        return $rule->isTranslated( $source, $target );
    }
}