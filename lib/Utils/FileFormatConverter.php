<?php

set_time_limit( 0 );
define ( "BOM", "\xEF\xBB\xBF" );

class FileFormatConverter {

    private $ip; //current converter chosen for this job
    private $port = "8732"; //port the convertrs listen to
    const toXliffFunction = "convert"; //action string for the converters to convert to XLIFF
    const fromXliffFunction = "derived"; //action string for the converters to convert to original
    const testFunction = "test"; // action check connection
    private $opt = array(); //curl options
    private $lang_handler; //object that exposes language utilities
    private $storage_lookup_map;
    private $segmentation_rule;

    private $conversionObject;

    /**
     * Set to true to send conversion failures report
     *
     * @var bool
     */
    public $sendErrorReport = true;

    public static $Storage_Lookup_IP_Map = array();
    public static $converters = array();
    public static $converter2segmRule = array();

    //http://stackoverflow.com/questions/2222643/php-preg-replace
    private static $Converter_Regexp = '/=\"\\\\\\\\10\.11\.0\.[1-9][13579]{1,2}\\\\tr/';

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
            Bootstrap::start();
        }
        $this->opt[ 'httpheader' ] = array( "Content-Type:multipart/form-data;charset=UTF-8" );
        $this->lang_handler        = Langs_Languages::getInstance();

        $this->conversionObject = new ArrayObject( array(
                'ip_machine'      => null,
                'ip_client'       => null,
                'path_name'       => null,
                'file_name'       => null,
                'path_backup'     => null,
                'file_size'       => 0,
                'direction'       => null,
                'error_message'   => null,
                'src_lang'        => null,
                'trg_lang'        => null,
                'status'          => 'ok',
                'conversion_time' => 0
        ), ArrayObject::ARRAY_AS_PROPS );

        $db         = Database::obtain();
        $converters = $db->fetch_array( "SELECT ip_converter, cpu_weight, ip_storage, segmentation_rule FROM converters WHERE status_active = 1 AND status_offline = 0" );

        foreach ( $converters as $converter_storage ) {
            self::$converters[ $converter_storage[ 'ip_converter' ] ]            = $converter_storage[ 'cpu_weight' ];
            self::$Storage_Lookup_IP_Map[ $converter_storage[ 'ip_converter' ] ] = $converter_storage[ 'ip_storage' ];
            self::$converter2segmRule[ $converter_storage[ 'ip_converter' ] ]    = $converter_storage[ 'segmentation_rule' ];
        }

//        self::$converters = array('10.11.0.98' => 1);//for debugging purposes
//        self::$Storage_Lookup_IP_Map = array('10.11.0.98' => '10.11.0.99');//for debugging purposes

        $this->storage_lookup_map = self::$Storage_Lookup_IP_Map;

        // Load matecat converter IP and port
        $this->ip = INIT::$CONVERTER_SERVER_ADDRESS;
        $this->port = INIT::$CONVERTER_SERVER_PORT;

    }

    /*
    //add UTF-8 BOM
    public function addBOM( $string ) {
        return BOM . $string;
    }

    //remove UTF-8 BOM
    public function stripBOM( $string, $utf = 8 ) {
        //depending on encoding, different slices are to be cut
        switch ( $utf ) {
            case 16:
                $string = substr( $string, 2 );
                break;
            case 32:
                $string = substr( $string, 4 );
                break;
            case 8:
            default:
                $string = substr( $string, 3 );
                break;
        }

        return $string;
    }

    //check if it has BOM
    public function hasBOM( $string ) {
        return ( substr( $string, 0, 3 ) == BOM );
    }
    */

    //get a converter at random, weighted on number of CPUs per node
    private function pickRandConverter( $segm_rule = null ) {

        $converters_map = array();

        $tot_cpu = 0;
        foreach ( self::$converters as $ip => $cpu ) {

            if ( self::$converter2segmRule[ $ip ] == $segm_rule ) {
                $tot_cpu += $cpu;
                $converters_map = array_merge( $converters_map, array_fill( 0, $cpu, $ip ) );
            }

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
        preg_match( $pattern, substr( $content, 0, 2048 ), $matches );

        return array( $matches[ 1 ], $matches[ 2 ] );
    }

    private function is_assoc( $array ) {
        return is_array( $array ) AND (bool)count( array_filter( array_keys( $array ), 'is_string' ) );
    }

    private function __parseOutput( $res, $conversionObject = array() ) {

        if ( empty( $conversionObject ) ) {
            $conversionObject = $this->conversionObject;
        }

        $ret                = array();
        $ret[ 'isSuccess' ] = $res[ 'isSuccess' ];
        $is_success         = $res[ 'isSuccess' ];

        if ( !$is_success ) {

            $ret[ 'errorMessage' ]           = $res[ 'errorMessage' ];
            $conversionObject->error_message = $res[ 'errorMessage' ];

            $backUp_dir                    = INIT::$STORAGE_DIR . '/conversion_errors/' . @$_COOKIE[ 'upload_session' ];
            $conversionObject->path_backup = $backUp_dir . "/" . $conversionObject->file_name;

            if ( !is_dir( $backUp_dir ) ) {
                mkdir( $backUp_dir, 0755, true );
            }

            $conversionObject->status = 'ko';

            //when Launched by CRON Script send Error Report is disabled
            if ( $this->sendErrorReport ) {
                @rename( $conversionObject->path_name, $conversionObject->path_backup );
                $this->__saveConversionErrorLog( $conversionObject );
                $this->__notifyError( $conversionObject );
            }

            return $ret;

        } else {
            $conversionObject->path_backup = $conversionObject->path_name;

            //when Launched by CRON Script send Error Report is disabled
            if ( $this->sendErrorReport ) {
                $this->__saveConversionErrorLog( $conversionObject );
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


        if ( $this->checkOpenService() ) {

            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_setopt( $ch, CURLOPT_USERAGENT, INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            //curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt( $ch, CURLOPT_POST, true );
            @curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

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
        }
        else {
            $output = json_encode( array( "isSuccess" => false, "errorMessage" => "Internal connection issue. Try converting it again." ) );
        }

        return $output;
    }

    public function convertToSdlxliff( $file_path, $source_lang, $target_lang, $chosen_by_user_machine = false, $segm_rule = null ) {
        if ( !file_exists( $file_path ) ) {
            throw new Exception( "Conversion Error : the file <$file_path> not exists" );
        }

        $filename    = pathinfo( $file_path, PATHINFO_FILENAME );
        $data = array('file'=>"@$file_path");

        //assign converter
        //if ( !$chosen_by_user_machine ) {
        //    $this->ip = $this->pickRandConverter();
        //} else {
        //    $this->ip = $chosen_by_user_machine;
        //}

        $sourceLocale = $this->lang_handler->getLangRegionCode( $source_lang );
        $targetLocale = $this->lang_handler->getLangRegionCode( $target_lang );

        $url = "http://{$this->ip}:{$this->port}/".self::toXliffFunction."/$sourceLocale/$targetLocale";

        log::doLog( $this->ip . " start conversion to xliff of $file_path" );

        $start_time  = microtime( true );
        $curl_result = $this->curl_post( $url, $data, $this->opt );
        $end_time    = microtime( true );

        $time_diff = $end_time - $start_time;
        log::doLog( $this->ip . " took $time_diff secs for $file_path" );

        $this->conversionObject->ip_machine      = $this->ip;
        $this->conversionObject->ip_client       = Utils::getRealIpAddr();
        $this->conversionObject->path_name       = $file_path;
        $this->conversionObject->file_name       = $filename;
        $this->conversionObject->direction       = 'fw';
        $this->conversionObject->src_lang        = $sourceLocale;
        $this->conversionObject->trg_lang        = $targetLocale;
        $this->conversionObject->file_size       = filesize($file_path);
        $this->conversionObject->conversion_time = $time_diff;

        $decode      = json_decode( $curl_result, true );
        $curl_result = null;
        $res         = $this->__parseOutput( $decode );

        return $res;
    }

    private function checkOpenService() {
        $url = "http://{$this->ip}:{$this->port}/".self::testFunction;
        $cl = curl_init($url);
        curl_setopt($cl,CURLOPT_CONNECTTIMEOUT,3);
        curl_setopt($cl,CURLOPT_HEADER,true);
        curl_setopt($cl,CURLOPT_NOBODY,true);
        curl_setopt($cl,CURLOPT_RETURNTRANSFER,true);
        curl_exec($cl);
        $httpcode = curl_getinfo($cl, CURLINFO_HTTP_CODE);
        curl_close($cl);
        return $httpcode >= 200 && $httpcode < 300;
    }

    public function convertToOriginal( $xliffVector, $chosen_by_user_machine = false ) {

        $xliffContent = $xliffVector[ 'content' ];
        //$xliffName    = $xliffVector[ 'out_xliff_name' ];

        //        Log::dolog( $xliffName );

        /*
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
        */

        $url = "http://{$this->ip}:{$this->port}/".self::fromXliffFunction;

        //$uid_ext       = $this->extractUidandExt( $xliffContent );
        //$data[ 'uid' ] = $uid_ext[ 0 ];

        //get random name for temporary location
        $tmp_name = tempnam( "/tmp", "MAT_BW" );

        //write encoded file to temporary location
        $fileSize = file_put_contents( $tmp_name, ( $xliffContent ) );


        //$data['xliffContent'] = $xliffContent;
        $data = array( 'file' => "@$tmp_name" );

        log::doLog( $this->ip . " start conversion back to original" );
        $start_time = microtime( true );

        //TODO: this helper doesn't help!
        //How TODO: create a resource handler e return it, so it can be added to a MultiCurl Handler instance
        $curl_result = $this->curl_post( $url, $data, $this->opt );
        $end_time    = microtime( true );
        $time_diff   = $end_time - $start_time;
        log::doLog( $this->ip . " took $time_diff secs" );

        $this->conversionObject->ip_machine      = $this->ip;
        $this->conversionObject->ip_client       = Utils::getRealIpAddr();
        $this->conversionObject->path_name       = $xliffVector[ 'out_xliff_name' ];
        $this->conversionObject->file_name       = pathinfo( $xliffVector[ 'out_xliff_name' ], PATHINFO_BASENAME );
        $this->conversionObject->direction       = 'bw';
        $this->conversionObject->src_lang        = $this->lang_handler->getLangRegionCode( $xliffVector[ 'source' ] );
        $this->conversionObject->trg_lang        = $this->lang_handler->getLangRegionCode( $xliffVector[ 'target' ] );
        $this->conversionObject->file_size       = $fileSize;
        $this->conversionObject->conversion_time = $time_diff;

        $decode = json_decode( $curl_result, true );
        unset( $curl_result );
        $res = $this->__parseOutput( $decode );
        unset( $decode );

	//remove temporary file
	unlink($tmp_name);

        return $res;
    }

    public function multiConvertToOriginal( $xliffVector_array, $chosen_by_user_machine = false ) {

        if ( empty( $xliffVector_array ) ) {
            return array();
        }

        $multiCurlObj = new MultiCurlHandler();

        $conversionObjects = array();

        $temporary_files = array();

        //iterate files.
        //For each file prepare a curl resource
        foreach ( $xliffVector_array as $id_file => $xliffVector ) {

            $xliffContent = $xliffVector[ 'documentContent' ];

            //assign converter
            //if ( !$chosen_by_user_machine ) {
            //    $this->ip = $this->pickRandConverter();
            //    $storage  = $this->getValidStorage();

                //add replace/regexp pattern because we have more than 1 replacement
                //http://stackoverflow.com/questions/2222643/php-preg-replace
            //    $xliffContent = self::replacedAddress( $storage, $xliffContent );
            //} else {
            //    $this->ip = $chosen_by_user_machine;
            //}

            $url = "http://{$this->ip}:{$this->port}/".self::fromXliffFunction;

            //$uid_ext       = $this->extractUidandExt( $xliffContent );
            //$data[ 'uid' ] = $uid_ext[ 0 ];

            //get random name for temporary location
            $tmp_name           = tempnam( "/tmp", "MAT_BW" );
            $temporary_files[ ] = $tmp_name;

            //write encoded file to temporary location
            $fileSize = file_put_contents( $tmp_name, ( $xliffContent ) );

            $data = array( 'file' => "@$tmp_name" );
            $xliffName              = $xliffVector[ 'out_xliff_name' ];

            //prepare conversion object
            $this->conversionObject->ip_machine = $this->ip;
            $this->conversionObject->ip_client  = Utils::getRealIpAddr();
            $this->conversionObject->path_name  = $xliffVector[ 'out_xliff_name' ];
            $this->conversionObject->file_name  = pathinfo( $xliffName, PATHINFO_BASENAME );
            $this->conversionObject->direction  = 'bw';
            $this->conversionObject->src_lang   = $this->lang_handler->getLangRegionCode( $xliffVector[ 'source' ] );
            $this->conversionObject->trg_lang   = $this->lang_handler->getLangRegionCode( $xliffVector[ 'target' ] );
            $this->conversionObject->file_size  = $fileSize;

            $conversionObjects[ $id_file ] = clone( $this->conversionObject );

            $options = array(
                    CURLOPT_URL            => $url,
                    CURLOPT_HEADER         => 0,
                    CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $data,
                    CURLOPT_HTTPHEADER     => $this->opt[ 'httpheader' ]
            );

            $multiCurlObj->createResource( $url, $options, $id_file );
        }

        //Perform curl
        Log::doLog( "multicurl_start" );
        $multiCurlObj->multiExec();
        Log::doLog( "multicurl_end" );

        $multiInfo      = $multiCurlObj->getAllInfo();
        $multiResponses = $multiCurlObj->getAllContents();

        //decode response and return the result
        foreach ( $multiResponses as $hash => $json ) {
            $multiResponses[ $hash ]                     = json_decode( $json, true );
            $conversionObjects[ $hash ]->conversion_time = $multiInfo[ $hash ][ 'curlinfo_total_time' ];
            $multiResponses[ $hash ]                     = $this->__parseOutput( $multiResponses[ $hash ], $conversionObjects[ $hash ] );
        }

        //remove temporary files
        foreach ( $temporary_files as $temp_name ) {
            unlink( $temp_name );
        }

        return $multiResponses;
    }


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

    private function __notifyError( $conversionObject ) {

        $remote_user = ( isset( $_SERVER[ 'REMOTE_USER' ] ) ) ? $_SERVER[ 'REMOTE_USER' ] : "N/A";
        $link_file   = "http://" . $_SERVER[ 'SERVER_NAME' ] . "/" . INIT::$CONVERSIONERRORS_REPOSITORY_WEB . "/" . $_COOKIE[ 'upload_session' ] . "/" . rawurlencode( $conversionObject->file_name );
        $message     = "MATECAT : conversion error notifier\n\nDetails:
			- machine_ip : " . $conversionObject->ip_machine . "
			- client ip :  " . $conversionObject->ip_client . "
			- source :     " . $conversionObject->src_lang . "
			- target :     " . $conversionObject->trg_lang . "
			- client user (if any used) : $remote_user
										   - direction : " . $conversionObject->direction . "
																- error : " . $conversionObject->error_message . "
																				  Download file clicking to $link_file
																							  ";

        Utils::sendErrMailReport( $message );

    }

    private function __saveConversionErrorLog( $conversionObject ) {

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

        $data = $conversionObject->getArrayCopy();
        Log::doLog( $conversionObject );

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
