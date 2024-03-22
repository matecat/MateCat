<?php


use ConnectedServices\GDrive\GDriveController;
use Engines_Intento as Intento;
use LexiQA\LexiQADecorator;

class newProjectController extends viewController {

    private $guid = '';
    private $mt_engines;
    private $lang_handler;

    private $sourceLangArray = [];
    private $targetLangArray = [];
    private $subjectArray    = [];

    private $project_name = '';

    private $keyList = [];

    protected $subject_handler;

    public function __construct() {

        parent::__construct();
        parent::makeTemplate( "upload.html" );

        $filterArgs = [
            'project_name' => [ 'filter' => FILTER_SANITIZE_STRING ]
        ];

        $__postInput        = filter_input_array( INPUT_GET, $filterArgs );
        $this->project_name = $__postInput[ "project_name" ];

        $this->lang_handler    = Langs_Languages::getInstance();
        $this->subject_handler = Langs_LanguageDomains::getInstance();

        $this->subjectArray = $this->subject_handler->getEnabledDomains();

    }

    public function doAction() {

        $this->setOrGetGuid();

        try {
            $this->evalSourceLangHistory();
        } catch ( Lang_InvalidLanguageException $e ) {
            Log::doJsonLog( $e->getMessage() );
        }

        try {
            $this->evalTargetLangHistory();
        } catch ( Lang_InvalidLanguageException $e ) {
            Log::doJsonLog( $e->getMessage() );
        }

        $this->initUploadDir();

        $engine            = new EnginesModel_EngineDAO( Database::obtain() );
        $engineQuery       = new EnginesModel_EngineStruct();
        $engineQuery->type = 'MT';

        $engineQuery->uid = ( $this->user->uid == null ? -1 : $this->user->uid );

        $engineQuery->active = 1;
        $this->mt_engines    = $engine->read( $engineQuery );
        $this->mt_engines    = $this->removeMMTFromEngines($this->mt_engines);

        if ( $this->isLoggedIn() ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );

            try {

                $_keyList = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                $dh       = new TmKeyManagement_MemoryKeyStruct( [ 'uid' => $this->user->uid ] );

                $keyList = $_keyList->read( $dh );
                foreach ( $keyList as $memKey ) {
                    //all keys are available in this condition ( we are creating a project
                    $this->keyList[] = $memKey->tm_key;
                }

            } catch ( Exception $e ) {
                Log::doJsonLog( $e->getMessage() );
            }
        }
    }

    /**
     * Here we want to be explicit about the team the user is currently working on.
     * Even if a user is included in more teams, we'd prefer to have the team bound
     * to the given session.
     *
     * @param     $arr
     * @param     $col
     * @param int $dir
     */
    private function array_sort_by_column( &$arr, $col, $dir = SORT_ASC ) {
        $sort_col = [];
        foreach ( $arr as $key => $row ) {
            $sort_col[ $key ] = $row[ $col ];
        }

        array_multisort( $sort_col, $dir, $arr );
    }

    private function getCurrentSourceLang() {
        if ( isset ( $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] ) ) {
            $ckSourceLang = filter_input( INPUT_COOKIE, Constants::COOKIE_SOURCE_LANG );

            if ( $ckSourceLang != Constants::EMPTY_VAL ) {
                $sourceLangHistory = $ckSourceLang;
                $sourceLangAr      = explode( '||', urldecode( $sourceLangHistory ) );

                if ( count( $sourceLangAr ) > 0 ) {
                    return $sourceLangAr[ 0 ];
                }
            }
        }

        return Constants::DEFAULT_TARGET_LANG;
    }

    private function evalSourceLangHistory() {

        if ( !isset( $_COOKIE[ Constants::COOKIE_SOURCE_LANG ] ) ) {
            CookieManager::setCookie( Constants::COOKIE_SOURCE_LANG, Constants::EMPTY_VAL,
                [
                    'expires'  => time() + ( 86400 * 365 ),
                    'path'     => '/',
                    'domain'   => INIT::$COOKIE_DOMAIN,
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'None',
                ]
            );
        }
    }

    private function setOrGetGuid() {

        // If isset the GDRIVE_LIST_COOKIE_NAME cookie, do nothing
        if(!isset($_COOKIE[GDriveController::GDRIVE_LIST_COOKIE_NAME])){

            // Get the guid from the guid if it exists, otherwise set the guid into the cookie
            if ( !empty( $_COOKIE[ 'upload_session' ] ) && Utils::isTokenValid( $_COOKIE[ 'upload_session' ] ) ) {
                Utils::deleteDir( INIT::$UPLOAD_REPOSITORY . '/' . $_COOKIE[ 'upload_session' ] . '/' );
            }

            $this->guid = Utils::uuid4();
            CookieManager::setCookie( "upload_session", $this->guid,
                [
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'domain'   => INIT::$COOKIE_DOMAIN,
                    'secure'   => true,
                    'httponly' => false,
                    'samesite' => 'None',
                ]
            );
        }
    }

    private function isUploadTMXAllowed( $default = false ) {
        if ( $default ) {
            return false;
        }
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $k => $v ) {
            foreach ( $v as $kk => $vv ) {
                if ( $kk == 'tmx' ) {
                    return true;
                }
            }
        }

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
        $ext_ret = [];
        foreach ( INIT::$UNSUPPORTED_FILE_TYPES as $kk => $vv ) {
            if ( !isset( $vv[ 1 ] ) or empty( $vv[ 1 ] ) ) {
                continue;
            }
            $ext_ret[] = [ "format" => "$kk", "message" => "$vv[1]" ];
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

    public function setTemplateVars() {
        $source_languages = $this->lang_handler->getEnabledLanguages( 'en' );
        $target_languages = $this->lang_handler->getEnabledLanguages( 'en' );

        $this->template->subject_array       = $this->subjectArray;

        $this->template->project_name = $this->project_name;

        $this->template->page             = 'home';
        $this->template->source_languages = $source_languages;
        $this->template->target_languages = $target_languages;
        $this->template->subjects         = json_encode($this->subjectArray);

        $this->template->mt_engines         = $this->mt_engines;
        $this->template->conversion_enabled = !empty( INIT::$FILTERS_ADDRESS );

        $this->template->isUploadTMXAllowed = false;
        if ( !empty( INIT::$FILTERS_ADDRESS ) ) {
            $this->template->isUploadTMXAllowed = $this->isUploadTMXAllowed();
        }

        $this->template->unsupported_file_types                = $this->getExtensionsUnsupported();
        $this->template->formats_number                        = $this->countExtensions();
        $this->template->volume_analysis_enabled               = INIT::$VOLUME_ANALYSIS_ENABLED;
        $this->template->extended_user                         = ( $this->isLoggedIn() !== false ) ? trim( $this->user->fullName() ) : "";
        $this->template->logged_user                           = ( $this->isLoggedIn() !== false ) ? $this->user->shortName() : "";
        $this->template->userMail                              = $this->user->email;
        $this->template->translation_engines_intento_providers = Intento::getProviderList();
        $this->template->translation_engines_intento_prov_json = str_replace("\\\"","\\\\\\\"", json_encode(Intento::getProviderList())); // needed by JSON.parse() function

        $this->template->build_number   = INIT::$BUILD_NUMBER;
        $this->template->maxFileSize    = INIT::$MAX_UPLOAD_FILE_SIZE;
        $this->template->maxTMXFileSize = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;
        $this->template->maxNumberFiles = INIT::$MAX_NUM_FILES;

        //this can be overridden by plugins to enable/disable the default flag on MyMemory lookup
        $this->template->get_public_matches = true;

        $this->template->user_keys     = $this->keyList;
        $this->template->user_keys_obj = json_encode( array_map( function ( $tmKeyStruct ) {
            return [ 'name' => $tmKeyStruct->name, 'key' => $tmKeyStruct->key ];
        }, $this->keyList ) );

        $this->template->developerKey = INIT::$OAUTH_BROWSER_API_KEY;
        $this->template->clientId     = INIT::$OAUTH_CLIENT_ID;

        $this->template->tag_projection_languages = json_encode( ProjectOptionsSanitizer::$tag_projection_allowed_languages );
        LexiQADecorator::getInstance( $this->template )->featureEnabled( $this->featureSet )->decorateViewLexiQA();

        $this->template->additional_input_params_base_path = INIT::$TEMPLATE_ROOT;

        //Enable tag projection at instance level
        $this->template->show_tag_projection    = true;
        $this->template->tag_projection_enabled = true;
        $this->template->tag_projection_default = true;

        $this->featureSet->appendDecorators( 'NewProjectDecorator', $this, $this->template );

        $this->template->globalMessage = Utils::getGlobalMessage()[ 'messages' ];
        if ( $this->isLoggedIn() ) {
            $this->template->teams = ( new \Teams\MembershipDao() )->findUserTeams( $this->user );
        }

    }

    private function getCurrentTargetLang() {
        if ( isset ( $_COOKIE[ Constants::COOKIE_TARGET_LANG ] ) ) {
            $ckTargetLang = filter_input( INPUT_COOKIE, Constants::COOKIE_TARGET_LANG );

            if ( $ckTargetLang != Constants::EMPTY_VAL ) {
                $targetLangHistory = $ckTargetLang;
                $targetLangAr      = explode( '||', urldecode( $targetLangHistory ) );

                if ( count( $targetLangAr ) > 0 ) {
                    return $targetLangAr[ 0 ];
                }
            }
        }

        return Constants::DEFAULT_SOURCE_LANG;
    }

    private function evalTargetLangHistory() {

        if ( !isset( $_COOKIE[ Constants::COOKIE_TARGET_LANG ] ) ) {
            CookieManager::setCookie( Constants::COOKIE_TARGET_LANG, Constants::EMPTY_VAL,
                [
                    'expires'  => time() + ( 86400 * 365 ),
                    'path'     => '/',
                    'domain'   => INIT::$COOKIE_DOMAIN,
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'None',
                ]
            );
        }
    }

    private function initUploadDir() {
        $intDir = INIT::$UPLOAD_REPOSITORY . '/' . $this->guid . '/';
        if ( !is_dir( $intDir ) ) {
            mkdir( $intDir, 0775, true );
        }
    }

}
