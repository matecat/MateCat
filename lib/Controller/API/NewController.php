<?php

use API\V2\Exceptions\AuthenticationError;
use Exceptions\ValidationError;
use ProjectQueue\Queue;
use Teams\MembershipDao;

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
     * @var boolean
     */
    private $only_private;

    /**
     * @var int
     */
    private $pretranslate_100;

    /**
     * @var Langs_Languages
     */
    private $lang_handler;
    /**
     * @var string
     */
    private $project_name;

    /**
     * @var string
     */
    private $source_lang;

    /**
     * @var string
     */
    private $target_lang;

    private $mt_engine;  //1 default MyMemory
    private $tms_engine;  //1 default MyMemory

    /**
     * @var array
     */
    private $private_tm_key;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var string
     */
    private $seg_rule;

    private $private_tm_user = null;
    private $private_tm_pass = null;

    protected $new_keys = [];

    private $owner = "";

    private $lexiqa             = false;
    private $speech2text        = false;
    private $tag_projection     = false;
    private $project_completion = false;

    /**
     * @var BasicFeatureStruct[]
     */
    private $projectFeatures = [];

    private $metadata = [];

    const MAX_NUM_KEYS = 5;

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

    protected $id_team;

    protected $projectStructure;

    /**
     * @var ProjectManager
     */
    protected $projectManager;

    protected $postInput;

    private $due_date;

    public function __construct() {

        parent::__construct();

        //force client to close connection, avoid UPLOAD_ERR_PARTIAL for keep-alive connections
        header( "Connection: close" );

        if ( !$this->validateAuthHeader() ) {
            header( 'HTTP/1.0 401 Unauthorized' );
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ] = 'Authentication failed';
            $this->finalize ();
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
        ];

        $filterArgs = $this->featureSet->filter( 'filterNewProjectInputFilters', $filterArgs, $this->userIsLogged );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        if ( !isset( $__postInput[ 'tms_engine' ] ) || is_null( $__postInput[ 'tms_engine' ] ) ) {
            $__postInput[ 'tms_engine' ] = 1;
        }
        if ( !isset( $__postInput[ 'mt_engine' ] ) || is_null( $__postInput[ 'mt_engine' ] ) ) {
            $__postInput[ 'mt_engine' ] = 1;
        }

        //default get all public matches from TM
        $this->only_private = ( is_null( $__postInput[ 'get_public_matches' ] ) ? false : !$__postInput[ 'get_public_matches' ] );

        foreach ( $__postInput as $key => $val ) {
            $__postInput[ $key ] = urldecode( $val );
        }

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->project_name = $__postInput[ 'project_name' ];
        $this->source_lang  = $__postInput[ 'source_lang' ];
        $this->target_lang  = $__postInput[ 'target_lang' ];

        $this->tms_engine = $__postInput[ 'tms_engine' ]; // Default 1 MyMemory
        $this->mt_engine  = $__postInput[ 'mt_engine' ]; // Default 1 MyMemory
        $this->seg_rule   = ( !empty( $__postInput[ 'segmentation_rule' ] ) ) ? $__postInput[ 'segmentation_rule' ] : '';
        $this->subject    = ( !empty( $__postInput[ 'subject' ] ) ) ? $__postInput[ 'subject' ] : 'general';
        $this->owner      = $__postInput[ 'owner_email' ];
        $this->id_team    = $__postInput[ 'id_team' ];
        $this->due_date = ( empty($__postInput[ 'due_date' ]) ? null : Utils::mysqlTimestamp( $__postInput[ 'due_date' ] ) );

        // Force pretranslate_100 to be 0 or 1
        $this->pretranslate_100 = (int)!!$__postInput[ 'pretranslate_100' ];

        if ( $this->owner === false ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = "Email is not valid";
            Log::doLog( "Email is not valid" );
            return -5;
        } else {
            if ( !is_null( $this->owner ) && !empty( $this->owner ) ) {
                $domain = explode( "@", $this->owner );
                $domain = $domain[ 1 ];
                if ( !checkdnsrr( $domain ) ) {
                    $this->api_output[ 'message' ] = "Project Creation Failure";
                    $this->api_output[ 'debug' ]   = "Email is not valid";
                    Log::doLog( "Email is not valid" );
                    return -5;
                }
            }
        }

        try {
            $this->lexiqa             = $__postInput[ 'lexiqa' ];
            $this->speech2text        = $__postInput[ 'speech2text' ];
            $this->tag_projection     = $__postInput[ 'tag_projection' ];
            $this->project_completion = $__postInput[ 'project_completion' ];

            $this->validateMetadataParam( $__postInput );

        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ] = 'Error evaluating metadata param';
            Log::doLog( $ex->getMessage() );
            return -1;
        }

        try {
            $this->validateEngines();
        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ] = $ex->getMessage();
            Log::doLog( $ex->getMessage() );
            return $ex->getCode();
        }

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

        if ( !in_array( $this->subject, $subjectList ) ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = "Subject not allowed: " . $this->subject;
            Log::doLog( "Subject not allowed: " . $this->subject );
            return -3;
        }

        if ( !in_array( $this->seg_rule, self::$allowed_seg_rules ) ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = "Segmentation rule not allowed: " . $this->seg_rule;
            Log::doLog( "Segmentation rule not allowed: " . $this->seg_rule );
            return -4;
        }

        //normalize segmentation rule to what it's used internally
        if ( $this->seg_rule == 'standard' || $this->seg_rule == '' ) {
            $this->seg_rule = null;
        }

        if ( empty( $_FILES ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "Missing file. Not Sent." ];
            return -1;
        }

        try {
            $this->validateTmAndKeys( $__postInput );
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = $e->getMessage();
            Log::doLog( "Error: " . $e->getCode() . " - " . $e->getMessage() );
            return -$e->getCode();
        }

        $this->postInput = $__postInput;

    }

    private function setProjectFeatures() {
        if ( $this->postInput[ 'project_completion' ] ) {
            $feature                 = new BasicFeatureStruct();
            $feature->feature_code   = 'project_completion';
            $this->projectFeatures[] = $feature;
        }

        $this->projectFeatures = $this->featureSet->filter(
                'filterCreateProjectFeatures', $this->projectFeatures, $this->postInput, $this->userIsLogged
        );

    }

    /**
     * @throws Exception
     */
    private function validateEngines() {

        if ( $this->tms_engine != 0 ) {
            Engine::getInstance( $this->tms_engine );
        }

        if ( $this->mt_engine != 0 && $this->mt_engine != 1 ) {
            if( !$this->userIsLogged ){
                throw new Exception( "Invalid MT Engine.", -2 );
            } else {
                $testEngine = Engine::getInstance( $this->mt_engine );
                if( $testEngine->getEngineRow()->uid != $this->getUser()->uid ){
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
        try {
            $this->setProjectFeatures();
        } catch ( ValidationError $e ) {
            $this->api_output = [ 'status' => 'FAIL', 'message' => $e->getMessage() ];
            return -1;
        }

        try {
            $this->__validateTeam();
        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = $ex->getMessage();
            Log::doLog( $ex->getMessage() );

            return -1;
        }

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

        if ( empty( $this->project_name ) ) {
            $this->project_name = $default_project_name; //'NO_NAME'.$this->create_project_name();
        }

        $this->lang_handler = Langs_Languages::getInstance();
        $this->validateSourceLang();
        $this->validateTargetLangs();

        //ONE OR MORE ERRORS OCCURRED : EXITING
        //for now we sent to api output only the LAST error message, but we log all
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1; //exit code
        }

        $cookieDir = $uploadFile->getDirUploadToken();
        $intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
        $errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;

        foreach ( $arFiles as $file_name ) {
            $ext = FilesStorage::pathinfo_fix( $file_name, PATHINFO_EXTENSION );

            $conversionHandler = new ConversionHandler();
            $conversionHandler->setFileName( $file_name );
            $conversionHandler->setSourceLang( $this->source_lang );
            $conversionHandler->setTargetLang( $this->target_lang );
            $conversionHandler->setSegmentationRule( $this->seg_rule );
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

                \Log::doLog( 'fileObjets', $fileObjects );

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
                $converter->source_lang = $this->source_lang;
                $converter->target_lang = $this->target_lang;
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
            Log::doLog( $status );

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
            if ( 'zip' == FilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {

                $fs = new FilesStorage();
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

        $this->projectManager = new ProjectManager();
        $projectStructure     = $this->projectManager->getProjectStructure();

        $projectStructure[ 'sanitize_project_options' ] = false;

        $projectStructure[ 'project_name' ] = $this->project_name;
        $projectStructure[ 'job_subject' ]  = $this->subject;

        $projectStructure[ 'private_tm_key' ]       = $this->private_tm_key;
        $projectStructure[ 'private_tm_user' ]      = $this->private_tm_user;
        $projectStructure[ 'private_tm_pass' ]      = $this->private_tm_pass;
        $projectStructure[ 'uploadToken' ]          = $uploadFile->getDirUploadToken();
        $projectStructure[ 'array_files' ]          = $arFiles; //list of file name
        $projectStructure[ 'source_language' ]      = $this->source_lang;
        $projectStructure[ 'target_language' ]      = explode( ',', $this->target_lang );
        $projectStructure[ 'mt_engine' ]            = $this->mt_engine;
        $projectStructure[ 'tms_engine' ]           = $this->tms_engine;
        $projectStructure[ 'status' ]               = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'skip_lang_validation' ] = true;
        $projectStructure[ 'owner' ]                = $this->owner;
        $projectStructure[ 'metadata' ]             = $this->metadata;
        $projectStructure[ 'pretranslate_100' ]     = $this->pretranslate_100;
        $projectStructure[ 'only_private' ]         = $this->only_private;

        $projectStructure[ 'user_ip' ]   = Utils::getRealIpAddr();
        $projectStructure[ 'HTTP_HOST' ] = INIT::$HTTPHOST;
        $projectStructure[ 'due_date' ]  = $this->due_date;

        if ( $this->user ) {
            $projectStructure[ 'userIsLogged' ] = true;
            $projectStructure[ 'uid' ]          = $this->user->getUid();
            $projectStructure[ 'id_customer' ]  = $this->user->getEmail();
            $projectStructure[ 'owner' ]        = $this->user->getEmail();
            $this->projectManager->setTeam( $this->team );
        }

        //set features override
        $projectStructure[ 'project_features' ] = $this->projectFeatures;

        FilesStorage::moveFileFromUploadSessionToQueuePath( $uploadFile->getDirUploadToken() );

        //reserve a project id from the sequence
        $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $projectStructure[ 'ppassword' ]  = $this->projectManager->generatePassword();

        $projectStructure = $this->featureSet->filter( 'addNewProjectStructureAttributes', $projectStructure, $this->postInput );

        $this->projectStructure = $projectStructure;

        $this->projectManager->sanitizeProjectStructure();

        Queue::sendProject( $projectStructure );

        $this->_pollForCreationResult();

        $this->_outputResult();
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

    private function validateSourceLang() {
        try {
            $this->lang_handler->validateLanguage( $this->source_lang );
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->result[ 'errors' ][]    = [ "code" => -3, "message" => $e->getMessage() ];
        }
    }

    private function validateTargetLangs() {
        $targets = explode( ',', $this->target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            $this->api_output[ 'message' ] = "Missing target language.";
            $this->result[ 'errors' ][]    = [ "code" => -4, "message" => "Missing target language." ];
        }

        try {

            foreach ( $targets as $target ) {
                $this->lang_handler->validateLanguage( $target );
            }

        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->result[ 'errors' ][]    = [ "code" => -4, "message" => $e->getMessage() ];
        }

        $this->target_lang = implode( ',', $targets );
    }

    /**
     * Tries to find authentication credentials in header. Returns false if credentials are provided and invalid. True otherwise.
     *
     * @return bool
     */
    private function validateAuthHeader() {

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

            Log::doLog( $key );
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
    private static function sanitizeTmKeyArr( $elem ) {

        $elem = TmKeyManagement_TmKeyManagement::sanitize( new TmKeyManagement_TmKeyStruct( $elem ) );

        return $elem->toArray();

    }

    /**
     * Expects the metadata param to be a json formatted string and tries to convert it
     * in array.
     * Json string is expected to be flat key value, this is enforced padding 1 to json
     * conversion depth param.
     *
     * @param $__postInput
     *
     * @throws Exception
     */
    private function validateMetadataParam( $__postInput ) {

        if ( !empty( $__postInput[ 'metadata' ] ) ) {
            if ( strlen( $__postInput[ 'metadata' ] ) > 2048 ) {
                throw new Exception( 'metadata string is too long' );
            }
            $depth                     = 2; // only converts key value structures
            $assoc                     = true;
            $__postInput[ 'metadata' ] = html_entity_decode( $__postInput[ 'metadata' ] );
            $this->metadata            = json_decode( $__postInput[ 'metadata' ], $assoc, $depth );
            Log::doLog( "Passed parameter metadata as json string." );
        }

        //override metadata with explicitly declared keys ( we maintain metadata for backward compatibility )
        if ( !empty( $this->lexiqa ) ) {
            $this->metadata[ 'lexiqa' ] = $this->lexiqa;
        }

        if ( !empty( $this->speech2text ) ) {
            $this->metadata[ 'speech2text' ] = $this->speech2text;
        }

        if ( !empty( $this->tag_projection ) ) {
            $this->metadata[ 'tag_projection' ] = $this->tag_projection;
        }

        if ( !empty( $this->project_completion ) ) {
            $this->metadata[ 'project_completion' ] = $this->project_completion;
        }

        $this->metadata = $this->featureSet->filter( 'filterProjectMetadata', $this->metadata, $__postInput );

    }

    private static function parseTmKeyInput( $tmKeyString ) {
        $tmKeyInfo = explode( ":", $tmKeyString );
        $read      = true;
        $write     = true;

        $permissionString = $tmKeyInfo[ 1 ];
        //if the key is not set, return null. It will be filtered in the next lines.
        if ( empty( $tmKeyInfo[ 0 ] ) ) {
            return null;
        } //if permissions are set, check if they are allowed or not and eventually set permissions
        elseif ( isset( $tmKeyInfo[ 1 ] ) ) {
            //permission string not allowed
            if ( !empty( $permissionString ) &&
                    !in_array( $permissionString, Constants_TmKeyPermissions::$_accepted_grants ) ) {
                $allowed_permissions = implode( ", ", Constants_TmKeyPermissions::$_accepted_grants );
                throw new Exception( "Permission modifier string not allowed. Allowed: <empty>, $allowed_permissions" );
            } else {
                switch ( $permissionString ) {
                    case 'r':
                        $write = false;
                        break;
                    case 'w':
                        $read = false;
                        break;
                    case 'rw':
                    case ''  :
                        break;
                    //this should never be triggered
                    default:
                        $allowed_permissions = implode( ", ", Constants_TmKeyPermissions::$_accepted_grants );
                        throw new Exception( "Permission modifier string not allowed. Allowed: <empty>, $allowed_permissions" );
                        break;
                }
            }
        }

        return [
                'key' => $tmKeyInfo[ 0 ],
                'r'   => $read,
                'w'   => $write,
        ];
    }

    protected function validateTmAndKeys( $__postInput ) {

        try {
            $this->private_tm_key = array_map(
                    'self::parseTmKeyInput',
                    explode( ",", $__postInput[ 'private_tm_key' ] )
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
                $pathinfo = FilesStorage::pathinfo_fix( $_fileinfo[ 'name' ] );
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

            $this->private_tm_key[ $__key_idx ] = self::sanitizeTmKeyArr( $this->private_tm_key[ $__key_idx ] );

        }

    }


    private function __validateTeam() {
        if ( $this->user && !empty( $this->id_team ) ) {
            $dao = new MembershipDao();
            $org = $dao->findTeamByIdAndUser( $this->id_team, $this->user );

            if ( !$org ) {
                throw new Exception( 'Team and user membership does not match' );
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
