<?php
global $_INI_FILE;
$_INI_FILE = parse_ini_file(dirname(__FILE__).'/config.ini', true);

ini_set("display_errors", (bool) $_INI_FILE['debug']['displayerrors']);
ini_set("error_reporting", eval("return ".$_INI_FILE['debug']['displayerrors'].";"));

class INIT {
    private static $instance;

    public static $DEBUG;
    public static $ERROR_REPORTING;

    public static $ROOT;
    public static $BASEURL;
    public static $DB_SERVER;
    public static $DB_DATABASE;
    public static $DB_USER;
    public static $DB_PASS;
    public static $LOG_REPOSITORY;
    public static $LOG_FILENAME;
    public static $TEMPLATE_ROOT;
    public static $MODEL_ROOT;
    public static $CONTROLLER_ROOT;
    public static $UTILS_ROOT;
    
    public static $DEFAULT_NUM_RESULTS_FROM_TM;
    public static $THRESHOLD_MATCH_TM_NOT_TO_SHOW;
    public static $TIME_TO_EDIT_ENABLED;
    public static $ENABLED_BROWSERS;
    public static $BUILD_NUMBER;

    public static $CATSERVER;
    public static $HTRSERVER;


    public static function obtain() {        
        if (!self::$instance) {
            self::$instance = new INIT();
        }
        return self::$instance;
    }

     private function __construct() {
        // Read general config from INI file
        global $_INI_FILE;

        $root = realpath(dirname(__FILE__).'/../');
        self::$ROOT = $root;  // Accesible by Apache/PHP
        
        self::$BASEURL = $_INI_FILE['ui']['baseurl']; // Accesible by the browser
        
	set_include_path(get_include_path() . PATH_SEPARATOR . $root);

        self::$TIME_TO_EDIT_ENABLED = $_INI_FILE['ui']['timetoedit'];
        
        self::$DEFAULT_NUM_RESULTS_FROM_TM=$_INI_FILE['mymemory']['numresults'];
	self::$THRESHOLD_MATCH_TM_NOT_TO_SHOW=$_INI_FILE['mymemory']['matchthreshold'];

        self::$DB_SERVER = $_INI_FILE['db']['hostname'];
               self::$DB_DATABASE = $_INI_FILE['db']['database'];
                self::$DB_USER = $_INI_FILE['db']['username'];
                self::$DB_PASS = $_INI_FILE['db']['password'];
 

        self::$LOG_REPOSITORY = self::$ROOT . "/". $_INI_FILE['log']['directory'];
        self::$LOG_FILENAME = $_INI_FILE['log']['filename'];
        self::$TEMPLATE_ROOT = self::$ROOT . "/lib/view";
        self::$MODEL_ROOT = self::$ROOT . '/lib/model';
        self::$CONTROLLER_ROOT = self::$ROOT . '/lib/controller';
        self::$UTILS_ROOT = self::$ROOT . '/lib/utils';

	self::$ENABLED_BROWSERS=array('chrome','firefox','safari');
	self::$BUILD_NUMBER='0.3.0';

        // Custom translation/HTR servers (TODO: see how can integrate $_GET params with rewritten URLs)
        self::$CATSERVER = isset($_GET['catserver']) ? $_GET['catserver'] : $_INI_FILE['casmacat']['catserver'];
        self::$HTRSERVER = isset($_GET['htrserver']) ? $_GET['htrserver'] : $_INI_FILE['casmacat']['htrserver'];
    }

}
?>
