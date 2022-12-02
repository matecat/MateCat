<?php

use ConnectedServices\GDrive as GDrive;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use ProjectQueue\Queue;
use Matecat\XliffParser\Utils\Files as XliffFiles;

class createProjectController extends ajaxController {

    private $file_name;
    private $project_name;
    private $source_lang;
    private $target_lang;
    private $job_subject;
    private $mt_engine;
    private $tms_engine = 1;  //1 default MyMemory
    private $private_tm_key;
    private $private_tm_user;
    private $private_tm_pass;
    private $lang_detect_files;
    private $disable_tms_engine_flag;
    private $pretranslate_100;
    private $only_private;
    private $due_date;
    private $metadata;

    /**
     * @var \Teams\TeamStruct
     */
    private $team;

    /**
     * @var BasicFeatureStruct[]
     */
    private $projectFeatures = [];

    public $postInput;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        $filterArgs = [
                'file_name'          => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'project_name'       => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'source_lang'    => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'target_lang'    => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'job_subject'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'due_date'           => [ 'filter' => FILTER_VALIDATE_INT ],
                'mt_engine'          => [ 'filter' => FILTER_VALIDATE_INT ],
                'disable_tms_engine' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],

                'private_tm_user'   => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'private_tm_pass'   => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'lang_detect_files' => [
                        'filter'  => FILTER_CALLBACK,
                        'options' => "Utils::filterLangDetectArray"
                ],
                'private_tm_key'    => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'pretranslate_100'  => [ 'filter' => FILTER_VALIDATE_INT ],
                'id_team'           => [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR ],

                'project_completion' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ], // features customization
                'get_public_matches' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ], // disable public TM matches

        ];

        $this->readLoginInfo( false );
        $this->setupUserFeatures();

        $filterArgs = $this->__addFilterForMetadataInput( $filterArgs );

        $this->postInput = filter_input_array( INPUT_POST, $filterArgs );

        //first we check the presence of a list from tm management panel
        $array_keys = json_decode( $_POST[ 'private_keys_list' ], true );
        $array_keys = array_merge( $array_keys[ 'ownergroup' ], $array_keys[ 'mine' ], $array_keys[ 'anonymous' ] );

        //if a string is sent by the client, transform it into a valid array
        if ( !empty( $this->postInput[ 'private_tm_key' ] ) ) {
            $this->postInput[ 'private_tm_key' ] = [
                    [
                            'key'  => trim( $this->postInput[ 'private_tm_key' ] ),
                            'name' => null,
                            'r'    => true,
                            'w'    => true
                    ]
            ];
        } else {
            $this->postInput[ 'private_tm_key' ] = [];
        }

        if ( $array_keys ) { // some keys are selected from panel

            //remove duplicates
            foreach ( $array_keys as $pos => $value ) {
                if ( isset( $this->postInput[ 'private_tm_key' ][ 0 ][ 'key' ] )
                        && $this->postInput[ 'private_tm_key' ][ 0 ][ 'key' ] == $value[ 'key' ]
                ) {
                    //same key was get from keyring, remove
                    $this->postInput[ 'private_tm_key' ] = [];
                }
            }

            //merge the arrays
            $private_keyList = array_merge( $this->postInput[ 'private_tm_key' ], $array_keys );


        } else {
            $private_keyList = $this->postInput[ 'private_tm_key' ];
        }

        $__postPrivateTmKey = array_filter( $private_keyList, [ "self", "sanitizeTmKeyArr" ] );

        // NOTE: This is for debug purpose only,
        // NOTE: Global $_POST Overriding from CLI
        // $this->__postInput = filter_var_array( $_POST, $filterArgs );

        $this->file_name               = $this->postInput[ 'file_name' ];       // da cambiare, FA SCHIFO la serializzazione
        $this->project_name            = $this->postInput[ 'project_name' ];
        $this->source_lang             = $this->postInput[ 'source_lang' ];
        $this->target_lang             = $this->postInput[ 'target_lang' ];
        $this->job_subject             = $this->postInput[ 'job_subject' ];
        $this->mt_engine               = ( $this->postInput[ 'mt_engine' ] != null ? $this->postInput[ 'mt_engine' ] : 0 );       // null NON è ammesso
        $this->disable_tms_engine_flag = $this->postInput[ 'disable_tms_engine' ]; // se false allora MyMemory
        $this->private_tm_key          = $__postPrivateTmKey;
        $this->private_tm_user         = $this->postInput[ 'private_tm_user' ];
        $this->private_tm_pass         = $this->postInput[ 'private_tm_pass' ];
        $this->lang_detect_files       = $this->postInput[ 'lang_detect_files' ];
        $this->pretranslate_100        = $this->postInput[ 'pretranslate_100' ];
        $this->only_private            = ( is_null( $this->postInput[ 'get_public_matches' ] ) ? false : !$this->postInput[ 'get_public_matches' ] );
        $this->due_date                = ( empty( $this->postInput[ 'due_date' ] ) ? null : Utils::mysqlTimestamp( $this->postInput[ 'due_date' ] ) );

        $this->__setMetadataFromPostInput();

        if ( $this->disable_tms_engine_flag ) {
            $this->tms_engine = 0; //remove default MyMemory
        }

        if ( empty( $this->file_name ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "Missing file name." ];
        }

        if ( empty( $this->job_subject ) ) {
            $this->result[ 'errors' ][] = [ "code" => -5, "message" => "Missing job subject." ];
        }

        if ( $this->pretranslate_100 !== 1 && $this->pretranslate_100 !== 0 ) {
            $this->result[ 'errors' ][] = [ "code" => -6, "message" => "invalid pretranslate_100 value" ];
        }


        $this->__validateSourceLang( Langs_Languages::getInstance() );
        $this->__validateTargetLangs( Langs_Languages::getInstance() );
        $this->__validateUserMTEngine();
        $this->__appendFeaturesToProject();
        $this->__generateTargetEngineAssociation();
        if ( $this->userIsLogged ) {
            $this->__setTeam( $this->postInput[ 'id_team' ] );
        }
    }

    /**
     * setProjectFeatures
     *
     * @throws \Exceptions\NotFoundException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */

    private function __appendFeaturesToProject() {
        // change project features

        if ( !empty( $this->postInput[ 'project_completion' ] ) ) {
            $feature                 = new BasicFeatureStruct();
            $feature->feature_code   = 'project_completion';
            $this->projectFeatures[] = $feature;
        }

        $this->projectFeatures = $this->featureSet->filter(
                'filterCreateProjectFeatures', $this->projectFeatures, $this
        );

    }

    public function doAction() {
        //check for errors. If there are, stop execution and return errors.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return false;
        }

        $arFiles = explode( '@@SEP@@', html_entity_decode( $this->file_name, ENT_QUOTES, 'UTF-8' ) );

        $default_project_name = $arFiles[ 0 ];
        if ( count( $arFiles ) > 1 ) {
            $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
        }

        if ( empty( $this->project_name ) ) {
            $this->project_name = $default_project_name;
        }

        $sourceLangHistory = $_COOKIE[ \Constants::COOKIE_SOURCE_LANG ];
        $targetLangHistory = $_COOKIE[ \Constants::COOKIE_TARGET_LANG ];

        // SET SOURCE COOKIE

        if ( $sourceLangHistory == \Constants::EMPTY_VAL ) {
            $sourceLangHistory = "";
        }
        $sourceLangAr = explode( '||', urldecode( $sourceLangHistory ) );

        if ( ( $key = array_search( $this->source_lang, $sourceLangAr ) ) !== false ) {
            unset( $sourceLangAr[ $key ] );
        }
        array_unshift( $sourceLangAr, $this->source_lang );
        if ( $sourceLangAr == \Constants::EMPTY_VAL ) {
            $sourceLangAr = "";
        }
        $newCookieVal = "";
        $sourceLangAr = array_slice( $sourceLangAr, 0, 3 );
        $sourceLangAr = array_reverse( $sourceLangAr );

        foreach ( $sourceLangAr as $key => $link ) {
            if ( $sourceLangAr[ $key ] == '' ) {
                unset( $sourceLangAr[ $key ] );
            }
        }

        foreach ( $sourceLangAr as $lang ) {
            if ( $lang != "" ) {
                $newCookieVal = $lang . "||" . $newCookieVal;
            }
        }

        CookieManager::setCookie( Constants::COOKIE_SOURCE_LANG, $newCookieVal,
                [
                        'expires'  => time() + ( 86400 * 365 ),
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'None',
                ]
        );

        // SET TARGET COOKIE

        if ( $targetLangHistory == \Constants::EMPTY_VAL ) {
            $targetLangHistory = "";
        }
        $targetLangAr = explode( '||', urldecode( $targetLangHistory ) );

        if ( ( $key = array_search( $this->target_lang, $targetLangAr ) ) !== false ) {
            unset( $targetLangAr[ $key ] );
        }
        array_unshift( $targetLangAr, $this->target_lang );
        if ( $targetLangAr == \Constants::EMPTY_VAL ) {
            $targetLangAr = "";
        }
        $newCookieVal = "";
        $targetLangAr = array_slice( $targetLangAr, 0, 3 );
        $targetLangAr = array_reverse( $targetLangAr );

        foreach ( $targetLangAr as $key => $link ) {
            if ( $targetLangAr[ $key ] == '' ) {
                unset( $targetLangAr[ $key ] );
            }
        }

        foreach ( $targetLangAr as $lang ) {
            if ( $lang != "" ) {
                $newCookieVal = $lang . "||" . $newCookieVal;
            }
        }

        CookieManager::setCookie( Constants::COOKIE_SOURCE_LANG, $newCookieVal,
                [
                        'expires'  => time() + ( 86400 * 365 ),
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'None',
                ]
        );

        //search in fileNames if there's a zip file. If it's present, get filenames and add the instead of the zip file.

        $uploadDir  = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $_COOKIE[ 'upload_session' ];
        $newArFiles = [];
        $fs = FilesStorageFactory::create();

        foreach ( $arFiles as $__fName ) {
            if ( 'zip' == AbstractFilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {

                $fs->cacheZipArchive( sha1_file( $uploadDir . DIRECTORY_SEPARATOR . $__fName ), $uploadDir . DIRECTORY_SEPARATOR . $__fName );

                $linkFiles = scandir( $uploadDir );

                //fetch cache links, created by converter, from upload directory
                foreach ( $linkFiles as $storedFileName ) {
                    //check if file begins with the name of the zip file.
                    // If so, then it was stored in the zip file.
                    if ( strpos( $storedFileName, $__fName ) !== false &&
                            substr( $storedFileName, 0, strlen( $__fName ) ) == $__fName ) {
                        //add file name to the files array
                        $newArFiles[] = $storedFileName;
                    }
                }

            } else { //this file was not in a zip. Add it normally

                if ( file_exists( $uploadDir . DIRECTORY_SEPARATOR . $__fName ) ) {
                    $newArFiles[] = $__fName;
                }

            }
        }

        $arFiles = $newArFiles;
        $arMeta  = [];

        // create array_files_meta
        foreach ($arFiles as $arFile){
            $arMeta[] = $this->getFileMetadata($uploadDir .DIRECTORY_SEPARATOR . $arFile);
        }

        $projectManager = new ProjectManager();

        $projectStructure = $projectManager->getProjectStructure();

        $projectStructure[ 'project_name' ]                 = $this->project_name;
        $projectStructure[ 'private_tm_key' ]               = $this->private_tm_key;
        $projectStructure[ 'private_tm_user' ]              = $this->private_tm_user;
        $projectStructure[ 'private_tm_pass' ]              = $this->private_tm_pass;
        $projectStructure[ 'uploadToken' ]                  = $_COOKIE[ 'upload_session' ];
        $projectStructure[ 'array_files' ]                  = $arFiles; //list of file name
        $projectStructure[ 'array_files_meta' ]             = $arMeta; //list of file metadata
        $projectStructure[ 'source_language' ]              = $this->source_lang;
        $projectStructure[ 'target_language' ]              = explode( ',', $this->target_lang );
        $projectStructure[ 'job_subject' ]                  = $this->job_subject;
        $projectStructure[ 'mt_engine' ]                    = $this->mt_engine;
        $projectStructure[ 'tms_engine' ]                   = $this->tms_engine;
        $projectStructure[ 'status' ]                       = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'lang_detect_files' ]            = $this->lang_detect_files;
        $projectStructure[ 'skip_lang_validation' ]         = true;
        $projectStructure[ 'pretranslate_100' ]             = $this->pretranslate_100;
        $projectStructure[ 'only_private' ]                 = $this->only_private;
        $projectStructure[ 'due_date' ]                     = $this->due_date;
        $projectStructure[ 'target_language_mt_engine_id' ] = $this->postInput[ 'target_language_mt_engine_id' ];
        $projectStructure[ 'user_ip' ]                      = Utils::getRealIpAddr();
        $projectStructure[ 'HTTP_HOST' ]                    = INIT::$HTTPHOST;

        //TODO enable from CONFIG
        $projectStructure[ 'metadata' ] = $this->metadata;

        if ( $this->userIsLogged ) {
            $projectStructure[ 'userIsLogged' ] = true;
            $projectStructure[ 'uid' ]          = $this->user->uid;
            $projectStructure[ 'id_customer' ]  = $this->user->email;
            $projectStructure[ 'owner' ]        = $this->user->email;
            $projectManager->setTeam( $this->team ); // set the team object to avoid useless query
        }

        //set features override
        $projectStructure[ 'project_features' ] = $this->projectFeatures;

        //reserve a project id from the sequence
        $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $projectStructure[ 'ppassword' ]  = $projectManager->generatePassword();

        try {
            $projectManager->sanitizeProjectStructure();
        } catch ( Exception $e ){
            $this->result[ 'errors' ][] = [
                    "code" => $e->getCode(),
                    "message" => $e->getMessage()
            ];

            return -1;
        }

        try {
            $fs::moveFileFromUploadSessionToQueuePath( $_COOKIE[ 'upload_session' ] );
        } catch ( Exception $e ){
            $this->result[ 'errors' ][] = [
                    "code" => -235, // Error during moving file from upload session folder to queue path
                    "message" => $e->getMessage()
            ];

            return -1;
        }

        Queue::sendProject( $projectStructure );

        $this->__clearSessionFiles();
        $this->__assignLastCreatedPid( $projectStructure[ 'id_project' ] );

        $this->result[ 'data' ] = [
                'id_project' => $projectStructure[ 'id_project' ],
                'password'   => $projectStructure[ 'ppassword' ]
        ];

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
             'proprietary'             => $info[ 'proprietary' ],
             'proprietary_name'        => $info[ 'proprietary_name' ],
             'proprietary_short_name'  => $info[ 'proprietary_short_name' ],
        ];

        return $metadata;
    }

    /**
     * Loads current features from current logged user.
     */
    private function setupUserFeatures() {
        if ( $this->userIsLogged ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
        }
    }

    private function __addFilterForMetadataInput( $filterArgs ) {
        $filterArgs = array_merge( $filterArgs, [
                'lexiqa'            => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'speech2text'       => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'tag_projection'    => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'segmentation_rule' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ] );

        $filterArgs = $this->featureSet->filter( 'filterCreateProjectInputFilters', $filterArgs );

        return $filterArgs;
    }


    private function __assignLastCreatedPid( $pid ) {
        $_SESSION[ 'redeem_project' ]   = false;
        $_SESSION[ 'last_created_pid' ] = $pid;
    }

    private function __validateTargetLangs( Langs_Languages $lang_handler ) {
        $targets = explode( ',', $this->target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            $this->result[ 'errors' ][] = [ "code" => -4, "message" => "Missing target language." ];
        }

        try {
            foreach ( $targets as $target ) {
                $lang_handler->validateLanguage( $target );
            }
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -4, "message" => $e->getMessage() ];
        }

        $this->target_lang = implode( ',', $targets );
    }

    private function __validateSourceLang( Langs_Languages $lang_handler ) {
        try {
            $lang_handler->validateLanguage( $this->source_lang );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -3, "message" => $e->getMessage() ];
        }
    }

    private function __clearSessionFiles() {

        if ( $this->userIsLogged ) {
            $gdriveSession = new GDrive\Session();
            $gdriveSession->clearFileListFromSession();
        }
    }

    private static function sanitizeTmKeyArr( $elem ) {

        $element = new TmKeyManagement_TmKeyStruct( $elem );
        $element->complete_format = true;
        $elem = TmKeyManagement_TmKeyManagement::sanitize( $element );

        return $elem->toArray();

    }

    /**
     * This function sets metadata property from input params.
     *
     */
    private function __setMetadataFromPostInput() {
        $options = [];

        if ( isset( $this->postInput[ 'lexiqa' ] ) ) {
            $options[ 'lexiqa' ] = $this->postInput[ 'lexiqa' ];
        }
        if ( isset( $this->postInput[ 'speech2text' ] ) ) {
            $options[ 'speech2text' ] = $this->postInput[ 'speech2text' ];
        }
        if ( isset( $this->postInput[ 'tag_projection' ] ) ) {
            $options[ 'tag_projection' ] = $this->postInput[ 'tag_projection' ];
        }
        if ( isset( $this->postInput[ 'segmentation_rule' ] ) ) {
            $options[ 'segmentation_rule' ] = $this->postInput[ 'segmentation_rule' ];
        }

        $this->metadata = $options;

        $this->metadata = $this->featureSet->filter( 'createProjectAssignInputMetadata', $this->metadata, [
                'input' => $this->postInput
        ] );
    }

    /**
     * TODO: this should be moved to a model that.
     *
     * @param null $id_team
     *
     * @throws Exception
     */
    private function __setTeam( $id_team = null ) {
        if ( is_null( $id_team ) ) {
            $this->team = $this->user->getPersonalTeam();
        } else {
            // check for the team to be allowed
            $dao  = new \Teams\MembershipDao();
            $team = $dao->findTeamByIdAndUser( $id_team, $this->user );

            if ( !$team ) {
                throw new Exception( 'Team and user memberships do not match' );
            } else {
                $this->team = $team;
            }
        }
    }

    private function __validateUserMTEngine() {

        if ( array_search( $this->mt_engine, [ 0, 1 ] ) === false ) {

            if ( !$this->userIsLogged ) {
                $this->result[ 'errors' ][] = [ "code" => -2, "message" => "Invalid MT Engine." ];

                return;
            }

            $engineQuery      = new EnginesModel_EngineStruct();
            $engineQuery->id  = $this->mt_engine;
            $engineQuery->uid = $this->user->uid;
            $enginesDao       = new EnginesModel_EngineDAO();
            $engine           = $enginesDao->setCacheTTL( 60 * 5 )->read( $engineQuery );

            if ( empty( $engine ) ) {
                $this->result[ 'errors' ][] = [ "code" => -2, "message" => "Invalid MT Engine." ];
            }

        }

    }

    /**
     * This could be already set by MMT engine if enabled ( so check key existence and do not override )
     *
     * @see filterCreateProjectFeatures callback
     * @see createProjectController::__appendFeaturesToProject()
     */
    private function __generateTargetEngineAssociation(){
        if( !isset( $this->postInput[ 'target_language_mt_engine_id' ] ) ){ // this could be already set by MMT engine if enabled ( so check and do not override )
            foreach( explode( ",", $this->target_lang ) as $_matecatTarget ){
                $this->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = $this->mt_engine;
            }
        }
    }

}

