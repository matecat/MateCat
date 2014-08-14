<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/ProjectManager.php";
include_once INIT::$UTILS_ROOT . "/RecursiveArrayObject.php";

define('DEFAULT_NUM_RESULTS', 2);
set_time_limit(0);

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

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'file_name'          => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'project_name'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'source_language'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'target_language'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'mt_engine'          => array( 'filter' => FILTER_VALIDATE_INT ),
            'disable_tms_engine' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'private_tm_key'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'private_tm_user'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'private_tm_pass'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
	        'lang_detect_files'  => array( 'filter' => FILTER_CALLBACK, 'options' => "Utils::filterLangDetectArray" ),
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

    }

    public function doAction() {

        if (empty($this->file_name)) {
            $this->result['errors'][] = array("code" => -1, "message" => "Missing file name.");
            return false;
        }
        $arFiles = explode('@@SEP@@',  html_entity_decode( $this->file_name, ENT_QUOTES, 'UTF-8' ) );
        $default_project_name = $arFiles[0];
        if (count($arFiles) > 1) {
            $default_project_name = "MATECAT_PROJ-" . date("Ymdhi");
        }


        if (empty($this->project_name)) {
            $this->project_name = $default_project_name;
        }

        if (empty($this->source_language)) {
            $this->result['errors'][] = array("code" => -3, "message" => "Missing source language.");
            return false;
        }

        if (empty($this->target_language)) {
            $this->result['errors'][] = array("code" => -4, "message" => "Missing target language.");
            return false;
        }

        $sourceLangHistory = $_COOKIE["sourceLang"];
        $targetLangHistory = $_COOKIE["targetLang"];

        // SET SOURCE COOKIE

        if ($sourceLangHistory == '_EMPTY_')
            $sourceLangHistory = "";
        $sourceLangAr = explode('||', urldecode($sourceLangHistory));

        if (($key = array_search($this->source_language, $sourceLangAr)) !== false) {
            unset($sourceLangAr[$key]);
        }
        array_unshift($sourceLangAr, $this->source_language);
        if ($sourceLangAr == '_EMPTY_')
            $sourceLangAr = "";
        $newCookieVal = "";
        $sourceLangAr = array_slice($sourceLangAr, 0, 3);
        $sourceLangAr = array_reverse($sourceLangAr);

        foreach ($sourceLangAr as $key => $link) {
            if ($sourceLangAr[$key] == '') {
                unset($sourceLangAr[$key]);
            }
        }

        foreach ($sourceLangAr as $lang) {
            if ($lang != "")
                $newCookieVal = $lang . "||" . $newCookieVal;
        }

        setcookie("sourceLang", $newCookieVal, time() + (86400 * 365));


        // SET TARGET COOKIE

        if ($targetLangHistory == '_EMPTY_')
            $targetLangHistory = "";
        $targetLangAr = explode('||', urldecode($targetLangHistory));

        if (($key = array_search($this->target_language, $targetLangAr)) !== false) {
            unset($targetLangAr[$key]);
        }
        array_unshift($targetLangAr, $this->target_language);
        if ($targetLangAr == '_EMPTY_')
            $targetLangAr = "";
        $newCookieVal = "";
        $targetLangAr = array_slice($targetLangAr, 0, 3);
        $targetLangAr = array_reverse($targetLangAr);

        foreach ($targetLangAr as $key => $link) {
            if ($targetLangAr[$key] == '') {
                unset($targetLangAr[$key]);
            }
        }

        foreach ($targetLangAr as $lang) {
            if ($lang != "")
                $newCookieVal = $lang . "||" . $newCookieVal;
        }

        setcookie("targetLang", $newCookieVal, time() + (86400 * 365));

        $projectStructure = new RecursiveArrayObject(
            array(
                'id_project'         => null,
                'id_customer'        => null,
                'user_ip'            => null,
                'project_name'       => $this->project_name,
                'result'             => $this->result,
                'private_tm_key'     => $this->private_tm_key,
                'private_tm_user'    => $this->private_tm_user,
                'private_tm_pass'    => $this->private_tm_pass,
                'uploadToken'        => $_COOKIE['upload_session'],
                'array_files'        => $arFiles, //list of file names
                'file_id_list'       => array(),
                'file_references'    => array(),
                'source_language'    => $this->source_language,
                'target_language'    => explode( ',', $this->target_language ),
                'mt_engine'          => $this->mt_engine,
                'tms_engine'         => $this->tms_engine,
                'ppassword'          => null, //project password
                'array_jobs'         => array( 'job_list' => array(), 'job_pass' => array(), 'job_segments' => array(  ) ),
                'job_segments'       => array(), //array of job_id => array( min_seg, max_seg )
                'segments'           => array(), //array of files_id => segmentsArray()
                'translations'       => array(), //one translation for every file because translations are files related
                'query_translations' => array(),
                'status'             => Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
                'job_to_split'       => null,
                'job_to_split_pass'  => null,
                'split_result'       => null,
	            'lang_detect_files'  => $this->lang_detect_files
            ) );

        $projectManager = new ProjectManager( $projectStructure );

        $projectManager->createProject();

        $this->result = $projectStructure['result'];

        if( !empty( $this->projectStructure['result']['errors'] ) ){
            setcookie( "upload_session", "", time() - 10000 );
        }

    }

}

?>
