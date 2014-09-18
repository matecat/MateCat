<?php

set_time_limit( 0 );
define ( "BOM", "\xEF\xBB\xBF" );

include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";


class FileFormatConverter {

    private $ip; //current converter chosen for this job
    private $port = "8732"; //port the convertrs listen to
    private $toXliffFunction = "AutomationService/original2xliff"; //action string for the converters to convert to XLIFF
    private $fromXliffFunction = "AutomationService/xliff2original"; //action string for the converters to convert to original
    private $opt = array(); //curl options
    private $lang_handler; //object that exposes language utilities
    private $storage_lookup_map;

    private $conversionObject;

    /**
     * Set to true to send conversion failures report
     *
     * @var bool
     */
    public $sendErrorReport = true;

    public static $Storage_Lookup_IP_Map = array();
    public static $converters = array();

    /**
     * Set more curl option for conversion call ( fw/bw )
     *
     * @param array $options
     */
    public function setCurlOpt( $options = array() ) {
        $this->opt = array_merge( $this->opt, $options );
    }

    public function __construct() {

        if ( !class_exists( "INIT" ) ) {
            include_once( "../../inc/config.inc.php" );
            INIT::obtain();
        }
        $this->opt[ 'httpheader' ] = array( "Content-Type:multipart/form-data;charset=UTF-8" );
        $this->lang_handler        = Languages::getInstance();

        $this->conversionObject = new ArrayObject( array(
                'ip_machine'    => null,
                'ip_client'     => null,
                'path_name'     => null,
                'file_name'     => null,
                'path_backup'   => null,
                'direction'     => null,
                'error_message' => null,
                'src_lang'      => null,
                'trg_lang'      => null,
                'status'        => 'ok',
        ), ArrayObject::ARRAY_AS_PROPS );

        $db         = Database::obtain();
        $converters = $db->fetch_array( "SELECT ip_converter, ip_storage FROM converters WHERE status_active = 1 AND status_offline = 0" );

        foreach ( $converters as $converter_storage ) {
            self::$converters[ $converter_storage[ 'ip_converter' ] ]            = 1;
            self::$Storage_Lookup_IP_Map[ $converter_storage[ 'ip_converter' ] ] = $converter_storage[ 'ip_storage' ];
        }

//		self::$converters = array('10.11.0.98' => 1);//for debugging purposes
//		self::$Storage_Lookup_IP_Map = array('10.11.0.98' => '10.11.0.99');//for debugging purposes

        $this->storage_lookup_map = self::$Storage_Lookup_IP_Map;

    }

    //add UTF-8 BOM
    private function addBOM( $string ) {
        return BOM . $string;
    }

    //check if it has BOM
    private function hasBOM( $string ) {
        return ( substr( $string, 0, 3 ) == BOM );
    }

    //get a converter at random, weighted on number of CPUs per node
    private function pickRandConverter() {

        $converters_map = array();

        $tot_cpu = 0;
        foreach ( self::$converters as $ip => $cpu ) {
            $tot_cpu += $cpu;
            $converters_map = array_merge( $converters_map, array_fill( 0, $cpu, $ip ) );
        }

        //pick random
        $num = rand( 0, $tot_cpu - 1 );

        return $converters_map[ $num ];

    }

    /**
     * check top of a single node by ip
     *
     * @param $ip
     *
     * @return mixed
     */
    public static function checkNodeLoad( &$ip ) {

        $top       = 0;
        $result    = "";
        $processes = array();

        //since sometimes it can fail, try again util we get something meaningful
        $ch = curl_init( "$ip:8082" );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 2 ); //we can wait max 2 seconds

        while ( empty( $result ) || empty( $processes ) ) {

            $result     = curl_exec( $ch );
            $curl_errno = curl_errno( $ch );
            $curl_error = curl_error( $ch );

            $processes = json_decode( $result, true );

            //$curl_errno == 28 /* CURLE_OPERATION_TIMEDOUT */
            if ( $curl_errno > 0 ) {
                $top = 200; //exclude current converter by set it's top to an extreme large value
                break;
            }

        }

        //close
        curl_close( $ch );

        //sum up total machine load
        foreach ( $processes as $process ) {
            $top += @$process[ 0 ];
        }

        //zero load is impossible (at least, there should be the java monitor); try again
        if ( 0 == $top ) {
            log::doLog( "suspicious zero load for $ip, recursive call" );
            usleep( 500 * 1000 ); //200ms
            $top = self::checkNodeLoad( $ip );
        }

        return $top;
    }

    private function pickIdlestConverter() {
        //scan each server load
        foreach ( self::$converters as $ip => $weight ) {
            $load = self::checkNodeLoad( $ip );
            log::doLog( "load for $ip is $load" );
            //add load as numeric index to an array
            $loadList[ "" . ( 10 * (float)$load ) ] = $ip;
        }
        //sort to pick lowest
        ksort( $loadList, SORT_NUMERIC );

        //pick lowest
        $ip = array_shift( $loadList );

        return $ip;
    }

    public function getValidStorage() {
        return $this->storage_lookup_map[ $this->ip ];
    }

    private function extractUidandExt( &$content ) {
        $pattern = '|<file original=".+?([a-f\-0-9]{36}).+?\.(.*)".*?>|';
        $matches = array();
        preg_match( $pattern, $content, $matches );

        return array( $matches[ 1 ], $matches[ 2 ] );
    }

    private function is_assoc( $array ) {
        return is_array( $array ) AND (bool)count( array_filter( array_keys( $array ), 'is_string' ) );
    }

    private function __parseOutput( $res ) {
        $ret                = array();
        $ret[ 'isSuccess' ] = $res[ 'isSuccess' ];
        $is_success         = $res[ 'isSuccess' ];

        if ( !$is_success ) {

            $ret[ 'errorMessage' ]                 = $res[ 'errorMessage' ];
            $this->conversionObject->error_message = $res[ 'errorMessage' ];

            $backUp_dir                          = INIT::$STORAGE_DIR . '/conversion_errors/' . @$_COOKIE[ 'upload_session' ];
            $this->conversionObject->path_backup = $backUp_dir . "/" . $this->conversionObject->file_name;

            if ( !is_dir( $backUp_dir ) ) {
                mkdir( $backUp_dir, 0755, true );
            }

            $this->conversionObject->status = 'ko';

            //when Launched by CRON Script send Error Report is disabled
            if ( $this->sendErrorReport ) {
                @rename( $this->conversionObject->path_name, $this->conversionObject->path_backup );
                $this->__saveConversionErrorLog();
                $this->__notifyError();
            }

            return $ret;

        } else {
            $this->conversionObject->path_backup = $this->conversionObject->path_name;

            //when Launched by CRON Script send Error Report is disabled
            if ( $this->sendErrorReport ) {
                $this->__saveConversionErrorLog();
            }

        }

        if ( array_key_exists( "documentContent", $res ) ) {
            $res[ 'documentContent' ] = base64_decode( $res[ 'documentContent' ] );
        }

        /**
         * Avoid Not Recoverable Error
         * Cannot unset string offsets
         *
         * If $res is not an array but boolean or string
         *
         */
        if ( isset( $res[ 'errorMessage' ] ) ) {
            unset( $res[ 'errorMessage' ] );
        }

        return $res;
    }

    private function curl_post( $url, $data, $opt = array() ) {
        if ( !$this->is_assoc( $data ) ) {
            throw new Exception( "The input data to " . __FUNCTION__ . "must be an associative array", -1 );
        }

        if ( $this->checkOpenService( $url ) ) {

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_USERAGENT, "Matecat-Cattool/v" . INIT::$BUILD_NUMBER );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            //curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

            if ( $this->is_assoc( $opt ) and !empty( $opt ) ) {
                foreach ( $opt as $k => $v ) {

                    if ( stripos( $k, "curlopt_" ) === false or stripos( $k, "curlopt_" ) !== 0 ) {
                        $k = "curlopt_$k";
                    }
                    $const_name = strtoupper( $k );
                    if ( defined( $const_name ) ) {
                        curl_setopt( $ch, constant( $const_name ), $v );
                    }
                }
            }

            $output     = curl_exec( $ch );
            $curl_errno = curl_errno( $ch );
            $curl_error = curl_error( $ch );
            $info       = curl_getinfo( $ch );

            // Chiude la risorsa curl
            curl_close( $ch );

            if ( $curl_errno > 0 ) {
                $output = json_encode( array( "isSuccess" => false, "errorMessage" => $curl_error ) );
            }

        } else {
            $output = json_encode( array( "isSuccess" => false, "errorMessage" => "port closed" ) );
        }

        return $output;
    }

    public function convertToSdlxliff( $file_path, $source_lang, $target_lang, $chosen_by_user_machine = false ) {
        if ( !file_exists( $file_path ) ) {
            throw new Exception( "Conversion Error : the file <$file_path> not exists" );
        }
        $fileContent = file_get_contents( $file_path );
        $extension   = pathinfo( $file_path, PATHINFO_EXTENSION );
        $filename    = pathinfo( $file_path, PATHINFO_FILENAME );
        if ( strtoupper( $extension ) == 'TXT' ) {
            $encoding = mb_detect_encoding( $fileContent );
            if ( $encoding != 'UTF-8' ) {
                $fileContent = iconv( $encoding, "UTF-8//IGNORE", $fileContent );
            }

            if ( !$this->hasBOM( $fileContent ) ) {
                $fileContent = $this->addBOM( $fileContent );
            }
        }

        //get random name for temporary location
        $tmp_name = tempnam( "/tmp", "MAT_FW" );

        //write encoded file to temporary location
        file_put_contents( $tmp_name, ( $fileContent ) );

        //assign file pointer for POST
        $data[ 'documentContent' ] = "@$tmp_name";

        //flush memory
        unset( $fileContent );

        //assign converter
        if ( !$chosen_by_user_machine ) {
            $this->ip = $this->pickRandConverter();
        } else {
            $this->ip = $chosen_by_user_machine;
        }

        $url = "$this->ip:$this->port/$this->toXliffFunction";

        $data[ 'fileExtension' ] = $extension;
        $data[ 'fileName' ]      = "$filename.$extension";
        $data[ 'sourceLocale' ]  = $this->lang_handler->getLangRegionCode( $source_lang );
        $data[ 'targetLocale' ]  = $this->lang_handler->getLangRegionCode( $target_lang );

        log::doLog( $this->ip . " start conversion to xliff of $file_path" );
        $start_time = time();

        $this->conversionObject->ip_machine = $this->ip;
        $this->conversionObject->ip_client  = Utils::getRealIpAddr();
        $this->conversionObject->path_name  = $file_path;
        $this->conversionObject->file_name  = $data[ 'fileName' ];
        $this->conversionObject->direction  = 'fw';
        $this->conversionObject->src_lang   = $data[ 'sourceLocale' ];
        $this->conversionObject->trg_lang   = $data[ 'targetLocale' ];

        $curl_result = $this->curl_post( $url, $data, $this->opt );
        $end_time    = time();
        $time_diff   = $end_time - $start_time;
        log::doLog( $this->ip . " took $time_diff secs for $file_path" );

        $decode      = json_decode( $curl_result, true );
        $curl_result = null;
        $res         = $this->__parseOutput( $decode );

        //remove temporary file
        unlink( $tmp_name );


        return $res;
    }

    private function checkOpenService( $url ) {
        //default is failure
        $open = false;

        //get address only
        $url = substr( $url, 0, strpos( $url, ':' ) );

        //attempt to connect
        $connection = @fsockopen( $url, $this->port );
        if ( $connection ) {
            //success
            $open = true;
            //close port
            fclose( $connection );
        }

        return $open;
    }

    public function convertToOriginal( $xliffVector, $chosen_by_user_machine = false ) {

        $xliffContent = $xliffVector[ 'content' ];
        $xliffName    = $xliffVector[ 'out_xliff_name' ];

//        Log::dolog( $xliffName );

        //assign converter
        if ( !$chosen_by_user_machine ) {
            $this->ip = $this->pickRandConverter();
            $storage  = $this->getValidStorage();

            //add replace/regexp pattern because we have more than 1 replacement
            //http://stackoverflow.com/questions/2222643/php-preg-replace
            $xliffContent = self::replacedAddress( $storage, $xliffContent );
        } else {
            $this->ip = $chosen_by_user_machine;
        }

        $url = "$this->ip:$this->port/$this->fromXliffFunction";

        $uid_ext       = $this->extractUidandExt( $xliffContent );
        $data[ 'uid' ] = $uid_ext[ 0 ];

        //get random name for temporary location
        $tmp_name = tempnam( "/tmp", "MAT_BW" );

        //write encoded file to temporary location
        file_put_contents( $tmp_name, ( $xliffContent ) );


        //$data['xliffContent'] = $xliffContent;
        $data[ 'xliffContent' ] = "@$tmp_name";

        $this->conversionObject->ip_machine = $this->ip;
        $this->conversionObject->ip_client  = Utils::getRealIpAddr();
        $this->conversionObject->path_name  = $xliffVector[ 'out_xliff_name' ];
        $this->conversionObject->file_name  = pathinfo( $xliffVector[ 'out_xliff_name' ], PATHINFO_BASENAME );
        $this->conversionObject->direction  = 'bw';
        $this->conversionObject->src_lang   = $this->lang_handler->getLangRegionCode( $xliffVector[ 'source' ] );
        $this->conversionObject->trg_lang   = $this->lang_handler->getLangRegionCode( $xliffVector[ 'target' ] );

        log::doLog( $this->ip . " start conversion back to original" );
        $start_time  = time();
        $curl_result = $this->curl_post( $url, $data, $this->opt );
        $end_time    = time();
        $time_diff   = $end_time - $start_time;
        log::doLog( $this->ip . " took $time_diff secs" );

        $decode = json_decode( $curl_result, true );
        unset( $curl_result );
        $res = $this->__parseOutput( $decode );
        unset( $decode );


        return $res;
    }


    //http://stackoverflow.com/questions/2222643/php-preg-replace
    private static $Converter_Regexp = '/=\"\\\\\\\\10\.11\.0\.[1-9][13579]{1,2}\\\\tr/';

    /**
     * Replace the storage address in xliff content with the right associated storage ip
     *
     * @param $storageIP    string
     * @param $xliffContent string
     *
     * @return string
     */
    public static function replacedAddress( $storageIP, $xliffContent ) {
        return preg_replace( self::$Converter_Regexp, '="\\\\\\\\' . $storageIP . '\\\\tr', $xliffContent );
    }

    private function __notifyError() {

        $remote_user = ( isset( $_SERVER[ 'REMOTE_USER' ] ) ) ? $_SERVER[ 'REMOTE_USER' ] : "N/A";
        $link_file   = "http://" . $_SERVER[ 'SERVER_NAME' ] . "/" . INIT::$CONVERSIONERRORS_REPOSITORY_WEB . "/" . $_COOKIE[ 'upload_session' ] . "/" . rawurlencode( $this->conversionObject->file_name );
        $message     = "MATECAT : conversion error notifier\n\nDetails:
    - machine_ip : " . $this->conversionObject->ip_machine . "
    - client ip :  " . $this->conversionObject->ip_client . "
    - source :     " . $this->conversionObject->src_lang . "
    - target :     " . $this->conversionObject->trg_lang . "
    - client user (if any used) : $remote_user
    - direction : " . $this->conversionObject->direction . "
    - error : " . $this->conversionObject->error_message . "
    Download file clicking to $link_file
	";

        Utils::sendErrMailReport( $message );

    }

    private function __saveConversionErrorLog() {

        try {
            $_connection = new PDO( 'mysql:dbname=matecat_conversions_log;host=' . INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS,
                    array(
                            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                            PDO::ATTR_EMULATE_PREPARES   => false,
                            PDO::ATTR_ORACLE_NULLS       => true,
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
                    ) );
        } catch ( Exception $ex ) {
            Log::doLog( 'Unable to open database connection' );
            Log::doLog( $ex->getMessage() );

            return;
        }

        $data = $this->conversionObject->getArrayCopy();
        Log::doLog( $this->conversionObject );

        unset ( $data[ 'path_name' ] );
        unset ( $data[ 'file_name' ] );

        $data_keys         = implode( ", ", array_keys( $data ) );
        $data_values       = array_values( $data );
        $data_placeholders = implode( ", ", array_fill( 0, count( $data ), "?" ) );
        $query             = "INSERT INTO failed_conversions_log ($data_keys) VALUES ( $data_placeholders );";

        try {
            $sttmnt = $_connection->prepare( $query );
            $sttmnt->execute( $data_values );
        } catch ( PDOException $ex ) {
            Log::doLog( $ex->getMessage() );
        }


    }

}
