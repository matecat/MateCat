<?php

set_time_limit( 180 );

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

    private $new_keys = array();

    private $owner = "";
    private $current_user = "";
    private $metadata = array();

    const MAX_NUM_KEYS = 5;

    private static $allowed_seg_rules = array(
            'standard', 'patent', ''
    );

    protected $api_output = array(
            'status'  => 'FAIL',
            'message' => 'Untraceable error (sorry, not mapped)'
    );


    public function __construct() {

        //limit execution time to 300 seconds
        set_time_limit( 300 );

        parent::__construct();

        //force client to close connection, avoid UPLOAD_ERR_PARTIAL for keep-alive connections
        header( "Connection: close" );

        $filterArgs = array(
                'project_name'      => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'source_lang'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'target_lang'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'tms_engine'        => array(
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => array( 'default' => 1, 'min_range' => 0 )
                ),
                'mt_engine'         => array(
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => array( 'default' => 1, 'min_range' => 0 )
                ),
                'private_tm_key'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'subject'           => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'segmentation_rule' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'owner_email'       => array(
                        'filter' => FILTER_VALIDATE_EMAIL
                ),
                'metadata' => array(
                    'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )

        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        if ( !isset( $__postInput[ 'tms_engine' ] ) || is_null( $__postInput[ 'tms_engine' ] ) ) {
            $__postInput[ 'tms_engine' ] = 1;
        }
        if ( !isset( $__postInput[ 'mt_engine' ] ) || is_null( $__postInput[ 'mt_engine' ] ) ) {
            $__postInput[ 'mt_engine' ] = 1;
        }

        foreach ( $__postInput as $key => $val ) {
            $__postInput[ $key ] = urldecode( $val );
        }


        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->project_name = $__postInput[ 'project_name' ];
        $this->source_lang  = $__postInput[ 'source_lang' ];

        $langTarget = explode( ',', $__postInput[ 'target_lang' ] );
        $langTarget = array_map('trim',$langTarget);
        $langTarget = array_unique($langTarget);
        $this->target_lang = implode( ',', $langTarget );

        $this->tms_engine   = $__postInput[ 'tms_engine' ]; // Default 1 MyMemory
        $this->mt_engine    = $__postInput[ 'mt_engine' ]; // Default 1 MyMemory
        $this->seg_rule     = ( !empty( $__postInput[ 'segmentation_rule' ] ) ) ? $__postInput[ 'segmentation_rule' ] : '';
        $this->subject      = ( !empty( $__postInput[ 'subject' ] ) ) ? $__postInput[ 'subject' ] : 'general';
        $this->owner        = $__postInput[ 'owner_email' ];

        try {
            $this->private_tm_key = array_map(
                    'self::parseTmKeyInput',
                    explode( ",", $__postInput[ 'private_tm_key' ] )
            );
        } catch ( Exception $e ) {
            $this->api_output[ 'message' ] = $e->getMessage();
            $this->api_output[ 'debug' ]   = $e->getMessage();
            Log::doLog( $e->getMessage() );

            return -6;
        }

        if ( $this->owner === false ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = "Email is not valid";
            Log::doLog( "Email is not valid" );

            return -5;
        } else if ( !is_null( $this->owner ) && !empty( $this->owner ) ) {
            $domain = explode( "@", $this->owner );
            $domain = $domain[ 1 ];
            if ( !checkdnsrr( $domain ) ) {
                $this->api_output[ 'message' ] = "Project Creation Failure";
                $this->api_output[ 'debug' ]   = "Email is not valid";
                Log::doLog( "Email is not valid" );

                return -5;
            }
        }

        try {
            $this->validateMetadataParam($__postInput['metadata']);
        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = 'Error evaluating metadata param';
            Log::doLog( $ex->getMessage() );

            return -1;
        }


        try {
            $this->validateEngines();
        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = $ex->getMessage();
            Log::doLog( $ex->getMessage() );

            return -1;
        }

        if ( count( $this->private_tm_key ) > self::MAX_NUM_KEYS ) {
            $this->api_output[ 'message' ] = "Project Creation Failure";
            $this->api_output[ 'debug' ]   = "Too much keys provided. Max number of keys is " . self::MAX_NUM_KEYS;
            Log::doLog( "Too much keys provided. Max number of keys is " . self::MAX_NUM_KEYS );

            return -2;
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
        if ( !function_exists( 'array_column' ) ) {
            $subjectList = Utils::array_column( $subjectList, 'key' );
        } else {
            $subjectList = array_column( $subjectList, 'key' );
        }

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
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "Missing file. Not Sent." );

            return -1;
        }

        $this->private_tm_key = array_values( array_filter( $this->private_tm_key ) );

        //If a TMX file has been uploaded and no key was provided, create a new key.
        if ( empty( $this->private_tm_key ) ) {
            foreach ( $_FILES as $_fileinfo ) {
                $pathinfo = FilesStorage::pathinfo_fix( $_fileinfo[ 'name' ] );
                if ( $pathinfo[ 'extension' ] == 'tmx' ) {
                    $this->private_tm_key[] = 'new';
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
                            array(
                                    'key'  => $newUser->key,
                                    'name' => null,
                                    'r'    => $tm_key[ 'r' ],
                                    'w'    => $tm_key[ 'w' ]

                            );
                    $this->new_keys[]                   = $newUser->key;

                } catch ( Exception $e ) {

                    $this->api_output[ 'message' ] = 'Project Creation Failure';
                    $this->api_output[ 'debug' ]   = array( "code" => $e->getCode(), "message" => $e->getMessage() );

                    return -1;
                }

            } //if a string is sent, transform it into a valid array
            else if ( !empty( $tm_key ) ) {
                $this->private_tm_key[ $__key_idx ] =
                        array(
                                'key'  => $tm_key[ 'key' ],
                                'name' => null,
                                'r'    => $tm_key['r'],
                                'w'    => $tm_key['w']

                        );
            }

            $this->private_tm_key[ $__key_idx ] = array_filter(
                    $this->private_tm_key[ $__key_idx ],
                    array( "self", "sanitizeTmKeyArr" )
            );
        }
    }

    /**
     * @throws Exception
     */
    private function validateEngines() {
        if ( $this->tms_engine != 0 ) {
            Engine::getInstance( $this->tms_engine );
        }
        if ( $this->mt_engine != 0 && $this->mt_engine != 1 ) {
            Engine::getInstance( $this->mt_engine );
        }
    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

    public function doAction() {
        if ( !$this->validateAuthHeader() ) {
            header( 'HTTP/1.0 401 Unauthorized' );
            $this->api_output[ 'message' ] = 'Authentication failed';

            return -1;
        }

        if ( @count( $this->api_output[ 'debug' ] ) > 0 ) {
            return;
        }

        $uploadFile = new Upload();

        try {
            $stdResult = $uploadFile->uploadFiles( $_FILES );
        } catch ( Exception $e ) {
            $stdResult                     = array();
            $this->result                  = array(
                    'errors' => array(
                            array( "code" => -1, "message" => $e->getMessage() )
                    )
            );
            $this->api_output[ 'message' ] = $e->getMessage();
        }

        $arFiles = array();

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

        if ( empty( $this->source_lang ) ) {
            $this->api_output[ 'message' ] = "Missing source language.";
            $this->result[ 'errors' ][]    = array( "code" => -3, "message" => "Missing source language." );
        }

        if ( empty( $this->target_lang ) ) {
            $this->api_output[ 'message' ] = "Missing target language.";
            $this->result[ 'errors' ][]    = array( "code" => -4, "message" => "Missing target language." );
        }

        //ONE OR MORE ERRORS OCCURRED : EXITING
        //for now we sent to api output only the LAST error message, but we log all
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1; //exit code
        }

        $cookieDir      = $uploadFile->getDirUploadToken();
        $intDir         = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
        $errDir         = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;
        $response_stack = array();

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

            $status = array();

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
                        $this->result[ 'errors' ][ $brokenFileName ] = array(
                                'code'    => $fileError->error[ 'code' ],
                                'message' => $fileError->error[ 'message' ],
                                'debug'   => $brokenFileName
                        );
                    }

                }

                $realFileObjectInfo  = $fileObjects;
                $realFileObjectNames = array_map(
                        array( 'ZipArchiveExtended', 'getFileName' ),
                        $fileObjects
                );

                foreach ( $realFileObjectNames as $i => &$fileObject ) {
                    $__fileName     = $fileObject;
                    $__realFileName = $realFileObjectInfo[ $i ];
                    $filesize       = filesize( $intDir . DIRECTORY_SEPARATOR . $__realFileName );

                    $fileObject               = array(
                            'name' => $__fileName,
                            'size' => $filesize
                    );
                    $realFileObjectInfo[ $i ] = $fileObject;
                }

                $this->result[ 'data' ][ $file_name ] = json_encode( $realFileObjectNames );

                $stdFileObjects = array();

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
                    $errors = array_map( array( 'Upload', 'formatExceptionMessage' ), $errors[ 'errors' ] );

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
                $converter->doAction();

                $status = $errors = $converter->checkResult();
                if ( count( $errors ) > 0 ) {
//                    $this->result[ 'errors' ] = array_merge( $this->result[ 'errors' ], $errors );
                    $this->result[ 'code' ] = 2;
                    foreach ( $errors as $__err ) {
                        $brokenFileName = ZipArchiveExtended::getFileName( $__err[ 'debug' ] );

                        if ( !isset( $this->result[ 'errors' ][ $brokenFileName ] ) ) {
                            $this->result[ 'errors' ][ $brokenFileName ] = array(
                                    'code'    => $__err[ 'code' ],
                                    'message' => $__err[ 'message' ],
                                    'debug'   => $brokenFileName
                            );
                        }
                    }
                }
            } else {
                $conversionHandler->doAction();

                $this->result = $conversionHandler->getResult();

                if ( $this->result[ 'code' ] > 0 ) {
                    $this->result = array();
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

        $newArFiles = array();
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

        $projectManager   = new ProjectManager();
        $projectStructure = $projectManager->getProjectStructure();

        $projectStructure[ 'project_name' ] = $this->project_name;
        $projectStructure[ 'job_subject' ]  = $this->subject;

        $projectStructure[ 'result' ]               = $this->result;
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
        $projectStructure[ 'metadata' ]         = $this->metadata ;

        if ( $this->current_user != null ) {
            $projectStructure[ 'owner' ]       = $this->current_user->getEmail();
            $projectStructure[ 'id_customer' ] = $this->current_user->getEmail();
        }

        $projectManager = new ProjectManager( $projectStructure );
        
        $projectManager->sanitizeProjectOptions = false ; 
        
        $projectManager->createProject();

        $this->result = $projectStructure[ 'result' ];

        if ( !empty( $projectStructure[ 'result' ][ 'errors' ] ) ) {
            //errors already logged
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = array_values( $projectStructure[ 'result' ][ 'errors' ] );

        } else {
            //everything ok
            $this->api_output[ 'status' ]       = 'OK';
            $this->api_output[ 'message' ]      = 'Success';
            $this->api_output[ 'id_project' ]   = $projectStructure[ 'result' ][ 'id_project' ];
            $this->api_output[ 'project_pass' ] = $projectStructure[ 'result' ][ 'ppassword' ];

            $this->api_output[ 'new_keys' ] = $this->new_keys;

            $this->api_output[ 'analyze_url' ] = $projectStructure[ 'result' ][ 'analyze_url' ];
        }

    }

    private function validateAuthHeader() {
        if ( $_SERVER[ 'HTTP_X_MATECAT_KEY' ] == null ) {
            return true;
        }

        $key = ApiKeys_ApiKeyDao::findByKey( $_SERVER[ 'HTTP_X_MATECAT_KEY' ] );
        if ( $key && $key->validSecret( $_SERVER[ 'HTTP_X_MATECAT_SECRET' ] ) ) {
            Log::doLog( $key );

            $this->current_user = $key->getUser();

            return true;
        } else {
            return false;
        }
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
     * @param $json_string
     */
    private function validateMetadataParam($json_string) {
        if (!empty($json_string)) {
            if ( strlen($json_string) > 2048 ) {
                throw new Exception('metadata string is too long');
            }
            $depth = 2 ; // only converts key value structures
            $assoc = TRUE;
            $json_string = html_entity_decode($json_string);
            $this->metadata = json_decode( $json_string, $assoc, $depth );
        }
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

        return array(
                'key' => $tmKeyInfo[ 0 ],
                'r'   => $read,
                'w'   => $write,
        );
    }
}
