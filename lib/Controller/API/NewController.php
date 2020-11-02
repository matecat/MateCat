<?php

use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use ProjectQueue\Queue;
use Teams\MembershipDao;
use Matecat\XliffParser\Utils\Files as XliffFiles;

//limit execution time to 300 seconds
set_time_limit( 300 );

/**
 *
 * Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol
 *
 * POST Params:
 *
 * 'project_name'       => (string) The name of the project you want create
 * 'source_lang'        => (string) RFC 3066 language Code ( en-US )
 * 'target_lang'        => (string) RFC 3066 language(s) Code. Comma separated ( it-IT,fr-FR,es-ES )
 * 'tms_engine'         => (int)    Identifier for Memory Server ( ZERO means disabled, ONE means MyMemory )
 * 'mt_engine'          => (int)    Identifier for TM Server ( ZERO means disabled, ONE means MyMemory )
 * 'private_tm_key'     => (string) Private Key for MyMemory ( set to new to create a new one )
 *
 */
class NewController extends ajaxController {

    /**
     * @var array
     */
    private $private_tm_key;

    private $private_tm_user = null;
    private $private_tm_pass = null;

    protected $new_keys = [];

    /**
     * @var BasicFeatureStruct[]
     */
    private $projectFeatures = [];

    private $metadata = [];

    const MAX_NUM_KEYS = 6;

    private static $allowed_seg_rules = [
            'standard', 'patent', ''
    ];

    protected $api_output = [
            'status'  => 'FAIL',
            'message' => 'Untraceable error (sorry, not mapped)'
    ];

    /**
     * @var \Teams\TeamStruct
     */
    protected $team;

    protected $projectStructure;

    /**
     * @var ProjectManager
     */
    protected $projectManager;

    public $postInput;

    /**
     * @var AbstractFilesStorage
     */
    protected $files_storage;

    public function __construct() {

        parent::__construct();

        //force client to close connection, avoid UPLOAD_ERR_PARTIAL for keep-alive connections
        header( "Connection: close" );

        if ( !$this->__validateAuthHeader() ) {
            header( 'HTTP/1.0 401 Unauthorized' );
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = 'Authentication failed';
            $this->finalize();
            die();
        }

        if ( $this->userIsLogged ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
        }

        $filterArgs = [
                'project_name'       => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'source_lang'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'target_lang'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'due_date'           => [ 'filter' => FILTER_VALIDATE_INT ],
                'tms_engine'         => [
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => [ 'default' => 1, 'min_range' => 0 ]
                ],
                'mt_engine'          => [
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => [ 'default' => 1, 'min_range' => 0 ]
                ],
                'private_tm_key'     => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'subject'            => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'segmentation_rule'  => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'owner_email'        => [
                        'filter' => FILTER_VALIDATE_EMAIL
                ],
                'metadata'           => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'pretranslate_100'   => [
                        'filter' => [ 'filter' => FILTER_VALIDATE_INT ]
                ],
                'id_team'            => [ 'filter' => FILTER_VALIDATE_INT ],
                'lexiqa'             => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'speech2text'        => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'tag_projection'     => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'project_completion' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'get_public_matches' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ], // disable public TM matches
                'instructions'    => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_REQUIRE_ARRAY,
                ],
                'project_info'       => [ 'filter' => FILTER_SANITIZE_STRING ]
        ];

        $filterArgs = $this->featureSet->filter( 'filterNewProjectInputFilters', $filterArgs, $this->userIsLogged );

        $this->postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        if ( empty( $_FILES ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "Missing file. Not Sent." ];

            return -1;
        }

        try {
            $this->__validateOwnerEmail();
            $this->__validateMetadataParam();
            $this->__validateEngines();
            $this->__validateSubjects();
            $this->__validateSegmentationRules();
            $this->__validateTmAndKeys();
            $this->__validateTeam();
            $this->__appendFeaturesToProject();
            $this->__generateTargetEngineAssociation();
        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = $ex->getMessage();
            Log::doJsonLog( $ex->getMessage() );

            return $ex->getCode();
        }

        $this->files_storage = FilesStorageFactory::create();

    }

    /**
     * @throws Exception
     */
    private function __validateOwnerEmail() {

        if ( $this->postInput[ 'owner_email' ] === false ) {
            throw new Exception( "Email is not valid", -5 );
        } else {
            if ( !is_null( $this->postInput[ 'owner_email' ] ) && !empty( $this->postInput[ 'owner_email' ] ) ) {
                $domain = explode( "@", $this->postInput[ 'owner_email' ] );
                $domain = $domain[ 1 ];
                if ( !checkdnsrr( $domain ) ) {
                    throw new Exception( "Email is not valid", -5 );
                }
            }
        }

    }

    /**
     * @throws Exception
     */
    private function __validateSegmentationRules() {

        $this->postInput[ 'segmentation_rule' ] = ( !empty( $this->postInput[ 'segmentation_rule' ] ) ) ? $this->postInput[ 'segmentation_rule' ] : '';

        if ( !in_array( $this->postInput[ 'segmentation_rule' ], self::$allowed_seg_rules ) ) {
            throw new Exception( "Segmentation rule not allowed: " . $this->postInput[ 'segmentation_rule' ], -4 );
        }

        //normalize segmentation rule to what it's used internally
        if ( $this->postInput[ 'segmentation_rule' ] == 'standard' || $this->postInput[ 'segmentation_rule' ] == '' ) {
            $this->postInput[ 'segmentation_rule' ] = null;
        }

    }

    /**
     * @throws Exception
     */
    private function __validateSubjects() {

        $langDomains = Langs_LanguageDomains::getInstance();
        $subjectList = $langDomains::getEnabledDomains();
        // In this list there is an item whose key is "----".
        // It is useful for UI purposes, but not here. So we unset it
        foreach ( $subjectList as $idx => $subject ) {
            if ( $subject[ 'key' ] == '----' ) {
                unset( $subjectList[ $idx ] );
                break;
            }
        }

        //Array_column() is not supported on PHP 5.4, so i'll rewrite it
        $subjectList = Utils::array_column( $subjectList, 'key' );

        $this->postInput[ 'subject' ] = ( !empty( $this->postInput[ 'subject' ] ) ) ? $this->postInput[ 'subject' ] : 'general';
        if ( !in_array( $this->postInput[ 'subject' ], $subjectList ) ) {
            throw new Exception( "Subject not allowed: " . $this->postInput[ 'subject' ], -3 );
        }

    }

    private function __appendFeaturesToProject() {
        if ( $this->postInput[ 'project_completion' ] ) {
            $feature                 = new BasicFeatureStruct();
            $feature->feature_code   = 'project_completion';
            $this->projectFeatures[] = $feature;
        }

        $this->projectFeatures = $this->featureSet->filter(
                'filterCreateProjectFeatures', $this->projectFeatures, $this
        );

    }

    /**
     * This could be already set by MMT engine if enabled ( so check key existence and do not override )
     *
     * @see filterCreateProjectFeatures callback
     * @see NewController::__appendFeaturesToProject()
     */
    private function __generateTargetEngineAssociation() {
        if ( !isset( $this->postInput[ 'target_language_mt_engine_id' ] ) ) { // this could be already set by MMT engine if enabled ( so check and do not override )
            foreach ( explode( ",", $this->postInput[ 'target_lang' ] ) as $_matecatTarget ) {
                $this->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = $this->postInput[ 'mt_engine' ];
            }
        }
    }

    /**
     * @throws Exception
     */
    private function __validateEngines() {

        if ( !isset( $this->postInput[ 'tms_engine' ] ) ) {
            $this->postInput[ 'tms_engine' ] = 1;
        }

        if ( !isset( $this->postInput[ 'mt_engine' ] ) ) {
            $this->postInput[ 'mt_engine' ] = 1;
        }

        if ( $this->postInput[ 'tms_engine' ] != 0 ) {
            Engine::getInstance( $this->postInput[ 'tms_engine' ] );
        }

        if ( $this->postInput[ 'mt_engine' ] != 0 && $this->postInput[ 'mt_engine' ] != 1 ) {
            if ( !$this->userIsLogged ) {
                throw new Exception( "Invalid MT Engine.", -2 );
            } else {
                $testEngine = Engine::getInstance( $this->postInput[ 'mt_engine' ] );
                if ( $testEngine->getEngineRow()->uid != $this->getUser()->uid ) {
                    throw new Exception( "Invalid MT Engine.", -21 );
                }
            }
        }

    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

    public function doAction() {

        $fs = FilesStorageFactory::create();

        if ( @count( $this->api_output[ 'debug' ] ) > 0 ) {
            return -1;
        }

        $uploadFile = new Upload();

        try {
            $stdResult = $uploadFile->uploadFiles( $_FILES );
        } catch ( Exception $e ) {
            $stdResult                     = [];
            $this->result                  = [
                    'errors' => [
                            [ "code" => -1, "message" => $e->getMessage() ]
                    ]
            ];
            $this->api_output[ 'message' ] = $e->getMessage();
        }

        $arFiles = [];

        foreach ( $stdResult as $input_name => $input_value ) {
            $arFiles[] = $input_value->name;
        }

        //if fileupload was failed this index ( 0 = does not exists )
        $default_project_name = @$arFiles[ 0 ];
        if ( count( $arFiles ) > 1 ) {
            $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
        }

        if ( empty( $this->postInput[ 'project_name' ] ) ) {
            $this->postInput[ 'project_name' ] = $default_project_name; //'NO_NAME'.$this->create_project_name();
        }

        $this->__validateSourceLang( Langs_Languages::getInstance() );
        $this->__validateTargetLangs( Langs_Languages::getInstance() );

        //ONE OR MORE ERRORS OCCURRED : EXITING
        //for now we sent to api output only the LAST error message, but we log all
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doJsonLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1; //exit code
        }

        $cookieDir = $uploadFile->getDirUploadToken();
        $intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
        $errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;

        foreach ( $arFiles as $file_name ) {
            $ext = AbstractFilesStorage::pathinfo_fix( $file_name, PATHINFO_EXTENSION );

            $conversionHandler = new ConversionHandler();
            $conversionHandler->setFileName( $file_name );
            $conversionHandler->setSourceLang( $this->postInput[ 'source_lang' ] );
            $conversionHandler->setTargetLang( $this->postInput[ 'target_lang' ] );
            $conversionHandler->setSegmentationRule( $this->postInput[ 'segmentation_rule' ] );
            $conversionHandler->setCookieDir( $cookieDir );
            $conversionHandler->setIntDir( $intDir );
            $conversionHandler->setErrDir( $errDir );
            $conversionHandler->setFeatures( $this->featureSet );
            $conversionHandler->setUserIsLogged( $this->userIsLogged );

            $status = [];

            if ( $ext == "zip" ) {
                // this makes the conversionhandler accumulate eventual errors on files and continue
                $conversionHandler->setStopOnFileException( false );

                $fileObjects = $conversionHandler->extractZipFile();

                \Log::doJsonLog( 'fileObjets', $fileObjects );

                //call convertFileWrapper and start conversions for each file

                if ( $conversionHandler->uploadError ) {
                    $fileErrors = $conversionHandler->getUploadedFiles();

                    foreach ( $fileErrors as $fileError ) {
                        if ( count( $fileError->error ) == 0 ) {
                            continue;
                        }

                        $brokenFileName = ZipArchiveExtended::getFileName( $fileError->name );

                        /*
                         * TODO
                         * return error code is 2 because
                         *      <=0 is for errors
                         *      1   is OK
                         *
                         * In this case, we raise warnings, hence the return code must be a new code
                         */
                        $this->result[ 'code' ]                      = 2;
                        $this->result[ 'errors' ][ $brokenFileName ] = [
                                'code'    => $fileError->error[ 'code' ],
                                'message' => $fileError->error[ 'message' ],
                                'debug'   => $brokenFileName
                        ];
                    }

                }

                $realFileObjectInfo  = $fileObjects;
                $realFileObjectNames = array_map(
                        [ 'ZipArchiveExtended', 'getFileName' ],
                        $fileObjects
                );

                foreach ( $realFileObjectNames as $i => &$fileObject ) {
                    $__fileName     = $fileObject;
                    $__realFileName = $realFileObjectInfo[ $i ];
                    $filesize       = filesize( $intDir . DIRECTORY_SEPARATOR . $__realFileName );

                    $fileObject               = [
                            'name' => $__fileName,
                            'size' => $filesize
                    ];
                    $realFileObjectInfo[ $i ] = $fileObject;
                }

                $this->result[ 'data' ][ $file_name ] = json_encode( $realFileObjectNames );

                $stdFileObjects = [];

                if ( $fileObjects !== null ) {
                    foreach ( $fileObjects as $fName ) {

                        if ( isset( $fileErrors ) &&
                                isset( $fileErrors->{$fName} ) &&
                                !empty( $fileErrors->{$fName}->error )
                        ) {
                            continue;
                        }

                        $newStdFile       = new stdClass();
                        $newStdFile->name = $fName;
                        $stdFileObjects[] = $newStdFile;

                    }
                } else {
                    $errors = $conversionHandler->getResult();
                    $errors = array_map( [ 'Upload', 'formatExceptionMessage' ], $errors[ 'errors' ] );

                    $this->result[ 'errors' ]      = array_merge( $this->result[ 'errors' ], $errors );
                    $this->api_output[ 'message' ] = "Zip Error";
                    $this->api_output[ 'debug' ]   = $this->result[ 'errors' ];

                    return false;
                }

                /* Do conversions here */
                $converter              = new ConvertFileWrapper( $stdFileObjects, false );
                $converter->intDir      = $intDir;
                $converter->errDir      = $errDir;
                $converter->cookieDir   = $cookieDir;
                $converter->source_lang = $this->postInput[ 'source_lang' ];
                $converter->target_lang = $this->postInput[ 'target_lang' ];
                $converter->featureSet  = $this->featureSet;
                $converter->setUser( $this->user );
                $converter->doAction();

                $status = $errors = $converter->checkResult();
                if ( count( $errors ) > 0 ) {
//                    $this->result[ 'errors' ] = array_merge( $this->result[ 'errors' ], $errors );
                    $this->result[ 'code' ] = 2;
                    foreach ( $errors as $__err ) {
                        $brokenFileName = ZipArchiveExtended::getFileName( $__err[ 'debug' ] );

                        if ( !isset( $this->result[ 'errors' ][ $brokenFileName ] ) ) {
                            $this->result[ 'errors' ][ $brokenFileName ] = [
                                    'code'    => $__err[ 'code' ],
                                    'message' => $__err[ 'message' ],
                                    'debug'   => $brokenFileName
                            ];
                        }
                    }
                }
            } else {
                $conversionHandler->doAction();

                $this->result = $conversionHandler->getResult();

                if ( $this->result[ 'code' ] > 0 ) {
                    $this->result = [];
                }

            }
        }

        $status = array_values( $status );

        if ( !empty( $status ) ) {
            $this->api_output[ 'message' ] = 'Project Conversion Failure';
            $this->api_output[ 'debug' ]   = $status;
            $this->result[ 'errors' ]      = $status;
            Log::doJsonLog( $status );

            return -1;
        }
        /* Do conversions here */

        if ( isset( $this->result[ 'data' ] ) && !empty( $this->result[ 'data' ] ) ) {
            foreach ( $this->result[ 'data' ] as $zipFileName => $zipFiles ) {
                $zipFiles = json_decode( $zipFiles, true );


                $fileNames = Utils::array_column( $zipFiles, 'name' );
                $arFiles   = array_merge( $arFiles, $fileNames );
            }
        }

        $newArFiles = [];
        $linkFiles  = scandir( $intDir );

        foreach ( $arFiles as $__fName ) {
            if ( 'zip' == AbstractFilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {


                $fs->cacheZipArchive( sha1_file( $intDir . DIRECTORY_SEPARATOR . $__fName ), $intDir . DIRECTORY_SEPARATOR . $__fName );

                $linkFiles = scandir( $intDir );

                //fetch cache links, created by converter, from upload directory
                foreach ( $linkFiles as $storedFileName ) {
                    //check if file begins with the name of the zip file.
                    // If so, then it was stored in the zip file.
                    if ( strpos( $storedFileName, $__fName ) !== false &&
                            substr( $storedFileName, 0, strlen( $__fName ) ) == $__fName
                    ) {
                        //add file name to the files array
                        $newArFiles[] = $storedFileName;
                    }
                }

            } else { //this file was not in a zip. Add it normally

                if ( file_exists( $intDir . DIRECTORY_SEPARATOR . $__fName ) ) {
                    $newArFiles[] = $__fName;
                }

            }
        }

        $arFiles = $newArFiles;
        $arMeta  = [];

        // create array_files_meta
        foreach ($arFiles as $arFile){
            $arMeta[] = $this->getFileMetadata( $intDir . DIRECTORY_SEPARATOR . $arFile );
        }


        $this->projectManager = new ProjectManager();
        $projectStructure     = $this->projectManager->getProjectStructure();

        $projectStructure[ 'sanitize_project_options' ] = false;

        $projectStructure[ 'project_name' ] = $this->postInput[ 'project_name' ];
        $projectStructure[ 'job_subject' ]  = $this->postInput[ 'subject' ];

        $projectStructure[ 'private_tm_key' ]       = $this->private_tm_key;
        $projectStructure[ 'private_tm_user' ]      = $this->private_tm_user;
        $projectStructure[ 'private_tm_pass' ]      = $this->private_tm_pass;
        $projectStructure[ 'uploadToken' ]          = $uploadFile->getDirUploadToken();
        $projectStructure[ 'array_files' ]          = $arFiles; //list of file name
        $projectStructure[ 'array_files_meta' ]     = $arMeta; //list of file metadata
        $projectStructure[ 'source_language' ]      = $this->postInput[ 'source_lang' ];
        $projectStructure[ 'target_language' ]      = explode( ',', $this->postInput[ 'target_lang' ] );
        $projectStructure[ 'mt_engine' ]            = $this->postInput[ 'mt_engine' ];
        $projectStructure[ 'tms_engine' ]           = $this->postInput[ 'tms_engine' ];
        $projectStructure[ 'status' ]               = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'skip_lang_validation' ] = true;
        $projectStructure[ 'owner' ]                = $this->postInput[ 'owner_email' ];
        $projectStructure[ 'metadata' ]             = $this->metadata;
        $projectStructure[ 'pretranslate_100' ]     = (int)!!$this->postInput[ 'pretranslate_100' ]; // Force pretranslate_100 to be 0 or 1

        //default get all public matches from TM
        $projectStructure[ 'only_private' ] = ( !isset( $this->postInput[ 'get_public_matches' ] ) ? false : !$this->postInput[ 'get_public_matches' ] );

        $projectStructure[ 'user_ip' ]                      = Utils::getRealIpAddr();
        $projectStructure[ 'HTTP_HOST' ]                    = INIT::$HTTPHOST;
        $projectStructure[ 'due_date' ]                     = ( !isset( $this->postInput[ 'due_date' ] ) ? null : Utils::mysqlTimestamp( $this->postInput[ 'due_date' ] ) );
        $projectStructure[ 'target_language_mt_engine_id' ] = $this->postInput[ 'target_language_mt_engine_id' ];
        $projectStructure[ 'instructions' ]                 = $this->postInput[ 'instructions' ];

        if ( $this->user ) {
            $projectStructure[ 'userIsLogged' ] = true;
            $projectStructure[ 'uid' ]          = $this->user->getUid();
            $projectStructure[ 'id_customer' ]  = $this->user->getEmail();
            $projectStructure[ 'owner' ]        = $this->user->getEmail();
            $this->projectManager->setTeam( $this->team );
        }

        //set features override
        $projectStructure[ 'project_features' ] = $this->projectFeatures;

        try {
            $this->projectManager->sanitizeProjectStructure();
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->api_output[ 'debug' ]   = $e->getCode();

            return -1;
        }

        $fs::moveFileFromUploadSessionToQueuePath( $uploadFile->getDirUploadToken() );

        //reserve a project id from the sequence
        $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $projectStructure[ 'ppassword' ]  = $this->projectManager->generatePassword();

        $projectStructure = $this->featureSet->filter( 'addNewProjectStructureAttributes', $projectStructure, $this->postInput );

        $this->projectStructure = $projectStructure;


        Queue::sendProject( $projectStructure );

        $this->_pollForCreationResult();

        $this->_outputResult();
    }

    /**
     * @param $filename
     *
     * @return ArrayObject
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function getFileMetadata($filename) {
        $info          = XliffProprietaryDetect::getInfo( $filename );
        $isXliff       = XliffFiles::isXliff( $filename );
        $isGlossary    = XliffFiles::isGlossaryFile( $filename );
        $isTMX         = XliffFiles::isTMXFile( $filename );
        $getMemoryType = XliffFiles::getMemoryFileType( $filename );

        $forceXliff = $this->getFeatureSet()->filter(
                'forceXLIFFConversion',
                INIT::$FORCE_XLIFF_CONVERSION,
                $this->userIsLogged,
                $info[ 'info' ][ 'dirname' ] . DIRECTORY_SEPARATOR . "$filename"
        );
        $mustBeConverted = XliffProprietaryDetect::fileMustBeConverted( $filename, $forceXliff, INIT::$FILTERS_ADDRESS );

        $metadata                      = [];
        $metadata[ 'basename' ]        = $info[ 'info' ][ 'basename' ];
        $metadata[ 'dirname' ]         = $info[ 'info' ][ 'dirname' ];
        $metadata[ 'extension' ]       = $info[ 'info' ][ 'extension' ];
        $metadata[ 'filename' ]        = $info[ 'info' ][ 'filename' ];
        $metadata[ 'mustBeConverted' ] = $mustBeConverted;
        $metadata[ 'getMemoryType' ]   = $getMemoryType;
        $metadata[ 'isXliff' ]         = $isXliff;
        $metadata[ 'isGlossary' ]      = $isGlossary;
        $metadata[ 'isTMX' ]           = $isTMX;
        $metadata[ 'proprietary' ]     = [
                [ 'proprietary' ]            => $info[ 'proprietary' ],
                [ 'proprietary_name' ]       => $info[ 'proprietary_name' ],
                [ 'proprietary_short_name' ] => $info[ 'proprietary_short_name' ],
        ];

        return $metadata;
    }

    protected function _outputResult() {
        if ( $this->result == null ) {
            $this->api_output[ 'status' ]  = 504;
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = 'Execution timeout';
        } elseif ( !empty( $this->result[ 'errors' ] ) ) {
            //errors already logged
            $this->api_output[ 'status' ]  = 500;
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = array_values( $this->result[ 'errors' ] );

        } else {
            //everything ok
            $this->_outputForSuccess();
        }
    }

    protected function _outputForSuccess() {
        $this->api_output[ 'status' ]       = 'OK';
        $this->api_output[ 'message' ]      = 'Success';
        $this->api_output[ 'id_project' ]   = $this->projectStructure[ 'id_project' ];
        $this->api_output[ 'project_pass' ] = $this->projectStructure[ 'ppassword' ];
        $this->api_output[ 'new_keys' ]     = $this->new_keys;
        $this->api_output[ 'analyze_url' ]  = $this->projectManager->getAnalyzeURL();
    }

    protected function _pollForCreationResult() {
        $time = time();
        do {
            $this->result = Queue::getPublishedResults( $this->projectStructure[ 'id_project' ] ); //LOOP for 290 seconds **** UGLY **** Deprecate in API V2
            if ( $this->result != null ) {
                break;
            }
            sleep( 2 );
        } while ( time() - $time <= 290 );
    }

    private function __validateSourceLang( Langs_Languages $lang_handler ) {
        try {
            $lang_handler->validateLanguage( $this->postInput[ 'source_lang' ] );
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->result[ 'errors' ][]    = [ "code" => -3, "message" => $e->getMessage() ];
        }
    }

    private function __validateTargetLangs( Langs_Languages $lang_handler ) {
        $targets = explode( ',', $this->postInput[ 'target_lang' ] );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            $this->api_output[ 'message' ] = "Missing target language.";
            $this->result[ 'errors' ][]    = [ "code" => -4, "message" => "Missing target language." ];
        }

        try {

            foreach ( $targets as $target ) {
                $lang_handler->validateLanguage( $target );
            }

        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->result[ 'errors' ][]    = [ "code" => -4, "message" => $e->getMessage() ];
        }

        $this->postInput[ 'target_lang' ] = implode( ',', $targets );
    }

    /**
     * Tries to find authentication credentials in header. Returns false if credentials are provided and invalid. True otherwise.
     *
     * @return bool
     */
    private function __validateAuthHeader() {

        $api_key    = @$_SERVER[ 'HTTP_X_MATECAT_KEY' ];
        $api_secret = ( !empty( $_SERVER[ 'HTTP_X_MATECAT_SECRET' ] ) ? $_SERVER[ 'HTTP_X_MATECAT_SECRET' ] : "wrong" );

        if ( false !== strpos( @$_SERVER[ 'HTTP_X_MATECAT_KEY' ], '-' ) ) {
            list( $api_key, $api_secret ) = explode( '-', $_SERVER[ 'HTTP_X_MATECAT_KEY' ] );
        }

        if ( $api_key && $api_secret ) {
            $key = \ApiKeys_ApiKeyDao::findByKey( $api_key );

            if ( !$key || !$key->validSecret( $api_secret ) ) {
                return false;
            }

            Log::doJsonLog( $key );
            $this->user = $key->getUser();

            $this->userIsLogged = (
                    !empty( $this->user->uid ) &&
                    !empty( $this->user->email ) &&
                    !empty( $this->user->first_name ) &&
                    !empty( $this->user->last_name )
            );

        }

        return true;
    }

    /**
     * @param $elem
     *
     * @return array
     */
    private static function __sanitizeTmKeyArr( $elem ) {

        $elem = TmKeyManagement_TmKeyManagement::sanitize( new TmKeyManagement_TmKeyStruct( $elem ) );

        return $elem->toArray();

    }

    /**
     * Expects the metadata param to be a json formatted string and tries to convert it
     * in array.
     * Json string is expected to be flat key value, this is enforced padding 1 to json
     * conversion depth param.
     *
     *
     * @throws Exception
     */
    private function __validateMetadataParam() {

        if ( !empty( $this->postInput[ 'metadata' ] ) ) {
            if ( strlen( $this->postInput[ 'metadata' ] ) > 2048 ) {
                throw new Exception( 'metadata string is too long' );
            }
            $depth                         = 2; // only converts key value structures
            $assoc                         = true;
            $this->postInput[ 'metadata' ] = html_entity_decode( $this->postInput[ 'metadata' ] );
            $this->metadata                = json_decode( $this->postInput[ 'metadata' ], $assoc, $depth );
            Log::doJsonLog( "Passed parameter metadata as json string." );
        }

        // project_info
        if ( !empty( $this->postInput[ 'project_info' ] ) ) {
            $this->metadata[ 'project_info' ] = $this->postInput[ 'project_info' ];
        }

        //override metadata with explicitly declared keys ( we maintain metadata for backward compatibility )
        if ( !empty( $this->postInput[ 'lexiqa' ] ) ) {
            $this->metadata[ 'lexiqa' ] = $this->postInput[ 'lexiqa' ];
        }

        if ( !empty( $this->postInput[ 'speech2text' ] ) ) {
            $this->metadata[ 'speech2text' ] = $this->postInput[ 'speech2text' ];
        }

        if ( !empty( $this->postInput[ 'tag_projection' ] ) ) {
            $this->metadata[ 'tag_projection' ] = $this->postInput[ 'tag_projection' ];
        }

        if ( !empty( $this->postInput[ 'project_completion' ] ) ) {
            $this->metadata[ 'project_completion' ] = $this->postInput[ 'project_completion' ];
        }

        $this->metadata = $this->featureSet->filter( 'filterProjectMetadata', $this->metadata, $this->postInput );

        $this->metadata = $this->featureSet->filter( 'createProjectAssignInputMetadata', $this->metadata, [
                'input' => $this->postInput
        ] );

    }

    private static function __parseTmKeyInput( $tmKeyString ) {
        $tmKeyInfo = explode( ":", $tmKeyString );
        $read      = true;
        $write     = true;

        $permissionString = @$tmKeyInfo[ 1 ];

        //if the key is not set, return null. It will be filtered in the next lines.
        if ( empty( $tmKeyInfo[ 0 ] ) ) {
            return null;
        } //if permissions are set, check if they are allowed or not and eventually set permissions

        //permission string check
        switch ( $permissionString ) {
            case 'r':
                $write = false;
                break;
            case 'w':
                $read = false;
                break;
            case 'rw':
            case ''  :
            case null:
                break;
            //permission string not allowed
            default:
                $allowed_permissions = implode( ", ", Constants_TmKeyPermissions::$_accepted_grants );
                throw new Exception( "Permission modifier string not allowed. Allowed: <empty>, $allowed_permissions" );
                break;
        }

        return [
                'key' => $tmKeyInfo[ 0 ],
                'r'   => $read,
                'w'   => $write,
        ];
    }

    protected function __validateTmAndKeys() {

        try {
            $this->private_tm_key = array_map(
                    [ 'NewController', '__parseTmKeyInput' ],
                    explode( ",", $this->postInput[ 'private_tm_key' ] )
            );
        } catch ( Exception $e ) {
            throw new Exception( $e->getMessage(), -6 );
        }

        if ( count( $this->private_tm_key ) > self::MAX_NUM_KEYS ) {
            throw new Exception( "Too much keys provided. Max number of keys is " . self::MAX_NUM_KEYS, -2 );
        }

        $this->private_tm_key = array_values( array_filter( $this->private_tm_key ) );

        //If a TMX file has been uploaded and no key was provided, create a new key.
        if ( empty( $this->private_tm_key ) ) {
            foreach ( $_FILES as $_fileinfo ) {
                $pathinfo = AbstractFilesStorage::pathinfo_fix( $_fileinfo[ 'name' ] );
                if ( $pathinfo[ 'extension' ] == 'tmx' ) {
                    $this->private_tm_key[] = [ 'key' => 'new' ];
                    break;
                }
            }
        }

        //remove all empty entries
        foreach ( $this->private_tm_key as $__key_idx => $tm_key ) {
            //from api a key is sent and the value is 'new'
            if ( $tm_key[ 'key' ] == 'new' ) {

                try {

                    $APIKeySrv = new TMSService();

                    $newUser = $APIKeySrv->createMyMemoryKey();

                    //TODO: i need to store an array of these
                    $this->private_tm_user = $newUser->id;
                    $this->private_tm_pass = $newUser->pass;

                    $this->private_tm_key[ $__key_idx ] =
                            [
                                    'key'  => $newUser->key,
                                    'name' => null,
                                    'r'    => $tm_key[ 'r' ],
                                    'w'    => $tm_key[ 'w' ]

                            ];
                    $this->new_keys[]                   = $newUser->key;

                } catch ( Exception $e ) {
                    throw new Exception( $e->getMessage(), -1 );
                }

            } //if a string is sent, transform it into a valid array
            elseif ( !empty( $tm_key ) ) {

                $uid = $this->user->uid;

                $this_tm_key = [
                        'key'  => $tm_key[ 'key' ],
                        'name' => null,
                        'r'    => $tm_key[ 'r' ],
                        'w'    => $tm_key[ 'w' ]
                ];

                /**
                 * Get the key description/name from the user keyring
                 */
                if ( $uid ) {
                    $mkDao = new TmKeyManagement_MemoryKeyDao();

                    /**
                     * @var $keyRing TmKeyManagement_MemoryKeyStruct[]
                     */
                    $keyRing = $mkDao->read(
                            ( new TmKeyManagement_MemoryKeyStruct( [
                                    'uid'    => $uid,
                                    'tm_key' => new TmKeyManagement_TmKeyStruct( $this_tm_key )
                            ] )
                            )
                    );

                    if ( count( $keyRing ) > 0 ) {
                        $this_tm_key[ 'name' ] = $keyRing[ 0 ]->tm_key->name;
                    }
                }

                $this->private_tm_key[ $__key_idx ] = $this_tm_key;
            }

            $this->private_tm_key[ $__key_idx ] = self::__sanitizeTmKeyArr( $this->private_tm_key[ $__key_idx ] );

        }

    }

    /**
     * @throws Exception
     */
    private function __validateTeam() {
        if ( $this->user && !empty( $this->postInput[ 'id_team' ] ) ) {
            $dao = new MembershipDao();
            $org = $dao->findTeamByIdAndUser( $this->postInput[ 'id_team' ], $this->user );

            if ( !$org ) {
                throw new Exception( 'Team and user membership does not match', -1 );
            } else {
                $this->team = $org;
            }
        } else {
            if ( $this->user ) {
                $this->team = $this->user->getPersonalTeam();
            }
        }
    }

}
