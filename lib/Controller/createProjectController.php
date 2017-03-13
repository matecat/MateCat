<?php

define( 'DEFAULT_NUM_RESULTS', 2 );
set_time_limit( 0 );

use ConnectedServices\GDrive as GDrive ;
use ProjectQueue\Queue;

class createProjectController extends ajaxController {

    private $file_name;
    private $project_name;
    private $source_language;
    private $target_language;
    private $job_subject;
    private $mt_engine;
    private $tms_engine = 1;  //1 default MyMemory
    private $private_tm_key;
    private $private_tm_user;
    private $private_tm_pass;
    private $lang_detect_files;

    private $disable_tms_engine_flag;
    private $pretranslate_100;

    private $dqf_key;
    private $metadata;

    private $lang_handler ;

    /**
     * @var \Organizations\OrganizationStruct
     */
    private $organization ;

    /**
     * @var FeatureSet
     */
    private $featureSet ;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        $filterArgs = array(
                'file_name'          => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'project_name'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'source_language'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'target_language'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'job_subject'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'mt_engine'          => array( 'filter' => FILTER_VALIDATE_INT ),
                'disable_tms_engine' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),

                'private_tm_user'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'private_tm_pass'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'lang_detect_files'  => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => "Utils::filterLangDetectArray"
                ),
                'private_tm_key'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'pretranslate_100'   => array( 'filter' => FILTER_VALIDATE_INT ),
                'dqf_key'            => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'id_organization' => array( 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR  )

                //            This will be sanitized inside the TmKeyManagement class
                //            SKIP
                //            'private_keys_list'  => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES ),

        );

        $this->checkLogin( false );


        $this->__setupFeatureSet();

        $filterArgs = $this->__addFilterForMetadataInput( $filterArgs ) ;

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //first we check the presence of a list from tm management panel
        $array_keys = json_decode( $_POST[ 'private_keys_list' ], true );
        $array_keys = array_merge( $array_keys[ 'ownergroup' ], $array_keys[ 'mine' ], $array_keys[ 'anonymous' ] );

        //if a string is sent by the client, transform it into a valid array
        if ( !empty( $__postInput[ 'private_tm_key' ] ) ) {
            $__postInput[ 'private_tm_key' ] = array(
                    array(
                            'key'  => trim($__postInput[ 'private_tm_key' ]),
                            'name' => null,
                            'r'    => true,
                            'w'    => true
                    )
            );
        }
        else {
            $__postInput[ 'private_tm_key' ] = array();
        }

        if ( $array_keys ) { // some keys are selected from panel

            //remove duplicates
            foreach ( $array_keys as $pos => $value ) {
                if ( isset( $__postInput[ 'private_tm_key' ][ 0 ][ 'key' ] )
                        && $__postInput[ 'private_tm_key' ][ 0 ][ 'key' ] == $value[ 'key' ]
                ) {
                    //same key was get from keyring, remove
                    $__postInput[ 'private_tm_key' ] = array();
                }
            }

            //merge the arrays
            $private_keyList = array_merge( $__postInput[ 'private_tm_key' ], $array_keys );


        }
        else {
            $private_keyList = $__postInput[ 'private_tm_key' ];
        }

        $__postPrivateTmKey = array_filter( $private_keyList, array( "self", "sanitizeTmKeyArr" ) );

        // NOTE: This is for debug purpose only,
        // NOTE: Global $_POST Overriding from CLI
        // $__postInput = filter_var_array( $_POST, $filterArgs );

        $this->file_name               = $__postInput[ 'file_name' ];       // da cambiare, FA SCHIFO la serializzazione
        $this->project_name            = $__postInput[ 'project_name' ];
        $this->source_language         = $__postInput[ 'source_language' ];
        $this->target_language         = $__postInput[ 'target_language' ];
        $this->job_subject             = $__postInput[ 'job_subject' ];
        $this->mt_engine               = ( $__postInput[ 'mt_engine' ] != null ? $__postInput[ 'mt_engine' ] : 0 );       // null NON è ammesso
        $this->disable_tms_engine_flag = $__postInput[ 'disable_tms_engine' ]; // se false allora MyMemory
        $this->private_tm_key          = $__postPrivateTmKey;
        $this->private_tm_user         = $__postInput[ 'private_tm_user' ];
        $this->private_tm_pass         = $__postInput[ 'private_tm_pass' ];
        $this->lang_detect_files       = $__postInput[ 'lang_detect_files' ];
        $this->pretranslate_100        = $__postInput[ 'pretranslate_100' ];
        $this->dqf_key                 = $__postInput[ 'dqf_key' ];

        $this->__setMetadataFromPostInput( $__postInput ) ;

        if ( $this->disable_tms_engine_flag ) {
            $this->tms_engine = 0; //remove default MyMemory
        }

        if ( empty( $this->file_name ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "Missing file name." );
        }

        if ( empty( $this->job_subject ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -5, "message" => "Missing job subject." );
        }

        if ( $this->pretranslate_100 !== 1 && $this->pretranslate_100 !== 0 ) {
            $this->result[ 'errors' ][ ] = array( "code" => -6, "message" => "invalid pretranslate_100 value" );
        }


        $this->lang_handler = Langs_Languages::getInstance();
        $this->__validateSourceLang();
        $this->__validateTargetLangs();
        $this->__validateUserMTEngine();

        if ( $this->userIsLogged ) {
            $this->__setOrganization( $__postInput['id_organization'] );
        }

    }

    public function doAction() {
        //check for errors. If there are, stop execution and return errors.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return false;
        }

        $arFiles              = explode( '@@SEP@@', html_entity_decode( $this->file_name, ENT_QUOTES, 'UTF-8' ) );

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

        if ( ( $key = array_search( $this->source_language, $sourceLangAr ) ) !== false ) {
            unset( $sourceLangAr[ $key ] );
        }
        array_unshift( $sourceLangAr, $this->source_language );
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

        setcookie( \Constants::COOKIE_SOURCE_LANG, $newCookieVal, time() + ( 86400 * 365 ) );

        // SET TARGET COOKIE

        if ( $targetLangHistory == \Constants::EMPTY_VAL ) {
            $targetLangHistory = "";
        }
        $targetLangAr = explode( '||', urldecode( $targetLangHistory ) );

        if ( ( $key = array_search( $this->target_language, $targetLangAr ) ) !== false ) {
            unset( $targetLangAr[ $key ] );
        }
        array_unshift( $targetLangAr, $this->target_language );
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

        setcookie( \Constants::COOKIE_TARGET_LANG, $newCookieVal, time() + ( 86400 * 365 ) );

        //search in fileNames if there's a zip file. If it's present, get filenames and add the instead of the zip file.

        $uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $_COOKIE[ 'upload_session' ];
        $newArFiles = array();

        foreach($arFiles as $__fName){
           if ( 'zip' == FilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {

               $fs = new FilesStorage();
               $fs->cacheZipArchive( sha1_file( $uploadDir . DIRECTORY_SEPARATOR . $__fName ), $uploadDir . DIRECTORY_SEPARATOR . $__fName );

               $linkFiles = scandir( $uploadDir );

               //fetch cache links, created by converter, from upload directory
               foreach ( $linkFiles as $storedFileName ) {
                   //check if file begins with the name of the zip file.
                   // If so, then it was stored in the zip file.
                   if(strpos($storedFileName, $__fName) !== false &&
                           substr($storedFileName,0, strlen($__fName)) == $__fName ){
                       //add file name to the files array
                       $newArFiles[] = $storedFileName;
                   }
               }

           } else { //this file was not in a zip. Add it normally

               if( file_exists( $uploadDir . DIRECTORY_SEPARATOR . $__fName ) ){
                   $newArFiles[ ] = $__fName;
               }

           }
        }

        $arFiles = $newArFiles;

        \Log::doLog( '------------------------------'); 
        \Log::doLog( $arFiles ); 

        FilesStorage::moveFileFromUploadSessionToQueuePath( $_COOKIE[ 'upload_session' ] );

        $projectManager = new ProjectManager();

        $projectStructure = $projectManager->getProjectStructure();

        $projectStructure[ 'project_name' ]         = $this->project_name;
        $projectStructure[ 'private_tm_key' ]       = $this->private_tm_key;
        $projectStructure[ 'private_tm_user' ]      = $this->private_tm_user;
        $projectStructure[ 'private_tm_pass' ]      = $this->private_tm_pass;
        $projectStructure[ 'uploadToken' ]          = $_COOKIE[ 'upload_session' ];
        $projectStructure[ 'array_files' ]          = $arFiles; //list of file name
        $projectStructure[ 'source_language' ]      = $this->source_language;
        $projectStructure[ 'target_language' ]      = explode( ',', $this->target_language );
        $projectStructure[ 'job_subject' ]          = $this->job_subject;
        $projectStructure[ 'mt_engine' ]            = $this->mt_engine;
        $projectStructure[ 'tms_engine' ]           = $this->tms_engine;
        $projectStructure[ 'status' ]               = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'lang_detect_files' ]    = $this->lang_detect_files;
        $projectStructure[ 'skip_lang_validation' ] = true;
        $projectStructure[ 'pretranslate_100' ]     = $this->pretranslate_100;

        $projectStructure[ 'user_ip' ]              = Utils::getRealIpAddr();

        //TODO enable from CONFIG
        $projectStructure[ 'metadata' ]             = $this->metadata;

        if ( INIT::$DQF_ENABLED ) {
            $projectStructure[ 'dqf_key' ] = $this->dqf_key;
        }

        if ( $this->userIsLogged ) {
            $projectStructure[ 'userIsLogged' ]  = true;
            $projectStructure[ 'uid' ]           = $this->uid;
            $projectStructure[ 'id_customer' ]   = $this->userMail;
            $projectStructure[ 'owner' ]         = $this->userMail ;
            $projectStructure[ 'id_organization' ] = $this->organization->id ;
        }

        //reserve a project id from the sequence
        $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $projectStructure[ 'ppassword' ]  = $projectManager->generatePassword();

        Queue::sendProject( $projectStructure );

        $this->__clearSessionFiles();
        $this->__assignLastCreatedPid( $projectStructure['id_project'] ) ; //TODO get ID from published results ( API or directly from handler in a loop )

        $this->result[ 'data' ] = [
                'id_project' => $projectStructure[ 'id_project' ],
                'password'   => $projectStructure[ 'ppassword' ]
        ];

    }

    private function __setupFeatureSet() {
        $this->featureSet = new FeatureSet() ;

        if ( $this->userIsLogged ) {
            $this->featureSet->loadFromUserEmail( $this->logged_user->email ) ;
        }
    }

    private function __addFilterForMetadataInput( $filterArgs ) {
        $filterArgs = array_merge( $filterArgs, array(
            'lexiqa'             => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'speech2text'        => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'tag_projection'     => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'segmentation_rule'  => array(
                'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            )
        ));

        $filterArgs = $this->featureSet->filter('filterCreateProjectInputFilters', $filterArgs );

        return $filterArgs ;
    }


    private function __assignLastCreatedPid( $pid ) {
        $_SESSION['redeem_project'] = FALSE ;
        $_SESSION['last_created_pid'] = $pid  ;
    }

    private function __validateTargetLangs() {
        $targets = explode( ',', $this->target_language );
        $targets = array_map('trim',$targets);
        $targets = array_unique($targets);

        if ( empty( $targets ) ) {
            $this->result[ 'errors' ][]    = array( "code" => -4, "message" => "Missing target language." );
        }

        try {
            foreach ( $targets as $target ) {
                $this->lang_handler->getLocalizedNameRFC( $target );
            }
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][]    = array( "code" => -4, "message" => $e->getMessage() );
        }

        $this->target_language = implode(',', $targets);
    }

    private function __validateSourceLang() {
        if ( empty( $this->source_language ) ) {
            $this->result[ 'errors' ][]    = array( "code" => -3, "message" => "Missing source language." );
        }

        try {
            $this->lang_handler->getLocalizedNameRFC( $this->source_language ) ;

        } catch ( Exception $e ) {
            $this->result[ 'errors' ][]    = array( "code" => -3, "message" => $e->getMessage() );
        }
    }

    private function __clearSessionFiles() {

        if ( $this->userIsLogged ) {
            $gdriveSession = new GDrive\Session() ;
            $gdriveSession->clearFiles() ;
        }
    }

    private static function sanitizeTmKeyArr( $elem ) {

        $elem = TmKeyManagement_TmKeyManagement::sanitize( new TmKeyManagement_TmKeyStruct( $elem ) );

        return $elem->toArray();

    }
    
    private function __setMetadataFromPostInput( $__postInput ) {
        $options = array() ;

        if ( isset( $__postInput['lexiqa']) )           $options['lexiqa'] = $__postInput[ 'lexiqa' ];
        if ( isset( $__postInput['speech2text']) )      $options['speech2text'] = $__postInput[ 'speech2text' ];
        if ( isset( $__postInput['tag_projection']) )   $options['tag_projection'] = $__postInput[ 'tag_projection' ];
        if ( isset( $__postInput['segmentation_rule']) ) $options['segmentation_rule'] = $__postInput[ 'segmentation_rule' ];

        $this->metadata = $options ;

        $this->metadata = $this->featureSet->filter('createProjectAssignInputMetadata', $this->metadata, array(
            'input' => $__postInput
        ));
    }

    /**
     * TODO: this should be moved to a model that.
     *
     * @param null $id_organization
     *
     * @throws Exception
     */
    private function __setOrganization($id_organization = null) {
        if ( is_null( $id_organization ) ) {
            $this->organization = $this->logged_user->getPersonalOrganization() ;
        }
        else {
            // check for the organization to be allowed
            $dao = new \Organizations\MembershipDao() ;
            $organization = $dao->findOrganizationByIdAndUser($id_organization, $this->logged_user) ;

            if ( !$organization ) {
                throw new Exception('Organization and user memberships do not match') ;
            }
            else {
                $this->organization = $organization ;
            }
        }
    }

    private function __validateUserMTEngine() {

        if( array_search( $this->mt_engine, [ 0, 1 ] ) === false ){

            if( !$this->userIsLogged ) {
                $this->result[ 'errors' ][]    = array( "code" => -2, "message" => "Invalid MT Engine." );
                return;
            }

            $engineQuery      = new EnginesModel_EngineStruct();
            $engineQuery->id  = $this->mt_engine;
            $engineQuery->uid = $this->uid;
            $enginesDao       = new EnginesModel_EngineDAO();
            $engine            = $enginesDao->setCacheTTL( 60 * 5 )->read( $engineQuery );

            if ( empty( $engine ) ){
                $this->result[ 'errors' ][]    = array( "code" => -2, "message" => "Invalid MT Engine." );
            }

        }

    }

}

