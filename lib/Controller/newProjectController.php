<?php

class newProjectController extends viewController {

    private $guid = '';
    private $mt_engines;
    private $tms_engines;
    private $lang_handler;

    private $sourceLangArray = array();
    private $targetLangArray = array();
    private $subjectArray = array();

    private $project_name='';
    private $private_tm_key='';

    /**
     * @var string The actual URL
     */
    private $incomingUrl;

    private $keyList = array();

    public function __construct() {

        parent::__construct( false );
        parent::makeTemplate( "upload.html" );

        $filterArgs = array(
                'project_name'      => array( 'filter' => FILTER_SANITIZE_STRING ),
                'private_tm_key' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_GET, $filterArgs );
        $this->project_name      = $__postInput[ "project_name" ];
        $this->private_tm_key = $__postInput[ "private_tm_key" ];

        $this->guid            = Utils::create_guid();
        $this->lang_handler    = Langs_Languages::getInstance();
        $this->subject_handler = Langs_LanguageDomains::getInstance();

        $this->subjectArray = $this->subject_handler->getEnabledDomains();
    }

    public function doAction() {
        //Get the guid from the guid if it exists, otherwise set the guid into the cookie
        if ( !isset( $_COOKIE[ 'upload_session' ] ) ) {
            setcookie( "upload_session", $this->guid, time() + 86400 );
        } else {
            $this->guid = $_COOKIE[ 'upload_session' ];
        }

        if ( isset ( $_COOKIE[ "sourceLang" ] ) and $_COOKIE[ "sourceLang" ] == "_EMPTY_" ) {
            $this->noSourceLangHistory = true;
        } else {

            if ( !isset( $_COOKIE[ 'sourceLang' ] ) ) {
                setcookie( "sourceLang", "_EMPTY_", time() + ( 86400 * 365 ) );
                $this->noSourceLangHistory = true;
            } else {

                if ( $_COOKIE[ "sourceLang" ] != "_EMPTY_" ) {
                    $this->noSourceLangHistory = false;
                    $this->sourceLangHistory   = $_COOKIE[ "sourceLang" ];
                    $this->sourceLangAr        = explode( '||', urldecode( $this->sourceLangHistory ) );
                    $tmpSourceAr               = array();
                    $tmpSourceArAs             = array();
                    foreach ( $this->sourceLangAr as $key => $lang ) {
                        if ( $lang != '' ) {
                            $tmpSourceAr[ $lang ] = $this->lang_handler->getLocalizedName( $lang );

                            $ar               = array();
                            $ar[ 'name' ]     = $this->lang_handler->getLocalizedName( $lang );
                            $ar[ 'code' ]     = $lang;
                            $ar[ 'selected' ] = ( $key == '0' ) ? 1 : 0;
                            $ar[ 'direction' ]    = ( $this->lang_handler->isRTL( strtolower( ( $lang ) ) ) ? 'rtl' : 'ltr' );
                            array_push( $tmpSourceArAs, $ar );
                        }
                    }
                    $this->sourceLangAr = $tmpSourceAr;
                    asort( $this->sourceLangAr );

                    $this->array_sort_by_column( $tmpSourceArAs, 'name' );
                    $this->sourceLangArray = $tmpSourceArAs;

                }
            }
        }

        if ( isset( $_COOKIE[ "targetLang" ] ) and $_COOKIE[ "targetLang" ] == "_EMPTY_" ) {
            $this->noTargetLangHistory = true;
        } else {
            if ( !isset( $_COOKIE[ 'targetLang' ] ) ) {
                setcookie( "targetLang", "_EMPTY_", time() + ( 86400 * 365 ) );
                $this->noTargetLangHistory = true;
            } else {
                if ( $_COOKIE[ "targetLang" ] != "_EMPTY_" ) {
                    $this->noTargetLangHistory = false;
                    $this->targetLangHistory   = $_COOKIE[ "targetLang" ];
                    $this->targetLangAr        = explode( '||', urldecode( $this->targetLangHistory ) );

                    $tmpTargetAr   = array();
                    $tmpTargetArAs = array();

                    foreach ( $this->targetLangAr as $key => $lang ) {
                        if ( $lang != '' ) {
                            $prova = explode( ',', urldecode( $lang ) );

                            $cl = "";
                            foreach ( $prova as $ll ) {
                                $cl .= $this->lang_handler->getLocalizedName( $ll ) . ',';
                            }
                            $cl = substr_replace( $cl, "", -1 );


                            $tmpTargetAr[ $lang ] = $cl;
                            //					$tmpTargetAr[$lang] = $this->lang_handler->getLocalizedName($lang,'en');

                            $ar               = array();
                            $ar[ 'name' ]     = $cl;
                            $ar[ 'direction' ]    = ( $this->lang_handler->isRTL( strtolower( ( $lang ) ) ) ? 'rtl' : 'ltr' );
                            $ar[ 'code' ]     = $lang;
                            $ar[ 'selected' ] = ( $key == '0' ) ? 1 : 0;
                            array_push( $tmpTargetArAs, $ar );
                        }
                    }
                    $this->targetLangAr = $tmpTargetAr;
                    asort( $this->targetLangAr );

                    $this->array_sort_by_column( $tmpTargetArAs, 'name' );
                    $this->targetLangArray = $tmpTargetArAs;

                }
            }
        }

        $intDir = INIT::$UPLOAD_REPOSITORY . '/' . $this->guid . '/';
        if ( !is_dir( $intDir ) ) {
            mkdir( $intDir, 0775, true );
        }

        // check if user is logged and generate authURL for logging in
        $this->doAuth();

        $this->generateAuthURL();

        list( $uid, $cid ) = $this->getLoginUserParams();
        $engine = new EnginesModel_EngineDAO( Database::obtain() );
        $engineQuery         = new EnginesModel_EngineStruct();
        $engineQuery->type   = 'MT';

        if ( @(bool)$_GET[ 'amt' ] == true ) {
            $engineQuery->uid    = 'all';
        } else {
            $engineQuery->uid    = ( $uid == null ? -1 : $uid );
        }

        $engineQuery->active = 1;
        $this->mt_engines = $engine->read( $engineQuery );

        if ( $this->isLoggedIn() ) {

            try {

                $_keyList = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                $dh       = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => @$_SESSION[ 'uid' ] ) );

                $keyList = $_keyList->read( $dh );
                foreach ( $keyList as $memKey ) {
                    //all keys are available in this condition ( we are creating a project
                    $this->keyList[ ] = $memKey->tm_key;
                }

            } catch ( Exception $e ) {
                Log::doLog( $e->getMessage() );
            }

        }

    }

    public function array_sort_by_column( &$arr, $col, $dir = SORT_ASC ) {
        $sort_col = array();
        foreach ( $arr as $key => $row ) {
            $sort_col[ $key ] = $row[ $col ];
        }

        array_multisort( $sort_col, $dir, $arr );
    }


    private function isUploadTMXAllowed( $default = false ) {
        if ( $default ) {
            return false;
        }
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $k => $v ) {
            foreach ( $v as $kk => $vv ) {
                if ( $kk == 'tmx' ) {
                    //	echo "true";
                    //	exit;
                    return true;
                }
            }
        }

        //echo "false";exit;
        return false;

    }

    private function getExtensions( $default = false ) {
        $ext_ret = "";
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $k => $v ) {
            foreach ( $v as $kk => $vv ) {
                if ( $default ) {
                    if ( $vv[ 0 ] != 'default' ) {
                        continue;
                    }
                }
                $ext_ret .= "$kk|";
            }
        }
        $ext_ret = rtrim( $ext_ret, "|" );

        return $ext_ret;
    }

    private function getExtensionsUnsupported() {
        $ext_ret = array();
        foreach ( INIT::$UNSUPPORTED_FILE_TYPES as $kk => $vv ) {
            if ( !isset( $vv[ 1 ] ) or empty( $vv[ 1 ] ) ) {
                continue;
            }
            $ext_ret[ ] = array( "format" => "$kk", "message" => "$vv[1]" );
        }
        $json = json_encode( $ext_ret );

        return $json;
    }

    private function countExtensions() {
        $count = 0;
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
            $count += count( $value );
        }

        return $count;
    }

    private function getCategories( $output = "array" ) {
        $ret = array();
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
            $val         = array();
            foreach ($value as $ext => $info) {
                $val[] = array(
                        'ext'   => $ext,
                        'class' => $info[2]
                );
            }
            $val = array_chunk($val, 12 );

            $ret[ $key ] = $val;
        }
        if ( $output == "json" ) {
            return json_encode( $ret );
        }

        return $ret;
    }

    private function doAuth() {

        //if no login set and login is required
        if ( !$this->isLoggedIn() ) {
            //take note of url we wanted to go after
            $this->incomingUrl = $_SESSION[ 'incomingUrl' ] = $_SERVER[ 'REQUEST_URI' ];
        }

    }

    public function setTemplateVars() {
        $source_languages = $this->lang_handler->getEnabledLanguages( 'en' );

        $target_languages = $this->lang_handler->getEnabledLanguages( 'en' );
//        foreach ( $target_languages as $k => $v ) {
//            if (in_array($v['code'],array('ko-KR', 'zh-CN','zh-TW','ja-JP'))){
//            if ( in_array( $v[ 'code' ], array( 'ja-JP' ) ) ) {
//                unset ( $target_languages[ $k ] );
//            }
//        }

        $this->template->project_name=$this->project_name;
        $this->template->private_tm_key=$this->private_tm_key;

        $this->template->page = 'home';
        $this->template->source_languages = $source_languages;
        $this->template->target_languages = $target_languages;
        $this->template->subjects = $this->subjectArray;

        $this->template->upload_session_id = $this->guid;

        $this->template->mt_engines         = $this->mt_engines;
//        $this->template->tms_engines        = $this->tms_engines;
        $this->template->conversion_enabled = !empty(INIT::$FILTERS_ADDRESS);

        $this->template->isUploadTMXAllowed = false;
        if ( !empty(INIT::$FILTERS_ADDRESS) ) {
            $this->template->allowed_file_types = $this->getExtensions( "" );
            $this->template->isUploadTMXAllowed = $this->isUploadTMXAllowed();
        } else {
            $this->template->allowed_file_types = $this->getExtensions( "default" );
        }

        $this->template->supported_file_types_array = $this->getCategories();
        $this->template->unsupported_file_types     = $this->getExtensionsUnsupported();
        $this->template->formats_number             = $this->countExtensions();
        $this->template->volume_analysis_enabled    = INIT::$VOLUME_ANALYSIS_ENABLED;
        $this->template->sourceLangHistory          = $this->sourceLangArray;
        $this->template->targetLangHistory          = $this->targetLangArray;
        $this->template->noSourceLangHistory        = $this->noSourceLangHistory;
        $this->template->noTargetLangHistory        = $this->noTargetLangHistory;
        $this->template->extended_user              = ($this->logged_user !== false ) ? trim( $this->logged_user->fullName() ) : "";
        $this->template->logged_user                = ($this->logged_user !== false ) ? $this->logged_user->shortName() : "";
        $this->template->build_number               = INIT::$BUILD_NUMBER;
        $this->template->maxFileSize                = INIT::$MAX_UPLOAD_FILE_SIZE;
        $this->template->maxTMXFileSize             = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;
        $this->template->maxNumberFiles             = INIT::$MAX_NUM_FILES;
        $this->template->incomingUrl                = '/login?incomingUrl=' . $_SERVER[ 'REQUEST_URI' ];

        $this->template->incomingURL = $this->incomingUrl;
        $this->template->authURL     = $this->authURL;

        $this->template->user_keys = $this->keyList;

        $this->template->isAnonymousUser = var_export( !$this->isLoggedIn(), true );
        $this->template->DQF_enabled = INIT::$DQF_ENABLED;

    }

}

