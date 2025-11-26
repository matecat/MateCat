<?php


namespace Utils\Registry;

use Exception;
use RuntimeException;
use Utils\Constants\Mime2Extension;

class AppConfig
{

    public static array $MANDATORY_KEYS = [
            'ENV',
            'DB_SERVER',
            'DB_DATABASE',
            'DB_USER',
            'DB_PASS',
            'REDIS_SERVERS',
            'QUEUE_BROKER_ADDRESS',
            'QUEUE_JMX_ADDRESS',
            'QUEUE_CREDENTIALS',
            'HTTPHOST',
            'CLI_HTTP_HOST',
            'COOKIE_DOMAIN',
            'AUTHSECRET',
    ];

    /**
     * @var string|null $ENV
     *
     * General server environment settings to define the usage of hard links rather than copy php method
     * must be one of these:
     *
     * - Production
     * - development
     * - test
     *
     * @see EnvWrap
     *
     */
    public static ?string $ENV = null;

    /**
     * Indicates whether the current instance is running as a daemon process.
     */
    public static bool $IS_DAEMON_INSTANCE = false;

    public static string  $ROOT;
    public static string  $BASEURL;
    public static string  $HTTPHOST;
    public static string  $CLI_HTTP_HOST;
    public static string  $COOKIE_DOMAIN;
    public static string  $PHP_SESSION_NAME = 'PHPSESSID';
    public static int     $AJAX_DOMAINS     = 100;
    public static string  $PROTOCOL         = 'https';
    public static bool    $DEBUG            = true;
    public static bool    $PRINT_ERRORS     = false;
    public static ?string $DB_SERVER        = null;
    public static ?string $DB_DATABASE      = null;
    public static ?string $DB_USER          = null;
    public static ?string $DB_PASS          = null;
    public static int     $INSTANCE_ID      = 0;
    public static string  $REDIS_SERVERS    = '';
    public static string  $QUEUE_BROKER_ADDRESS;
    public static string  $QUEUE_JMX_ADDRESS;
    public static string  $QUEUE_CREDENTIALS;

    public static bool   $ENABLE_MULTI_DOMAIN_API = false;
    public static string $XSRF_TOKEN              = 'Xsrf-Token';

    /**
     * Use or not the js tracking codes macro import (Ex: Google Analytics code injection)
     *
     * PHPTAL macro located in lib/View/external_sources.html
     *
     * @var string Customized path for the tracking codes (empty default: lib/View)
     */
    public static string $TRACKING_CODES_VIEW_PATH = "";

    public static bool   $COMMENTS_ENABLED                = true;
    public static string $SOCKET_NOTIFICATIONS_QUEUE_NAME = "/queue/matecat_socket_notifications";
    public static string $SOCKET_BASE_URL                 = '';

    public static ?string $SMTP_HOST     = null;
    public static ?int    $SMTP_PORT     = null;
    public static ?string $SMTP_SENDER   = null;
    public static ?string $SMTP_HOSTNAME = null;

    public static string $MAILER_FROM_NAME   = 'Matecat';
    public static string $MAILER_RETURN_PATH = 'no-reply@matecat.com';

    public static ?string $LOG_REPOSITORY              = null;
    public static ?string $STORAGE_DIR                 = null;
    public static string  $UPLOAD_REPOSITORY;
    public static string  $FILES_REPOSITORY;
    public static string  $CACHE_REPOSITORY;
    public static string  $ZIP_REPOSITORY;
    public static string  $ANALYSIS_FILES_REPOSITORY;
    public static string  $QUEUE_PROJECT_REPOSITORY;
    public static string  $CONVERSION_ERRORS_REPOSITORY;
    public static string  $TMP_DOWNLOAD;
    public static string  $TEMPLATE_ROOT;
    public static string  $UTILS_ROOT;
    public static int     $DEFAULT_NUM_RESULTS_FROM_TM = 3;
    public static string  $AUTHSECRET;
    public static string  $AUTHSECRET_PATH;

    public static bool   $FORCE_XLIFF_CONVERSION    = false;
    public static bool   $FILTERS_OCR_CHECK         = true;
    public static bool   $VOLUME_ANALYSIS_ENABLED   = true;
    public static int    $WARNING_POLLING_INTERVAL  = 20; //seconds
    public static int    $SEGMENT_QA_CHECK_INTERVAL = 1; //seconds
    public static string $AUTHCOOKIENAME            = 'matecat_login_v6';
    public static string $SUPPORT_MAIL              = 'the owner of this MateCat instance.';//the default string is 'the owner of this Matecat instance'
    public static int    $ANALYSIS_WORDS_PER_DAYS   = 3000;
    public static int    $AUTHCOOKIEDURATION        = 86400 * 7;        // 24 hours
    public static int    $MAX_UPLOAD_FILE_SIZE      = 62914560;     // 60 * 1024 * 1024 // bytes
    public static int    $MAX_UPLOAD_TMX_FILE_SIZE  = 314572800;    // 300 * 1024 * 1024 // bytes
    public static int    $MAX_NUM_FILES             = 100;
    public static int    $MAX_SOURCE_WORDS          = 250000;

    /**
     * OPENAI configuration
     */
    public static string $OPENAI_API_KEY     = '';
    public static string $OPEN_AI_MODEL      = '';
    public static int    $OPEN_AI_TIMEOUT    = 30; //seconds
    public static string $OPEN_AI_MAX_TOKENS = '';

    /**
     * We propose that lxq_server is in a configuration file
     * lxq_license: ${lxq_license}
     *
     * THIS SHOULD BE YOUR LEXIQA LICENSE, Get your license key at
     * @see http://www.lexiqa.net
     *
     */
    public static ?string $LXQ_LICENSE = null;
    public static string  $LXQ_SERVER  = "https://backend.lexiqa.net";
    /**
     * Your partnerId will be provided along with your
     * @see http://www.lexiqa.net
     *
     */
    public static ?string $LXQ_PARTNERID = null;
    /**
     * Time zone string that should match the one set in the database.
     * @var string
     */
    public static string $TIME_ZONE = 'Europe/Rome';

    /**
     * Use this setting to indicate the upperbound memory limit you want to
     * apply to fast analysis. You may want to set this to allow analysis of
     * big files.
     * @var string|null memory limit. Example "2048M"
     */
    public static ?string $FAST_ANALYSIS_MEMORY_LIMIT = null;

    /**
     * Default Matecat user agent string
     */
    const string MATECAT_USER_AGENT = 'Matecat-Cattool/v';

    /**
     * ENABLE_OUTSOURCE set as true, will show the option to outsource to an external
     * translation provider (translated.net by default).
     * You can set it to false, but We are happy if you keep this on.
     * For each translation outsourced to Translated.net (the main Matecat developer),
     * Matecat gets more development budget and bugs fixes and new features get implemented faster.
     * In short: please turn it off only if strictly necessary :)
     * @var bool
     */
    public static bool $ENABLE_OUTSOURCE = true;

    /**
     * MateCat Filters configuration
     */
    public static string $FILTERS_USER_AGENT                    = "MateCat Community Instance";
    public static string $FILTERS_ADDRESS                       = "https://translated-matecat-filters-v1.p.rapidapi.com";
    public static string $FILTERS_RAPIDAPI_KEY                  = "https://rapidapi.com/translated/api/matecat-filters to obtain your RapidAPI Key";
    public static bool   $FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = false;
    public static bool   $FILTERS_EMAIL_FAILURES                = false;

    /**
     * The MateCat Version
     */
    public static string $BUILD_NUMBER = '';

    /**
     * MyMemory Developer email Key for the cattool
     * @var string
     */
    public static string $MYMEMORY_API_KEY = 'demo@matecat.com';

    /**
     * MyMemory Developer email Key for the analysis
     * @var string
     */
    public static string $MYMEMORY_TM_API_KEY = 'tmanalysis@matecat.com';

    /**
     * Default key used to call the TM Server on an Import TMX panel
     * @var string
     */
    public static string $DEFAULT_TM_KEY = '';

    /**
     * @var string The default MMT license is applied when Lara falls back for unsupported languages and the user doesn't add their personal MMT license.
     */
    public static string $DEFAULT_MMT_KEY = '';

    /**
     * Holds the value of the LARA pre-shared key.
     *
     * This property stores the actual pre-shared key used for LARA authentication.
     *
     * @var string
     */
    public static string $LARA_PRE_SHARED_KEY_HEADER = ''; //TODO: to be removed when Lara will read directly from the internal queue

    /**
     * If you don't have a client id and client secret, please visit
     * Google Developer Console (https://console.developers.google.com/)
     * and follow these instructions:
     * - click "Create Project" button and specify project name
     * - In the sidebar on the left, select APIs & auth.
     * - In the displayed list of APIs, make sure "Google+ API" shows a status of ON. If it doesn't, enable it.
     * - In the sidebar on the left, select "Credentials" under "APIs & auth" menu.
     * - Click "Create new client ID" button
     * - under APPLICATION TYPE, select "web app" option
     * - under AUTHORIZED JAVASCRIPT ORIGINS, insert the domain on which you installed MateCat
     * - under REDIRECT URIs, insert "http://<domain>/oauth/response" , where <domain> is the same that you specified in the previous step
     * - click "Create client ID"
     * - Still in Credentials page, click "Create credentials" and select "API key"
     * - Click "Browser key"
     * - under Name, insert the name of your Browser API key
     * - under "Accept requests from these HTTP referrers (websites)", insert "<domain>/*",
     *   where <domain> is the same that you specified in the previous steps.
     * - In the sidebar select Overview and search for Google Picker
     * - Click on the "Google Picker API" link, and then click Enable button
     *
     * Your client ID, client secret, and Browser API key are now available.
     *
     * Edit the file inc/oauth_config.ini.sample with the right parameters obtained in the previous step of this guide.
     * Set:
     * OAUTH_CLIENT_ID with your client ID
     * OAUTH_CLIENT_SECRET with your client secret
     * OAUTH_CLIENT_APP_NAME with your custom app name if you want, or leave Matecat
     * OAUTH_BROWSER_API_KEY with your browser API key, required to open Google Picker
     *
     * Save and rename to oauth_config.ini file.
     *
     * Done.
     */
    public static array $OAUTH_CONFIG = [];

    /**
     * Google credentials
     */
    public static ?string $GOOGLE_OAUTH_CLIENT_ID       = null;
    public static ?string $GOOGLE_OAUTH_CLIENT_SECRET   = null;
    public static ?string $GOOGLE_OAUTH_CLIENT_APP_NAME = null;
    public static ?string $GOOGLE_OAUTH_REDIRECT_URL    = null;
    public static ?string $GOOGLE_OAUTH_BROWSER_API_KEY = null;

    /**
     * GitHub credentials
     */
    public static ?string $GITHUB_OAUTH_CLIENT_ID     = null;
    public static ?string $GITHUB_OAUTH_CLIENT_SECRET = null;
    public static ?string $GITHUB_OAUTH_REDIRECT_URL  = null;

    /**
     * Linkedin credentials
     */
    public static ?string $LINKEDIN_OAUTH_CLIENT_ID     = null;
    public static ?string $LINKEDIN_OAUTH_CLIENT_SECRET = null;
    public static ?string $LINKEDIN_OAUTH_REDIRECT_URL  = null;

    /**
     * Microsoft credentials
     */
    public static ?string $MICROSOFT_OAUTH_CLIENT_ID     = null;
    public static ?string $MICROSOFT_OAUTH_CLIENT_SECRET = null;
    public static ?string $MICROSOFT_OAUTH_REDIRECT_URL  = null;

    /**
     * Facebook credentials
     */
    public static ?string $FACEBOOK_OAUTH_CLIENT_ID     = null;
    public static ?string $FACEBOOK_OAUTH_CLIENT_SECRET = null;
    public static ?string $FACEBOOK_OAUTH_REDIRECT_URL  = null;

    public static bool $SKIP_SQL_CACHE = false;

    /**
     * FileStorage Configuration: [s3|fs]
     */
    public static string $FILE_STORAGE_METHOD = '';

    /**
     * S3FilesStorage Configuration
     */
    public static ?string $AWS_ACCESS_KEY_ID = null;
    public static ?string $AWS_SECRET_KEY    = null;
    public static string  $AWS_VERSION;
    public static string  $AWS_REGION;
    public static bool    $AWS_SSL_VERIFY    = false;
    public static bool    $AWS_CACHING       = false;
    public static string  $AWS_STORAGE_BASE_BUCKET;

    /**
     * Logging configuration
     */
    public static array $MONOLOG_HANDLERS = [];

    public static string $REPLACE_HISTORY_DRIVER = '';
    public static int    $REPLACE_HISTORY_TTL    = 0;

    private static ?AppConfig $MYSELF = null;

    protected function __construct(
        string $rootPath,
        string $envName,
        string $matecatVersion,
        array $configuration,
        array $taskManagerConfiguration,
    )
    {
        self::$ENV                = $envName;
        self::$BUILD_NUMBER       = $matecatVersion;
        self::$TASK_RUNNER_CONFIG = $taskManagerConfiguration;

        // Overridable defaults
        self::$ROOT                        = $rootPath; // Accessible by Apache/PHP
        self::$BASEURL                     = "/"; // Accessible by the browser
        self::$DEFAULT_NUM_RESULTS_FROM_TM = 3;
        self::$TRACKING_CODES_VIEW_PATH    = self::$ROOT . "/lib/View/templates";

        // Detects if the script is running via Command Line Interface (CLI) to flag the instance as a daemon/background worker
        AppConfig::$IS_DAEMON_INSTANCE = stripos(PHP_SAPI, 'cli') !== false;

        //Override default configuration
        foreach ($configuration as $KEY => $value) {
            if (property_exists(self::class, $KEY)) {
                if ($KEY == 'MONOLOG_HANDLERS') {
                    foreach ($value as $handler) {
                        AppConfig::${$KEY}[$handler] = $configuration[$handler] ?? [];
                    }
                } else {
                    AppConfig::${$KEY} = $value;
                }
            }
        }

        if (!self::$IS_DAEMON_INSTANCE) {
            // Get HTTPS server status
            // Override if the header is set from load balancer
            $localProto = 'http';
            foreach (['HTTPS', 'HTTP_X_FORWARDED_PROTO'] as $_key) {
                if (isset($_SERVER[ $_key ])) {
                    $localProto = 'https';
                    break;
                }
            }
            self::$PROTOCOL = $localProto;
        }

        if (empty(self::$STORAGE_DIR)) {
            self::$STORAGE_DIR = self::$ROOT . "/local_storage";
        }

        self::$HTTPHOST                     = self::$CLI_HTTP_HOST;
        self::$LOG_REPOSITORY               = self::$STORAGE_DIR . "/log_archive";
        self::$UPLOAD_REPOSITORY            = self::$STORAGE_DIR . "/upload";
        self::$FILES_REPOSITORY             = self::$STORAGE_DIR . "/files_storage/files";
        self::$CACHE_REPOSITORY             = self::$STORAGE_DIR . "/files_storage/cache";
        self::$ZIP_REPOSITORY               = self::$STORAGE_DIR . "/files_storage/originalZip";
        self::$ANALYSIS_FILES_REPOSITORY    = self::$STORAGE_DIR . "/files_storage/fastAnalysis";
        self::$QUEUE_PROJECT_REPOSITORY     = self::$STORAGE_DIR . "/files_storage/queueProjects";
        self::$CONVERSION_ERRORS_REPOSITORY = self::$STORAGE_DIR . "/conversion_errors";
        self::$TMP_DOWNLOAD                 = self::$STORAGE_DIR . "/tmp_download";
        self::$TEMPLATE_ROOT                = self::$ROOT . "/lib/View";
        self::$UTILS_ROOT                   = self::$ROOT . '/lib/Utils';

        $oauth_config_file = self::$ROOT . DIRECTORY_SEPARATOR . 'inc/oauth_config.ini';

        if (file_exists($oauth_config_file)) {
            self::$OAUTH_CONFIG = parse_ini_file($oauth_config_file, true) ?? [];
        }

        //auth sections
        self::$AUTHSECRET_PATH = self::$ROOT . '/inc/login_secret.dat';

        //if a secret is set in file
        if (file_exists(self::$AUTHSECRET_PATH)) {
            //fetch it
            self::$AUTHSECRET = file_get_contents(self::$AUTHSECRET_PATH);
        } else {
            //generates pass
            try {
                $x                = random_bytes(256);
                self::$AUTHSECRET = bin2Hex($x);
            } catch (Exception $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            //put the file
            file_put_contents(self::$AUTHSECRET_PATH, self::$AUTHSECRET);
            //if put succeeds
            if (file_exists(self::$AUTHSECRET_PATH)) {
                //restrict permissions
                chmod(self::$AUTHSECRET_PATH, 0400);
            } else {
                //if we couldn't create due to permissions, use default secret
                self::$AUTHSECRET = 'ScavengerOfHumanSorrow';
            }
        }

        self::$GITHUB_OAUTH_CLIENT_ID     = self::$OAUTH_CONFIG[ 'GITHUB_OAUTH_CONFIG' ][ 'GITHUB_OAUTH_CLIENT_ID' ] ?? null;
        self::$GITHUB_OAUTH_CLIENT_SECRET = self::$OAUTH_CONFIG[ 'GITHUB_OAUTH_CONFIG' ][ 'GITHUB_OAUTH_CLIENT_SECRET' ] ?? null;
        self::$GITHUB_OAUTH_REDIRECT_URL  = self::$OAUTH_CONFIG[ 'GITHUB_OAUTH_CONFIG' ][ 'GITHUB_OAUTH_REDIRECT_URL' ] ?? null;

        self::$LINKEDIN_OAUTH_CLIENT_ID     = self::$OAUTH_CONFIG[ 'LINKEDIN_OAUTH_CONFIG' ][ 'LINKEDIN_OAUTH_CLIENT_ID' ] ?? null;
        self::$LINKEDIN_OAUTH_CLIENT_SECRET = self::$OAUTH_CONFIG[ 'LINKEDIN_OAUTH_CONFIG' ][ 'LINKEDIN_OAUTH_CLIENT_SECRET' ] ?? null;
        self::$LINKEDIN_OAUTH_REDIRECT_URL  = self::$OAUTH_CONFIG[ 'LINKEDIN_OAUTH_CONFIG' ][ 'LINKEDIN_OAUTH_REDIRECT_URL' ] ?? null;

        self::$MICROSOFT_OAUTH_CLIENT_ID     = self::$OAUTH_CONFIG[ 'MICROSOFT_OAUTH_CONFIG' ][ 'MICROSOFT_OAUTH_CLIENT_ID' ] ?? null;
        self::$MICROSOFT_OAUTH_CLIENT_SECRET = self::$OAUTH_CONFIG[ 'MICROSOFT_OAUTH_CONFIG' ][ 'MICROSOFT_OAUTH_CLIENT_SECRET' ] ?? null;
        self::$MICROSOFT_OAUTH_REDIRECT_URL  = self::$OAUTH_CONFIG[ 'MICROSOFT_OAUTH_CONFIG' ][ 'MICROSOFT_OAUTH_REDIRECT_URL' ] ?? null;

        self::$FACEBOOK_OAUTH_CLIENT_ID     = self::$OAUTH_CONFIG[ 'FACEBOOK_OAUTH_CONFIG' ][ 'FACEBOOK_OAUTH_CLIENT_ID' ] ?? null;
        self::$FACEBOOK_OAUTH_CLIENT_SECRET = self::$OAUTH_CONFIG[ 'FACEBOOK_OAUTH_CONFIG' ][ 'FACEBOOK_OAUTH_CLIENT_SECRET' ] ?? null;
        self::$FACEBOOK_OAUTH_REDIRECT_URL  = self::$OAUTH_CONFIG[ 'FACEBOOK_OAUTH_CONFIG' ][ 'FACEBOOK_OAUTH_REDIRECT_URL' ] ?? null;

        self::$GOOGLE_OAUTH_CLIENT_ID     = self::$OAUTH_CONFIG[ 'GOOGLE_OAUTH_CONFIG' ][ 'GOOGLE_OAUTH_CLIENT_ID' ] ?? null;
        self::$GOOGLE_OAUTH_CLIENT_SECRET = self::$OAUTH_CONFIG[ 'GOOGLE_OAUTH_CONFIG' ][ 'GOOGLE_OAUTH_CLIENT_SECRET' ] ?? null;
        self::$GOOGLE_OAUTH_REDIRECT_URL  = self::$OAUTH_CONFIG[ 'GOOGLE_OAUTH_CONFIG' ][ 'GOOGLE_OAUTH_REDIRECT_URL' ] ?? null;

        # Drive
        self::$GOOGLE_OAUTH_CLIENT_APP_NAME = self::$OAUTH_CONFIG[ 'GOOGLE_OAUTH_CONFIG' ][ 'GOOGLE_OAUTH_CLIENT_APP_NAME' ] ?? null;
        self::$GOOGLE_OAUTH_BROWSER_API_KEY = self::$OAUTH_CONFIG[ 'GOOGLE_OAUTH_CONFIG' ][ 'GOOGLE_OAUTH_BROWSER_API_KEY' ] ?? null;

        self::$MIME_TYPES = Mime2Extension::getMimeTypes();
    }

    public static array $SUPPORTED_FILE_TYPES = [
            'Office'              => [
                    'pages'   => ['', '', 'extdoc'],
                    'doc'     => ['', '', 'extdoc'],
                    'dot'     => ['', '', 'extdoc'],
                    'docx'    => ['', '', 'extdoc'],
                    'docm'    => ['', '', 'extdoc'],
                    'dotx'    => ['', '', 'extdoc'],
                    'dotm'    => ['', '', 'extdoc'],
                    'rtf'     => ['', '', 'extdoc'],
                    'odt'     => ['', '', 'extdoc'],
                    'ott'     => ['', '', 'extdoc'],
                    'pdf'     => ['', '', 'extpdf'],
                    'numbers' => ['', '', 'extxls'],
                    'txt'     => ['', '', 'exttxt'],
                    'xls'     => ['', '', 'extxls'],
                    'xlt'     => ['', '', 'extxls'],
                    'xlsx'    => ['', '', 'extxls'],
                    'xlsm'    => ['', '', 'extxls'],
                    'xltx'    => ['', '', 'extxls'],
                    'xltm'    => ['', '', 'extxls'],
                    'ods'     => ['', '', 'extxls'],
                    'ots'     => ['', '', 'extxls'],
                //'csv'  => array( '', '', 'extxls' ),
                    'tsv'     => ['', '', 'extxls'],
                    'key'     => ['', '', 'extppt'],
                    'ppt'     => ['', '', 'extppt'],
                    'pps'     => ['', '', 'extppt'],
                    'pot'     => ['', '', 'extppt'],
                    'pptx'    => ['', '', 'extppt'],
                    'pptm'    => ['', '', 'extppt'],
                    'ppsx'    => ['', '', 'extppt'],
                    'ppsm'    => ['', '', 'extppt'],
                    'potx'    => ['', '', 'extppt'],
                    'potm'    => ['', '', 'extppt'],
                    'odp'     => ['', '', 'extppt'],
                    'otp'     => ['', '', 'extppt'],
                    'xml'     => ['', '', 'extxml'],
                    'zip'     => ['', '', 'extzip'],
            ],
            'Web'                 => [
                    'htm'    => ['', '', 'exthtm'],
                    'html'   => ['', '', 'exthtm'],
                    'xhtml'  => ['', '', 'exthtm'],
                    'xml'    => ['', '', 'extxml'],
                    'dtd'    => ['', '', 'extxml'],
//                    'php'   => array( '', '', 'extxml' ),
                    'json'   => ['', '', 'extxml'],
                    'jsont'  => ['', '', 'extxml'],
                    'jsont2' => ['', '', 'extxml'],
                    'yaml'   => ['', '', 'extxml'],
                    'yml'    => ['', '', 'extxml'],
                    'md'     => ['', '', 'extxml'],
            ],
            'Scanned Files'       => [
                    'pdf'  => ['', '', 'extpdf'],
                    'bmp'  => ['', '', 'extimg'],
                    'png'  => ['', '', 'extimg'],
                    'gif'  => ['', '', 'extimg'],
                    'jpeg' => ['', '', 'extimg'],
                    'jpg'  => ['', '', 'extimg'],
                    'jfif' => ['', '', 'extimg'],
                    'tiff' => ['', '', 'extimg']
            ],
            "Interchange Formats" => [
                    'xliff'    => ['default', '', 'extxif'],
                    'sdlxliff' => ['default', '', 'extxif'],
                    'tmx'      => ['', '', 'exttmx'],
                    'ttx'      => ['', '', 'extttx'],
                    'xlf'      => ['default', '', 'extxlf'],
            ],
            "Desktop Publishing"  => [
                    'mif'     => ['', '', 'extmif'],
                    'idml'    => ['', '', 'extidd'],
                    'icml'    => ['', '', 'exticml'],
                    'xml'     => ['', '', 'extxml'],
                    'dita'    => ['', '', 'extdit'],
                    'ditamap' => ['', '', 'extdit']
            ],
            "Localization"        => [
                    'properties'  => ['', '', 'extpro'],
                    'resx'        => ['', '', 'extres'],
                    'xml'         => ['', '', 'extxml'],
                    'sxml'        => ['', '', 'extxml'],
                    'txml'        => ['', '', 'extxml'],
                    'dita'        => ['', '', 'extdit'],
                    'ditamap'     => ['', '', 'extdit'],
                    'Android xml' => ['', '', 'extxml'],
                    'strings'     => ['', '', 'extstr'],
                    'sbv'         => ['', '', 'extsbv'],
                    'srt'         => ['', '', 'extsrt'],
                    'vtt'         => ['', '', 'extvtt'],
                    'wix'         => ['', '', 'extwix'],
                    'po'          => ['', '', 'extpo'],
                    'g'           => ['', '', 'extg'],
                    'ts'          => ['', '', 'exts'],
            ]
    ];

    public static array $MIME_TYPES = [];

    /*
     * The maximum filename length accepted.
     * Usually OSes accept names of 255 characters at most.
     * During the execution, a hash string can be prepended to the filename.
     * So we reserve 45 chars for internal purposes.
     */
    public static int $MAX_FILENAME_LENGTH = 210;

    public static array $AUTOLOAD_PLUGINS = ["second_pass_review"];

    /**
     * Definitions for the asynchronous task runner
     * @var array
     */
    public static array $TASK_RUNNER_CONFIG = [];

    public static bool $SEND_ERR_MAIL_REPORT = true;

    /**
     * Initialize the Class Instance
     *
     * @param string $rootPath
     * @param string $envName
     * @param string $matecatVersion
     * @param array $configuration
     * @param array $taskManagerConfiguration
     */
    public static function init(
        string $rootPath,
        string $envName,
        string $matecatVersion,
        array $configuration,
        array $taskManagerConfiguration,
    ): void
    {
        if (empty(self::$MYSELF)) {
            self::$MYSELF = new self($rootPath, $envName, $matecatVersion, $configuration, $taskManagerConfiguration);
        }
    }

    /**
     * Check if all mandatory keys are present
     *
     * @return bool true if all mandatory keys are present, false otherwise
     */
    public static function areMandatoryKeysPresent(): bool
    {
        foreach (self::$MANDATORY_KEYS as $key) {
            if (!property_exists(self::class, $key) || self::$$key === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if Google Drive is properly configured.
     *
     * This method verifies the presence of the required configuration values
     * for Google Drive integration.
     * Specifically, it checks if the `GOOGLE_OAUTH_CLIENT_ID`
     * and `GOOGLE_OAUTH_BROWSER_API_KEY` properties are set and not empty.
     *
     * @return bool Returns `true` if both required properties are configured, `false` otherwise.
     */
    public static function isGDriveConfigured(): bool
    {
        if (empty(self::$GOOGLE_OAUTH_CLIENT_ID) || empty(self::$GOOGLE_OAUTH_BROWSER_API_KEY)) {
            return false;
        }

        return true;
    }

}
