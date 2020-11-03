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
use ConnectedServices\GDrive as GDrive;
use ConnectedServices\GDrive\Session;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;
use Jobs\SplitQueue;
use Matecat\XliffParser\XliffParser;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use Matecat\XliffParser\XliffUtils\XliffVersionDetector;
use ProjectManager\ProjectManagerModel;
use SubFiltering\Filter;
use Teams\TeamStruct;
use Translators\TranslatorsModel;

class ProjectManager {

    /**
     * Counter fro the total number of segments in the project with the flag ( show_in_cattool == true )
     *
     * @var int
     */
    protected $show_in_cattool_segs_counter = 0;
    protected $files_word_count             = 0;
    protected $total_segments               = 0;
    protected $min_max_segments_id          = [];

    /**
     * @var ArrayObject|RecursiveArrayObject
     */
    protected $projectStructure;

    protected $tmxServiceWrapper;

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
    protected $project;

    /**
     * @var Session
     */
    protected $gdriveSession;

    /**
     * @var FeatureSet
     */
    protected $features;

    const TRANSLATED_USER = 'translated_user';

    /**
     * @var Users_UserStruct ;
     */
    protected $user;

    /**
     * @var Database|IDatabase
     */
    protected $dbHandler;
    protected $filter;

    /**
     * ProjectManager constructor.
     *
     * @param ArrayObject|null $projectStructure
     *
     * @throws Exception
     * @throws \Exceptions\NotFoundException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     */
    public function __construct( ArrayObject $projectStructure = null ) {


        if ( $projectStructure == null ) {
            $projectStructure = new RecursiveArrayObject(
                    [
                            'HTTP_HOST'                    => null,
                            'id_project'                   => null,
                            'create_date'                  => date( "Y-m-d H:i:s" ),
                            'id_customer'                  => self::TRANSLATED_USER,
                            'project_features'             => [],
                            'user_ip'                      => null,
                            'project_name'                 => null,
                            'result'                       => [ "errors" => [], "data" => [] ],
                            'private_tm_key'               => 0,
                            'private_tm_user'              => null,
                            'private_tm_pass'              => null,
                            'uploadToken'                  => null,
                            'array_files'                  => [], //list of file names
                            'array_files_meta'             => [], //list of file names
                            'file_id_list'                 => [],
                            'source_language'              => null,
                            'target_language'              => null,
                            'job_subject'                  => 'general',
                            'mt_engine'                    => null,
                            'tms_engine'                   => null,
                            'ppassword'                    => null,
                            'array_jobs'                   => [
                                    'job_list'      => [],
                                    'job_pass'      => [],
                                    'job_segments'  => [],
                                    'job_languages' => [],
                                    'payable_rates' => [],
                            ],
                            'job_segments'                 => [], //array of job_id => [  min_seg, max_seg  ]
                            'segments'                     => [], //array of files_id => segments[  ]
                            'segments-original-data'       => [], //array of files_id => segments-original-data[  ]
                            'segments_metadata'            => [], //array of segments_metadata
                            'translations'                 => [],
                            'notes'                        => [],
                            'context-group'                => [],
                        //one translation for every file because translations are files related
                            'status'                       => Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
                            'job_to_split'                 => null,
                            'job_to_split_pass'            => null,
                            'split_result'                 => null,
                            'job_to_merge'                 => null,
                            'lang_detect_files'            => [],
                            'tm_keys'                      => [],
                            'userIsLogged'                 => false,
                            'uid'                          => null,
                            'skip_lang_validation'         => false,
                            'pretranslate_100'             => 0,
                            'only_private'                 => 0,
                            'owner'                        => '',
                            'word_count_type'              => '',
                            'metadata'                     => [],
                            'id_assignee'                  => null,
                            'session'                      => ( isset( $_SESSION ) ? $_SESSION : false ),
                            'instance_id'                  => ( !is_null( INIT::$INSTANCE_ID ) ? (int)INIT::$INSTANCE_ID : 0 ),
                            'id_team'                      => null,
                            'team'                         => null,
                            'sanitize_project_options'     => true,
                            'file_segments_count'          => [],
                            'due_date'                     => null,
                            'target_language_mt_engine_id' => [],
                            'standard_analysis_wc'         => 0
                    ] );

        }

        $this->projectStructure = $projectStructure;

        //get the TMX management component from the factory
        $this->tmxServiceWrapper = new TMSService();

        $this->langService = Langs_Languages::getInstance();

        $this->checkTMX = 0;

        $this->dbHandler = Database::obtain();

        $this->features = new FeatureSet( $this->_getRequestedFeatures() );

        if ( !empty( $this->projectStructure[ 'id_customer' ] ) ) {
            $this->features->loadAutoActivableOwnerFeatures( $this->projectStructure[ 'id_customer' ] );
        }

        $this->_log( $this->features->getCodes() );

        $this->filter = Filter::getInstance( $this->features );

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
    }

    protected function _log( $_msg ) {
        Log::doJsonLog( $_msg );
    }

    /**
     * @return array
     */
    protected function _getRequestedFeatures() {
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
     * Project name is required to build the analyize URL. Project name is memoized in a instance variable
     * so to perform the check only the first time on $projectStructure['project_name'].
     *
     * @return bool|mixed
     * @throws Exception
     */
    protected function _sanitizeProjectName() {
        $newName = self::_sanitizeName( $this->projectStructure[ 'project_name' ] );

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
     * @param \Teams\TeamStruct $team
     */
    public function setTeam( TeamStruct $team ) {
        $this->projectStructure[ 'team' ]    = $team;
        $this->projectStructure[ 'id_team' ] = $team->id;
    }

    /**
     * @param $id
     *
     * @throws \Exceptions\NotFoundException
     */
    public function setProjectIdAndLoadProject( $id ) {
        $this->project = Projects_ProjectDao::findById( $id, 60 * 60 );
        if ( $this->project == false ) {
            throw new \Exceptions\NotFoundException( "Project was not found: id $id " );
        }
        $this->projectStructure[ 'id_project' ]  = $this->project->id;
        $this->projectStructure[ 'id_customer' ] = $this->project->id_customer;

        $this->reloadFeatures();

    }

    public function setProjectAndReLoadFeatures( Projects_ProjectStruct $pStruct ) {
        $this->project                           = $pStruct;
        $this->projectStructure[ 'id_project' ]  = $this->project->id;
        $this->projectStructure[ 'id_customer' ] = $this->project->id_customer;
        $this->reloadFeatures();
    }

    private function reloadFeatures() {
        $this->features = new FeatureSet();
        $this->features->loadForProject( $this->project );
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
        $options = $this->projectStructure[ 'metadata' ];

        /**
         * Here we have the opportunity to add other features as dependencies of the ones
         * which are already explicitly set.
         */
        $this->features->loadProjectDependenciesFromProjectMetadata( $options );

        if ( $this->projectStructure[ 'sanitize_project_options' ] ) {
            $options = $this->sanitizeProjectOptions( $options );
        }

        if ( empty( $options ) ) {
            return;
        }

        $dao = new Projects_MetadataDao();

        $featureCodes = $this->features->getCodes();
        if ( !empty( $featureCodes ) ) {
            $dao->set( $this->projectStructure[ 'id_project' ],
                    Projects_MetadataDao::FEATURES_KEY,
                    implode( ',', $featureCodes )
            );
        }

        foreach ( $options as $key => $value ) {
            $dao->set(
                    $this->projectStructure[ 'id_project' ],
                    $key,
                    $value
            );
        }
    }

    private function sanitizeProjectOptions( $options ) {
        $sanitizer = new ProjectOptionsSanitizer( $options );

        $sanitizer->setLanguages(
                $this->projectStructure[ 'source_language' ],
                $this->projectStructure[ 'target_language' ]
        );

        return $sanitizer->sanitize();
    }

    /**
     * Perform sanitization of the projectStructure and assign errors.
     * Resets the errors array to avoid subsequent calls to pile up errors.
     *
     * @throws Exception
     */
    public function sanitizeProjectStructure() {

        $this->projectStructure[ 'result' ][ 'errors' ] = new ArrayObject();
        $this->_sanitizeProjectName();
        $this->_validateUploadToken();

    }

    /**
     * Creates record in projects tabele and instantiates the project struct
     * internally.
     *
     */
    private function __createProjectRecord() {
        $this->project = ProjectManagerModel::createProjectRecord( $this->projectStructure );
    }

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
            $teamDao = new \Teams\TeamDao();
            $teamDao->destroyCacheAssignee( $this->projectStructure[ 'team' ] );

        }

    }

    /**
     * @return bool|void
     * @throws \Exception
     */
    public function createProject() {

        try {
            $this->sanitizeProjectStructure();
        } catch ( Exception $e ) {
            //Found Errors
        }

        $fs = FilesStorageFactory::create();

        if ( !empty( $this->projectStructure[ 'session' ][ 'uid' ] ) ) {
            $this->gdriveSession = GDrive\Session::getInstanceForCLI( $this->projectStructure[ 'session' ] );
        }

        $this->__checkForProjectAssignment();

        /**
         * This is the last chance to perform the validation before the project is created
         * in the database.
         * Validations should populate the projectStructure with errors and codes.
         */
        $this->features->run( 'validateProjectCreation', $this->projectStructure );

        /**
         * @var ArrayObject $this ->projectStructure['result']['errors']
         */
        if ( $this->projectStructure[ 'result' ][ 'errors' ]->count() ) {
            return false;
        }

        $this->__createProjectRecord();
        $this->saveMetadata();

        //sort files in order to process TMX first
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
                    $firstTMXFileName = ( empty( $firstTMXFileName ) ? $firstTMXFileName = $fileName : null );
                    $this->checkTMX   = 1;
                }

                //not used at moment but needed if we want to do a poll for status
                if ( $meta[ 'isGlossary' ] ) {
                    $this->checkGlossary = 1;
                }

                //prepend in front of the list
                array_unshift( $sortedFiles, $fileName );
                array_unshift( $sortedMeta, $meta );

            } else {

                //append at the end of the list
                array_push( $sortedFiles, $fileName );
                array_push( $sortedMeta, $meta );
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

        $uploadDir = $this->uploadDir = INIT::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure[ 'uploadToken' ];

        \Log::doJsonLog( $uploadDir );

        //we are going to access the storage, get model object to manipulate it
        $linkFiles = $fs->getHashesFromDir( $this->uploadDir );

        \Log::doJsonLog( $linkFiles );

        /*
            loop through all input files to
            1) upload INSERT INTMX and Glossaries
        */
        try {
            $this->_pushTMXToMyMemory();
        } catch ( Exception $e ) {
            $this->_log( $e->getMessage() );

            //exit project creation
            return false;
        }
        //TMX Management

        /*
            loop through all input files to
            2)convert, in case, non standard XLIFF files to a format that Matecat understands

            Note that XLIFF that don't need conversion are moved anyway as they are to cache in order not to alter the workflow
         */

        foreach ( $this->projectStructure[ 'array_files' ] as $pos => $fileName ) {

            // get corresponding meta
            $meta            = $this->projectStructure[ 'array_files_meta' ][ $pos ];
            $mustBeConverted = $meta[ 'mustBeConverted' ];

            //if it's one of the listed formats or conversion is not enabled in first place
            if ( !$mustBeConverted ) {
                /*
                   filename is already an xliff and it's in upload directory
                   we have to make a cache package from it to avoid altering the original path
                 */
                //get file
                $filePathName = "$this->uploadDir/$fileName";

                // NOTE: 12 Aug 2019
                // I am not absolute sure that the queue file exists,
                // so I check it and in negative case I force the download of the file to file system from S3
                $isFsOnS3 = AbstractFilesStorage::isOnS3();

                if ( $isFsOnS3 and false === file_exists( $filePathName ) ) {
                    $this->getSingleS3QueueFile( $fileName );
                }

                // calculate hash + add the fileName, if i load 3 equal files with the same content
                // they will be squashed to the last one
                $sha1 = sha1_file( $filePathName );

                // make a cache package (with work/ only, empty orig/)
                try {
                    $fs->makeCachePackage( $sha1, $this->projectStructure[ 'source_language' ], false, $filePathName );
                } catch ( \Exception $e ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code"    => -230,
                            "message" => $e->getMessage()
                    ];
                }

                // put reference to cache in upload dir to link cache to session
                $fs->linkSessionToCacheForAlreadyConvertedFiles(
                        $sha1,
                        $this->projectStructure[ 'source_language' ],
                        $this->projectStructure[ 'uploadToken' ],
                        $fileName
                );

                //add newly created link to list
                $linkFiles[ 'conversionHashes' ][ 'sha' ][] = $sha1 . $this->__getStorageFilesDelimiter() . $this->projectStructure[ 'source_language' ];

                $linkFiles[ 'conversionHashes' ][ 'fileName' ][ $sha1 . $this->__getStorageFilesDelimiter() . $this->projectStructure[ 'source_language' ] ][] = $fileName;

                //when the same sdlxliff is uploaded more than once with different names
                $linkFiles[ 'conversionHashes' ][ 'sha' ] = array_unique( $linkFiles[ 'conversionHashes' ][ 'sha' ] );
                unset( $sha1 );
            }
        }


        try {
            $this->_zipFileHandling( $linkFiles );
        } catch ( Exception $e ) {
            $this->_log( $e );
            //Zip file Handling
            $this->projectStructure[ 'result' ][ 'errors' ][] = [
                    "code"    => $e->getCode(),
                    "message" => $e->getMessage()
            ];
        }

        //now, upload dir contains only hash-links
        //we start copying files to "file" dir, inserting metadata in db and extracting segments
        $totalFilesStructure = [];
        foreach ( $linkFiles[ 'conversionHashes' ][ 'sha' ] as $linkFile ) {
            //converted file is inside cache directory
            //get hash from file name inside UUID dir
            $hashFile = AbstractFilesStorage::basename_fix( $linkFile );
            $hashFile = explode( $this->__getStorageFilesDelimiter(), $hashFile );

            // Example:
            // $hashFile[ 0 ] = 917f7b03c8f54350fb65387bda25fbada43ff7d8
            // $hashFile[ 1 ] = it-it
            $sha1_original = $hashFile[ 0 ];
            $lang          = $hashFile[ 1 ];

            //use hash and lang to fetch file from package
            $cachedXliffFilePathName = $fs->getXliffFromCache( $sha1_original, $lang );

            //associate the hash to the right file in upload directory
            //get original file name, to insert into DB and cp in storage
            //PLEASE NOTE, this can be an array when the same file added more
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

                //check if the files language equals the source language. If not, set an error message.
                if ( !$this->projectStructure[ 'skip_lang_validation' ] ) {
                    $this->validateFilesLanguages();
                }

            } catch ( Exception $e ) {

                if ( $e->getCode() == -10 ) {

                    //Failed to store the original Zip
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code" => -10, "message" => $e->getMessage()
                    ];

                } elseif ( $e->getCode() == -11 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code" => -7, "message" => "Failed to store reference files on disk. Permission denied"
                    ];
                } elseif ( $e->getCode() == -12 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code" => -7, "message" => "Failed to store reference files in database"
                    ];
                } // SEVERE EXCEPTIONS HERE
                elseif ( $e->getCode() == -6 ) {
                    //"File not found on server after upload."
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code"    => -6,
                            "message" => $e->getMessage()
                    ];
                } elseif ( $e->getCode() == -3 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code"    => -7,
                            "message" => "File not found. Failed to save XLIFF conversion on disk."
                    ];
                } elseif ( $e->getCode() == -13 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code" => -13, "message" => $e->getMessage()
                    ];
                    //we can not write to disk!! Break project creation
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

            //array append like array_merge but it do not renumber the numeric keys, so we can preserve the files id
            $totalFilesStructure += $filesStructure;

        } //end of conversion hash-link loop

        //Throws exception
        try {

            //Try to extract segments after all checks
            $exceptionsFound = 0;
            foreach ( $totalFilesStructure as $fid => $file_info ) {

                try {
                    $this->_extractSegments( $fid, $file_info );
                } catch ( Exception $e ) {

                    $this->_log( $totalFilesStructure );
                    $this->_log( "Code: " . $e->getCode() );
                    $this->_log( "Count fileSt.: " . count( $totalFilesStructure ) );
                    $this->_log( "Exceptions: " . $exceptionsFound );

                    if ( $e->getCode() == -1 && count( $totalFilesStructure ) > 1 && $exceptionsFound < count( $totalFilesStructure ) ) {
                        $this->_log( "No text to translate in the file {$e->getMessage()}." );
                        $exceptionsFound += 1;
                        continue;
                    } else {
                        throw $e;
                    }
                }

            }

            if ( $this->total_segments > 100000 || ( $this->files_word_count * count( $this->projectStructure[ 'target_language' ] ) ) > 1000000 ) {
                //Allow projects with only one target language and 100000 segments ( ~ 550.000 words )
                //OR
                //A multi language project with max 420000 segments ( EX: 42000 segments in 10 languages ~ 2.700.000 words )
                throw new Exception( "MateCat is unable to create your project. We can do it for you. Please contact " . INIT::$SUPPORT_MAIL, 128 );
            }

            $this->features->run( "beforeInsertSegments", $this->projectStructure,
                    [
                            'total_project_segments' => $this->total_segments,
                            'files_wc'               => $this->files_word_count
                    ]
            );

            foreach ( $totalFilesStructure as $fid => $empty ) {
                $this->_storeSegments( $fid );
            }

            $this->_createJobs( $this->projectStructure );
            $this->writeFastAnalysisData();

        } catch ( Exception $e ) {

            if ( $e->getCode() == -1 ) {
                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code" => -1, "message" => "No text to translate in the file {$e->getMessage()}."
                ];
                if ( INIT::$FILE_STORAGE_METHOD != 's3' ) {
                    $fs->deleteHashFromUploadDir( $this->uploadDir, $linkFile );
                }
            } elseif ( $e->getCode() == -4 ) {
                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code"    => -7,
                        "message" => "Internal Error. Xliff Import: Error parsing. ( {$e->getMessage()} )"
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
                        "code" => $e->getCode(), "message" => $e->getMessage()
                ];
            }

            $this->_log( $this->projectStructure[ 'result' ][ 'errors' ] );

            //EXIT
            return false;
        }

        $this->projectStructure[ 'status' ] = ( INIT::$VOLUME_ANALYSIS_ENABLED ) ? Constants_ProjectStatus::STATUS_NEW : Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE;

        if ( $this->show_in_cattool_segs_counter == 0 ) {
            $this->_log( "Segment Search: No segments in this project - \n" );
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
        $this->projectStructure[ 'result' ][ 'project_name' ]    = $this->projectStructure[ 'project_name' ];
        $this->projectStructure[ 'result' ][ 'source_language' ] = $this->projectStructure[ 'source_language' ];
        $this->projectStructure[ 'result' ][ 'target_language' ] = $this->projectStructure[ 'target_language' ];
        $this->projectStructure[ 'result' ][ 'status' ]          = $this->projectStructure[ 'status' ];
        $this->projectStructure[ 'result' ][ 'lang_detect' ]     = $this->projectStructure[ 'lang_detect_files' ];

        $k_file = 0;
        foreach ( $totalFilesStructure as $fid => $file_info ) {
            if ( isset( $this->projectStructure[ 'instructions' ][ $k_file ] ) && !empty( $this->projectStructure[ 'instructions' ][ $k_file ] ) ) {
                $this->_insertInstructions( $fid, $this->projectStructure[ 'instructions' ][ $k_file ] );
            }
            $k_file++;
        }


        if ( INIT::$VOLUME_ANALYSIS_ENABLED ) {
            $this->projectStructure[ 'result' ][ 'analyze_url' ] = $this->getAnalyzeURL();
        }

        Projects_ProjectDao::updateAnalysisStatus(
                $this->projectStructure[ 'id_project' ],
                $this->projectStructure[ 'status' ],
                $this->files_word_count * count( $this->projectStructure[ 'array_jobs' ][ 'job_languages' ] )
        );

        $this->pushActivityLog();

        Database::obtain()->begin();

        //pre-fetch Analysis page in transaction and store in cache
        ( new Projects_ProjectDao() )->destroyCacheForProjectData( $this->projectStructure[ 'id_project' ], $this->projectStructure[ 'ppassword' ] );
        ( new Projects_ProjectDao() )->setCacheTTL( 60 * 60 * 24 )->getProjectData( $this->projectStructure[ 'id_project' ], $this->projectStructure[ 'ppassword' ] );

        $this->features->run( 'postProjectCreate', $this->projectStructure );

        Database::obtain()->commit();

        $this->features->run( 'postProjectCommit', $this->projectStructure );

        try {

            if ( AbstractFilesStorage::isOnS3() ) {
                \Log::doJsonLog( 'Deleting folder' . $this->uploadDir . ' from S3' );
                /** @var $fs S3FilesStorage */
                $fs->deleteQueue( $this->uploadDir );
            } else {
                \Log::doJsonLog( 'Deleting folder' . $this->uploadDir . ' from filesystem' );
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

            $this->_log( $output );

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
        $params[ 'bucket' ]  = \INIT::$AWS_STORAGE_BASE_BUCKET;
        $params[ 'key' ]     = $fs::QUEUE_FOLDER . DIRECTORY_SEPARATOR . $fs::getUploadSessionSafeName( $fs->getTheLastPartOfKey( $this->uploadDir ) ) . DIRECTORY_SEPARATOR . $fileName;
        $params[ 'save_as' ] = "$this->uploadDir/$fileName";
        $client->downloadItem( $params );
    }

    /**
     * @return string
     */
    private function __getStorageFilesDelimiter() {
        if ( AbstractFilesStorage::isOnS3() ) {
            return S3FilesStorage::OBJECTS_SAFE_DELIMITER;
        }

        return '|';
    }

    private function __clearFailedProject( Exception $e ) {
        $this->_log( $e->getMessage() );
        $this->_log( $e->getTraceAsString() );
        $this->_log( "Deleting Records." );
        ( new Projects_ProjectDao() )->deleteFailedProject( $this->projectStructure[ 'id_project' ] );
        ( new Files_FileDao() )->deleteFailedProjectFiles( $this->projectStructure[ 'file_id_list' ]->getArrayCopy() );
        $this->_log( "Deleted Project ID: " . $this->projectStructure[ 'id_project' ] );
        $this->_log( "Deleted Files ID: " . json_encode( $this->projectStructure[ 'file_id_list' ]->getArrayCopy() ) );
    }

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
            $segmentElement[ 'segment' ]       = Filter::getInstance( $this->features )->fromLayer0ToLayer1( $segmentElement[ 'segment' ] );

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
        Activity::save( $activity );

    }

    /**
     * @param $http_host string
     *
     * @return string
     */
    public function getAnalyzeURL() {
        return Routes::analyze(
                [
                        'project_name' => $this->projectStructure[ 'project_name' ],
                        'id_project'   => $this->projectStructure[ 'id_project' ],
                        'password'     => $this->projectStructure[ 'ppassword' ]
                ],
                [
                        'http_host' => ( is_null( $this->projectStructure[ 'HTTP_HOST' ] ) ?
                                INIT::$HTTPHOST :
                                $this->projectStructure[ 'HTTP_HOST' ]
                        ),
                ]
        );
    }

    /**
     * @throws Exception
     */
    protected function _pushTMXToMyMemory() {

        //TMX Management
        foreach ( $this->projectStructure[ 'array_files' ] as $pos => $fileName ) {

            // get corresponding meta
            $meta = $this->projectStructure[ 'array_files_meta' ][ $pos ];

            $ext = $meta[ 'extension' ];

            $file = new stdClass();
            if ( in_array( $ext, [ 'tmx', 'g' ] ) ) {

                if ( INIT::$FILE_STORAGE_METHOD == 's3' ) {
                    $this->getSingleS3QueueFile( $fileName );
                }

                $file->file_path = "$this->uploadDir/$fileName";
                $this->tmxServiceWrapper->setName( $fileName );
                $this->tmxServiceWrapper->setFile( [ $file ] );

            }

            try {

                if ( 'tmx' == $ext ) {
                    $this->tmxServiceWrapper->addTmxInMyMemory();
                    $this->features->run( 'postPushTMX', $file, $this->projectStructure[ 'id_customer' ], $this->tmxServiceWrapper->getTMKey() );
                } elseif ( 'g' == $ext ) {
                    $this->tmxServiceWrapper->addGlossaryInMyMemory();
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
            if ( 'tmx' == AbstractFilesStorage::pathinfo_fix( $fileName, PATHINFO_EXTENSION ) ) {

                $this->tmxServiceWrapper->setName( $fileName );

                $result = [];

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

                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code" => $e->getCode(), "message" => $e->getMessage()
                        ];

                        $this->_log( $e->getMessage() . "\n" . $e->getTraceAsString() );

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
                        $this->projectStructure[ 'result' ][ 'errors' ][] = [
                                "code"    => -16,
                                "message" => "The TMX you provided explicitly specifies {$result['data']['source_lang']} as source language. Check that the specified language source in the TMX file match the language source of your project or remove that specification in TMX file."
                        ];

                        $this->checkTMX = 0;

                        $this->_log( $this->projectStructure[ 'result' ] );
                    }

                }

                unset( $this->projectStructure[ 'array_files' ][ $kname ] );
                unset( $this->projectStructure[ 'array_files_meta' ][ $kname ] );

            }

        }

        if ( 1 == $this->checkTMX ) {
            //this means that none of uploaded TMX were usable for this project. Warn the user.
            $this->projectStructure[ 'result' ][ 'errors' ][] = [
                    "code"    => -16,
                    "message" => "The TMX did not contain any usable segment. Check that the languages in the TMX file match the languages of your project."
            ];

            $this->_log( $this->projectStructure[ 'result' ] );

            throw new Exception( "The TMX did not contain any usable segment. Check that the languages in the TMX file match the languages of your project." );
        }

    }

    protected function _doCheckForErrors() {

        if ( count( $this->projectStructure[ 'result' ][ 'errors' ] ) ) {
            $this->_log( "Project Creation Failed. Sent to Output all errors." );
            $this->_log( $this->projectStructure[ 'result' ][ 'errors' ] );

            return false;
        }

        return true;

    }

    protected function _zipFileHandling( $linkFiles ) {

        $fs = FilesStorageFactory::create();

        //begin of zip hashes manipulation
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

//            $this->features->run( 'addInstructionsToZipProject', $this->projectStructure, $fs->getZipDir() );

        } //end zip hashes manipulation

    }

    protected function _createJobs( ArrayObject $projectStructure ) {

        foreach ( $projectStructure[ 'target_language' ] as $target ) {

            //shorten languages and get payable rates
            $shortSourceLang = substr( $projectStructure[ 'source_language' ], 0, 2 );
            $shortTargetLang = substr( $target, 0, 2 );

            //get payable rates
            $payableRates = Analysis_PayableRates::getPayableRates( $shortSourceLang, $shortTargetLang );
            $payableRates = json_encode( $this->features->filter( "filterPayableRates", $payableRates, $projectStructure[ 'source_language' ], $target ) );

            $password = $this->generatePassword();

            $tm_key = [];

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

            }


            $this->_log( $projectStructure[ 'private_tm_key' ] );

            $projectStructure[ 'tm_keys' ] = json_encode( $tm_key );

            $newJob                       = new Jobs_JobStruct();
            $newJob->password             = $password;
            $newJob->id_project           = $projectStructure[ 'id_project' ];
            $newJob->id_translator        = is_null( $projectStructure[ 'private_tm_user' ] ) ? "" : $projectStructure[ 'private_tm_user' ];
            $newJob->source               = $projectStructure[ 'source_language' ];
            $newJob->target               = $target;
            $newJob->id_tms               = $projectStructure[ 'tms_engine' ];
            $newJob->id_mt_engine         = $projectStructure[ 'target_language_mt_engine_id' ][ $target ];
            $newJob->create_date          = date( "Y-m-d H:i:s" );
            $newJob->subject              = $projectStructure[ 'job_subject' ];
            $newJob->owner                = $projectStructure[ 'owner' ];
            $newJob->job_first_segment    = $this->min_max_segments_id[ 'job_first_segment' ];
            $newJob->job_last_segment     = $this->min_max_segments_id[ 'job_last_segment' ];
            $newJob->tm_keys              = $projectStructure[ 'tm_keys' ];
            $newJob->payable_rates        = $payableRates;
            $newJob->total_raw_wc         = $this->files_word_count;
            $newJob->only_private_tm      = $projectStructure[ 'only_private' ];

            $this->features->run( "beforeInsertJobStruct", $newJob, $projectStructure, [
                            'total_project_segments' => $this->total_segments,
                            'files_wc'               => $this->files_word_count
                    ]
            );

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

                Files_FileDao::insertFilesJob( $newJob->id, $fid );

                if ( $this->gdriveSession && $this->gdriveSession->hasFiles() ) {
                    $this->gdriveSession->createRemoteCopiesWhereToSaveTranslation( $fid, $newJob->id );
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

        $this->features->run( 'processJobsCreated', $projectStructure );

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
        $filename2SourceLangCheck = [];

        //istantiate MyMemory analyzer and detect languages for each file uploaded
        $mma = Engine::getInstance( 1 /* MyMemory */ );
        /**
         * @var $mma Engines_MyMemory
         */
        $res = $mma->detectLanguage( $filesSegments, $this->projectStructure[ 'lang_detect_files' ] );

        //for each language detected, check if it's not equal to the source language
        $langsDetected = $res[ 'responseData' ][ 'translatedText' ];
        $this->_log( __CLASS__ . " - DETECT LANG RES:", $langsDetected );
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

                $this->_log( __CLASS__ . " - DETECT LANG COMPARISON:", "$fileLang@@$sourceLang" );
                //get extended language name using google language code
                $languageExtendedName = Langs_GoogleLanguageMapper::getLanguageCode( $fileLang );

                //get extended language name using standard language code
                $langClass                  = Langs_Languages::getInstance();
                $sourceLanguageExtendedName = strtolower( $langClass->getLocalizedName( $sourceLang ) );
                $this->_log( __CLASS__ . " - DETECT LANG NAME COMPARISON:", "$sourceLanguageExtendedName@@$languageExtendedName" );

                //Check job's detected language. In case of undefined language, mark it as valid
                if ( $fileLang !== 'und' &&
                        $fileLang != $sourceLang &&
                        $sourceLanguageExtendedName != $languageExtendedName
                ) {

                    $filename2SourceLangCheck[ $currFileName ] = 'warning';

                    $languageExtendedName = ucfirst( $languageExtendedName );

                    $this->projectStructure[ 'result' ][ 'errors' ][] = [
                            "code"    => -17,
                            "message" => "The source language you selected seems " .
                                    "to be different from the source language in \"$currFileName\". Please check."
                    ];
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
    public function getSplitData( ArrayObject $projectStructure, $num_split = 2, $requestedWordsPerSplit = [] ) {

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

        $rows = ( new Jobs_JobDao() )->getSplitData( $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );

        if ( empty( $rows ) ) {
            throw new Exception( 'No segments found for job ' . $projectStructure[ 'job_to_split' ], -5 );
        }

        $row_totals = array_pop( $rows ); //get the last row ( ROLLUP )
        unset( $row_totals[ 'id' ] );

        if ( empty( $row_totals[ 'job_first_segment' ] ) || empty( $row_totals[ 'job_last_segment' ] ) ) {
            throw new Exception( 'Wrong job id or password. Job segment range not found.', -6 );
        }

        $count_type  = $this->getWordCountType( $row_totals );
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

        $counter = [];
        $chunk   = 0;

        $reverse_count = [ 'eq_word_count' => 0, 'raw_word_count' => 0 ];

        foreach ( $rows as $row ) {

            if ( !array_key_exists( $chunk, $counter ) ) {
                $counter[ $chunk ] = [
                        'eq_word_count'       => 0,
                        'raw_word_count'      => 0,
                        'segment_start'       => $row[ 'id' ],
                        'segment_end'         => 0,
                        'last_opened_segment' => 0,
                ];
            }

            $counter[ $chunk ][ 'eq_word_count' ]  += $row[ 'eq_word_count' ];
            $counter[ $chunk ][ 'raw_word_count' ] += $row[ 'raw_word_count' ];
            $counter[ $chunk ][ 'segment_end' ]    = $row[ 'id' ];

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

                $reverse_count[ 'eq_word_count' ]  += (int)$counter[ $chunk ][ 'eq_word_count' ];
                $reverse_count[ 'raw_word_count' ] += (int)$counter[ $chunk ][ 'raw_word_count' ];

                $chunk++;
            }
        }

        if ( $total_words > $reverse_count[ $count_type ] ) {
            if ( !empty( $counter[ $chunk ] ) ) {
                $counter[ $chunk ][ 'eq_word_count' ]  = round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
                $counter[ $chunk ][ 'raw_word_count' ] = round( $row_totals[ 'raw_word_count' ] - $reverse_count[ 'raw_word_count' ] );
            } else {
                $counter[ $chunk - 1 ][ 'eq_word_count' ]  += round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
                $counter[ $chunk - 1 ][ 'raw_word_count' ] += round( $row_totals[ 'raw_word_count' ] - $reverse_count[ 'raw_word_count' ] );
            }
        }

        if ( count( $counter ) < 2 ) {
            throw new Exception( 'The requested number of words for the first chunk is too large. I cannot create 2 chunks.', -7 );
        }

        $chunk = Jobs_JobDao::getByIdAndPassword($projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ]);
        $row_totals[ 'standard_analysis_count' ] = $chunk->standard_analysis_wc;

        $result = array_merge( $row_totals->getArrayCopy(), [ 'chunks' => $counter ] );

        $projectStructure[ 'split_result' ] = new ArrayObject( $result );

        return $projectStructure[ 'split_result' ];
    }


    private function getWordCountType( $row_totals ) {
        $project_count_type = $this->project->getWordCountType();
        $eq_word_count      = (float)$row_totals[ 'eq_word_count' ];
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

        // init JobDao
        $jobDao = new Jobs_JobDao();

        // job to split
        $jobToSplit = Jobs_JobDao::getByIdAndPassword( $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );

        $translatorModel   = new TranslatorsModel( $jobToSplit );
        $jTranslatorStruct = $translatorModel->getTranslator( 0 ); // no cache
        if ( !empty( $jTranslatorStruct ) && !empty( $this->projectStructure[ 'uid' ] ) ) {

            $translatorModel
                    ->setUserInvite( ( new Users_UserDao() )->setCacheTTL( 60 * 60 )->getByUid( $this->projectStructure[ 'uid' ] ) )
                    ->setDeliveryDate( $jTranslatorStruct->delivery_date )
                    ->setJobOwnerTimezone( $jTranslatorStruct->job_owner_timezone )
                    ->setEmail( $jTranslatorStruct->email )
                    ->setNewJobPassword( Utils::randomString() );

            $translatorModel->update();
        }

        // calculate total_raw_wc and standard_analysis_wc
        $num_split                     = count( $projectStructure[ 'split_result' ][ 'chunks' ] );
        $total_raw_wc                  = $jobToSplit[ 'total_raw_wc' ];
        $splitted_total_raw_wc         = round( $total_raw_wc / $num_split );
        $splitted_standard_analysis_wc = round( $projectStructure[ 'split_result' ]['standard_analysis_count'] / $num_split );

        $jobDao->updateStdWcAndTotalWc( $jobToSplit->id, $splitted_standard_analysis_wc, $splitted_total_raw_wc );

        foreach ( $projectStructure[ 'split_result' ][ 'chunks' ] as $chunk => $contents ) {

            //IF THIS IS NOT the original job, UPDATE relevant fields
            if ( $contents[ 'segment_start' ] != $projectStructure[ 'split_result' ][ 'job_first_segment' ] ) {
                //next insert
                $jobToSplit[ 'password' ]                = $this->generatePassword();
                $jobToSplit[ 'create_date' ]             = date( 'Y-m-d H:i:s' );
                $jobToSplit[ 'avg_post_editing_effort' ] = 0;
                $jobToSplit[ 'total_time_to_edit' ]      = 0;
            }

            $jobToSplit[ 'last_opened_segment' ]  = $contents[ 'last_opened_segment' ];
            $jobToSplit[ 'job_first_segment' ]    = $contents[ 'segment_start' ];
            $jobToSplit[ 'job_last_segment' ]     = $contents[ 'segment_end' ];
            $jobToSplit[ 'standard_analysis_wc' ] = $splitted_standard_analysis_wc;
            $jobToSplit[ 'total_raw_wc' ]         = $splitted_total_raw_wc;

            $stmt = $jobDao->getSplitJobPreparedStatement( $jobToSplit );
            $stmt->execute();

            $wCountManager = new WordCount_CounterModel();
            $wCountManager->initializeJobWordCount( $jobToSplit->id, $jobToSplit->password );

            if ( $this->dbHandler->affected_rows == 0 ) {
                $msg = "Failed to split job into " . count( $projectStructure[ 'split_result' ][ 'chunks' ] ) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r( $stmt->queryString, true ) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r( $jobToSplit, true ) . "\n";
//                Utils::sendErrMailReport( $msg );
                $this->_log( $msg );
                throw new Exception( 'Failed to insert job chunk, project damaged.', -8 );
            }

            $stmt->closeCursor();
            unset( $stmt );

            /**
             * Async worker to re-count avg-PEE and total-TTE for splitted jobs
             */
            SplitQueue::recount( $jobToSplit );

            //add here job id to list
            $projectStructure[ 'array_jobs' ][ 'job_list' ]->append( $projectStructure[ 'job_to_split' ] );
            //add here passwords to list
            $projectStructure[ 'array_jobs' ][ 'job_pass' ]->append( $jobToSplit[ 'password' ] );

            $projectStructure[ 'array_jobs' ][ 'job_segments' ]->offsetSet( $projectStructure[ 'job_to_split' ] . "-" . $jobToSplit[ 'password' ], new ArrayObject( [
                    $contents[ 'segment_start' ], $contents[ 'segment_end' ]
            ] ) );

        }

        ( new Jobs_JobDao() )->destroyCacheByProjectId( $projectStructure[ 'id_project' ] );

        $projectStruct = $jobToSplit->getProject( 60 * 10 );
        ( new Projects_ProjectDao() )->destroyCacheForProjectData( $projectStruct->id, $projectStruct->password );

        Shop_Cart::getInstance( 'outsource_to_external_cache' )->deleteCart();

        $this->features->run( 'postJobSplitted', $projectStructure );

    }

    /**
     * Apply new structure of job
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    public function applySplit( ArrayObject $projectStructure ) {
        Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();

        \Database::obtain()->begin();
        $this->_splitJob( $projectStructure );
        $this->dbHandler->getConnection()->commit();

    }

    /**
     * @param ArrayObject      $projectStructure
     * @param Jobs_JobStruct[] $jobStructs
     *
     * @throws Exception
     */
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

        //get the min and
        $countSplittedJobs    = count( $jobStructs );
        $total_raw_wc         = $first_job[ 'total_raw_wc' ];
        $standard_analysis_wc = $first_job[ 'standard_analysis_wc' ];

        //merge TM keys: preserve only owner's keys
        $tm_keys = [];
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
            $this->_log( __METHOD__ . " -> Merge Jobs error - TM key problem: " . $e->getMessage() );
        }

        $totalAvgPee     = 0;
        $totalTimeToEdit = 0;
        foreach ( $jobStructs as $_jStruct ) {
            $totalAvgPee     += $_jStruct->avg_post_editing_effort;
            $totalTimeToEdit += $_jStruct->total_time_to_edit;
        }
        $first_job[ 'avg_post_editing_effort' ] = $totalAvgPee;
        $first_job[ 'total_time_to_edit' ]      = $totalTimeToEdit;

        \Database::obtain()->begin();

        if ( $first_job->getTranslator() ) {
            //Update the password in the struct and in the database for the first job
            Jobs_JobDao::updateForMerge( $first_job, self::generatePassword() );
            Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();
        } else {
            Jobs_JobDao::updateForMerge( $first_job, false );
        }

        Jobs_JobDao::deleteOnMerge( $first_job );

        $wCountManager = new WordCount_CounterModel();
        $wCountManager->initializeJobWordCount( $first_job[ 'id' ], $first_job[ 'password' ] );

        $chunk = new Chunks_ChunkStruct( $first_job->toArray() );
        $this->features->run( 'postJobMerged', $projectStructure, $chunk );

        $jobDao = new Jobs_JobDao();

        $jobDao->updateStdWcAndTotalWc( $first_job[ 'id' ], ( $standard_analysis_wc * $countSplittedJobs ), ( $total_raw_wc * $countSplittedJobs ) );

        $this->dbHandler->getConnection()->commit();

        $jobDao->destroyCacheByProjectId( $projectStructure[ 'id_project' ] );

        $projectStruct = $jobStructs[ 0 ]->getProject( 60 * 10 );
        ( new Projects_ProjectDao() )->destroyCacheForProjectData( $projectStruct->id, $projectStruct->password );

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

        $xliff_file_content = $this->getXliffFileContent( $file_info[ 'path_cached_xliff' ] );
        $mimeType           = $file_info[ 'mime_type' ];

        // create Structure for multiple files
        $this->projectStructure[ 'segments' ]->offsetSet( $fid, new ArrayObject( [] ) );
        $this->projectStructure[ 'segments-original-data' ]->offsetSet( $fid, new ArrayObject( [] ) );

        $xliffParser = new XliffParser();

        try {
            $xliff        = $xliffParser->xliffToArray( $xliff_file_content );
            $xliffVersion = XliffVersionDetector::detect( $xliff_file_content );
        } catch ( Exception $e ) {
            throw new Exception( $file_info[ 'original_filename' ], $e->getCode(), $e );
        }

        // Checking that parsing went well
        if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
            $this->_log( "Xliff Import: Error parsing. " . join( "\n", $xliff[ 'parser-errors' ] ) );
            throw new Exception( $file_info[ 'original_filename' ], -4 );
        }

        //needed to check if a file has only one segment
        //for correctness: we could have more tag files in the xliff
        $_fileCounter_Show_In_Cattool = 0;

        // Creating the Query
        foreach ( $xliff[ 'files' ] as $xliff_file ) {

            if ( !array_key_exists( 'trans-units', $xliff_file ) ) {
                continue;
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
                    $show_in_cattool = 0;
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

                            //init tags
                            $seg_source[ 'mrk-ext-prec-tags' ] = '';
                            $seg_source[ 'mrk-ext-succ-tags' ] = '';

                            if ( empty( $wordCount ) ) {
                                $show_in_cattool = 0;
                            } else {

                                if ( $xliffVersion === 1 ) {
                                    $extract_external                  = $this->_strip_external( $seg_source[ 'raw-content' ] );
                                    $seg_source[ 'mrk-ext-prec-tags' ] = $extract_external[ 'prec' ];
                                    $seg_source[ 'mrk-ext-succ-tags' ] = $extract_external[ 'succ' ];
                                    $seg_source[ 'raw-content' ]       = $extract_external[ 'seg' ];
                                }

                                if ( isset( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ] ) ) {

                                    if ( $xliffVersion === 1 ) {
                                        $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ] );

                                        //
                                        // -----------------------------------------------
                                        // NOTE 2020-06-16
                                        // -----------------------------------------------
                                        //
                                        // before calling html_entity_decode function we convert
                                        // all unicode entities with no corresponding HTML entity
                                        //
                                        $extract_external[ 'seg' ]        = CatUtils::restoreUnicodeEntitesToOriginalValues( $extract_external[ 'seg' ] );
                                        $target_extract_external[ 'seg' ] = CatUtils::restoreUnicodeEntitesToOriginalValues( $target_extract_external[ 'seg' ] );

                                        // we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                        // AND IF IT IS ONLY A CHAR? like "*" ?
                                        // we can't distinguish if it is translated or not
                                        // this means that we lose the tags id inside the target if different from source
                                        $src = CatUtils::trimAndStripFromAnHtmlEntityDecoded( $extract_external[ 'seg' ] );
                                        $trg = CatUtils::trimAndStripFromAnHtmlEntityDecoded( $target_extract_external[ 'seg' ] );

                                        if ( $this->__isTranslated( $src, $trg, $xliff_trans_unit ) && !is_numeric( $src ) && !empty( $trg ) ) { //treat 0,1,2.. as translated content!

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
                                                    new ArrayObject( [ 2 => $target, 4 => $xliff_trans_unit ] )
                                            );

                                            //seg-source and target translation can have different mrk id
                                            //override the seg-source surrounding mrk-id with them of target
                                            $seg_source[ 'mrk-ext-prec-tags' ] = $target_extract_external[ 'prec' ];
                                            $seg_source[ 'mrk-ext-succ-tags' ] = $target_extract_external[ 'succ' ];

                                        }
                                    }
                                }
                            }

                            $segStruct = new Segments_SegmentStruct( [
                                    'id_file'                 => $fid,
                                    'id_project'              => $this->projectStructure[ 'id_project' ],
                                    'internal_id'             => $xliff_trans_unit[ 'attr' ][ 'id' ],
                                    'xliff_mrk_id'            => $seg_source[ 'mid' ],
                                    'xliff_ext_prec_tags'     => $seg_source[ 'ext-prec-tags' ],
                                    'xliff_mrk_ext_prec_tags' => $seg_source[ 'mrk-ext-prec-tags' ],
                                    'segment'                 => $this->filter->fromRawXliffToLayer0( $seg_source[ 'raw-content' ] ),
                                    'segment_hash'            => md5( $seg_source[ 'raw-content' ] ),
                                    'xliff_mrk_ext_succ_tags' => $seg_source[ 'mrk-ext-succ-tags' ],
                                    'xliff_ext_succ_tags'     => $seg_source[ 'ext-succ-tags' ],
                                    'raw_word_count'          => $wordCount,
                                    'show_in_cattool'         => $show_in_cattool
                            ] );

                            $this->projectStructure[ 'segments' ][ $fid ]->append( $segStruct );

                            // segment original data
                            // if its empty pass create a Segments_SegmentOriginalDataStruct with no data
                            $segmentOriginalDataStructMap = (!empty( $dataRefMap )) ? ['map' => $dataRefMap]: [];
                            $segmentOriginalDataStruct = new Segments_SegmentOriginalDataStruct($segmentOriginalDataStructMap);
                            $this->projectStructure[ 'segments-original-data' ][ $fid ]->append( $segmentOriginalDataStruct );

                            //increment counter for word count
                            $this->files_word_count += $wordCount;

                        } // end foreach seg-source

                        if ( self::notesAllowedByMimeType( $mimeType ) ) {
                            $this->__addNotesToProjectStructure( $xliff_trans_unit, $fid );
                            $this->__addTUnitContextsToProjectStructure( $xliff_trans_unit, $fid );
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

                                $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ] );

                                if ( $this->__isTranslated( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $target_extract_external[ 'seg' ], $xliff_trans_unit ) ) {

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
                                            new ArrayObject( [ 2 => $target, 4 => $xliff_trans_unit ] )
                                    );

                                }

                            }

                        }

                        if ( self::notesAllowedByMimeType( $mimeType ) ) {
                            $this->__addNotesToProjectStructure( $xliff_trans_unit, $fid );
                            $this->__addTUnitContextsToProjectStructure( $xliff_trans_unit, $fid );
                        }

                        $segStruct = new Segments_SegmentStruct( [
                                'id_file'             => $fid,
                                'id_project'          => $this->projectStructure[ 'id_project' ],
                                'internal_id'         => $xliff_trans_unit[ 'attr' ][ 'id' ],
                                'xliff_ext_prec_tags' => ( !is_null( $prec_tags ) ? $prec_tags : null ),
                                'segment'             => $this->filter->fromRawXliffToLayer0( $xliff_trans_unit[ 'source' ][ 'raw-content' ] ),
                                'segment_hash'        => md5( $xliff_trans_unit[ 'source' ][ 'raw-content' ] ),
                                'xliff_ext_succ_tags' => ( !is_null( $succ_tags ) ? $succ_tags : null ),
                                'raw_word_count'      => $wordCount,
                                'show_in_cattool'     => $show_in_cattool
                        ] );

                        $this->projectStructure[ 'segments' ][ $fid ]->append( $segStruct );

                        // segment original data
                        if ( !empty( $segmentOriginalData ) ) {

                            $dataRefReplacer = new \Matecat\XliffParser\XliffUtils\DataRefReplacer( $segmentOriginalData );

                            $segmentOriginalDataStruct = new Segments_SegmentOriginalDataStruct( [
                                    'data'             => $segmentOriginalData,
                                    'replaced_segment' => $dataRefReplacer->replace( $this->filter->fromRawXliffToLayer0( $xliff_trans_unit[ 'source' ][ 'raw-content' ] ) ),
                            ] );

                            $this->projectStructure[ 'segments-original-data' ][ $fid ]->append( $segmentOriginalDataStruct );
                        }

                        //increment counter for word count
                        $this->files_word_count += $wordCount;

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
            $this->_log( "Segment import - no segments found in {$file_info[ 'original_filename' ]}\n" );
            throw new Exception( $file_info[ 'original_filename' ], -1 );
        } else {
            //increment global counter
            $this->show_in_cattool_segs_counter += $_fileCounter_Show_In_Cattool;
        }

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
    protected function _insertFiles( $_originalFileNames, $sha1_original, $cachedXliffFilePathName ) {
        $fs = FilesStorageFactory::create();

        $yearMonthPath    = date_create( $this->projectStructure[ 'create_date' ] )->format( 'Ymd' );
        $fileDateSha1Path = $yearMonthPath . DIRECTORY_SEPARATOR . $sha1_original;

        //return structure
        $filesStructure = [];

        foreach ( $_originalFileNames as $pos => $originalFileName ) {

            // get metadata
            $meta = $this->projectStructure[ 'array_files_meta' ][ $pos ];

            $mimeType = AbstractFilesStorage::pathinfo_fix( $originalFileName, PATHINFO_EXTENSION );
            $fid      = ProjectManagerModel::insertFile( $this->projectStructure, $originalFileName, $mimeType, $fileDateSha1Path, @$meta );

            if ( $this->gdriveSession ) {
                $gdriveFileId = $this->gdriveSession->findFileIdByName( $originalFileName );
                if ( $gdriveFileId ) {
                    $this->gdriveSession->createRemoteFile( $fid, $gdriveFileId );
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
                throw new \Exception( 'Project creation failed. Please refresh page and retry.', -200 );
            }

            $this->projectStructure[ 'file_id_list' ]->append( $fid );

            $filesStructure[ $fid ] = [ 'fid' => $fid, 'original_filename' => $originalFileName, 'path_cached_xliff' => $cachedXliffFilePathName, 'mime_type' => $mimeType ];
        }

        return $filesStructure;
    }

    /**
     * @param ArrayObject $projectStructure
     * @param             $file_name
     * @param             $mime_type
     * @param             $fileDateSha1Path
     *
     * @return mixed|string
     * @throws Exception
     */
    protected function _insertFile( ArrayObject $projectStructure, $file_name, $mime_type, $fileDateSha1Path ) {
        $idFile = ProjectManagerModel::insertFile( $projectStructure, $file_name, $mime_type, $fileDateSha1Path );

        return $idFile;
    }


    protected function _insertInstructions( $fid, $value ) {
        $metadataDao = new \Files\MetadataDao();
        $metadataDao->insert( $this->projectStructure[ 'id_project' ], $fid, 'instructions', $value );

    }

    protected function _storeSegments( $fid ) {

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
             * @var $this ->projectStructure[ 'segments' ][ $fid ][ $position ] Segments_SegmentStruct
             */
            $this->projectStructure[ 'segments' ][ $fid ][ $position ]->id = $id_segment;

            // persist original data map if present
            /** @var Segments_SegmentOriginalDataStruct $segmentOriginalDataStruct */
            $segmentOriginalDataStruct = $this->projectStructure[ 'segments-original-data' ][ $fid ][ $position ];
            if(isset($segmentOriginalDataStruct->map)){
                Segments_SegmentOriginalDataDao::insertRecord( $id_segment, $segmentOriginalDataStruct->map );
            }

            if ( !isset( $this->projectStructure[ 'file_segments_count' ] [ $fid ] ) ) {
                $this->projectStructure[ 'file_segments_count' ] [ $fid ] = 0;
            }
            $this->projectStructure[ 'file_segments_count' ] [ $fid ]++;

            // TODO: continue here to find the count of segments per project
            $_metadata = [
                    'id'                => $id_segment,
                    'internal_id'       => self::sanitizedUnitId( $this->projectStructure[ 'segments' ][ $fid ][ $position ]->internal_id, $fid ),
                    'segment'           => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->segment,
                    'segment_hash'      => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->segment_hash,
                    'raw_word_count'    => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->raw_word_count,
                    'xliff_mrk_id'      => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->xliff_mrk_id,
                    'show_in_cattool'   => $this->projectStructure[ 'segments' ][ $fid ][ $position ]->show_in_cattool,
                    'additional_params' => null,
            ];

            /*
             *This hook allows plugins to manipulate data analysis content, should be not allowed to change existing data but only to eventually add new fields
             */
            $_metadata = $this->features->filter( 'appendFieldToAnalysisObject', $_metadata, $this->projectStructure );

            $segments_metadata[] = $_metadata;

        }

        $segmentsDao = new Segments_SegmentDao();
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

            foreach ( $segments_metadata as $k => $row ) {

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
                    //WARNING offset 2 is the target translation
                    $this->projectStructure[ 'translations' ][ $row[ 'internal_id' ] ][ $short_var_counter ]->offsetSet( 3, $row[ 'segment_hash' ] );
                    /**
                     * WARNING offset 4 is the Trans-Unit
                     * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                     */

                    // Remove an existent translation, we won't send these segment to the analysis because it is marked as locked
                    unset( $segments_metadata[ $k ] );

                }

            }

        }

        //merge segments_metadata for every files in the project
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
     * @param array $xliff_trans_unit
     *
     * @param       $xliff_file_attributes
     *
     * @throws Exception
     */
    protected function _manageAlternativeTranslations( $xliff_trans_unit, $xliff_file_attributes ) {

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
        $engine = Engine::getInstance( 1 );
        $config = $engine->getConfigStruct();

        if ( count( $this->projectStructure[ 'private_tm_key' ] ) != 0 ) {

            foreach ( $this->projectStructure[ 'private_tm_key' ] as $i => $tm_info ) {
                if ( $tm_info[ 'w' ] == 1 ) {
                    $config[ 'id_user' ][] = $tm_info[ 'key' ];
                }
            }

        }

        $config[ 'source' ] = $xliff_file_attributes[ 'source-language' ];
        $config[ 'target' ] = $xliff_file_attributes[ 'target-language' ];
        $config[ 'email' ]  = \INIT::$MYMEMORY_API_KEY;

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
                $source_extract_external = $this->_strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );
            }

            //Override with the alt-trans source value
            if ( !empty( $altTrans[ 'source' ] ) ) {
                $source_extract_external = $this->_strip_external( $altTrans[ 'source' ] );
            }

            $target_extract_external = $this->_strip_external( $altTrans[ 'target' ] );

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

    protected function _insertPreTranslations( $jid ) {

        $this->_cleanSegmentsMetadata();

        $status = Constants_TranslationStatus::STATUS_TRANSLATED;

        $status = $this->features->filter( 'filter_status_for_pretranslated_segments',
                $status,
                $this->projectStructure
        );

        $query_translations_values = [];
        foreach ( $this->projectStructure[ 'translations' ] as $trans_unit_reference => $struct ) {

            if ( empty( $struct ) ) {
                continue;
            }

            //array of segmented translations
            foreach ( $struct as $pos => $translation_row ) {

                $iceLockArray = $this->features->filter( 'setSegmentTranslationFromXliffValues',
                        [
                                'approved'            => @$translation_row [ 4 ][ 'attr' ][ 'approved' ],
                                'locked'              => 0,
                                'match_type'          => 'ICE',
                                'eq_word_count'       => 0,
                                'standard_word_count' => null,
                                'status'              => $status,
                                'suggestion_match'    => null,
                                'suggestion'          => null,
                                'trans-unit'          => $translation_row[ 4 ],
                                'payable_rates'       => $this->projectStructure[ 'array_jobs' ][ 'payable_rates' ][ $jid ]
                        ],
                        $this->projectStructure,
                        $this->filter
                );

                // Use QA to get target segment
                $chunk   = \Chunks_ChunkDao::getByJobID( $jid )[ 0 ];
                $segment = ( new Segments_SegmentDao() )->getById( $translation_row [ 0 ] );
                $source  = $segment->segment;
                $target  = $translation_row [ 2 ];
                $check   = new QA( $source, $target );
                $check->setFeatureSet( $this->features );
                $check->setSourceSegLang( $chunk->source );
                $check->setTargetSegLang( $chunk->target );
                $check->performConsistencyCheck();

                /* WARNING do not change the order of the keys */
                $sql_values = [
                        'id_segment'          => $translation_row [ 0 ],
                        'id_job'              => $jid,
                        'segment_hash'        => $translation_row [ 3 ],
                        'status'              => $iceLockArray[ 'status' ],
                        'translation'         => $check->getTargetSeg(),
                        'locked'              => 0, // not allowed to change locked status for pre-translations
                        'match_type'          => $iceLockArray[ 'match_type' ],
                        'eq_word_count'       => $iceLockArray[ 'eq_word_count' ],
                        'suggestion_match'    => $iceLockArray[ 'suggestion_match' ],
                        'standard_word_count' => $iceLockArray[ 'standard_word_count' ],
                ];

                $query_translations_values[] = $sql_values;

            }

        }

        // Executing the Query
        if ( !empty( $query_translations_values ) ) {
            ProjectManagerModel::insertPreTranslations( $query_translations_values );
        }

        //clean translations and queries
        unset( $query_translations_values );

    }

    /**
     * @param $segment
     *
     * @return array
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    protected function _strip_external( $segment ) {

        if ( $this->features->filter( 'skipTagLessFeature', false, $segment ) ) {
            return [ 'prec' => null, 'seg' => $segment, 'succ' => null ];
        }

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

        //TODO IMPROVEMENT:
        // - Why scan entire string if the fist char is not a less-than sign? We can't strip nothing
        // - Why continue if the first char is a less-than sign but we realize that it is not a tag?

        $segmentLength = strlen( $segment );

        // This is the fastest way I found to spot Unicode whitespaces in the string.
        // Removing this step gives a gain of 7% in speed.
        $isSpace = [];

        if ( preg_match_all( '|[\p{Mc}]+|u', $segment, $matches, PREG_OFFSET_CAPTURE ) ) {
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
        $openings = [];
        // Stores all the tags found: key is '<' position of the opening tag,
        // value is '>' position of the closure tag.
        $tags = [];
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

        return [ 'prec' => $before, 'seg' => $cleanSegment, 'succ' => $after ];
    }

    /**
     * @param $mimeType
     *
     * @return bool
     */
    public static function notesAllowedByMimeType( $mimeType ) {
        return in_array( $mimeType, [ 'sdlxliff', 'xliff', 'xlf' ] );
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
     */
    private function __addNotesToProjectStructure( $trans_unit, $fid ) {

        $internal_id = self::sanitizedUnitId( $trans_unit[ 'attr' ][ 'id' ], $fid );
        if ( isset( $trans_unit[ 'notes' ] ) ) {

            foreach ( $trans_unit[ 'notes' ] as $note ) {
                $this->initArrayObject( 'notes', $internal_id );

                if ( !$this->projectStructure[ 'notes' ][ $internal_id ]->offsetExists( 'entries' ) ) {
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'entries', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'json', new ArrayObject() );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'json_segment_ids', [] );
                    $this->projectStructure[ 'notes' ][ $internal_id ]->offsetSet( 'segment_ids', [] );
                }

                if ( isset( $note[ 'json' ] ) ) {
                    $this->projectStructure[ 'notes' ][ $internal_id ][ 'json' ]->append( $note[ 'json' ] );
                } elseif ( isset( $note[ 'raw-content' ] ) ) {
                    $this->projectStructure[ 'notes' ][ $internal_id ][ 'entries' ]->append( $note[ 'raw-content' ] );
                }

            }

        }

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
    private function __setSegmentIdForNotes( $row ) {
        $internal_id = $row[ 'internal_id' ];

        if ( $this->projectStructure[ 'notes' ]->offsetExists( $internal_id ) ) {

            if ( count( $this->projectStructure[ 'notes' ][ $internal_id ][ 'json' ] ) != 0 ) {
                array_push( $this->projectStructure[ 'notes' ][ $internal_id ][ 'json_segment_ids' ], $row[ 'id' ] );
            } else {
                array_push( $this->projectStructure[ 'notes' ][ $internal_id ][ 'segment_ids' ], $row[ 'id' ] );
            }

        }

    }

    /**
     * @throws \Exception
     */
    private function insertSegmentNotesForFile() {
        $this->projectStructure = $this->features->filter( 'handleJsonNotesBeforeInsert', $this->projectStructure );
        ProjectManagerModel::bulkInsertSegmentNotes( $this->projectStructure[ 'notes' ] );
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
            array_push( $this->projectStructure[ 'context-group' ][ $internal_id ][ 'context_json_segment_ids' ], $row[ 'id' ] );
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

    private static function sanitizedUnitId( $trans_unitID, $fid ) {
        return $fid . "|" . $trans_unitID;
    }

    private function fileMustBeConverted( $filePathName, $forceXliff ) {

        $mustBeConverted = XliffProprietaryDetect::fileMustBeConverted( $filePathName, $forceXliff, INIT::$FILTERS_ADDRESS );

        /**
         * Application misconfiguration.
         * upload should not be happened, but if we are here, raise an error.
         * @see upload.class.php
         * */
        if ( -1 === $mustBeConverted ) {
            $this->projectStructure[ 'result' ][ 'errors' ][] = [
                    "code"    => -8,
                    "message" => "Proprietary xlf format detected. Not able to import this XLIFF file. ($filePathName)"
            ];
            if ( PHP_SAPI != 'cli' ) {
                setcookie( "upload_session", "", time() - 10000, '/', \INIT::$COOKIE_DOMAIN );
            }
        }

        return $mustBeConverted;

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
     * @return bool
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function setPrivateTMKeys( $firstTMXFileName ) {

        foreach ( $this->projectStructure[ 'private_tm_key' ] as $i => $_tmKey ) {

            $this->tmxServiceWrapper->setTmKey( $_tmKey[ 'key' ] );

            try {

                $keyExists = $this->tmxServiceWrapper->checkCorrectKey();

                if ( !isset( $keyExists ) || $keyExists === false ) {
                    $this->_log( __METHOD__ . " -> TM key is not valid." );

                    throw new Exception( "TM key is not valid: " . $_tmKey[ 'key' ], -4 );
                }

            } catch ( Exception $e ) {

                $this->projectStructure[ 'result' ][ 'errors' ][] = [
                        "code" => $e->getCode(), "message" => $e->getMessage()
                ];

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

            $userTmKeys             = [];
            $memoryKeysToBeInserted = [];

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
                    $this->_log( 'skip insertion' );
                }

            }
            try {
                $mkDao->createList( $memoryKeysToBeInserted );

                $featuresSet = new FeatureSet();
                $featuresSet->run( 'postTMKeyCreation', $memoryKeysToBeInserted, $this->projectStructure[ 'uid' ] );

            } catch ( Exception $e ) {
                $this->_log( $e->getMessage() );

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

        $this->projectStructure[ 'private_tm_key' ] = $this->features->filter( 'filter_project_manager_private_tm_key',
                $this->projectStructure[ 'private_tm_key' ],
                [ 'project_structure' => $this->projectStructure ]
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
     * @param $xliff_trans_unit
     *
     * @return bool|mixed
     * @throws \Exceptions\NotFoundException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function __isTranslated( $source, $target, $xliff_trans_unit ) {
        if ( $source != $target ) {

            // evaluate if different source and target should be considered translated
            $differentSourceAndTargetIsTranslated = ( empty( $target ) ) ? false : true;
            $differentSourceAndTargetIsTranslated = $this->features->filter(
                    'filterDifferentSourceAndTargetIsTranslated',
                    $differentSourceAndTargetIsTranslated, $this->projectStructure, $xliff_trans_unit
            );

            return $differentSourceAndTargetIsTranslated;
            //return true;
        }

        // evaluate if identical source and target should be considered non translated
        $identicalSourceAndTargetIsTranslated = false;
        $identicalSourceAndTargetIsTranslated = $this->features->filter(
                'filterIdenticalSourceAndTargetIsTranslated',
                $identicalSourceAndTargetIsTranslated, $this->projectStructure, $xliff_trans_unit
        );

        return $identicalSourceAndTargetIsTranslated;
    }
}
