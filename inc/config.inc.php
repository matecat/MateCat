<?php
class INIT {
    private static $instance;

    public static $ROOT;
    public static $BASEURL;
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
    public static $THRESHOLD_MATCH_TM_NOT_TO_SHOW;
    public static $TIME_TO_EDIT_ENABLED;
    public static $ENABLED_BROWSERS;
    public static $BUILD_NUMBER;


    public static function obtain() {        
        if (!self::$instance) {
            self::$instance = new INIT();
        }
        return self::$instance;
    }

     private function __construct() {
        //$root = isset ($_SERVER['DOCUMENT_ROOT'])?$_SERVER['DOCUMENT_ROOT']:getcwd();	
        $root = realpath(dirname(__FILE__).'/../');
        self::$ROOT = $root;  // Accesible by Apache/PHP
        self::$BASEURL = "/"; // Accesible by the browser
        
	set_include_path(get_include_path() . PATH_SEPARATOR . $root);

        self::$TIME_TO_EDIT_ENABLED = false;

        
        self::$DEFAULT_NUM_RESULTS_FROM_TM=3;
	self::$THRESHOLD_MATCH_TM_NOT_TO_SHOW=50;

        self::$DB_SERVER = "10.30.1.241"; //database server
               self::$DB_DATABASE = "matecat_sandbox"; //database name
                self::$DB_USER = "matecat"; //database login 
                self::$DB_PASS = "matecat01"; //databasepassword
 

        self::$LOG_REPOSITORY = self::$ROOT . "/storage/log_archive";
        self::$TEMPLATE_ROOT = self::$ROOT . "/lib/view";
        self::$MODEL_ROOT = self::$ROOT . '/lib/model';
        self::$CONTROLLER_ROOT = self::$ROOT . '/lib/controller';
        self::$UTILS_ROOT = self::$ROOT . '/lib/utils';

	self::$ENABLED_BROWSERS=array('chrome','firefox','safari');
	self::$BUILD_NUMBER='0.3.0';
    }

}
?>
