<?php
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
    public static $THRESHOLD_MATCH_TM_NOT_TO_SHOW;


    public static function obtain() {        
        if (!self::$instance) {
            self::$instance = new INIT();
        }
        return self::$instance;
    }

     private function __construct() {
        $root = isset ($_SERVER['DOCUMENT_ROOT'])?$_SERVER['DOCUMENT_ROOT']:getcwd();	
        self::$ROOT = $root;
        
        self::$DEFAULT_NUM_RESULTS_FROM_TM=3;
	self::$THRESHOLD_MATCH_TM_NOT_TO_SHOW=50;

        self::$DB_SERVER = "10.30.1.241"; //database server
        self::$DB_DATABASE = "matecat_sandbox"; //database name
        self::$DB_USER = "matecat"; //database login 
        self::$DB_PASS = "matecat01"; //databasepassword
 

        self::$LOG_REPOSITORY = self::$ROOT . "/log_archive";
        self::$TEMPLATE_ROOT = self::$ROOT . "/lib/view";
        self::$MODEL_ROOT = self::$ROOT . '/lib/model';
        self::$CONTROLLER_ROOT = self::$ROOT . '/lib/controller';
        self::$UTILS_ROOT = self::$ROOT . '/lib/utils';
    }

}
?>
