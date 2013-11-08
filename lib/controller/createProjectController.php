<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$ROOT . "/lib/utils/segmentExtractor.php";

define('DEFAULT_NUM_RESULTS', 2);

class createProjectController extends ajaxcontroller {

    private $file_name;
    private $project_name;
    private $source_language;
    private $target_language;
    private $mt_engine;
    private $tms_engine = 1;  //1 default MyMemory
    private $private_tm_key;
    private $private_tm_user;
    private $private_tm_pass;
    private $analysis_status;

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
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->file_name               = $__postInput[ 'file_name' ]; // da cambiare
        $this->project_name            = $__postInput[ 'project_name' ];
        $this->source_language         = $__postInput[ 'source_language' ];
        $this->target_language         = $__postInput[ 'target_language' ];
        $this->mt_engine               = $__postInput[ 'mt_engine' ]; // null è ammesso
        $this->disable_tms_engine_flag = $__postInput[ 'disable_tms_engine' ]; // se false allora MyMemory
        $this->private_tm_key          = $__postInput[ 'private_tm_key' ];
        $this->private_tm_user         = $__postInput[ 'private_tm_user' ];
        $this->private_tm_pass         = $__postInput[ 'private_tm_pass' ];

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
            $this->project_name = $default_project_name; //'NO_NAME'.$this->create_project_name();
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

        // aggiungi path file in caricamento al cookie"pending_upload"a
        // add her the cookie mangement for remembere the last 3 choosed languages
        // project name sanitize
        $this->project_name = preg_replace('/[^\p{L}0-9a-zA-Z_\.\-]/u', "_", $this->project_name);
        $this->project_name = preg_replace('/[_]{2,}/', "_", $this->project_name);
        $this->project_name = str_replace('_.', ".", $this->project_name);
        // project name validation        
        $pattern = "/^[\p{L}\ 0-9a-zA-Z_\.\-]+$/u";
        if (!preg_match($pattern, $this->project_name, $rr)) {
            $this->result['errors'][] = array("code" => -5, "message" => "Invalid Project Name $this->project_name: it should only contain numbers and letters!");
            return false;
        }

        // create project
        $ppassword = CatUtils::generate_password();

        $ip = Utils::getRealIpAddr();
        $id_customer = 'translated_user';

        $pid = insertProject($id_customer, $this->project_name, 'NOT_READY_FOR_ANALYSIS', $ppassword, $ip);
        //create user (Massidda 2013-01-24)
        //this is done only if an API key is provided
        if (!empty($this->private_tm_key)) {
            //the base case is when the user clicks on "generate private TM" button: 
            //a (user, pass, key) tuple is generated and can be inserted
            //if it comes with it's own key without querying the creation api, create a (key,key,key) user 
            if (empty($this->private_tm_user)) {
                $this->private_tm_user = $this->private_tm_key;
                $this->private_tm_pass = $this->private_tm_key;
            }
            $user_id = insertTranslator($this->private_tm_user, $this->private_tm_pass, $this->private_tm_key);
            $this->private_tm_user = $user_id;
        }


        $intDir = INIT::$UPLOAD_REPOSITORY . "/" . $_COOKIE['upload_session'];
        $fidList = array();
        foreach ($arFiles as $file) {


            $fileSplit = explode('.', $file);
            $mimeType = strtolower($fileSplit[count($fileSplit) - 1]);


            /**
             * Conversion Enforce
             *
             * Check Extension no more sufficient, we want check content
             * if this is an idiom xlf file type, conversion are enforced
             * $enforcedConversion = true; //( if conversion is enabled )
             */
            $enforcedConversion = false;
            try {

                $fileType = DetectProprietaryXliff::getInfo( INIT::$UPLOAD_REPOSITORY. '/' .$_COOKIE['upload_session'].'/' . $file );
                //Log::doLog( 'Proprietary detection: ' . var_export( $fileType, true ) );

                if( $fileType['proprietary'] == true  ){

                    if( INIT::$CONVERSION_ENABLED && $fileType['proprietary_name'] == 'idiom world server' ){
                        $enforcedConversion = true;
                        Log::doLog( 'Idiom found, conversion Enforced: ' . var_export( $enforcedConversion, true ) );

                    } else {
                        /**
                         * Application misconfiguration.
                         * upload should not be happened, but if we are here, raise an error.
                         * @see upload.class.php
                         * */
                        $this->result['errors'][] = array("code" => -8, "message" => "Proprietary xlf format detected. Not able to import this XLIFF file. ($file)");
                        setcookie("upload_session", "", time() - 10000);
                        return;
                        //stop execution
                    }
                }
            } catch ( Exception $e ) { Log::doLog( $e->getMessage() ); }

            $original_content = "";
            if ( ( ( $mimeType != 'sdlxliff' && $mimeType != 'xliff' && $mimeType != 'xlf' ) || $enforcedConversion ) && INIT::$CONVERSION_ENABLED ) {
                $fileDir = $intDir . '_converted';
                $filename_to_catch = $file . '.sdlxliff';

                $original_content = file_get_contents("$intDir/$file");
                $sha1_original = sha1($original_content);
            } else {
                $sha1_original = "";
                $fileDir = $intDir;
                $filename_to_catch = $file;
            }

            if (!empty($original_content)) {
                $original_content = gzdeflate($original_content, 5);
            }

            $filename = $fileDir . '/' . $filename_to_catch;

            if (!file_exists($filename)) {
                $this->result['errors'][] = array("code" => -6, "message" => "File not found on server after upload.");
            }
            $contents = file_get_contents($filename);
            $fid = insertFile($pid, $file, $this->source_language, $mimeType, $contents, $sha1_original, $original_content);
            $fidList[] = $fid;

            try{
                //return by reference, could be large
                //deprecated: PHP Strict standards:  Only variables should be assigned by reference
                //TODO: segment Extractor should be a class
                $SegmentTranslations[$fid] = & extractSegments($fileDir, $filename_to_catch, $pid, $fid);
            } catch ( Exception $e ){

                if ( $e->getCode() == -1 ) {
                    $this->result['errors'][] = array("code" => -7, "message" => "No segments found in your XLIFF file. ($file)");
                } else if( $e->getCode() == -2 ) {
                    $this->result['errors'][] = array("code" => -7, "message" => "Not able to import this XLIFF file. ($file)");
                }

                return false;

            }
            //exit;
        }

        //Log::doLog( array_pop( array_chunk( $SegmentTranslations[$fid], 25, true ) ) );
        //create job

        $this->target_language = explode(',', $this->target_language);

        $_jobList = array();
        $_passwordList = array();
        foreach ($this->target_language as $target) {
            $password = CatUtils::generate_password();
            if (isset($_SESSION['cid']) and !empty($_SESSION['cid'])) {
                $owner = $_SESSION['cid'];
            } else {

                $_SESSION['_anonym_pid'] = $pid;

                //default user
                $owner = '';
            }
            $jid = insertJob($password, $pid, $this->private_tm_user, $this->source_language, $target, $this->mt_engine, $this->tms_engine, $owner);
            foreach ($fidList as $fid) {

                try {
                    //prepare pre-translated segments queries
                    if( !empty( $SegmentTranslations ) ){
                        insertPreTranslations( $SegmentTranslations[$fid], $jid );
                    }
                } catch ( Exception $e ) {
                    $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export( $e->getMessage(), true );
                    Utils::sendErrMailReport($msg);
                }

                insertFilesJob($jid, $fid);
            }

            $_jobList[] = $jid;
            $_passwordList[] = $password;

        }

        $this->deleteDir( $intDir );
        if ( is_dir( $intDir . '_converted' ) ) {
            $this->deleteDir( $intDir . '_converted' );
        }

        $analysis_status = ( INIT::$VOLUME_ANALYSIS_ENABLED ) ? 'NEW' : 'NOT_TO_ANALYZE';
        changeProjectStatus( $pid, $analysis_status );
        $this->result[ 'code' ]            = 1;
        $this->result[ 'data' ]            = "OK";
        $this->result[ 'password' ]        = $_passwordList;
        $this->result[ 'ppassword' ]       = $ppassword;
        $this->result[ 'id_job' ]          = $_jobList;
        $this->result[ 'id_project' ]      = $pid;
        $this->result[ 'project_name' ]    = $this->project_name;
        $this->result[ 'source_language' ] = $this->source_language;
        $this->result[ 'target_language' ] = $this->target_language;

        setcookie( "upload_session", "", time() - 10000 );

    }

    public static function deleteDir($dirPath) {
        return true;
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException('$dirPath must be a directory.');
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

}

?>
