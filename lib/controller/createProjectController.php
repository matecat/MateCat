<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/ProjectManager.php";
include_once INIT::$UTILS_ROOT . "/RecursiveArrayObject.php";

define( 'DEFAULT_NUM_RESULTS', 2 );
set_time_limit( 0 );

class createProjectController extends ajaxController {

    private $file_name;
    private $project_name;
    private $source_language;
    private $target_language;
    private $mt_engine;
    private $tms_engine = 1;  //1 default MyMemory
    private $private_tm_key;
    private $private_tm_user;
    private $private_tm_pass;
    private $lang_detect_files;

    private $disable_tms_engine_flag;

    private $isLogged;
    private $uid;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        $filterArgs = array(
                'file_name'          => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'project_name'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'source_language'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'target_language'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'mt_engine'          => array( 'filter' => FILTER_VALIDATE_INT ),
                'disable_tms_engine' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
                'private_tm_key'     => array(
                        'filter' => FILTER_CALLBACK,
                        'options' => array( "self", "sanitizeTmKeyArr" )
                ),
                'private_tm_user'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'private_tm_pass'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'lang_detect_files'  => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => "Utils::filterLangDetectArray"
                )
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->file_name               = $__postInput[ 'file_name' ]; // da cambiare, FA SCHIFO la serializzazione
        $this->project_name            = $__postInput[ 'project_name' ];
        $this->source_language         = $__postInput[ 'source_language' ];
        $this->target_language         = $__postInput[ 'target_language' ];
        $this->mt_engine               = $__postInput[ 'mt_engine' ]; // null è ammesso
        $this->disable_tms_engine_flag = $__postInput[ 'disable_tms_engine' ]; // se false allora MyMemory
        $this->private_tm_key          = $__postInput[ 'private_tm_key' ];
        $this->private_tm_user         = $__postInput[ 'private_tm_user' ];
        $this->private_tm_pass         = $__postInput[ 'private_tm_pass' ];
        $this->lang_detect_files       = $__postInput[ 'lang_detect_files' ];

        if ( $this->disable_tms_engine_flag ) {
            $this->tms_engine = 0; //remove default MyMemory
        }

        //json_decode the tm_key array if it has been passed
        if( !empty($this->private_tm_key )){
//            $this->private_tm_key = json_decode($this->private_tm_key);

            if($this->private_tm_key === null ){
                $this->result[ 'errors' ][ ] = array( "code" => -5, "message" => "Invalid tm key passed." );
            }
        }

        if ( empty( $this->file_name ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "Missing file name." );
        }

        if ( empty( $this->source_language ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "Missing source language." );
        }

        if ( empty( $this->target_language ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "Missing target language." );
        }
    }

    public function doAction() {
        //check for errors. If there are, stop execution and return errors.
        if(count($this->result[ 'errors' ] ) ) return false;

        $arFiles              = explode( '@@SEP@@', html_entity_decode( $this->file_name, ENT_QUOTES, 'UTF-8' ) );
        $default_project_name = $arFiles[ 0 ];
        if ( count( $arFiles ) > 1 ) {
            $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
        }

        if ( empty( $this->project_name ) ) {
            $this->project_name = $default_project_name;
        }

        $sourceLangHistory = $_COOKIE[ "sourceLang" ];
        $targetLangHistory = $_COOKIE[ "targetLang" ];

        // SET SOURCE COOKIE

        if ( $sourceLangHistory == '_EMPTY_' ) {
            $sourceLangHistory = "";
        }
        $sourceLangAr = explode( '||', urldecode( $sourceLangHistory ) );

        if ( ( $key = array_search( $this->source_language, $sourceLangAr ) ) !== false ) {
            unset( $sourceLangAr[ $key ] );
        }
        array_unshift( $sourceLangAr, $this->source_language );
        if ( $sourceLangAr == '_EMPTY_' ) {
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

        setcookie( "sourceLang", $newCookieVal, time() + ( 86400 * 365 ) );

        // SET TARGET COOKIE

        if ( $targetLangHistory == '_EMPTY_' ) {
            $targetLangHistory = "";
        }
        $targetLangAr = explode( '||', urldecode( $targetLangHistory ) );

        if ( ( $key = array_search( $this->target_language, $targetLangAr ) ) !== false ) {
            unset( $targetLangAr[ $key ] );
        }
        array_unshift( $targetLangAr, $this->target_language );
        if ( $targetLangAr == '_EMPTY_' ) {
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

        setcookie( "targetLang", $newCookieVal, time() + ( 86400 * 365 ) );

        $projectManager = new ProjectManager( );

        $projectStructure = $projectManager->getProjectStructure();

        $projectStructure[ 'project_name' ]      = $this->project_name;
        $projectStructure[ 'result' ]            = $this->result;
        $projectStructure[ 'private_tm_key' ]    = $this->private_tm_key;
        $projectStructure[ 'private_tm_user' ]   = $this->private_tm_user;
        $projectStructure[ 'private_tm_pass' ]   = $this->private_tm_pass;
        $projectStructure[ 'uploadToken' ]       = $_COOKIE[ 'upload_session' ];
        $projectStructure[ 'array_files' ]       = $arFiles; //list of file name
        $projectStructure[ 'source_language' ]   = $this->source_language;
        $projectStructure[ 'target_language' ]   = explode( ',', $this->target_language );
        $projectStructure[ 'mt_engine' ]         = $this->mt_engine;
        $projectStructure[ 'tms_engine' ]        = $this->tms_engine;
        $projectStructure[ 'status' ]            = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'lang_detect_files' ] = $this->lang_detect_files;

        //if user is logged in, set the uid and the userIsLogged flag
        $this->checkLogin();

        if($this->isLogged) {
            $projectStructure[ 'userIsLogged' ] = true;
            $projectStructure[ 'uid' ]          = $this->uid;
        }

        $projectManager = new ProjectManager( $projectStructure );
        $projectManager->createProject();

        $this->result = $projectStructure[ 'result' ];

        if ( !empty( $this->projectStructure[ 'result' ][ 'errors' ] ) ) {
            setcookie( "upload_session", "", time() - 10000 );
        }

    }

    private static function sanitizeTmKeyArr( $elem ){
        return filter_var( $elem, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_LOW ) );
    }

    public function checkLogin() {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        parent::sessionStart();
        $this->isLogged = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
        $this->uid  = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );
        parent::disableSessions();

        return $this->isLogged;
    }

}

?>

 SELECT uid,
    key_value,
    key_name,
    key_tm AS tm,
    key_glos AS glos
FROM memory_keys WHERE uid = 560

