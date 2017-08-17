<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 22/10/13
 * Time: 17.25
 *
 */

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Analysis\DqfQueueHandler;
use ConnectedServices\GDrive as GDrive  ;
use Teams\TeamStruct;
use Translators\TranslatorsModel;

include_once INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";

class ProjectManager {

    /**
     * Counter fro the total number of segments in the project with the flag ( show_in_cattool == true )
     *
     * @var int
     */
    protected $show_in_cattool_segs_counter = 0;
    protected $files_word_count = 0;
    protected $total_segments = 0;
    protected $min_max_segments_id = [];

    /**
     * @var ArrayObject|RecursiveArrayObject
     */
    protected $projectStructure;

    protected $tmxServiceWrapper;

    /**
     * @var FilesStorage
     */
    protected $fileStorage;

    protected $uploadDir;

    protected $checkTMX;

    protected $checkGlossary;

    /*
       flag used to indicate TMX check status: 
       0-not to check, or check passed
       1-still checking, but no useful TM for this project have been found, so far (no one matches this project langpair)
     */

    protected $langService;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected $gdriveSession ;

    const TRANSLATED_USER = 'translated_user';

    /**
     * @var Users_UserStruct ;
     */
    protected $user ;

    public function __construct( ArrayObject $projectStructure = null ) {


        if ( $projectStructure == null ) {
            $projectStructure = new RecursiveArrayObject(
                    [
                            'HTTP_HOST'            => null,
                            'id_project'           => null,
                            'create_date'          => date( "Y-m-d H:i:s" ),
                            'id_customer'          => self::TRANSLATED_USER,
                            'project_features'     => [],
                            'user_ip'              => null,
                            'project_name'         => null,
                            'result'               => [ "errors" => [], "data" => [] ],
                            'private_tm_key'       => 0,
                            'private_tm_user'      => null,
                            'private_tm_pass'      => null,
                            'uploadToken'          => null,
                            'array_files'          => [], //list of file names
                            'file_id_list'         => [],
                            'source_language'      => null,
                            'target_language'      => null,
                            'job_subject'          => 'general',
                            'mt_engine'            => null,
                            'tms_engine'           => null,
                            'ppassword'            => null,
                            'array_jobs'           => [
                                    'job_list'      => [],
                                    'job_pass'      => [],
                                    'job_segments'  => [],
                                    'job_languages' => [],
                                    'payable_rates' => [],
                            ],
                            'job_segments'         => [], //array of job_id => [  min_seg, max_seg  ]
                            'segments'             => [], //array of files_id => segments[  ]
                            'segments_metadata'    => [], //array of segments_metadata
                            'translations'         => [],
                            'notes'                => [],
                        //one translation for every file because translations are files related
                            'query_translations'   => [],
                            'status'               => Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
                            'job_to_split'         => null,
                            'job_to_split_pass'    => null,
                            'split_result'         => null,
                            'job_to_merge'         => null,
                            'lang_detect_files'    => [],
                            'tm_keys'              => [],
                            'userIsLogged'         => false,
                            'uid'                  => null,
                            'skip_lang_validation' => false,
                            'pretranslate_100'     => 0,
                            'dqf_key'              => null,
                            'owner'                => '',
                            'word_count_type'      => '',
                            'metadata'             => [],
                            'id_assignee'          => null,
                            'session'              => ( isset( $_SESSION ) ? $_SESSION : false ),
                            'instance_id'          => ( !is_null( INIT::$INSTANCE_ID ) ? (int)INIT::$INSTANCE_ID : 0 ),
                            'id_team'              => null,
                            'team'                 => null,
                            'sanitize_project_options' => true
                    ] );

        }

        $this->projectStructure = $projectStructure;

        //get the TMX management component from the factory
        $this->tmxServiceWrapper = new TMSService();

        $this->langService = Langs_Languages::getInstance();

        $this->checkTMX = 0;

        $this->dbHandler = Database::obtain();

        $features = [];
        if( !empty( $this->projectStructure[ 'project_features' ] ) ){
            foreach( $this->projectStructure[ 'project_features' ] as $key => $feature ){
                /**
                 * @var $feature RecursiveArrayObject
                 */
                $this->projectStructure[ 'project_features' ][ $key ] = new BasicFeatureStruct( $feature->getArrayCopy() );
            }
            $features = $this->projectStructure[ 'project_features' ]->getArrayCopy();
        }

        $this->features = new FeatureSet( $features );

        if ( !empty( $this->projectStructure['id_customer']) ) {
           $this->features->loadFromUserEmail( $this->projectStructure['id_customer'] );
        }

        $this->projectStructure['array_files'] = $this->features->filter(
                'filter_project_manager_array_files',
                $this->projectStructure['array_files'],
                $this->projectStructure
        );

    }

    /**
     * Project name is required to build the analyize URL. Project name is memoized in a instance variable
     * so to perform the check only the first time on $projectStructure['project_name'].
     *
     * @return bool|mixed
     */
    protected function _sanitizeProjectName() {
        $newName = self::_sanitizeName( $this->projectStructure[ 'project_name' ] );

        if ( !$newName ) {
            $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                    "code"    => -5,
                    "message" => "Invalid Project Name " . $this->projectStructure['project_name'] . ": it should only contain numbers and letters!"
            );
        }

        $this->projectStructure['project_name'] = $newName ;
    }

    /**
     * @param \Teams\TeamStruct $team
     */
    public function setTeam( TeamStruct $team ) {
        $this->projectStructure['team'] = $team ;
        $this->projectStructure['id_team'] = $team->id ;
    }

    /**
     * @param $id
     *
     * @throws Exceptions_RecordNotFound
     */
    public function setProjectIdAndLoadProject( $id ) {
        $this->project = Projects_ProjectDao::findById($id, 60 * 60);
        if ( $this->project == FALSE ) {
            throw new Exceptions_RecordNotFound("Project was not found: id $id ");
        }
        $this->projectStructure['id_project'] = $this->project->id ;
        $this->projectStructure['id_customer'] = $this->project->id_customer ;

        $this->reloadFeatures();

    }

    public function setProjectAndReLoadFeatures( Projects_ProjectStruct $pStruct ){
        $this->project = $pStruct;
        $this->projectStructure['id_project'] = $this->project->id ;
        $this->projectStructure['id_customer'] = $this->project->id_customer ;
        $this->reloadFeatures();
    }

    private function reloadFeatures() {
        $this->features = new FeatureSet();
        $this->features->loadForProject( $this->project ) ;
    }

    public function getProjectStructure() {
         return $this->projectStructure;
    }


    /**
     *  saveMetadata
     *
     * This is where, among other things, we put project options.
     * 
     * Project options may need to be sanitized so that we can silently ignore impossible combinations, 
     * and we can apply defaults when those are missing. 
     * 
     */
    private function saveMetadata() {
        $dao = new Projects_MetadataDao();
        $dao->set( $this->projectStructure['id_project'], Projects_MetadataDao::FEATURES_KEY,  implode(',', $this->features->getCodes() ) ) ;

        $options = $this->projectStructure['metadata'];
        
        if ( $this->projectStructure[ 'sanitize_project_options' ] ) {
            $options = $this->sanitizeProjectOptions( $options ) ; 
        }

        if ( empty( $options ) ) {
            return ;
        }

        foreach( $options as $key => $value ) {
            $dao->set(
                    $this->projectStructure['id_project'],
                    $key,
                    $value
            );
        }
    }

    private function sanitizeProjectOptions( $options ) {
        $sanitizer = new ProjectOptionsSanitizer( $options );
        
        $sanitizer->setLanguages(
                $this->projectStructure['source_language'],
                $this->projectStructure['target_language']
        );
        
        return $sanitizer->sanitize(); 
    }

    /**
     * Perform sanitization of the projectStructure and assign errors.
     * Resets the errors array to avoid subsequent calls to pile up errors.
     *
     */
    public function sanitizeProjectStructure() {
        $this->projectStructure[ 'result' ][ 'errors' ] = new ArrayObject() ;

        $this->_sanitizeProjectName();
    }
        
    /**
     * Creates record in projects tabele and instantiates the project struct
     * internally.
     *
     */
    private function __createProjectRecord() {
        $this->project = insertProject( $this->projectStructure );
    }

    private function __checkForProjectAssignment(){

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
            $teamDao = new \Teams\TeamDao();
            $teamDao->destroyCacheAssignee( $this->projectStructure[ 'team' ] );

        }

    }

    public function createProject() {
        $this->sanitizeProjectStructure();

        if ( !empty( $this->projectStructure[ 'session' ][ 'uid' ] ) ) {
            $this->gdriveSession = GDrive\Session::getInstanceForCLI( $this->projectStructure[ 'session' ] ) ;
        }

        $this->__checkForProjectAssignment();

        /**
         * This is the last chance to perform the validation before the project is created
         * in the database.
         * Validations should populate the projectStructure with errors and codes.
         */
        $this->features->run('validateProjectCreation', $this->projectStructure);

        /**
         * @var ArrayObject $this->projectStructure['result']['errors']
         */
        if ( $this->projectStructure['result']['errors']->count() ) {
            return false;
        }

        $this->__createProjectRecord();
        $this->saveMetadata();

        //sort files in order to process TMX first
        $sortedFiles = array();
        $firstTMXFileName = "";
        foreach ( $this->projectStructure[ 'array_files' ] as $fileName ) {

            //check for glossary files and tmx and put them in front of the list
            $infoFile = DetectProprietaryXliff::getInfo( $fileName );
            if ( DetectProprietaryXliff::getMemoryFileType() ) {

                //found TMX, enable language checking routines
                if ( DetectProprietaryXliff::isTMXFile() ) {

                    //export the name of the first TMX Files for latter use
                    $firstTMXFileName = ( empty( $firstTMXFileName ) ? $firstTMXFileName = $fileName : null );
                    $this->checkTMX = 1;
                }

                //not used at moment but needed if we want to do a poll for status
                if ( DetectProprietaryXliff::isGlossaryFile() ) {
                    $this->checkGlossary = 1;
                }

                //prepend in front of the list
                array_unshift( $sortedFiles, $fileName );
            } else {

                //append at the end of the list
                array_push( $sortedFiles, $fileName );
            }

        }
        $this->projectStructure[ 'array_files' ] = $sortedFiles;
        unset( $sortedFiles );

        if ( count( $this->projectStructure[ 'private_tm_key' ] ) ) {
            $this->setPrivateTMKeys( $firstTMXFileName );

            if ( count( $this->projectStructure[ 'result' ][ 'errors' ] ) > 0 ) {
                // This return value was introduced after a refactoring
                return;
            }
        }

        $uploadDir = $this->uploadDir = INIT::$QUEUE_PROJECT_REPOSITORY. DIRECTORY_SEPARATOR . $this->projectStructure[ 'uploadToken' ];

        //we are going to access the storage, get model object to manipulate it
        $this->fileStorage = new FilesStorage();
        $linkFiles         = $this->fileStorage->getHashesFromDir( $this->uploadDir );

        /*
            loop through all input files to
            1) upload TMX and Glossaries
        */
        try {
            $this->_pushTMXToMyMemory();
        } catch ( Exception $e ) {
            Log::doLog( $e->getMessage() );

            //exit project creation
            return false;
        }
        //TMX Management

        /*
            loop through all input files to
            2)convert, in case, non standard XLIFF files to a format that Matecat understands

            Note that XLIFF that don't need conversion are moved anyway as they are to cache in order not to alter the workflow
         */
        foreach ( $this->projectStructure[ 'array_files' ] as $fileName ) {

            /*
               Conversion Enforce
               Checking Extension is no more sufficient, we want check content
               $enforcedConversion = true; //( if conversion is enabled )
             */
            $isAFileToConvert = $this->isConversionToEnforce( $fileName );

            //if it's one of the listed formats or conversion is not enabled in first place
            if ( !$isAFileToConvert ) {
                /*
                   filename is already an xliff and it's in upload directory
                   we have to make a cache package from it to avoid altering the original path
                 */
                //get file
                $filePathName = "$this->uploadDir/$fileName";

                //calculate hash + add the fileName, if i load 3 equal files with the same content
                // they will be squashed to the last one
                $sha1 = sha1_file( $filePathName );

                //make a cache package (with work/ only, emtpy orig/)
                $this->fileStorage->makeCachePackage( $sha1, $this->projectStructure[ 'source_language' ], false, $filePathName );

                //put reference to cache in upload dir to link cache to session
                $this->fileStorage->linkSessionToCacheForAlreadyConvertedFiles(
                        $sha1,
                        $this->projectStructure[ 'source_language' ],
                        $this->projectStructure[ 'uploadToken' ],
                        $fileName
                );

                //add newly created link to list
                $linkFiles[ 'conversionHashes' ][ 'sha' ][] = $sha1 . "|" . $this->projectStructure[ 'source_language' ];
                $linkFiles[ 'conversionHashes' ][ 'fileName' ][ $sha1 . "|" . $this->projectStructure[ 'source_language' ] ][] = $fileName;

                //when the same sdlxliff is uploaded more than once with different names
                $linkFiles[ 'conversionHashes' ][ 'sha' ] = array_unique( $linkFiles[ 'conversionHashes' ][ 'sha' ] );
                unset( $sha1 );
            }
        }


        try{
            $this->_zipFileHandling( $linkFiles );
        } catch ( Exception $e ){
            Log::doLog( $e );
            //Zip file Handling
            $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                    "code" => $e->getCode(), "message" => $e->getMessage()
            );
        }

        //now, upload dir contains only hash-links
        //we start copying files to "file" dir, inserting metadata in db and extracting segments
        $totalFilesStructure = [];
        foreach ( $linkFiles[ 'conversionHashes' ][ 'sha' ] as $linkFile ) {
            //converted file is inside cache directory
            //get hash from file name inside UUID dir
            $hashFile = FilesStorage::basename_fix( $linkFile );
            $hashFile = explode( '|', $hashFile );

            //use hash and lang to fetch file from package
            $cachedXliffFilePathName = $this->fileStorage->getXliffFromCache( $hashFile[ 0 ], $hashFile[ 1 ] );

            //get sha
            $sha1_original = $hashFile[ 0 ];

            //associate the hash to the right file in upload directory
            //get original file name, to insert into DB and cp in storage
            //PLEASE NOTE, this can be an array when the same file added more
            // than once and with different names
            $_originalFileNames = $linkFiles[ 'conversionHashes' ][ 'fileName' ][ $linkFile ];

            unset( $hashFile );

            try {

                if ( !file_exists( $cachedXliffFilePathName ) ) {
                    throw new Exception( "File not found on server after upload.", -6 );
                }

                $info = FilesStorage::pathinfo_fix( $cachedXliffFilePathName );

                if ( !in_array( $info[ 'extension' ], array( 'xliff', 'sdlxliff', 'xlf' ) ) ) {
                    throw new Exception( "Failed to find converted Xliff", -3 );
                }

                $filesStructure = $this->_insertFiles( $_originalFileNames, $sha1_original, $cachedXliffFilePathName );

                //check if the files language equals the source language. If not, set an error message.
                if ( !$this->projectStructure[ 'skip_lang_validation' ] ) {
                    $this->validateFilesLanguages();
                }

            } catch ( Exception $e ) {

                if ( $e->getCode() == -10 ) {

                    //Failed to store the original Zip
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => -10, "message" => $e->getMessage()
                    );

                } elseif ( $e->getCode() == -11 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => -7, "message" => "Failed to store reference files on disk. Permission denied"
                    );
                } elseif ( $e->getCode() == -12 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => -7, "message" => "Failed to store reference files in database"
                    );
                }
                // SEVERE EXCEPTIONS HERE
                elseif ( $e->getCode() == -6 ) {
                    //"File not found on server after upload."
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code"    => -6,
                            "message" => $e->getMessage()
                    );
                } elseif ( $e->getCode() == -3 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code"    => -7,
                            "message" => "File not found. Failed to save XLIFF conversion on disk."
                    );
                } elseif ( $e->getCode() == -13 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => -13, "message" => $e->getMessage()
                    );
                    //we can not write to disk!! Break project creation
                }

                $this->__clearFailedProject( $e );

                //EXIT
                return false;

            }

            //Try to extract segments after all checks for the CURRENT file ( we are iterating through files )
            try{

                foreach( $filesStructure as $fid => $file_info ){
                    $this->_extractSegments( $fid, $file_info );
                    if ( $this->total_segments > 100000 || ( $this->total_segments * count( $this->projectStructure[ 'target_language' ] ) ) > 420000 ) {
                        //Allow projects with only one target language and 100000 segments ( ~ 550.000 words )
                        //OR
                        //A multi language project with max 420000 segments ( EX: 42000 segments in 10 languages ~ 2.700.000 words )
                        throw new Exception( "MateCat is unable to create your project. We can do it for you. Please contact " . INIT::$SUPPORT_MAIL , 128 );
                    }
                }

            } catch( Exception $e ){

                if ( $e->getCode() == -1 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => -1, "message" => "No text to translate in the file {$e->getMessage()}."
                    );
                    $this->fileStorage->deleteHashFromUploadDir( $this->uploadDir, $linkFile );
                } elseif ( $e->getCode() == -4 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code"    => -7,
                            "message" => "Internal Error. Xliff Import: Error parsing. ( {$e->getMessage()} )"
                    );
                } elseif ( $e->getCode() == 400 ) {
                    //invalid Trans-unit value found empty ID
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => $e->getCode(), "message" => $e->getPrevious()->getMessage() . " in {$e->getMessage()}"
                    );
                } else {

                    //Generic error
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => $e->getCode(), "message" => $e->getMessage()
                    );

                }

                //EXIT
                return false;
            }

            //array append like array_merge but it do not renumber the numeric keys, so we can preserve the files id
            $totalFilesStructure += $filesStructure;

        } //end of conversion hash-link loop

        //Throws exception
        try {

            foreach ( $totalFilesStructure as $fid => $file_info ) {
                $this->_storeSegments( $fid );
            }

            $this->_createJobs( $this->projectStructure );
            $this->writeFastAnalysisData();

        } catch ( Exception $e ) {

            $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                    "code" => -9, "message" => "Failed to create Job. ( {$e->getMessage()} )"
            );

            //EXIT
            return false;
        }

        try {

            Utils::deleteDir( $this->uploadDir );
            if ( is_dir( $this->uploadDir . '_converted' ) ) {
                Utils::deleteDir( $this->uploadDir . '_converted' );
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

            Log::doLog( $output );

            Utils::sendErrMailReport( $output, $e->getMessage() );

        }

        $this->projectStructure[ 'status' ] = ( INIT::$VOLUME_ANALYSIS_ENABLED ) ? Constants_ProjectStatus::STATUS_NEW : Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE;

        $isEmptyProject = false;
        //FIXME for project with only pre-translations this condition is not enough, because the translated segments are marked as not to be shown in cattool
        //we need to compare the number of segments with the number of translations
        if ( $this->show_in_cattool_segs_counter == 0 ) {
            Log::doLog( "Segment Search: No segments in this project - \n" );
            $isEmptyProject = true;
        }
        if ( $isEmptyProject ) {
            $this->projectStructure[ 'status' ] = Constants_ProjectStatus::STATUS_EMPTY;
        }

        // TODO: this remapping is for presentation purpose and should be removed from here.
        $this->projectStructure[ 'result' ][ 'code' ]            = 1;
        $this->projectStructure[ 'result' ][ 'data' ]            = "OK";
        $this->projectStructure[ 'result' ][ 'ppassword' ]       = $this->projectStructure[ 'ppassword' ];
        $this->projectStructure[ 'result' ][ 'password' ]        = $this->projectStructure[ 'array_jobs' ][ 'job_pass' ];
        $this->projectStructure[ 'result' ][ 'id_job' ]          = $this->projectStructure[ 'array_jobs' ][ 'job_list' ];
        $this->projectStructure[ 'result' ][ 'job_segments' ]    = $this->projectStructure[ 'array_jobs' ][ 'job_segments' ];
        $this->projectStructure[ 'result' ][ 'id_project' ]      = $this->projectStructure[ 'id_project' ];
        $this->projectStructure[ 'result' ][ 'project_name' ]    = $this->projectStructure[ 'project_name'] ;
        $this->projectStructure[ 'result' ][ 'source_language' ] = $this->projectStructure[ 'source_language' ];
        $this->projectStructure[ 'result' ][ 'target_language' ] = $this->projectStructure[ 'target_language' ];
        $this->projectStructure[ 'result' ][ 'status' ]          = $this->projectStructure[ 'status' ];
        $this->projectStructure[ 'result' ][ 'lang_detect' ]     = $this->projectStructure[ 'lang_detect_files' ];

        if ( INIT::$VOLUME_ANALYSIS_ENABLED ) {
            $this->projectStructure[ 'result' ][ 'analyze_url' ] = $this->getAnalyzeURL() ;
        }

        $update_project_count = "
            UPDATE projects
              SET status_analysis = '%s', standard_analysis_wc = %u
            WHERE id = %u
        ";

        $update_project_count = sprintf(
                $update_project_count,
                $this->projectStructure[ 'status' ],
                $this->files_word_count * count( $this->projectStructure[ 'array_jobs' ][ 'job_languages' ] ),  //estimation of total segments in the project
                $this->projectStructure[ 'id_project' ]
        );

        $this->dbHandler->query( $update_project_count );

        $this->pushActivityLog();

        //create Project into DQF queue
        if ( INIT::$DQF_ENABLED && !empty( $this->projectStructure[ 'dqf_key' ] ) ) {

            $dqfProjectStruct                  = DQF_DqfProjectStruct::getStruct();
            $dqfProjectStruct->api_key         = $this->projectStructure[ 'dqf_key' ];
            $dqfProjectStruct->project_id      = $this->projectStructure[ 'id_project' ];
            $dqfProjectStruct->name            = $this->projectStructure[ 'project_name' ];
            $dqfProjectStruct->source_language = $this->projectStructure[ 'source_language' ];

            $dqfQueue = new DqfQueueHandler();

            try {

                $projectManagerInfo = $dqfQueue->checkProjectManagerKey( $this->projectStructure[ 'dqf_key' ] );

                $dqfQueue->createProject( $dqfProjectStruct );

                //for each job, push a task into AMQ's DQF queue
                foreach ( $this->projectStructure[ 'array_jobs' ][ 'job_list' ] as $i => $jobID ) {
                    /**
                     * @var $dqfTaskStruct DQF_DqfTaskStruct
                     */
                    $dqfTaskStruct                  = DQF_DqfTaskStruct::getStruct();
                    $dqfTaskStruct->api_key         = $this->projectStructure[ 'dqf_key' ];
                    $dqfTaskStruct->project_id      = $this->projectStructure[ 'id_project' ];
                    $dqfTaskStruct->task_id         = $jobID;
                    $dqfTaskStruct->target_language = $this->projectStructure[ 'target_language' ][ $i ];
                    $dqfTaskStruct->file_name       = uniqid( '', true ) . $this->projectStructure[ 'project_name' ];

                    $dqfQueue->createTask( $dqfTaskStruct );

                }
            } catch ( Exception $exn ) {
                $output = __METHOD__ . " (code " . $exn->getCode() . " ) - " . $exn->getMessage();
                Log::doLog( $output );

                Utils::sendErrMailReport( $output, $exn->getMessage() );
            }
        }
        
        Database::obtain()->begin();
        $this->features->run('postProjectCreate',
            $this->projectStructure
        );
        Database::obtain()->commit();

    }

    private function __clearFailedProject( Exception $e ){
        Log::doLog( $e->getMessage() );
        Log::doLog( $e->getTraceAsString() );
        Log::doLog( "Deleting Records." );
        ( new Projects_ProjectDao() )->deleteFailedProject( $this->projectStructure[ 'id_project' ] );
        ( new Files_FileDao() )->deleteFailedProjectFiles( $this->projectStructure[ 'file_id_list' ]->getArrayCopy() );
        Log::doLog( "Deleted Project ID: " . $this->projectStructure[ 'id_project' ] );
        Log::doLog( "Deleted Files ID: " . json_encode( $this->projectStructure[ 'file_id_list' ]->getArrayCopy() ) );
    }

    private function writeFastAnalysisData(){

        $job_id_passes = ltrim(
                array_reduce(
                        array_keys( $this->projectStructure[ 'array_jobs' ][ 'job_segments' ]->getArrayCopy() ),
                            function ( $acc, $value ) {
                                $acc .= "," . strtr( $value, '-', ':' );
                                return $acc;
                            }
                ), "," );

        foreach ( $this->projectStructure[ 'segments_metadata' ] as &$segmentList ) {

            unset( $segmentList[ 'internal_id' ] );
            unset( $segmentList[ 'xliff_mrk_id' ] );
            unset( $segmentList[ 'show_in_cattool' ] );

            $segmentList[ 'jsid' ]          = $segmentList[ 'id' ] . "-" . $job_id_passes;
            $segmentList[ 'source' ]        = $this->projectStructure[ 'source_language' ];
            $segmentList[ 'target' ]        = implode( ",", $this->projectStructure[ 'array_jobs' ][ 'job_languages' ]->getArrayCopy() );
            $segmentList[ 'payable_rates' ] = $this->projectStructure[ 'array_jobs' ][ 'payable_rates' ]->getArrayCopy();

        }

        FilesStorage::storeFastAnalysisFile( $this->project->id, $this->projectStructure[ 'segments_metadata' ]->getArrayCopy() );

        //free memory
        unset( $this->projectStructure[ 'segments_metadata' ] );

    }

    private function pushActivityLog(){

        $activity             = new ActivityLogStruct();
        $activity->id_project = $this->projectStructure[ 'id_project' ];
        $activity->action     = ActivityLogStruct::PROJECT_CREATED;
        $activity->ip         = $this->projectStructure[ 'user_ip' ];
        $activity->uid        = $this->projectStructure[ 'uid' ];
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * @param $http_host string
     * @return string
     */
    public function getAnalyzeURL() {
        return Routes::analyze(
                [
                        'project_name' => $this->projectStructure[ 'project_name'],
                        'id_project'   => $this->projectStructure[ 'id_project' ],
                        'password'     => $this->projectStructure[ 'ppassword' ]
                ],
                [
                        'http_host'    => ( is_null( $this->projectStructure['HTTP_HOST'] ) ?
                                INIT::$HTTPHOST :
                                $this->projectStructure['HTTP_HOST']
                        ),
                ]
        );
    }

    /**
     * @throws Exception
     */
    protected function _pushTMXToMyMemory() {

        //TMX Management
        foreach ( $this->projectStructure[ 'array_files' ] as $fileName ) {

            //if TMX,
            if ( 'tmx' == FilesStorage::pathinfo_fix( $fileName, PATHINFO_EXTENSION ) ) {
                //load it into MyMemory; we'll check later on how it went
                $file            = new stdClass();
                $file->file_path = "$this->uploadDir/$fileName";
                $this->tmxServiceWrapper->setName( $fileName );
                $this->tmxServiceWrapper->setFile( array( $file ) );

                try {
                    $this->tmxServiceWrapper->addTmxInMyMemory();
                } catch ( Exception $e ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => $e->getCode(), "message" => $e->getMessage()
                    );

                    throw new Exception( $e );
                }

                //in any case, skip the rest of the loop, go to the next file
                continue;

            } elseif ( 'g' == FilesStorage::pathinfo_fix( $fileName, PATHINFO_EXTENSION ) ) {

                //{"responseStatus":"202","responseData":{"id":505406}}
                //load it into MyMemory; we'll check later on how it went
                $file            = new stdClass();
                $file->file_path = "$this->uploadDir/$fileName";
                $this->tmxServiceWrapper->setName( $fileName );
                $this->tmxServiceWrapper->setFile( array( $file ) );

                try {
                    $this->tmxServiceWrapper->addGlossaryInMyMemory();
                } catch ( Exception $e ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code" => $e->getCode(), "message" => $e->getMessage()
                    );

                    throw new Exception( $e );
                }

                //in any case, skip the rest of the loop, go to the next file
                continue;
            }

        }

        /**
         * @throws Exception
         */
        $this->_loopForTMXLoadStatus();

    }

    /**
     * @throws Exception
     */
    protected function _loopForTMXLoadStatus() {

        //TMX Management

        /****************/
        //loop again through files to check to check for TMX loading
        foreach ( $this->projectStructure[ 'array_files' ] as $kname => $fileName ) {

            //if TMX,
            if ( 'tmx' == FilesStorage::pathinfo_fix( $fileName, PATHINFO_EXTENSION ) ) {

                $this->tmxServiceWrapper->setName( $fileName );

                $result = array();

                //is the TM loaded?
                //wait until current TMX is loaded
                while ( true ) {

                    try {

                        $result = $this->tmxServiceWrapper->tmxUploadStatus();

                        if ( $result[ 'completed' ] ) {

                            //"$fileName" has been loaded into MyMemory"
                            //exit the loop
                            break;

                        }

                        //"waiting for "$fileName" to be loaded into MyMemory"
                        sleep( 3 );

                    } catch ( Exception $e ) {

                        $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                                "code" => $e->getCode(), "message" => $e->getMessage()
                        );

                        Log::doLog( $e->getMessage() . "\n" . $e->getTraceAsString() );

                        //exit project creation
                        throw new Exception( $e );

                    }

                }

                //once the language is loaded, check if language is compliant (unless something useful has already been found)
                if ( 1 == $this->checkTMX ) {

                    //get localized target languages of TM (in case it's a multilingual TM)
                    $tmTargets = explode( ';', $result[ 'data' ][ 'target_lang' ] );

                    //indicates if something has been found for current memory
                    $found = false;

                    //compare localized target languages array (in case it's a multilingual project) to the TM supplied
                    //if nothing matches, then the TM supplied can't have matches for this project

                    //create an empty var and add the source language too
                    $project_languages = array_merge( (array)$this->projectStructure[ 'target_language' ], (array)$this->projectStructure[ 'source_language' ] );
                    foreach ( $project_languages as $projectTarget ) {
                        if ( in_array( $this->langService->getLocalizedName( $projectTarget ), $tmTargets ) ) {
                            $found = true;
                            break;
                        }
                    }

                    //if this TM matches the project lagpair and something has been found
                    if ( $found and $result[ 'data' ][ 'source_lang' ] == $this->langService->getLocalizedName( $this->projectStructure[ 'source_language' ] ) ) {

                        //the TMX is good to go
                        $this->checkTMX = 0;

                    } elseif ( $found and $result[ 'data' ][ 'target_lang' ] == $this->langService->getLocalizedName( $this->projectStructure[ 'source_language' ] ) ) {

                        /*
                         * This means that the TMX has a srclang as specification in the header. Warn the user.
                         * Ex:
                         * <header creationtool="SDL Language Platform"
                         *      creationtoolversion="8.0"
                         *      datatype="rtf"
                         *      segtype="sentence"
                         *      adminlang="DE-DE"
                         *      srclang="DE-DE" />
                         */
                        $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                                "code"    => -16,
                                "message" => "The TMX you provided explicitly specifies {$result['data']['source_lang']} as source language. Check that the specified language source in the TMX file match the language source of your project or remove that specification in TMX file."
                        );

                        $this->checkTMX = 0;

                        Log::doLog( $this->projectStructure[ 'result' ] );
                    }

                }

                unset( $this->projectStructure[ 'array_files' ][ $kname ] );

            }

        }

        if ( 1 == $this->checkTMX ) {
            //this means that noone of uploaded TMX were usable for this project. Warn the user.
            $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                    "code"    => -16,
                    "message" => "The TMX did not contain any usable segment. Check that the languages in the TMX file match the languages of your project."
            );

            Log::doLog( $this->projectStructure[ 'result' ] );

            throw new Exception( "The TMX did not contain any usable segment. Check that the languages in the TMX file match the languages of your project." );
        }

    }

    protected function _doCheckForErrors() {

        if ( count( $this->projectStructure[ 'result' ][ 'errors' ] ) ) {
            Log::doLog( "Project Creation Failed. Sent to Output all errors." );
            Log::doLog( $this->projectStructure[ 'result' ][ 'errors' ] );

            return false;
        }

        return true;

    }

    protected function _zipFileHandling( $linkFiles ) {

        //begin of zip hashes manipulation
        foreach ( $linkFiles[ 'zipHashes' ] as $zipHash ) {

            $result = $this->fileStorage->linkZipToProject(
                    $this->projectStructure[ 'create_date' ],
                    $zipHash,
                    $this->projectStructure[ 'id_project' ]
            );

            if ( !$result ) {

                Log::doLog( "Failed to store the Zip file $zipHash - \n" );
                throw new Exception( "Failed to store the original Zip $zipHash ", -10 );
                //Exit
            }

        } //end zip hashes manipulation

    }

    protected function _createJobs( ArrayObject $projectStructure ) {

        foreach ( $projectStructure[ 'target_language' ] as $target ) {

            //shorten languages and get payable rates
            $shortSourceLang = substr( $projectStructure[ 'source_language' ], 0, 2 );
            $shortTargetLang = substr( $target, 0, 2 );

            //get payable rates
            $payableRates = json_encode( Analysis_PayableRates::getPayableRates( $shortSourceLang, $shortTargetLang ) );

            $password = $this->generatePassword();

            $tm_key = array();

            if ( !empty( $projectStructure[ 'private_tm_key' ] ) ) {
                foreach ( $projectStructure[ 'private_tm_key' ] as $tmKeyObj ) {
                    $newTmKey = TmKeyManagement_TmKeyManagement::getTmKeyStructure();

                    $newTmKey->tm    = true;
                    $newTmKey->glos  = true;
                    $newTmKey->owner = true;
                    $newTmKey->name  = $tmKeyObj[ 'name' ];
                    $newTmKey->key   = $tmKeyObj[ 'key' ];
                    $newTmKey->r     = $tmKeyObj[ 'r' ];
                    $newTmKey->w     = $tmKeyObj[ 'w' ];

                    $tm_key[] = $newTmKey;
                }

                //TODO: change this: private tm key field should not be used
                //set private tm key string to the first tm_key for retro-compatibility

                Log::doLog( $projectStructure[ 'private_tm_key' ] );

            }

            $projectStructure[ 'tm_keys' ] = json_encode( $tm_key );

            $newJob = new Jobs_JobStruct();
            $newJob->password          = $password;
            $newJob->id_project        = $projectStructure[ 'id_project' ];
            $newJob->id_translator     = is_null($projectStructure[ 'private_tm_user' ]) ?  "" : $projectStructure[ 'private_tm_user' ] ;
            $newJob->source            = $projectStructure[ 'source_language' ];
            $newJob->target            = $target;
            $newJob->id_tms            = $projectStructure[ 'tms_engine' ];
            $newJob->id_mt_engine      = $projectStructure[ 'mt_engine' ];
            $newJob->create_date       = date( "Y-m-d H:i:s" );
            $newJob->subject           = $projectStructure[ 'job_subject' ];
            $newJob->owner             = $projectStructure[ 'owner' ];
            $newJob->job_first_segment = $this->min_max_segments_id[ 'job_first_segment' ];
            $newJob->job_last_segment  = $this->min_max_segments_id[ 'job_last_segment' ];
            $newJob->tm_keys           = $projectStructure[ 'tm_keys' ];
            $newJob->payable_rates     = $payableRates;
            $newJob->dqf_key           = $projectStructure[ 'dqf_key' ];
            $newJob->total_raw_wc      = $this->files_word_count;

            $newJob = Jobs_JobDao::createFromStruct( $newJob );

            $projectStructure[ 'array_jobs' ][ 'job_list' ]->append( $newJob->id );
            $projectStructure[ 'array_jobs' ][ 'job_pass' ]->append( $newJob->password );
            $projectStructure[ 'array_jobs' ][ 'job_segments' ]->offsetSet( $newJob->id . "-" . $newJob->password, $this->min_max_segments_id );
            $projectStructure[ 'array_jobs' ][ 'job_languages' ]->offsetSet( $newJob->id, $newJob->id . ":" . $target );
            $projectStructure[ 'array_jobs' ][ 'payable_rates' ]->offsetSet( $newJob->id, $payableRates );

            try {
                //prepare pre-translated segments queries
                if ( !empty( $projectStructure[ 'translations' ] ) ) {
                    $this->_insertPreTranslations( $newJob->id );
                }
            } catch ( Exception $e ) {
                $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export( $e->getMessage(), true );
                Utils::sendErrMailReport( $msg );
            }

            foreach ( $projectStructure[ 'file_id_list' ] as $fid ) {

                if ( !empty( $this->projectStructure[ 'notes' ] ) ) {
                    $this->insertSegmentNotesForFile();
                }

                insertFilesJob( $newJob->id, $fid );

                if ( $this->gdriveSession && $this->gdriveSession->hasFiles() ) {
                    $this->gdriveSession->createRemoteCopiesWhereToSaveTranslation( $fid, $newJob->id ) ;
                }
            }
        }

        //Clean Translation array
        $this->projectStructure[ 'translations' ]->exchangeArray( array() );

        $this->features->run('processJobsCreated', $projectStructure );

    }

    /**
     *
     */
    private function insertSegmentNotesForFile() {
        Segments_SegmentNoteDao::bulkInsertFromProjectStructure( $this->projectStructure['notes'] )  ;
    }

    /**
     * This function executes a language detection call to mymemory for an array of segments,
     * located in projectStructure
     */
    private function validateFilesLanguages() {
        /**
         * @var $filesSegments RecursiveArrayObject
         */
        $filesSegments = $this->projectStructure[ 'segments' ];

        /**
         * This is a map <file_name, check_result>, where check_result is one
         * of these status strings:<br/>
         * - ok         --> the language detected for this file is the same of source language<br/>
         * - warning    --> the language detected for this file is different from the source language
         *
         * @var $filename2SourceLangCheck array
         */
        $filename2SourceLangCheck = array();

        //istantiate MyMemory analyzer and detect languages for each file uploaded
        $mma = Engine::getInstance( 1 /* MyMemory */ );
        /**
         * @var $mma Engines_MyMemory
         */
        $res = $mma->detectLanguage( $filesSegments, $this->projectStructure[ 'lang_detect_files' ] );

        //for each language detected, check if it's not equal to the source language
        $langsDetected = $res[ 'responseData' ][ 'translatedText' ];
        Log::dolog( __CLASS__ . " - DETECT LANG RES:", $langsDetected );
        if ( $res !== null &&
                is_array( $langsDetected ) &&
                count( $langsDetected ) == count( $this->projectStructure[ 'array_files' ] )
        ) {

            $counter = 0;
            foreach ( $langsDetected as $fileLang ) {

                $currFileName = $this->projectStructure[ 'array_files' ][ $counter ];

                //get language code
                if ( strpos( $fileLang, "-" ) === false ) {
                    //PHP Strict: Only variables should be passed by reference
                    $_tmp       = explode( "-", $this->projectStructure[ 'source_language' ] );
                    $sourceLang = array_shift( $_tmp );
                } else {
                    $sourceLang = $this->projectStructure[ 'source_language' ];
                }

                Log::dolog( __CLASS__ . " - DETECT LANG COMPARISON:", "$fileLang@@$sourceLang" );
                //get extended language name using google language code
                $languageExtendedName = Langs_GoogleLanguageMapper::getLanguageCode( $fileLang );

                //get extended language name using standard language code
                $langClass                  = Langs_Languages::getInstance();
                $sourceLanguageExtendedName = strtolower( $langClass->getLocalizedName( $sourceLang ) );
                Log::dolog( __CLASS__ . " - DETECT LANG NAME COMPARISON:", "$sourceLanguageExtendedName@@$languageExtendedName" );

                //Check job's detected language. In case of undefined language, mark it as valid
                if ( $fileLang !== 'und' &&
                        $fileLang != $sourceLang &&
                        $sourceLanguageExtendedName != $languageExtendedName
                ) {

                    $filename2SourceLangCheck[ $currFileName ] = 'warning';

                    $languageExtendedName = ucfirst( $languageExtendedName );

                    $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                            "code"    => -17,
                            "message" => "The source language you selected seems " .
                                    "to be different from the source language in \"$currFileName\". Please check."
                    );
                } else {
                    $filename2SourceLangCheck[ $currFileName ] = 'ok';
                }

                $counter++;
            }

            if ( in_array( "warning", array_values( $filename2SourceLangCheck ) ) ) {
                $this->projectStructure[ 'result' ][ 'lang_detect' ] = $filename2SourceLangCheck;
            }
        } else {
            //There are errors while parsing JSON.
            //Noop
        }
    }

    /**
     *
     * Build a job split structure, minimum split value are 2 chunks
     *
     * @param ArrayObject $projectStructure
     * @param int         $num_split
     * @param array       $requestedWordsPerSplit Matecat Equivalent Words ( Only valid for Pro Version )
     *
     * @return RecursiveArrayObject
     *
     * @throws Exception
     */
    public function getSplitData( ArrayObject $projectStructure, $num_split = 2, $requestedWordsPerSplit = array() ) {

        $num_split = (int)$num_split;

        if ( $num_split < 2 ) {
            throw new Exception( 'Minimum Chunk number for split is 2.', -2 );
        }

        if ( !empty( $requestedWordsPerSplit ) && count( $requestedWordsPerSplit ) != $num_split ) {
            throw new Exception( "Requested words per chunk and Number of chunks not consistent.", -3 );
        }

        if ( !empty( $requestedWordsPerSplit ) && !INIT::$VOLUME_ANALYSIS_ENABLED ) {
            throw new Exception( "Requested words per chunk available only for Matecat PRO version", -4 );
        }

        $rows = ( new Jobs_JobDao() )->getSplitData( $projectStructure['job_to_split'], $projectStructure['job_to_split_pass'] );

        if ( empty( $rows ) ) {
            throw new Exception( 'No segments found for job ' . $projectStructure[ 'job_to_split' ], -5 );
        }

        $row_totals = array_pop( $rows ); //get the last row ( ROLLUP )
        unset( $row_totals[ 'id' ] );

        if ( empty( $row_totals[ 'job_first_segment' ] ) || empty( $row_totals[ 'job_last_segment' ] ) ) {
            throw new Exception( 'Wrong job id or password. Job segment range not found.', -6 );
        }

        $count_type = $this->getWordCountType( $row_totals );
        $total_words = $row_totals[ $count_type ];

        if ( empty( $requestedWordsPerSplit ) ) {
            /*
             * Simple Split with pretty equivalent number of words per chunk
             */
            $words_per_job = array_fill( 0, $num_split, round( $total_words / $num_split, 0 ) );
        } else {
            /*
             * User defined words per chunk, needs some checks and control structures
             */
            $words_per_job = $requestedWordsPerSplit;
        }

        $counter = array();
        $chunk   = 0;

        $reverse_count = array( 'eq_word_count' => 0, 'raw_word_count' => 0 );

        foreach ( $rows as $row ) {

            if ( !array_key_exists( $chunk, $counter ) ) {
                $counter[ $chunk ] = array(
                        'eq_word_count'       => 0,
                        'raw_word_count'      => 0,
                        'segment_start'       => $row[ 'id' ],
                        'segment_end'         => 0,
                        'last_opened_segment' => 0,
                );
            }

            $counter[ $chunk ][ 'eq_word_count' ] += $row[ 'eq_word_count' ];
            $counter[ $chunk ][ 'raw_word_count' ] += $row[ 'raw_word_count' ];
            $counter[ $chunk ][ 'segment_end' ] = $row[ 'id' ];

            //if last_opened segment is not set and if that segment can be showed in cattool
            //set that segment as the default last visited
            ( $counter[ $chunk ][ 'last_opened_segment' ] == 0 && $row[ 'show_in_cattool' ] == 1 ? $counter[ $chunk ][ 'last_opened_segment' ] = $row[ 'id' ] : null );

            //check for wanted words per job.
            //create a chunk when we reach the requested number of words
            //and we are below the requested number of splits.
            //in this manner, we add to the last chunk all rests
            if ( $counter[ $chunk ][ $count_type ] >= $words_per_job[ $chunk ] && $chunk < $num_split - 1 /* chunk is zero based */ ) {
                $counter[ $chunk ][ 'eq_word_count' ]  = (int)$counter[ $chunk ][ 'eq_word_count' ];
                $counter[ $chunk ][ 'raw_word_count' ] = (int)$counter[ $chunk ][ 'raw_word_count' ];

                $reverse_count[ 'eq_word_count' ] += (int)$counter[ $chunk ][ 'eq_word_count' ];
                $reverse_count[ 'raw_word_count' ] += (int)$counter[ $chunk ][ 'raw_word_count' ];

                $chunk++;
            }

        }

        if ( $total_words > $reverse_count[ $count_type ] ) {
            if( !empty( $counter[ $chunk ] ) ){
                $counter[ $chunk ][ 'eq_word_count' ]  = round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
                $counter[ $chunk ][ 'raw_word_count' ] = round( $row_totals[ 'raw_word_count' ] - $reverse_count[ 'raw_word_count' ] );
            } else {
                $counter[ $chunk -1 ][ 'eq_word_count' ]  += round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
                $counter[ $chunk -1][ 'raw_word_count' ] += round( $row_totals[ 'raw_word_count' ] - $reverse_count[ 'raw_word_count' ] );
            }
        }

        if ( count( $counter ) < 2 ) {
            throw new Exception( 'The requested number of words for the first chunk is too large. I cannot create 2 chunks.', -7 );
        }

        $result = array_merge( $row_totals->getArrayCopy(), array( 'chunks' => $counter ) );

        $projectStructure[ 'split_result' ] = new ArrayObject( $result );

        return $projectStructure[ 'split_result' ];

    }


    private function getWordCountType( $row_totals ) {
        $project_count_type = $this->project->getWordCountType();
        $eq_word_count =  (float)$row_totals[ 'eq_word_count' ];
        if (
                $project_count_type == Projects_MetadataDao::WORD_COUNT_EQUIVALENT &&
                !empty( $eq_word_count )
        ) {
            $count_type = 'eq_word_count';
        } else {
            $count_type = 'raw_word_count';
        }

        return $count_type;
    }

    /**
     * Do the split based on previous getSplitData analysis
     * It clone the original job in the right number of chunks and fill these rows with:
     * first/last segments of every chunk, last opened segment as first segment of new job
     * and the timestamp of creation
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    protected function _splitJob( ArrayObject $projectStructure ) {

        $query_job = "SELECT * FROM jobs WHERE id = %u AND password = '%s'";
        $query_job = sprintf( $query_job, $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );
        //$projectStructure[ 'job_to_split' ]

        $jobInfo = $this->dbHandler->fetch_array( $query_job );
        $jobInfo = $jobInfo[ 0 ];


        /*
         * If a translator is assigned to the job, we must send an email to inform that job is changed
         */
        $jStruct = new Jobs_JobStruct( $jobInfo );
        $translatorModel = new TranslatorsModel( $jStruct );
        $jTranslatorStruct = $translatorModel->getTranslator( 0 ); // no cache
        if ( !empty( $jTranslatorStruct ) && !empty( $this->projectStructure[ 'session' ][ 'uid' ] ) ) {

            $translatorModel
                    ->setUserInvite( ( new Users_UserDao() )->setCacheTTL( 60 * 60 )->getByUid( $this->projectStructure[ 'session' ][ 'uid' ] ) )
                    ->setDeliveryDate( $jTranslatorStruct->delivery_date )
                    ->setJobOwnerTimezone( $jTranslatorStruct->job_owner_timezone )
                    ->setEmail( $jTranslatorStruct->email )
                    ->setNewJobPassword( CatUtils::generate_password() );

            $translatorModel->update();
            $jobInfo[ 'password'] = $jStruct->password;

        }

        $data = array();
        $jobs = array();

        foreach ( $projectStructure[ 'split_result' ][ 'chunks' ] as $chunk => $contents ) {

            //            Log::doLog( $projectStructure['split_result']['chunks'] );

            //IF THIS IS NOT the original job, DELETE relevant fields
            if ( $contents[ 'segment_start' ] != $projectStructure[ 'split_result' ][ 'job_first_segment' ] ) {
                //next insert
                $jobInfo[ 'password' ]    = $this->generatePassword();
                $jobInfo[ 'create_date' ] = date( 'Y-m-d H:i:s' );
            }

            $jobInfo[ 'last_opened_segment' ] = $contents[ 'last_opened_segment' ];
            $jobInfo[ 'job_first_segment' ]   = $contents[ 'segment_start' ];
            $jobInfo[ 'job_last_segment' ]    = $contents[ 'segment_end' ];

            $query = "INSERT INTO jobs ( " . implode( ", ", array_keys( $jobInfo ) ) . " )
                VALUES ( '" . implode( "', '", array_values( array_map( array(
                            $this->dbHandler, 'escape'
                    ), $jobInfo ) ) ) . "' )
                ON DUPLICATE KEY UPDATE
                last_opened_segment = {$jobInfo['last_opened_segment']},
                job_first_segment = '{$jobInfo['job_first_segment']}',
                job_last_segment = '{$jobInfo['job_last_segment']}'";


            //add here job id to list
            $projectStructure[ 'array_jobs' ][ 'job_list' ]->append( $projectStructure[ 'job_to_split' ] );
            //add here passwords to list
            $projectStructure[ 'array_jobs' ][ 'job_pass' ]->append( $jobInfo[ 'password' ] );

            $projectStructure[ 'array_jobs' ][ 'job_segments' ]->offsetSet( $projectStructure[ 'job_to_split' ] . "-" . $jobInfo[ 'password' ], new ArrayObject( array(
                    $contents[ 'segment_start' ], $contents[ 'segment_end' ]
            ) ) );

            $data[] = $query;
            $jobs[] = $jobInfo;
        }

        foreach ( $data as $position => $query ) {

            $res = $this->dbHandler->query( $query );

            $wCountManager = new WordCount_Counter();
            $wCountManager->initializeJobWordCount( $jobs[ $position ][ 'id' ], $jobs[ $position ][ 'password' ] );

            if ( $this->dbHandler->affected_rows == 0 ) {
                $msg = "Failed to split job into " . count( $projectStructure[ 'split_result' ][ 'chunks' ] ) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r( $data, true ) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r( $query, true ) . "\n";
                Utils::sendErrMailReport( $msg );
                throw new Exception( 'Failed to insert job chunk, project damaged.', -8 );
            }

            Shop_Cart::getInstance( 'outsource_to_external_cache' )->deleteCart();

        }

    }

    /**
     * Apply new structure of job
     *
     * @param ArrayObject $projectStructure
     */
    public function applySplit( ArrayObject $projectStructure ) {
        Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();

        \Database::obtain()->begin();
        $this->_splitJob( $projectStructure );
        $this->features->run( 'postJobSplitted', $projectStructure );
        $this->dbHandler->getConnection()->commit();

    }

    public function mergeALL( ArrayObject $projectStructure, array $jobStructs ) {

        $metadata_dao = new Projects_MetadataDao();
        $metadata_dao->cleanupChunksOptions( $jobStructs );

        //get the min and
        $first_job         = reset( $jobStructs );
        $job_first_segment = $first_job[ 'job_first_segment' ];

        //the max segment from job list
        $last_job         = end( $jobStructs );
        $job_last_segment = $last_job[ 'job_last_segment' ];

        //change values of first job
        $first_job[ 'job_first_segment' ] = $job_first_segment; // redundant
        $first_job[ 'job_last_segment' ]  = $job_last_segment;

        //merge TM keys: preserve only owner's keys
        $tm_keys = array();
        foreach ( $jobStructs as $chunk_info ) {
            $tm_keys[] = $chunk_info[ 'tm_keys' ];
        }

        try {
            $owner_tm_keys = TmKeyManagement_TmKeyManagement::getOwnerKeys( $tm_keys );

            /**
             * @var $owner_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $owner_tm_keys as $i => $owner_key ) {
                $owner_tm_keys[ $i ] = $owner_key->toArray();
            }

            $first_job[ 'tm_keys' ] = json_encode( $owner_tm_keys );
        } catch ( Exception $e ) {
            Log::doLog( __METHOD__ . " -> Merge Jobs error - TM key problem: " . $e->getMessage() );
        }

        $oldPassword = $first_job[ 'password' ];
        if( $first_job->getTranslator() ){
            $first_job[ 'password' ] = self::generatePassword();
            Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();
        }

        $_data = array();
        foreach ( $first_job as $field => $value ) {
            $_data[] = "`$field`='" . $this->dbHandler->escape( $value ) . "'";
        }

        //----------------------------------------------------

        $queries = array();

        $queries[] = "UPDATE jobs SET " . implode( ", \n", $_data ) .
                " WHERE id = {$first_job['id']} AND password = '{$oldPassword}'"; //use old password

        //delete all old jobs
        $queries[] = "DELETE FROM jobs WHERE id = {$first_job['id']} AND password != '{$first_job['password']}' "; //use new password

        \Database::obtain()->begin();

        foreach ( $queries as $query ) {
            $res = $this->dbHandler->query( $query );
            if ( $this->dbHandler->affected_rows == 0 ) {
                $msg = "Failed to merge job  " . $first_job[ 'id' ] . " from " . count( $jobStructs ) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r( $queries, true ) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r( $query, true ) . "\n";
                $msg .= "Original Status for rebuild job and project was: \n" . print_r( $jobStructs, true ) . "\n";
                Utils::sendErrMailReport( $msg );
                throw new Exception( 'Failed to merge jobs, project damaged. Contact Matecat Support to rebuild project.', -8 );
            }
        }

        $wCountManager = new WordCount_Counter();
        $wCountManager->initializeJobWordCount( $first_job[ 'id' ], $first_job[ 'password' ] );

        $this->features->run('postJobMerged',
            $projectStructure
        );

        $this->dbHandler->getConnection()->commit();

    }

    /**
     * Extract sources and pre-translations from sdlxliff file and put them in Database
     *
     * @param $fid
     * @param $file_info
     *
     * @throws Exception
     * @internal param $filesStructure
     *
     * @internal param $xliff_file_content
     * @internal param $fid
     */
    protected function _extractSegments( $fid, $file_info ) {

        $xliff_file_content = file_get_contents( $file_info[ 'path_cached_xliff' ] );
        $mimeType           = $file_info[ 'mime_type' ];

        //create Structure fro multiple files
        $this->projectStructure[ 'segments' ]->offsetSet( $fid, new ArrayObject( array() ) );

        $xliff_obj = new Xliff_Parser();

        try {
            $xliff = $xliff_obj->Xliff2Array( $xliff_file_content );
        } catch ( Exception $e ) {
            throw new Exception( $file_info[ 'original_filename' ], $e->getCode(), $e );
        }


        // Checking that parsing went well
        if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
            Log::doLog( "Xliff Import: Error parsing. " . join( "\n", $xliff[ 'parser-errors' ] ) );
            throw new Exception( $file_info[ 'original_filename' ], -4 );
        }

        //needed to check if a file has only one segment
        //for correctness: we could have more tag files in the xliff
        $_fileCounter_Show_In_Cattool = 0;
        $num_words                    = 0; //initialize counter for words in the file to avoid IDE warnings

        // Creating the Query
        foreach ( $xliff[ 'files' ] as $xliff_file ) {

            if ( !array_key_exists( 'trans-units', $xliff_file ) ) {
                continue;
            }

            //extract internal reference base64 files and store their index in $this->projectStructure
//            $this->_extractFileReferences( $fid, $xliff_file );

            foreach ( $xliff_file[ 'trans-units' ] as $xliff_trans_unit ) {

                //initialize flag
                $show_in_cattool = 1;

                if ( !isset( $xliff_trans_unit[ 'attr' ][ 'translate' ] ) ) {
                    $xliff_trans_unit[ 'attr' ][ 'translate' ] = 'yes';
                }

                if ( $xliff_trans_unit[ 'attr' ][ 'translate' ] == "no" ) {
                    //No segments to translate
                    //don't increment global counter '$this->fileCounter_Show_In_Cattool'
                    $show_in_cattool = 0;
                } else {

                    $trans_unit_reference = self::sanitizedUnitId( $xliff_trans_unit[ 'attr' ][ 'id' ], $fid );

                    // If the XLIFF is already segmented (has <seg-source>)
                    if ( isset( $xliff_trans_unit[ 'seg-source' ] ) ) {
                        foreach ( $xliff_trans_unit[ 'seg-source' ] as $position => $seg_source ) {

                            //rest flag because if the first mrk of the seg-source is not translatable the rest of
                            //mrk in the list will not be too!!!
                            $show_in_cattool = 1;

                            $wordCount = CatUtils::segment_raw_wordcount( $seg_source[ 'raw-content' ], $xliff_file[ 'attr' ][ 'source-language' ] );

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
                                    $target_extract_external = $this->_strip_external(
                                            $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ]
                                    );

                                    //we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                    //AND IF IT IS ONLY A CHAR? like "*" ?
                                    //we can't distinguish if it is translated or not
                                    //this means that we lose the tags id inside the target if different from source
                                    $src = trim( strip_tags( html_entity_decode( $extract_external[ 'seg' ], ENT_QUOTES, 'UTF-8' ) ) );
                                    $trg = trim( strip_tags( html_entity_decode( $target_extract_external[ 'seg' ], ENT_QUOTES, 'UTF-8' ) ) );


                                    if ( $this->__isTranslated( $src, $trg ) && !is_numeric( $src ) && !empty( $trg ) ) { //treat 0,1,2.. as translated content!

                                        $target_extract_external[ 'seg' ] = CatUtils::raw2DatabaseXliff( $target_extract_external[ 'seg' ] );
                                        $target                           = $this->dbHandler->escape( $target_extract_external[ 'seg' ] );

                                        //add an empty string to avoid casting to int: 0001 -> 1
                                        //useful for idiom internal xliff id
                                        if ( !$this->projectStructure[ 'translations' ]->offsetExists( $trans_unit_reference ) ) {
                                            $this->projectStructure[ 'translations' ]->offsetSet( $trans_unit_reference, new ArrayObject() );
                                        }
                                        $this->projectStructure[ 'translations' ][ $trans_unit_reference ]->offsetSet( $seg_source[ 'mid' ], new ArrayObject( array( 2 => $target ) ) );

                                        //seg-source and target translation can have different mrk id
                                        //override the seg-source surrounding mrk-id with them of target
                                        $seg_source[ 'mrk-ext-prec-tags' ] = $target_extract_external[ 'prec' ];
                                        $seg_source[ 'mrk-ext-succ-tags' ] = $target_extract_external[ 'succ' ];

                                    }

                                }

                            }

                            //Log::doLog( $xliff_trans_unit ); die();

                            // $seg_source[ 'raw-content' ] = CatUtils::placeholdnbsp( $seg_source[ 'raw-content' ] );

                            $mid               = $this->dbHandler->escape( $seg_source[ 'mid' ] );
                            $ext_tags          = $this->dbHandler->escape( $seg_source[ 'ext-prec-tags' ] );
                            $source            = $this->dbHandler->escape( CatUtils::raw2DatabaseXliff( $seg_source[ 'raw-content' ] ) );
                            $source_hash       = $this->dbHandler->escape( md5( $seg_source[ 'raw-content' ] ) );
                            $ext_succ_tags     = $this->dbHandler->escape( $seg_source[ 'ext-succ-tags' ] );
                            $num_words         = $wordCount;
                            $trans_unit_id     = $this->dbHandler->escape( $xliff_trans_unit[ 'attr' ][ 'id' ] );
                            $mrk_ext_prec_tags = $this->dbHandler->escape( $seg_source[ 'mrk-ext-prec-tags' ] );
                            $mrk_ext_succ_tags = $this->dbHandler->escape( $seg_source[ 'mrk-ext-succ-tags' ] );

                            $this->projectStructure[ 'segments' ][ $fid ]->append( [
                                    $trans_unit_id,
                                    $fid,
                                    $this->projectStructure[ 'id_project' ],
                                    $source,
                                    $source_hash,
                                    $num_words,
                                    $mid,
                                    $ext_tags,
                                    $ext_succ_tags,
                                    $show_in_cattool,
                                    $mrk_ext_prec_tags,
                                    $mrk_ext_succ_tags
                            ] );

                            //increment counter for word count
                            $this->files_word_count += $num_words;

                        } // end foreach seg-source

                        if ( self::notesAllowedByMimeType( $mimeType ) ) {
                            $this->addNotesToProjectStructure( $xliff_trans_unit, $fid );
                        }

                    } else {

                        $wordCount = CatUtils::segment_raw_wordcount( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $xliff_file[ 'attr' ][ 'source-language' ] );

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

                                $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ] );

                                if ( $this->__isTranslated( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $target_extract_external[ 'seg' ] ) ) {

                                    $target = CatUtils::raw2DatabaseXliff( $target_extract_external[ 'seg' ] );
                                    $target = $this->dbHandler->escape( $target );

                                    //add an empty string to avoid casting to int: 0001 -> 1
                                    //useful for idiom internal xliff id
                                    if ( !$this->projectStructure[ 'translations' ]->offsetExists( $trans_unit_reference ) ) {
                                        $this->projectStructure[ 'translations' ]->offsetSet( $trans_unit_reference, new ArrayObject() );
                                    }
                                    $this->projectStructure[ 'translations' ][ $trans_unit_reference ]->append( new ArrayObject( array( 2 => $target ) ) );

                                }

                            }

                        }

                        if ( self::notesAllowedByMimeType( $mimeType ) ) {
                            $this->addNotesToProjectStructure( $xliff_trans_unit, $fid );
                        }

                        $source = $xliff_trans_unit[ 'source' ][ 'raw-content' ];

                        //we do the word count after the place-holding with <x id="nbsp"/>
                        //so &nbsp; are now not recognized as word and not counted as payable
                        $num_words = $wordCount;

                        //applying escaping after raw count
                        $source      = $this->dbHandler->escape( CatUtils::raw2DatabaseXliff( $source ) );
                        $source_hash = $this->dbHandler->escape( md5( $source ) );

                        $trans_unit_id = $this->dbHandler->escape( $xliff_trans_unit[ 'attr' ][ 'id' ] );

                        if ( !is_null( $prec_tags ) ) {
                            $prec_tags = $this->dbHandler->escape( $prec_tags );
                        }
                        if ( !is_null( $succ_tags ) ) {
                            $succ_tags = $this->dbHandler->escape( $succ_tags );
                        }

                        $this->projectStructure[ 'segments' ][ $fid ]->append( [
                                $trans_unit_id,
                                $fid,
                                $this->projectStructure[ 'id_project' ],
                                $source,
                                $source_hash,
                                $num_words,
                                null,
                                $prec_tags,
                                $succ_tags,
                                $show_in_cattool,
                                null,
                                null
                        ] );

                        //increment counter for word count
                        $this->files_word_count += $num_words;

                    }
                }

                //increment the counter for not empty segments
                $_fileCounter_Show_In_Cattool += $show_in_cattool;

            }

            $this->total_segments += count( $xliff_file[ 'trans-units' ] );

        }

        // *NOTE*: PHP>=5.3 throws UnexpectedValueException, but PHP 5.2 throws ErrorException
        //use generic
        if ( count( $this->projectStructure[ 'segments' ][ $fid ] ) == 0 || $_fileCounter_Show_In_Cattool == 0 ) {
            Log::doLog( "Segment import - no segments found\n" );
            throw new Exception( $file_info[ 'original_filename' ], -1 );
        } else {
            //increment global counter
            $this->show_in_cattool_segs_counter += $_fileCounter_Show_In_Cattool;
        }

    }

    protected function _insertFiles( $_originalFileNames, $sha1_original, $cachedXliffFilePathName ){

        $yearMonthPath    = date_create( $this->projectStructure[ 'create_date' ] )->format( 'Ymd' );
        $fileDateSha1Path = $yearMonthPath . DIRECTORY_SEPARATOR . $sha1_original;

        //return structure
        $filesStructure = [];

        //PLEASE NOTE, this can be an array when the same file added more
        // than once and with different names
        //
        foreach ( $_originalFileNames as $originalFileName ) {

            $mimeType = FilesStorage::pathinfo_fix( $originalFileName, PATHINFO_EXTENSION );
            $fid      = insertFile( $this->projectStructure, $originalFileName, $mimeType, $fileDateSha1Path );

            if ( $this->gdriveSession )  {
                $gdriveFileId = $this->gdriveSession->findFileIdByName( $originalFileName ) ;
                if ($gdriveFileId) {
                    $this->gdriveSession->createRemoteFile( $fid, $gdriveFileId );
                }
            }

            $this->fileStorage->moveFromCacheToFileDir(
                    $fileDateSha1Path,
                    $this->projectStructure[ 'source_language' ],
                    $fid,
                    $originalFileName
            );

            $this->projectStructure[ 'file_id_list' ]->append( $fid );

            $filesStructure[ $fid ] = [ 'fid' => $fid, 'original_filename' => $originalFileName , 'path_cached_xliff' => $cachedXliffFilePathName, 'mime_type' =>$mimeType ];

        }

        return $filesStructure;

    }

    protected function _storeSegments( $fid ){

        $baseQuery = "INSERT INTO segments ( id, internal_id, id_file,/* id_project, */ segment, segment_hash, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) values ";

        Log::doLog( "Segments: Total Rows to insert: " . count( $this->projectStructure[ 'segments' ][ $fid ] ) );
        $sequenceIds = $this->dbHandler->nextSequence( Database::SEQ_ID_SEGMENT, count( $this->projectStructure[ 'segments' ][ $fid ] ) );
        Log::doLog( "Id sequence reserved." );

        //Update/Initialize the min-max sequences id
        if( !isset( $this->min_max_segments_id[ 'job_first_segment' ] ) ){
            $this->min_max_segments_id[ 'job_first_segment' ] = reset( $sequenceIds );
        }

        //update the last id, if there is another cycle update this value
        $this->min_max_segments_id[ 'job_last_segment' ] = end( $sequenceIds );


        $segments_metadata = [];
        foreach ( $sequenceIds as $position => $id_segment ){

            /**
             *  $trans_unit_id,
             *  $fid,
             *  $id_project,
             *  $source,
             *  $source_hash,
             *  $num_words,
             *  $mid,
             *  $ext_tags,
             *  $ext_succ_tags,
             *  $show_in_cattool,
             *  $mrk_ext_prec_tags,
             *  $mrk_ext_succ_tags
             */
            $tuple = $this->projectStructure[ 'segments' ][ $fid ][ $position ];
//            $tuple_string = "'{$tuple[0]}',{$tuple[1]},{$tuple[2]},'{$tuple[3]}','{$tuple[4]}',{$tuple[5]},'{$tuple[6]}','{$tuple[7]}','{$tuple[8]}',{$tuple[9]},'{$tuple[10]}','{$tuple[11]}'";
            $tuple_string = "'{$tuple[0]}',{$tuple[1]},'{$tuple[3]}','{$tuple[4]}',{$tuple[5]},'{$tuple[6]}','{$tuple[7]}','{$tuple[8]}',{$tuple[9]},'{$tuple[10]}','{$tuple[11]}'";

            $this->projectStructure[ 'segments' ][ $fid ][ $position ] = "( $id_segment,$tuple_string )";

            $segments_metadata[] = [
                    'id'              => $id_segment,
                    'internal_id'     => self::sanitizedUnitId( $tuple[ 0 ], $fid ),
                    'segment'         => $tuple[ 3 ],
                    'segment_hash'    => $tuple[ 4 ],
                    'raw_word_count'  => $tuple[ 5 ],
                    'xliff_mrk_id'    => $tuple[ 6 ],
                    'show_in_cattool' => $tuple[ 9 ],
            ];

        }

        //split the query in to chunks if there are too much segments
        $this->projectStructure[ 'segments' ][ $fid ]->exchangeArray( array_chunk( $this->projectStructure[ 'segments' ][ $fid ]->getArrayCopy(), 100 ) );

        Log::doLog( "Segments: Total Queries to execute: " . count( $this->projectStructure[ 'segments' ][ $fid ] ) );

        foreach ( $this->projectStructure[ 'segments' ][ $fid ] as $i => $chunk ) {

            try {
                $this->dbHandler->query( $baseQuery . join( ",\n", $chunk ) );
                Log::doLog( "Segments: Executed Query " . ( $i + 1 ) );
            } catch ( PDOException $e ) {
                Log::doLog( "Segment import - DB Error: " . $e->getMessage() . " - \n" );
                throw new Exception( "Segment import - DB Error: " . $e->getMessage() . " - $chunk", -2 );
            }

        }

        // Here we make a query for the last inserted segments. This is the point where we
        // can read the id of the segments table to reference it in other inserts in other tables.
        //
        if ( !(
                empty( $this->projectStructure[ 'notes' ] ) &&
                empty( $this->projectStructure[ 'translations' ] )
        )
        ) {

            //internal counter for the segmented translations ( mrk in target )
            $array_internal_segmentation_counter = array();

            foreach ( $segments_metadata as $k => $row ) {

                // The following call is to save `id_segment` for notes,
                // to be used later to insert the record in notes table.
                $this->setSegmentIdForNotes( $row );

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
                    //WARNING offset 2 are the target translations
                    $this->projectStructure[ 'translations' ][ $row[ 'internal_id' ] ][ $short_var_counter ]->offsetSet( 3, $row[ 'segment_hash' ] );

                    // Remove an existent translation, we won't send these segment to the analysis because it is marked as locked
                    unset( $segments_metadata[ $k ] );

                }

            }

        }

        $this->projectStructure[ 'segments_metadata' ]->exchangeArray( array_merge( $this->projectStructure[ 'segments_metadata' ]->getArrayCopy(), $segments_metadata ) );

        //free memory
        $this->projectStructure[ 'segments' ][ $fid ]->exchangeArray( [] );

    }

    protected function _cleanSegmentsMetadata(){
        //More cleaning on the segments, remove show_in_cattool == false
        $this->projectStructure[ 'segments_metadata' ]->exchangeArray(
                array_filter( $this->projectStructure[ 'segments_metadata' ]->getArrayCopy(), function ( $value ) {
                    return $value[ 'show_in_cattool' ] == 1;
                } )
        );
    }

    /**
     * setSegmentIdForNotes
     *
     * Adds notes to segment, taking into account that a same note may be assigned to
     * more than one MateCat segment, due to the <mrk> tags.
     *
     * Example:
     * ['notes'][ $internal_id] => array( 'xxx' );
     * ['notes'][ $internal_id] => array( 'xxx', 'yyy' ); // in case of mrk tags
     *
     */

    private function setSegmentIdForNotes( $row ) {
        $internal_id = $row[ 'internal_id' ];

        if ( $this->projectStructure[ 'notes' ]->offsetExists( $internal_id ) ) {
            array_push( $this->projectStructure[ 'notes' ][ $internal_id ][ 'segment_ids' ], $row[ 'id' ] );
        }

    }

    protected function _insertPreTranslations( $jid ) {

        $this->_cleanSegmentsMetadata();

        $status = Constants_TranslationStatus::STATUS_TRANSLATED;

        $status = $this->features->filter('filter_status_for_pretranslated_segments',
                $status,
                $this->projectStructure
        );

        foreach ( $this->projectStructure[ 'translations' ] as $trans_unit_reference => $struct ) {

            if ( empty( $struct ) ) {
                continue;
            }

            //array of segmented translations
            foreach ( $struct as $pos => $translation_row ) {

                $sql_values = sprintf(
                    "( '%s', %s, '%s', '%s', '%s', NOW(), 'DONE', 1, 'ICE', '%s' )",
                    $translation_row [ 0 ],
                    $jid,
                    $translation_row [ 3 ],
                    $status,
                    $translation_row [ 2 ],
                    0
                );

                $this->projectStructure[ 'query_translations' ]->append( $sql_values ) ;
            }

        }

        // Executing the Query
        if ( !empty( $this->projectStructure[ 'query_translations' ] ) ) {

            $baseQuery = "INSERT INTO segment_translations (
                id_segment, id_job, segment_hash, status, translation, translation_date,
                tm_analysis_status, locked, match_type, eq_word_count )
				values ";

            Log::doLog( "Pre-Translations: Total Rows to insert: " . count( $this->projectStructure[ 'query_translations' ] ) );
            //split the query in to chunks if there are too much segments
            $this->projectStructure[ 'query_translations' ]->exchangeArray( array_chunk( $this->projectStructure[ 'query_translations' ]->getArrayCopy(), 100 ) );

            Log::doLog( "Pre-Translations: Total Queries to execute: " . count( $this->projectStructure[ 'query_translations' ] ) );

            foreach ( $this->projectStructure[ 'query_translations' ] as $i => $chunk ) {

                try {
                    $this->dbHandler->query( $baseQuery . join( ",\n", $chunk ) );
                    Log::doLog( "Pre-Translations: Executed Query " . ( $i + 1 ) );
                } catch ( PDOException $e ) {
                    Log::doLog( "Segment import - DB Error: " . $e->getMessage() . " - \n" );
                    throw new PDOException( "Translations Segment import - DB Error: " . $e->getMessage() . " - $chunk", -2 );
                }

            }

        }

        //clean translations and queries
        $this->projectStructure[ 'query_translations' ]->exchangeArray( array() );

    }

    protected function _strip_external( $segment ) {
        // With regular expressions you can't strip a segment like this:
        //   <g>hello <g>world</g></g>
        // While keeping untouched this other:
        //   <g>hello</g> <g>world</g>

        // For this reason, regular expression are not suitable for this task.
        // The previous version of this function used regular expressions,
        // but was limited. The new version works in every situation and is
        // equally fast (tested in a batch execution on the segments of 500
        // real docs).

        // The function scans the entire string looking for tags and letters.
        // Spaces and self-closing tags are ignored. After the string scan,
        // the function remembers the first and last letter, and the positions
        // of all tags openings/closures. In the second step the function checks
        // all the tags opened or closed between the first and last letter, and
        // ensures that closures and openings of those tags are not stripped out.

        $segmentLength = strlen( $segment );

        // This is the fastest way I found to spot Unicode whitespaces in the string.
        // Removing this step gives a gain of 7% in speed.
        $isSpace = array();
        if ( preg_match_all( '|[\pZ\pC]+|u', $segment, $matches, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $matches[ 0 ] as $match ) {
                // All the bytes in the matched groups are whitespaces and must be
                // ignored in the next steps
                $start = $match[ 1 ];
                $end   = $start + strlen( $match[ 0 ] );
                for ( $i = $start; $i < $end; $i++ ) {
                    $isSpace[ $i ] = true;
                }
            }
        }

        // Used as a stack: push on tag openings, pop on tag closure
        $openings = array();
        // Stores all the tags found: key is '<' position of the opening tag,
        // value is '>' position of the closure tag.
        $tags = array();
        // If the XML in the segment is malformed, no stripping is performed and the
        // segment is returned as it is
        $malformed = false;

        // The positions of first and last letters
        $firstLetter = -1;
        $lastLetter  = -1;

        // Scan the input segment
        for ( $i = 0; $i < $segmentLength; $i++ ) {
            if ( isset( $isSpace[ $i ] ) ) {  // Using isset is faster than checking the addressed value
                // The current char is a space, skip it
                continue;

            } elseif ( $segment[ $i ] == '<' ) {
                // A tag is starting here
                $tagStart = $i;

                if ( $i == $segmentLength - 1 ) {
                    // If this is the last char of the string, we have a problem
                    $malformed = true;
                    break;
                }

                $i++;
                // It's a closure tag if it starts with '</'
                $closureTag = ( $segment[ $i ] == '/' );

                // Fast forward to the '>' char
                while ( $i < $segmentLength && $segment[ $i ] != '>' ) {
                    $i++;
                }

                if ( $i == $segmentLength && $segment[ $i ] != '>' ) {
                    // If we reached the end of the string and no '>' was found
                    // the segment is malformed
                    $malformed = true;
                    break;
                }

                if ( $segment[ $i - 1 ] == '/' ) {
                    // If the tag ends with '/>' it's a self-closing tag, and
                    // it can be skipped
                    continue;

                } else {
                    if ( $closureTag ) {
                        // It's a closure tag
                        if ( count( $openings ) == 0 ) {
                            // If there are no openings in the stack the input is malformed
                            $malformed = true;
                            break;
                        }
                        $opening = array_pop( $openings );
                        // Remember the tag opening and closure for later
                        $tags[ $opening ] = $i;

                    } else {
                        // It's an opening tag, add it to the stack
                        $openings[] = $tagStart;
                        // Following line ensures that the tags in the array
                        // are sorted by openings; leaving just the assignment in the
                        // closure handling code would make the array sorted by
                        // closures, breaking the logic of the loop in the next step
                        $tags[ $tagStart ] = -1;
                    }
                }

            } else {
                // If here, the char is not a space and it's not inside a tag
                if ( $firstLetter == -1 ) {
                    $firstLetter = $i;
                }
                $lastLetter = $i;
            }
        }

        if ( count( $openings ) != 0 ) {
            // If after the entire string scan we have pending openings in the stack,
            // the input is malformed
            $malformed = true;
        }

        if ( $malformed ) {
            // If malformed don't strip nothing, return the input as it is
            $before       = '';
            $cleanSegment = $segment;
            $after        = '';

        } elseif ( $firstLetter == -1 ) {
            // No letters found, so the entire segment can be stripped
            $before       = $segment;
            $cleanSegment = '';
            $after        = '';

        } else {
            // Here is the regular situation.
            // Start supposing that the output segment starts at the first letter
            // and ends at the last one.
            $segStart = $firstLetter;
            $segEnd   = $lastLetter;

            // Loop through all the tags found
            foreach ( $tags as $start => $end ) {
                // At the first tag starting after the last letter we're done here
                if ( $start > $lastLetter ) {
                    break;
                }
                if ( $start > $firstLetter && $start < $lastLetter ) {
                    // Found an opening tag in the meaningful slice: ensure that
                    // the closure tag is not stripped out
                    $segEnd = max( $segEnd, $end );
                } elseif ( $end > $firstLetter && $end < $lastLetter ) {
                    // Found a closure tag in the meaningful slice: ensure that
                    // the opening tag is not stripped out
                    $segStart = min( $segStart, $start );
                }
            }

            // Almost finished
            $before       = substr( $segment, 0, $segStart );
            $cleanSegment = substr( $segment, $segStart, $segEnd - $segStart + 1 );
            $after        = substr( $segment, $segEnd + 1 );
            // Following line needed in case $segEnd points to the last char of $segment
            if ( $after === false ) {
                $after = '';
            }
        }

        return array( 'prec' => $before, 'seg' => $cleanSegment, 'succ' => $after );
    }

    /**
     * @param $mimeType
     * @return bool
     */
    public static function notesAllowedByMimeType( $mimeType ) {
        return in_array( $mimeType, array('sdlxliff', 'xliff', 'xlf') ) ;
    }

    public static function getExtensionFromMimeType( $mime_type ) {

        if ( array_key_exists( $mime_type, INIT::$MIME_TYPES ) ) {
            if ( array_key_exists( 'default', INIT::$MIME_TYPES[ $mime_type ] ) ) {
                return INIT::$MIME_TYPES[ $mime_type ][ 'default' ];
            }

            return INIT::$MIME_TYPES[ $mime_type ][ array_rand( INIT::$MIME_TYPES[ $mime_type ] ) ]; // rand :D
        }

        return null;

    }


    /**
     * Extract internal reference base64 files
     * and store their index in $this->projectStructure
     *
     * @param $project_file_id
     * @param $xliff_file_array
     *
     * @return null|int $file_reference_id
     *
     * @throws Exception
     *
     * @deprecated
     * @removed
     */
    protected function _extractFileReferences( $project_file_id, $xliff_file_array ) {

        $fName = self::_sanitizeName( $xliff_file_array[ 'attr' ][ 'original' ] );

        if ( $fName != false ) {
            $fName = $this->dbHandler->escape( $fName );
        } else {
            $fName = '';
        }

        $serialized_reference_meta     = array();
        $serialized_reference_binaries = array();

        /* Fix: PHP Warning:  Invalid argument supplied for foreach() */
        if ( !isset( $xliff_file_array[ 'reference' ] ) ) {
            return null;
        }

        foreach ( $xliff_file_array[ 'reference' ] as $pos => $ref ) {

            $found_ref = true;

            $_ext = self::getExtensionFromMimeType( $ref[ 'form-type' ] );
            if ( $_ext !== null ) {

                //insert in database if exists extension
                //and add the id_file_part to the segments insert statement

                $refName = $this->projectStructure[ 'id_project' ] . "-" . $pos . "-" . $fName . "." . $_ext;

                $serialized_reference_meta[ $pos ][ 'filename' ]   = $refName;
                $serialized_reference_meta[ $pos ][ 'mime_type' ]  = $this->dbHandler->escape( $ref[ 'form-type' ] );
                $serialized_reference_binaries[ $pos ][ 'base64' ] = $ref[ 'base64' ];

                if ( !is_dir( INIT::$REFERENCE_REPOSITORY ) ) {
                    mkdir( INIT::$REFERENCE_REPOSITORY, 0755 );
                }

                $wBytes = file_put_contents( INIT::$REFERENCE_REPOSITORY . "/$refName", base64_decode( $ref[ 'base64' ] ) );

                if ( !$wBytes ) {
                    throw new Exception ( "Failed to import references. $wBytes Bytes written.", -11 );
                }

            }

        }

        if ( isset( $found_ref ) && !empty( $serialized_reference_meta ) ) {

            $serialized_reference_meta     = serialize( $serialized_reference_meta );
            $serialized_reference_binaries = serialize( $serialized_reference_binaries );
            $queries                       = "INSERT INTO file_references ( id_project, id_file, part_filename, serialized_reference_meta, serialized_reference_binaries ) VALUES ( " . $this->projectStructure[ 'id_project' ] . ", $project_file_id, '$fName', '$serialized_reference_meta', '$serialized_reference_binaries' )";

            $this->dbHandler->query( $queries );

            $affected = $this->dbHandler->affected_rows;
            $last_id  = "SELECT LAST_INSERT_ID() as fpID";
            $result   = $this->dbHandler->query_first( $last_id );
            $result   = $result[ 0 ];

            //last Insert id
            $file_reference_id = $result[ 'fpID' ];
            $this->projectStructure[ 'file_references' ]->offsetSet( $project_file_id, $file_reference_id );

            if ( !$affected || !$file_reference_id ) {
                throw new Exception ( "Failed to import references.", -12 );
            }

            return $file_reference_id;
        }
    }

    protected static function _sanitizeName( $nameString ) {

        $nameString = preg_replace( '/[^\p{L}0-9a-zA-Z_\.\-]/u', "_", $nameString );
        $nameString = preg_replace( '/[_]{2,}/', "_", $nameString );
        $nameString = str_replace( '_.', ".", $nameString );

        // project name validation
        $pattern = '/^[\p{L}\ 0-9a-zA-Z_\.\-]+$/u';

        if ( !preg_match( $pattern, $nameString, $rr ) ) {
            return false;
        }

        return $nameString;

    }

    public function generatePassword( $length = 12 ) {
        return CatUtils::generate_password( $length );
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
     */
    private function addNotesToProjectStructure( $trans_unit, $fid ) {

        $internal_id = self::sanitizedUnitId( $trans_unit[ 'attr' ][ 'id' ], $fid );
        if ( isset( $trans_unit[ 'notes' ] ) ) {
            foreach ( $trans_unit[ 'notes' ] as $note ) {
                $this->initArrayObject( 'notes', $internal_id );

                if ( !$this->projectStructure[ 'notes' ][ $internal_id ]->offsetExists( 'entries' ) ) {
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'entries', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'segment_ids', array() );
                }

                $this->projectStructure[ 'notes' ][ $internal_id ][ 'entries' ]->append( $note[ 'raw-content' ] );
            }
        }
    }

    private function initArrayObject( $key, $id ) {
        if ( !$this->projectStructure[ $key ]->offsetExists( $id ) ) {
            $this->projectStructure[ $key ]->offsetSet( $id, new ArrayObject() );
        }
    }

    private static function sanitizedUnitId( $trans_unitID, $fid ) {
        return $fid . "|" . $trans_unitID;
    }

    private function isConversionToEnforce( $fileName ) {
        $isAConvertedFile = true;

        $fullPath = INIT::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure[ 'uploadToken' ] . DIRECTORY_SEPARATOR . $fileName;
        try {
            $isAConvertedFile = DetectProprietaryXliff::isConversionToEnforce( $fullPath );

            if ( -1 === $isAConvertedFile ) {
                $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                        "code"    => -8,
                        "message" => "Proprietary xlf format detected. Not able to import this XLIFF file. ($fileName)"
                );
                if( PHP_SAPI != 'cli' ){
                    setcookie( "upload_session", "", time() - 10000 );
                }
            }

        } catch ( Exception $e ) {
            Log::doLog( $e->getMessage() );
        }

        return $isAConvertedFile;
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
     * @return array
     */
    private function setPrivateTMKeys( $firstTMXFileName ) {

        foreach ( $this->projectStructure[ 'private_tm_key' ] as $i => $_tmKey ) {

            $this->tmxServiceWrapper->setTmKey( $_tmKey[ 'key' ] );

            try {

                $keyExists = $this->tmxServiceWrapper->checkCorrectKey();

                if ( !isset( $keyExists ) || $keyExists === false ) {
                    Log::doLog( __METHOD__ . " -> TM key is not valid." );

                    throw new Exception( "TM key is not valid: " . $_tmKey[ 'key' ], -4 );
                }

            } catch ( Exception $e ) {

                $this->projectStructure[ 'result' ][ 'errors' ][] = array(
                        "code" => $e->getCode(), "message" => $e->getMessage()
                );

                return false;
            }

            // TODO: evaluate if it's the case to remove this line from here. This is required for later calls
            // for instance when it's time to push the TMX the TM Engine.

        }


        //check if the MyMemory keys provided by the user are already associated to him.
        if ( $this->projectStructure[ 'userIsLogged' ] ) {

            $mkDao = new TmKeyManagement_MemoryKeyDao( $this->dbHandler );

            $searchMemoryKey      = new TmKeyManagement_MemoryKeyStruct();
            $searchMemoryKey->uid = $this->projectStructure[ 'uid' ];

            $userMemoryKeys = $mkDao->read( $searchMemoryKey );

            $userTmKeys             = array();
            $memoryKeysToBeInserted = array();

            //extract user tm keys
            foreach ( $userMemoryKeys as $_memoKey ) {
                /**
                 * @var $_memoKey TmKeyManagement_MemoryKeyStruct
                 */
                $userTmKeys[] = $_memoKey->tm_key->key;
            }


            foreach ( $this->projectStructure[ 'private_tm_key' ] as $_tmKey ) {

                if ( !in_array( $_tmKey[ 'key' ], $userTmKeys ) ) {
                    $newMemoryKey   = new TmKeyManagement_MemoryKeyStruct();
                    $newTmKey       = new TmKeyManagement_TmKeyStruct();
                    $newTmKey->key  = $_tmKey[ 'key' ];
                    $newTmKey->tm   = true;
                    $newTmKey->glos = true;

                    //THIS IS A NEW KEY and must be inserted into the user keyring
                    //So, if a TMX file is present in the list of uploaded files, and the Key name provided is empty
                    // assign TMX name to the key
                    $newTmKey->name = ( !empty( $_tmKey[ 'name' ] ) ? $_tmKey[ 'name' ] : $firstTMXFileName );

                    $newMemoryKey->tm_key = $newTmKey;
                    $newMemoryKey->uid    = $this->projectStructure[ 'uid' ];

                    $memoryKeysToBeInserted[] = $newMemoryKey;
                } else {
                    Log::doLog( 'skip insertion' );
                }

            }
            try {
                $mkDao->createList( $memoryKeysToBeInserted );

            } catch ( Exception $e ) {
                Log::doLog( $e->getMessage() );

                # Here we handle the error, displaying HTML, logging, ...
                $output = "<pre>\n";
                $output .= $e->getMessage() . "\n\t";
                $output .= "</pre>";
                Utils::sendErrMailReport( $output );

            }

        }

        //the base case is when the user clicks on "generate private TM" button:
        //a (user, pass, key) tuple is generated and can be inserted
        //if it comes with it's own key without querying the creation API, create a (key,key,key) user
        if ( empty( $this->projectStructure[ 'private_tm_user' ] ) ) {
            $this->projectStructure[ 'private_tm_user' ] = $this->projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ];
            $this->projectStructure[ 'private_tm_pass' ] = $this->projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ];
        }

        $this->projectStructure['private_tm_key'] = $this->features->filter('filter_project_manager_private_tm_key',
                $this->projectStructure['private_tm_key'],
                array( 'project_structure' => $this->projectStructure )
        );

        if ( count( $this->projectStructure[ 'private_tm_key' ] ) > 0 ) {
            $this->tmxServiceWrapper->setTmKey( $this->projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ] );
        }
    }

    /**
     * Decide if the pair source and target should be considered translated.
     * If the strings are different, it's always considered translated.
     *
     * If they  are identical, let plugins decide how to treat the case.
     *
     * @param $source
     * @param $target
     *
     * @return bool|mixed
     */
    private function __isTranslated( $source, $target ) {
        if ( $source != $target ) {
            return true ;
        }
        else {
            // evaluate if identical source and target should be considered non translated
            $identicalSourceAndTargetIsTranslated = false;
            $identicalSourceAndTargetIsTranslated = $this->features->filter(
                    'filterIdenticalSourceAndTargetIsTranslated',
                    $identicalSourceAndTargetIsTranslated, $this->projectStructure
            );

            return $identicalSourceAndTargetIsTranslated ;
        }
    }

}
