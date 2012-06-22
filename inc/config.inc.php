<?php

//print_r ($_SERVER['HTTP_HOST']);

class INIT {
    private static $instance;

    public static $ROOT;
    public static $DEBUG;
    public static $DB_SERVER;
    public static $DB_DATABASE;
    public static $DB_USER;
    public static $DB_PASS;
    public static $LOG_REPOSITORY;
    public static $TEMPLATE_ROOT;
    public static $MODEL_ROOT;
    public static $CONTROLLER_ROOT;
    public static $UTILS_ROOT;
    
    public static $DEFAULT_NUM_RESULTS_FROM_TM;

    public static function obtain() {        
        if (!self::$instance) {
            self::$instance = new INIT();
        }
        return self::$instance;
    }

    private function __construct() {
        $root = getcwd();
        self::$ROOT = $root;
        
        self::$DEFAULT_NUM_RESULTS_FROM_TM=2;
        
        switch ($_SERVER['HTTP_HOST']) {
            case ('matecat.pi'):
            case ('matecat.local'):
                self::$DEBUG = true;
                self::$DB_SERVER = "localhost";
                self::$DB_DATABASE = "matecat_sandbox"; //database name
                self::$DB_USER = "root"; //database login name
                self::$DB_PASS = "root"; //database login password
                break;
            case 'cattooldemo.matecat.com':
            case 'matecat.translated.home':
            case 'matecat.com':
            case 'www.matecat.com':
                self::$DEBUG = false;
                self::$DB_SERVER = "213.215.131.241"; //database server
                self::$DB_DATABASE = "matecat_sandbox"; //database name
                self::$DB_USER = "translated"; //database login name
                self::$DB_PASS = "azerty1"; //database login password
                break;
            default :
                die(__FILE__ . " " . __LINE__ . " : unrecognided domain. Unable to set ROOT");
        }

        self::$LOG_REPOSITORY = self::$ROOT . "/log_archive";
        self::$TEMPLATE_ROOT = self::$ROOT . "/lib/view";
        self::$MODEL_ROOT = self::$ROOT . '/lib/model';
        self::$CONTROLLER_ROOT = self::$ROOT . '/lib/controller';
        self::$UTILS_ROOT = self::$ROOT . '/lib/utils';
    }

}


/*
if (!defined('CONST')) {
    define('CONST', 1);
    define('ROOT_URL', '/index.php');

    if (!defined('ROOT')) {
        // $root = dirname(getcwd());
        $root = getcwd();
        define('ROOT', $root);
        switch ($_SERVER['HTTP_HOST']) {
            case ('matecat.pi'):
            case ('matecat.local'):
                define('DEBUG', true);
                define('DB_SERVER', "localhost");
                define('DB_DATABASE', "matecat_sandbox"); //database name
                define('DB_USER', "root"); //database login name
                define('DB_PASS', "root"); //database login password
                break;
            case 'cattooldemo.matecat.com':
            case 'matecat.translated.home':
            case 'matecat.com':
            case 'www.matecat.com':
                define('DEBUG', false);
                define('DB_SERVER', "213.215.131.241"); //database server
                define('DB_DATABASE', "matecat"); //database name
                define('DB_USER', "translated"); //database login name
                define('DB_PASS', "azerty1"); //database login password
                break;
            default :
                die(__FILE__ . " " . __LINE__ . " : unrecognided domain. Unable to set ROOT");
        }
    }

    //     echo "ecco " . ROOT;exit;
    //print_r ($_SERVER);exit;

    define('LOG_REPOSITORY', ROOT . "/log_archive");
    define('TEMPLATE_ROOT', ROOT . '/lib/view');
    define('MODEL_ROOT', ROOT . '/lib/model');
    define('CONTROLLER_ROOT', ROOT . '/lib/controller');
    define('UTILS_ROOT', ROOT . '/lib/utils');
}
 * 
 */

?>
