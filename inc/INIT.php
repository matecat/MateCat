<?php


class INIT {

    public static $MANDATORY_KEYS      = array(
            'ENV',
            'CHECK_FS',
            'DB_SERVER',
            'DB_DATABASE',
            'DB_USER',
            'DB_PASS'
    );

    /**
     * @var $ENV
     *
     * General server environment settings to define the the usage of hard links rather than copy php method
     * must be one of these:
     *
     * - production
     * - development
     * - test
     *
     * @see EnvWrap
     *
     */
    public static $ENV ;

    public static $ROOT;
    public static $BASEURL;
    public static $HTTPHOST;
    public static $PROTOCOL;
    public static $DEBUG               = true;
    public static $EXCEPTION_DEBUG     = false;
    public static $DB_SERVER;
    public static $DB_DATABASE;
    public static $DB_USER;
    public static $DB_PASS;
    public static $REDIS_SERVERS       = array();
    public static $QUEUE_BROKER_ADDRESS;
    public static $QUEUE_DQF_ADDRESS;
    public static $QUEUE_JMX_ADDRESS;
    public static $USE_COMPILED_ASSETS = false;

    /**
     * Use or not the js tracking codes macro import ( Ex: google analytics code injection )
     *
     * PHPTAL macro located in lib/View/external_sources.html
     *
     * @var string Customized path for the tracking codes ( empty default: lib/View )
     */
    public static $TRACKING_CODES_VIEW_PATH = "";

    public static $QUEUE_NAME = "matecat_analysis_queue";
    //This queue will be used for dqf project creation
    public static $DQF_PROJECTS_TASKS_QUEUE_NAME = "matecat_dqf_project_task_queue";
    //This queue will be used for dqf project creation
    public static $DQF_SEGMENTS_QUEUE_NAME = "matecat_dqf_segment_queue";

    public static $COMMENTS_ENABLED = true ;
    public static $SSE_COMMENTS_QUEUE_NAME = "matecat_sse_comments";
    public static $SSE_BASE_URL;

    public static $SMTP_HOST;
    public static $SMTP_PORT;
    public static $SMTP_SENDER;
    public static $SMTP_HOSTNAME;

    public static $MAILER_FROM = 'cattool@matecat.com' ;
    public static $MAILER_FROM_NAME = 'MateCat';
    public static $MAILER_RETURN_PATH = 'no-reply@matecat.com';

    public static $LOG_REPOSITORY;
    public static $STORAGE_DIR;
    public static $UPLOAD_REPOSITORY;
    public static $FILES_REPOSITORY;
    public static $CACHE_REPOSITORY;
    public static $ZIP_REPOSITORY;
    public static $CONVERSIONERRORS_REPOSITORY;
    public static $CONVERSIONERRORS_REPOSITORY_WEB;
    public static $TMP_DOWNLOAD;
    public static $TEMPLATE_ROOT;
    public static $MODEL_ROOT;
    public static $CONTROLLER_ROOT;
    public static $UTILS_ROOT;
    public static $DEFAULT_NUM_RESULTS_FROM_TM;
    public static $THRESHOLD_MATCH_TM_NOT_TO_SHOW;
    public static $TIME_TO_EDIT_ENABLED;
    public static $DEFAULT_FILE_TYPES;
    public static $CONVERSION_FILE_TYPES;
    public static $CONVERSION_FILE_TYPES_PARTIALLY_SUPPORTED;
    public static $AUTHSECRET;
    public static $AUTHSECRET_PATH;
    public static $REFERENCE_REPOSITORY;
    public static $DQF_ENABLED = false;

    public static $FORCE_XLIFF_CONVERSION    = false;
    public static $FILTERS_OCR_CHECK         = true;
    public static $VOLUME_ANALYSIS_ENABLED   = true;
    public static $WARNING_POLLING_INTERVAL  = 20; //seconds
    public static $SEGMENT_QA_CHECK_INTERVAL = 1; //seconds
    public static $SAVE_SHASUM_FOR_FILES_LOADED = true;
    public static $AUTHCOOKIENAME = 'matecat_login_v4';
    public static $SUPPORT_MAIL = 'the owner of this MateCat instance';//default string is 'the owner of this Matecat instance'
    public static $ANALYSIS_WORDS_PER_DAYS = 3000;
    public static $AUTHCOOKIEDURATION = 5184000;            // 86400 * 60;         // seconds
    public static $MAX_UPLOAD_FILE_SIZE = 62914560;         // 60 * 1024 * 1024;  // bytes
    public static $MAX_UPLOAD_TMX_FILE_SIZE = 314572800;    // 300 * 1024 * 1024; // bytes
    public static $MAX_NUM_FILES = 100;

    /**
     * Time zone string that should match the one set in the database.
     * @var string
     */
    public static $TIME_ZONE = 'Europe/Rome';

    /**
     * Use this settings to indicate the upperbuond memory limit you want to
     * apply to fast analysis. You may want to set this to allow analysis of
     * big files.
     * @var string memory limit. Example "2048M"
     */
    public static $FAST_ANALYSIS_MEMORY_LIMIT;

    public static $CONFIG_VERSION_ERR_MESSAGE = "Your config.ini file is not up-to-date.";

    /**
     * This interval is needed for massive copy-source-to-target feature. <br>
     * If user triggers that feature 3 times within this interval (in seconds),
     * a popup appears asking him if he wants to trigger the massive function.
     * @var int Interval in seconds
     */
    public static $COPY_SOURCE_INTERVAL = 300;
    public static $MAX_NUM_SEGMENTS = 500;

    /**
     * Default Matecat user agent string
     */
    const MATECAT_USER_AGENT = 'Matecat-Cattool/v';

    /**
     * @const JOB_ARCHIVABILITY_THRESHOLD int number of days of inactivity for a job before it's automatically archived
     */
    const JOB_ARCHIVABILITY_THRESHOLD = 90;

    /**
     * ENABLE_OUTSOURCE set as true will show the option to outsource to an external
     * translation provider (translated.net by default).
     * You can set it to false, but We are happy if you keep this on.
     * For each translation outsourced to Translated.net (the main Matecat developer),
     * Matecat gets more development budget and bugs fixes and new features get implemented faster.
     * In short: please turn it off only if strictly necessary :)
     * @var bool
     */
    public static $ENABLE_OUTSOURCE = true;

    /**
     * MateCat Filters configuration
     */
    public static $FILTERS_USER_AGENT = "MateCat Community Instance";
    public static $FILTERS_ADDRESS = "https://translated-matecat-filters-v1.p.mashape.com";
    public static $FILTERS_MASHAPE_KEY = "Register to https://market.mashape.com/translated/matecat-filters to obtain your Mashape Key";
    public static $FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
    public static $FILTERS_EMAIL_FAILURES = false;

    /**
     * The MateCat Version
     */
    //TODO: Rename variable to MATECAT_VERSION
    public static $BUILD_NUMBER;

    /**
     * MyMemory Developer Email Key for the cattool
     * @var string
     */
    public static $MYMEMORY_API_KEY = 'demo@matecat.com' ;

    /**
     * MyMemory Developer Email Key for the analysis
     * @var string
     */
    public static $MYMEMORY_TM_API_KEY = 'tmanalysis@matecat.com' ;


    public static $ENABLED_BROWSERS = array( 'applewebkit', 'chrome', 'safari' ); //, 'firefox');

    // sometimes the browser declare to be Mozilla but does not provide a valid Name (e.g. Safari).
    // This occurs especially in mobile environment. As an example, when you try to open a link from within
    // the GMail app, it redirect to an internal browser that does not declare a valid user agent
    // In this case we will show a notice on the top of the page instead of deny the access
    public static $UNTESTED_BROWSERS = array( 'mozillageneric' );

    /**
     * If you don't have a client id and client secret, please visit
     * Google Developers Console (https://console.developers.google.com/)
     * and follow these instructions:
     * - click "Create Project" button and specify project name
     * - In the sidebar on the left, select APIs & auth.
     * - In the displayed list of APIs, make sure "Google+ API" show a status of ON. If it doesn't, enable it.
     * - In the sidebar on the left, select "Credentials" under "APIs & auth" menu.
     * - Click "Create new client ID" button
     * - under APPLICATION TYPE, select "web application" option
     * - under AUTHORIZED JAVASCRIPT ORIGINS, insert the domain on which you installed MateCat
     * - under REDIRECT URIs, insert "http://<domain>/oauth/response" , where <domain> is the same that you specified in the previous step
     * - click "Create client ID"
     * - Still in Credentials page, click "Create credentials" and select "API key"
     * - Click "Browser key"
     * - under Name, insert the name of your Browser API key
     * - under "Accept requests from these HTTP referrers (web sites)", insert "<domain>/*",
     *   where <domain> is the same that you specified in the previous steps.
     * - In the sidebar select Overview and search for Google Picker
     * - Click on the "Google Picker API" link, and then click on Enable button
     *
     * Your client ID, client secret and Browser API key are now available.
     *
     * Edit the file inc/oauth_config.ini.sample with the right parameters obtained in the previous step of this guide.
     * set:
     * OAUTH_CLIENT_ID with your client ID
     * OAUTH_CLIENT_SECRET with your client secret
     * OAUTH_CLIENT_APP_NAME with your custom app name, if you want, or leave Matecat
     * OAUTH_BROWSER_API_KEY with your browser API key, required to open Google Picker
     *
     * save and rename to oauth_config.ini file.
     *
     * Done!
     */
    public static $OAUTH_CONFIG;
    public static $OAUTH_CLIENT_ID;
    public static $OAUTH_CLIENT_SECRET;
    public static $OAUTH_CLIENT_APP_NAME;
    public static $OAUTH_REDIRECT_URL;
    public static $OAUTH_SCOPES;
    public static $OAUTH_BROWSER_API_KEY;
    public static $OAUTH_GDRIVE_SCOPES;

    public static $ENABLE_OMEGAT_DOWNLOAD = false;
    public static $UNLOCKABLE_TAGS = false;

    public function __construct(){

        self::$OAUTH_CLIENT_ID       = INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_ID' ];
        self::$OAUTH_CLIENT_SECRET   = INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_SECRET' ];
        self::$OAUTH_CLIENT_APP_NAME = INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_APP_NAME' ];
        self::$OAUTH_BROWSER_API_KEY = INIT::$OAUTH_CONFIG[ 'OAUTH_BROWSER_API_KEY' ];

        self::$OAUTH_REDIRECT_URL = INIT::$HTTPHOST . "/oauth/response";
        self::$OAUTH_SCOPES       = array(
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'profile'
        );
        self::$OAUTH_GDRIVE_SCOPES = array(
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'https://www.googleapis.com/auth/drive',
                'https://www.googleapis.com/auth/drive.install',
                'profile'
        );

        self::$MIME_TYPES = include( 'Mime2Extension.php' );

    }



    public static $SPELL_CHECK_TRANSPORT_TYPE = 'shell';
    public static $SPELL_CHECK_ENABLED        = false;
    public static $SUPPORTED_FILE_TYPES = array(
            'Office'              => array(
                    'doc'  => array( '', '', 'extdoc' ),
                    'dot'  => array( '', '', 'extdoc' ),
                    'docx' => array( '', '', 'extdoc' ),
                    'docm' => array( '', '', 'extdoc' ),
                    'dotx' => array( '', '', 'extdoc' ),
                    'dotm' => array( '', '', 'extdoc' ),
                    'rtf'  => array( '', '', 'extdoc' ),
                    'odt'  => array( '', '', 'extdoc' ),
                    'ott'  => array( '', '', 'extdoc' ),
                    'pdf'  => array( '', '', 'extpdf' ),
                    'txt'  => array( '', '', 'exttxt' ),
                    'xls'  => array( '', '', 'extxls' ),
                    'xlt'  => array( '', '', 'extxls' ),
                    'xlsx' => array( '', '', 'extxls' ),
                    'xlsm' => array( '', '', 'extxls' ),
                    'xltx' => array( '', '', 'extxls' ),
                    'xltm' => array( '', '', 'extxls' ),
                    'ods'  => array( '', '', 'extxls' ),
                    'ots'  => array( '', '', 'extxls' ),
                    //'csv'  => array( '', '', 'extxls' ),
                    'tsv'  => array( '', '', 'extxls' ),
                    'ppt'  => array( '', '', 'extppt' ),
                    'pps'  => array( '', '', 'extppt' ),
                    'pot'  => array( '', '', 'extppt' ),
                    'pptx' => array( '', '', 'extppt' ),
                    'pptm' => array( '', '', 'extppt' ),
                    'ppsx' => array( '', '', 'extppt' ),
                    'ppsm' => array( '', '', 'extppt' ),
                    'potx' => array( '', '', 'extppt' ),
                    'potm' => array( '', '', 'extppt' ),
                    'odp'  => array( '', '', 'extppt' ),
                    'otp'  => array( '', '', 'extppt' ),
                    'xml'  => array( '', '', 'extxml' ),
                    'zip'  => array( '', '', 'extzip' ),
            ),
            'Web'                 => array(
                    'htm'   => array( '', '', 'exthtm' ),
                    'html'  => array( '', '', 'exthtm' ),
                    'xhtml' => array( '', '', 'exthtm' ),
                    'xml'   => array( '', '', 'extxml' ),
                    'dtd'   => array( '', '', 'extxml' ),
//                    'php'   => array( '', '', 'extxml' ),
                    'json'  => array( '', '', 'extxml'),
                    'yaml'   => array( '', '', 'extxml' )
            ),
            'Scanned Files'                 => array(
                    'pdf'   => array( '', '', 'extpdf' ),
                    'bmp'   => array( '', '', 'extimg' ),
                    'png'   => array( '', '', 'extimg' ),
                    'gif'   => array( '', '', 'extimg' ),
                    'jpeg'   => array( '', '', 'extimg' ),
                    'tiff'  => array( '', '', 'extimg' )
            ),
            "Interchange Formats" => array(
                    'xliff'    => array( 'default', '', 'extxif' ),
                    'sdlxliff' => array( 'default', '', 'extxif' ),
                    'tmx'      => array( '', '', 'exttmx' ),
                    'ttx'      => array( '', '', 'extttx' ),
                    'xlf'      => array( 'default', '', 'extxlf' ),
            ),
            "Desktop Publishing"  => array(
                    'mif'  => array( '', '', 'extmif' ),
                    'idml' => array( '', '', 'extidd' ),
                    'icml' => array( '', '', 'exticml' ),
                    'xml'  => array( '', '', 'extxml' ),
                    'dita' => array( '', '', 'extdit' )
            ),
            "Localization"        => array(
                    'properties'  => array( '', '', 'extpro' ),
                    'resx'        => array( '', '', 'extres' ),
                    'xml'         => array( '', '', 'extxml' ),
                    'sxml'        => array( '', '', 'extxml' ),
                    'txml'        => array( '', '', 'extxml' ),
                    'dita'        => array( '', '', 'extdit' ),
                    'Android xml' => array( '', '', 'extxml' ),
                    'strings'     => array( '', '', 'extstr' ),
                    'srt'         => array( '', '', 'extsrt' ),
                    'wix'         => array( '', '', 'extwix' ),
                    'po'          => array( '', '', 'extpo'  ),
                    'g'           => array( '', '', 'extg' )
            )
    );

    public static $MIME_TYPES = array();


    public static $UNSUPPORTED_FILE_TYPES = array(
            'fm'   => array( '', "Try converting to MIF" ),
            'indd' => array( '', "Try converting to INX" )
    );

    public static $DEPRECATE_LEGACY_XLIFFS = true;

    /*
     * The maximum filename length accepted.
     * Usually OSes accept names of 255 characters at most.
     * During the execution a hash string can be prepended to the filename.
     * So we reserve 45 chars for internal purposes.
     */
    public static $MAX_FILENAME_LENGTH = 210;

    public static $PLUGIN_LOAD_PATHS = array();

    public static $MANDATORY_PLUGINS = array();

    /**
     * Definitions for the asynchronous task runner
     * @var array
     */
    public static $TASK_RUNNER_CONFIG = null;

    public static $SEND_ERR_MAIL_REPORT = true ;

    /**
     * Initialize the Class Instance
     */
    public static function obtain() {
        new self();
    }

}
