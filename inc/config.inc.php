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
    public static $DEFAULT_FILE_TYPES;
    public static $SUPPORTED_FILE_TYPES;
    public static $CONVERSION_FILE_TYPES;
    public static $CONVERSION_FILE_TYPES_PARTIALLY_SUPPORTED;
    public static $CONVERSION_ENABLED;
    public static $ANALYSIS_WORDS_PER_DAYS;
    public static $VOLUME_ANALYSIS_ENABLED;
    public static $RTL_LANGUAGES;

    private function initOK() {

        $flagfile = ".initok.lock";
        if (file_exists($flagfile)) {
            return true;
        }
        $errors = "";
        if (self::$DB_SERVER == "@ip address@") {
            $errors.="\$DB_SERVER not initialized\n";
        }

        if (self::$DB_USER == "@username@") {
            $errors.="\$DB_USER not initialized\n";
        }

        if (self::$DB_PASS == "@password@") {
            $errors.="\$DB_PASS not initialized\n";
        }


        if (empty($errors)) {
            touch($flagfile);
            return true;
        } else {

            echo "<pre>
                APPLICATION INIT ERROR : \n
                $errors\n";
            return false;
        }
    }

    public static function obtain() {
        if (!self::$instance) {
            self::$instance = new INIT();
        }
        
        return self::$instance;
    }

    private function __construct() {
        $root = realpath(dirname(__FILE__) . '/../');
        self::$ROOT = $root;  // Accesible by Apache/PHP
        self::$BASEURL = "/"; // Accesible by the browser

        set_include_path(get_include_path() . PATH_SEPARATOR . $root);

        self::$TIME_TO_EDIT_ENABLED = false;


        self::$DEFAULT_NUM_RESULTS_FROM_TM = 3;
        self::$THRESHOLD_MATCH_TM_NOT_TO_SHOW = 50;

        self::$DB_SERVER = "10.30.1.241"; //database server
        self::$DB_DATABASE = "matecat_sandbox"; //database name
        self::$DB_USER = "matecat"; //database login 
        self::$DB_PASS = "matecat01"; //databasepassword


        self::$LOG_REPOSITORY = self::$ROOT . "/storage/log_archive";
        self::$TEMPLATE_ROOT = self::$ROOT . "/lib/view";
        self::$MODEL_ROOT = self::$ROOT . '/lib/model';
        self::$CONTROLLER_ROOT = self::$ROOT . '/lib/controller';
        self::$UTILS_ROOT = self::$ROOT . '/lib/utils';

        self::$ENABLED_BROWSERS = array('chrome', 'firefox', 'safari');
        self::$CONVERSION_ENABLED = true;
        self::$ANALYSIS_WORDS_PER_DAYS = 3000;
        self::$BUILD_NUMBER = '0.3.0.1';
        self::$VOLUME_ANALYSIS_ENABLED = true;
        
        self::$RTL_LANGUAGES=array("he-IL");

        self::$SUPPORTED_FILE_TYPES = array(
            'Office' => array(
                'doc' => array(''),
                'dot' => array(''),
                'docx' => array(''),
                'dotx' => array(''),
                'docm' => array(''),
                'dotm' => array(''),
                'rtf' => array(''),
                'pdf' => array(''),
                'xls' => array(''),
                'xlt' => array(''),
                'xlsm' => array(''),
                'xlsx' => array(''),
                'xltx' => array(''),
                'pot' => array(''),
                'pps' => array(''),
                'ppt' => array(''),
                'potm' => array(''),
                'potx' => array(''),
                'ppsm' => array(''),
                'ppsx' => array(''),
                'pptm' => array(''),
                'pptx' => array(''),
                'odp' => array(''),
                'ods' => array(''),
                'odt' => array(''),
                'sxw' => array(''),
                'sxc' => array(''),
                'sxi' => array(''),
                'txt' => array(''),
                'csv' => array(''),
                'xml' => array('')
//                'vxd' => array("Try converting to XML")
            ),
            'Web' => array(
                'htm' => array(''),
                'html' => array(''),
                'xhtml' => array(''),
                'xml' => array('')
            ),
            "Interchange Formats" => array(
                'xliff' => array('default'),
                'sdlxliff' => array('default'),
                'ttx' => array(''),
                'itd' => array(''),
                'xlf' => array('default')
            ),
            "Desktop Publishing" => array(
                'fm' => array('', "Try converting to MIF"),
                'mif' => array(''),
                'inx' => array(''),
                'idml' => array(''),
                'icml' => array(''),
                'indd' => array('', "Try converting to INX"),
                'xtg' => array(''),
                'tag' => array(''),
                'xml' => array(''),
                'dita' => array('')
            ),
            "Localization" => array(
                'properties' => array(''),
                'rc' => array(''),
                'resx' => array(''),
                'xml' => array(''),
                'dita' => array(''),
                'sgml' => array(''),
                'sgm' => array('')
            )
        );

        //self::$DEFAULT_FILE_TYPES = 'xliff|sdlxliff|xlf';
        //self::$CONVERSION_FILE_TYPES = 'doc|dot|docx|dotx|docm|dotm|rtf|pdf|xls|xlsx|xlt|xltx|pot|pps|ppt|potm|potx|ppsm|ppsx|pptm|pptx|mif|inx|idml|icml|txt|csv|htm|html|xhtml|properties|odp|ods|odt|sxw|sxc|sxi|xtg|tag|itd|sgml|sgm|dll|exe|rc|ttx|resx|dita|fm|vxd|indd';
        //self::$CONVERSION_FILE_TYPES_PARTIALLY_SUPPORTED = '[{"format": "fm", "message": "Try converting to MIF"},{"format": "indd", "message": "Try converting to INX"},{"format": "vxd", "message": "Try converting to XML"}]';

        //if (!$this->initOK()) {
        //    throw new Exception("ERROR");
        //}
    }

}

?>
