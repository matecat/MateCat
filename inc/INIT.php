<?php


class INIT {

    public static $MANDATORY_KEYS = [
            'ENV',
            'CHECK_FS',
            'DB_SERVER',
            'DB_DATABASE',
            'DB_USER',
            'DB_PASS'
    ];

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
    public static $ENV;

    public static $ROOT;
    public static $BASEURL;
    public static $HTTPHOST;
    public static $CLI_HTTP_HOST;
    public static $COOKIE_DOMAIN;
    public static $PHP_SESSION_NAME        = 'PHPSESSID';
    public static $AJAX_DOMAINS            = 100;
    public static $PROTOCOL;
    public static $DEBUG                   = true;
    public static $PRINT_ERRORS            = false;
    public static $DB_SERVER;
    public static $DB_DATABASE;
    public static $DB_USER;
    public static $DB_PASS;
    public static $INSTANCE_ID             = 0;
    public static $REDIS_SERVERS           = [];
    public static $QUEUE_BROKER_ADDRESS;
    public static $QUEUE_JMX_ADDRESS;
    public static $QUEUE_CREDENTIALS;
    public static $USE_COMPILED_ASSETS     = false;
    public static $ENABLE_MULTI_DOMAIN_API = false;
    public static $BLACKLIST_FILE_SIZE_MAX = 2097152;


    /**
     * Use or not the js tracking codes macro import ( Ex: google analytics code injection )
     *
     * PHPTAL macro located in lib/View/external_sources.html
     *
     * @var string Customized path for the tracking codes ( empty default: lib/View )
     */
    public static $TRACKING_CODES_VIEW_PATH = "";

    public static $QUEUE_NAME                   = "matecat_analysis_queue";
    public static $COMMENTS_ENABLED             = true;
    public static $SSE_NOTIFICATIONS_QUEUE_NAME = "/topic/matecat_sse_notifications";
    public static $SSE_BASE_URL;

    public static $SMTP_HOST;
    public static $SMTP_PORT;
    public static $SMTP_SENDER;
    public static $SMTP_HOSTNAME;

    public static $MAILER_FROM        = 'cattool@matecat.com';
    public static $MAILER_FROM_NAME   = 'MateCat';
    public static $MAILER_RETURN_PATH = 'no-reply@matecat.com';

    public static $LOG_REPOSITORY;
    public static $STORAGE_DIR;
    public static $UPLOAD_REPOSITORY;
    public static $FILES_REPOSITORY;
    public static $CACHE_REPOSITORY;
    public static $ZIP_REPOSITORY;
    public static $BLACKLIST_REPOSITORY;
    public static $ANALYSIS_FILES_REPOSITORY;
    public static $QUEUE_PROJECT_REPOSITORY;
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

    public static $FORCE_XLIFF_CONVERSION       = false;
    public static $FILTERS_OCR_CHECK            = true;
    public static $VOLUME_ANALYSIS_ENABLED      = true;
    public static $WARNING_POLLING_INTERVAL     = 20; //seconds
    public static $SEGMENT_QA_CHECK_INTERVAL    = 1; //seconds
    public static $SAVE_SHASUM_FOR_FILES_LOADED = true;
    public static $AUTHCOOKIENAME               = 'matecat_login_v5';
    public static $SUPPORT_MAIL                 = 'the owner of this MateCat instance.';//default string is 'the owner of this Matecat instance'
    public static $ANALYSIS_WORDS_PER_DAYS      = 3000;
    public static $AUTHCOOKIEDURATION           = 5184000;            // 86400 * 60;         // seconds
    public static $MAX_UPLOAD_FILE_SIZE         = 62914560;         // 60 * 1024 * 1024;  // bytes
    public static $MAX_UPLOAD_TMX_FILE_SIZE     = 314572800;    // 300 * 1024 * 1024; // bytes
    public static $MAX_NUM_FILES                = 100;
    public static $MAX_SOURCE_WORDS             = 250000;

    /**
     * DQF configuration
     *
     */
    public static $DQF_BASE_URL;
    public static $DQF_ID_PREFIX = '';
    public static $DQF_API_KEY;
    public static $DQF_ENCRYPTION_KEY;
    public static $DQF_ENCRYPTION_IV;

    public static $DQF_GENERIC_USERNAME;
    public static $DQF_GENERIC_PASSWORD;

    /**
     * We proose that lxq_server is in a configuration file
     * lxq_license: ${lxq_license},
     *
     * THIS SHOULD BE YOUR LEXIQA LICENSE, Request your license key at
     * @see http://www.lexiqa.net
     *
     */
    public static $LXQ_LICENSE = false;
    public static $LXQ_SERVER  = "https://backend.lexiqa.net";
    /**
     * Your partnerid will be provided along with your
     * @see http://www.lexiqa.net
     *
     */
    public static $LXQ_PARTNERID = false;
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
    public static $MAX_NUM_SEGMENTS     = 500;

    /**
     * Default Matecat user agent string
     */
    const MATECAT_USER_AGENT = 'Matecat-Cattool/v';

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
    public static $FILTERS_USER_AGENT                    = "MateCat Community Instance";
    public static $FILTERS_ADDRESS                       = "https://translated-matecat-filters-v1.p.rapidapi.com";
    public static $FILTERS_RAPIDAPI_KEY                  = "https://rapidapi.com/translated/api/matecat-filters to obtain your RapidAPI Key";
    public static $FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
    public static $FILTERS_EMAIL_FAILURES                = false;

    /**
     * The MateCat Version
     */
    //TODO: Rename variable to MATECAT_VERSION
    public static $BUILD_NUMBER;

    /**
     * MyMemory Developer Email Key for the cattool
     * @var string
     */
    public static $MYMEMORY_API_KEY = 'demo@matecat.com';

    /**
     * MyMemory Developer Email Key for the analysis
     * @var string
     */
    public static $MYMEMORY_TM_API_KEY = 'tmanalysis@matecat.com';

    /**
     * Default key used to call the TM Server on Import TMX panel
     * @var string
     */
    public static $DEFAULT_TM_KEY = '';


    public static $ENABLED_BROWSERS = [ 'applewebkit', 'chrome', 'safari', 'edge' ]; //, 'firefox');

    // sometimes the browser declare to be Mozilla but does not provide a valid Name (e.g. Safari).
    // This occurs especially in mobile environment. As an example, when you try to open a link from within
    // the GMail app, it redirect to an internal browser that does not declare a valid user agent
    // In this case we will show a notice on the top of the page instead of deny the access
    public static $UNTESTED_BROWSERS = [ 'mozillageneric' ];

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

    public static $ENABLE_OMEGAT_DOWNLOAD = false;
    public static $UNLOCKABLE_TAGS        = false;

    public static $SKIP_SQL_CACHE = false;

    /**
     * FileStorage Configuration: [s3|fs]
     */
    public static $FILE_STORAGE_METHOD;

    /**
     * S3FilesStorage Configuration
     */
    public static $AWS_ACCESS_KEY_ID;
    public static $AWS_SECRET_KEY;
    public static $AWS_VERSION;
    public static $AWS_REGION;
    public static $AWS_SSL_VERIFY;
    public static $AWS_CACHING = false;
    public static $AWS_STORAGE_BASE_BUCKET;

    public static $REPLACE_HISTORY_DRIVER;
    public static $REPLACE_HISTORY_TTL;

    public function __construct() {

        self::$OAUTH_CLIENT_ID       = @INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_ID' ];
        self::$OAUTH_CLIENT_SECRET   = @INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_SECRET' ];
        self::$OAUTH_CLIENT_APP_NAME = @INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_APP_NAME' ];
        self::$OAUTH_BROWSER_API_KEY = @INIT::$OAUTH_CONFIG[ 'OAUTH_BROWSER_API_KEY' ];

        self::$OAUTH_REDIRECT_URL = INIT::$HTTPHOST . "/oauth/response";

        self::$OAUTH_SCOPES = [
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'profile'
        ];

        self::$MIME_TYPES = include( 'Mime2Extension.php' );
    }

    public static $SPELL_CHECK_TRANSPORT_TYPE = 'shell';
    public static $SPELL_CHECK_ENABLED        = false;
    public static $SUPPORTED_FILE_TYPES       = [
            'Office'              => [
                    'pages'   => [ '', '', 'extdoc' ],
                    'doc'     => [ '', '', 'extdoc' ],
                    'dot'     => [ '', '', 'extdoc' ],
                    'docx'    => [ '', '', 'extdoc' ],
                    'docm'    => [ '', '', 'extdoc' ],
                    'dotx'    => [ '', '', 'extdoc' ],
                    'dotm'    => [ '', '', 'extdoc' ],
                    'rtf'     => [ '', '', 'extdoc' ],
                    'odt'     => [ '', '', 'extdoc' ],
                    'ott'     => [ '', '', 'extdoc' ],
                    'pdf'     => [ '', '', 'extpdf' ],
                    'numbers' => [ '', '', 'extxls' ],
                    'txt'     => [ '', '', 'exttxt' ],
                    'xls'     => [ '', '', 'extxls' ],
                    'xlt'     => [ '', '', 'extxls' ],
                    'xlsx'    => [ '', '', 'extxls' ],
                    'xlsm'    => [ '', '', 'extxls' ],
                    'xltx'    => [ '', '', 'extxls' ],
                    'xltm'    => [ '', '', 'extxls' ],
                    'ods'     => [ '', '', 'extxls' ],
                    'ots'     => [ '', '', 'extxls' ],
                //'csv'  => array( '', '', 'extxls' ),
                    'tsv'     => [ '', '', 'extxls' ],
                    'key'     => [ '', '', 'extppt' ],
                    'ppt'     => [ '', '', 'extppt' ],
                    'pps'     => [ '', '', 'extppt' ],
                    'pot'     => [ '', '', 'extppt' ],
                    'pptx'    => [ '', '', 'extppt' ],
                    'pptm'    => [ '', '', 'extppt' ],
                    'ppsx'    => [ '', '', 'extppt' ],
                    'ppsm'    => [ '', '', 'extppt' ],
                    'potx'    => [ '', '', 'extppt' ],
                    'potm'    => [ '', '', 'extppt' ],
                    'odp'     => [ '', '', 'extppt' ],
                    'otp'     => [ '', '', 'extppt' ],
                    'xml'     => [ '', '', 'extxml' ],
                    'zip'     => [ '', '', 'extzip' ],
            ],
            'Web'                 => [
                    'htm'   => [ '', '', 'exthtm' ],
                    'html'  => [ '', '', 'exthtm' ],
                    'xhtml' => [ '', '', 'exthtm' ],
                    'xml'   => [ '', '', 'extxml' ],
                    'dtd'   => [ '', '', 'extxml' ],
//                    'php'   => array( '', '', 'extxml' ),
                    'json'  => [ '', '', 'extxml' ],
                    'jsont' => [ '', '', 'extxml' ],
                    'jsont2' => [ '', '', 'extxml' ],
                    'yaml'  => [ '', '', 'extxml' ],
                    'yml'   => [ '', '', 'extxml' ],
                    'md'    => [ '', '', 'extxml' ],
            ],
            'Scanned Files'       => [
                    'pdf'  => [ '', '', 'extpdf' ],
                    'bmp'  => [ '', '', 'extimg' ],
                    'png'  => [ '', '', 'extimg' ],
                    'gif'  => [ '', '', 'extimg' ],
                    'jpeg' => [ '', '', 'extimg' ],
                    'jpg'  => [ '', '', 'extimg' ],
                    'jfif' => [ '', '', 'extimg' ],
                    'tiff' => [ '', '', 'extimg' ]
            ],
            "Interchange Formats" => [
                    'xliff'    => [ 'default', '', 'extxif' ],
                    'sdlxliff' => [ 'default', '', 'extxif' ],
                    'tmx'      => [ '', '', 'exttmx' ],
                    'ttx'      => [ '', '', 'extttx' ],
                    'xlf'      => [ 'default', '', 'extxlf' ],
            ],
            "Desktop Publishing"  => [
                    'mif'  => [ '', '', 'extmif' ],
                    'idml' => [ '', '', 'extidd' ],
                    'icml' => [ '', '', 'exticml' ],
                    'xml'  => [ '', '', 'extxml' ],
                    'dita' => [ '', '', 'extdit' ]
            ],
            "Localization"        => [
                    'properties'  => [ '', '', 'extpro' ],
                    'resx'        => [ '', '', 'extres' ],
                    'xml'         => [ '', '', 'extxml' ],
                    'sxml'        => [ '', '', 'extxml' ],
                    'txml'        => [ '', '', 'extxml' ],
                    'dita'        => [ '', '', 'extdit' ],
                    'Android xml' => [ '', '', 'extxml' ],
                    'strings'     => [ '', '', 'extstr' ],
                    'sbv'         => [ '', '', 'extsbv' ],
                    'srt'         => [ '', '', 'extsrt' ],
                    'vtt'         => [ '', '', 'extvtt' ],
                    'wix'         => [ '', '', 'extwix' ],
                    'po'          => [ '', '', 'extpo' ],
                    'g'           => [ '', '', 'extg' ],
                    'ts'          => [ '', '', 'exts' ],
            ]
    ];

    public static $MIME_TYPES = [];


    public static $UNSUPPORTED_FILE_TYPES = [
            'fm'   => [ '', "Try converting to MIF" ],
            'indd' => [ '', "Try converting to INX" ]
    ];

    public static $DEPRECATE_LEGACY_XLIFFS = true;

    /*
     * The maximum filename length accepted.
     * Usually OSes accept names of 255 characters at most.
     * During the execution a hash string can be prepended to the filename.
     * So we reserve 45 chars for internal purposes.
     */
    public static $MAX_FILENAME_LENGTH = 210;

    public static $AUTOLOAD_PLUGINS = [];

    /**
     * Definitions for the asynchronous task runner
     * @var array
     */
    public static $TASK_RUNNER_CONFIG = null;

    public static $SEND_ERR_MAIL_REPORT = true;

    /**
     * Initialize the Class Instance
     */
    public static function obtain() {
        new self();
    }

}
