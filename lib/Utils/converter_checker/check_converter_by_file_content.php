<?php
/**
 * Based on check_converter_by_file_content.php by Alberto Massidda
 * User: domenico
 * Date: 13/11/13
 * Time: 12.54
 *
 */

set_time_limit( 0 );

class ConvertersMonitor {

    //init params
    public $source_lang = "en-US";
    public $target_language = "it-IT";
    public $original_dir = 'original';
    public $converted_dir = 'converted';
    public $template_dir = 'expected';
    public $path;
    public $ROOT;
    public $db;

    protected static $ipLog = '';

    public $host_machine_map = array();
    public $resultSet = array();
    public $convertersTop = array();
    public $setForReboot = array();

    public $converterFactory;

    const selectAllLikeMatecat               = "SELECT SUM( status_active ) as in_pool FROM converters WHERE status_offline = 0";
    const selectAllNotOffline                = "SELECT * FROM converters WHERE status_offline = 0";
    const hideRow                            = "UPDATE converters SET status_active = 0 WHERE ip_converter = '%s' ";
    const insertLogRow                       = "INSERT INTO converters_log VALUES ( NULL , %u, CURRENT_TIMESTAMP, %u )";
    const selectLastTwoLogs_beforeUpdate     = "SELECT test_passed FROM converters_log
                                                    WHERE id_converter = '%s'
                                                    AND check_time <= '%s'
                                                    ORDER BY check_time DESC
                                                    LIMIT 2
                                               ";
    const selectLastTenLogs_beforeLastUpdate = "SELECT test_passed FROM converters_log
                                                    WHERE id_converter = '%s'
                                                    AND check_time <= '%s'
                                                    ORDER BY check_time DESC
                                                    LIMIT 9
                                                 ";
    const selectLogs_afterLastUpdate         = "SELECT test_passed, check_time FROM converters_log
                                                    WHERE id_converter = '%s'
                                                    AND check_time > '%s'
                                                    ORDER BY check_time DESC
                                               ";


    public function __construct() {

        $this->ROOT = realpath( dirname( __FILE__ ) . '/../../../' );

        //imports
        require_once $this->ROOT . '/inc/config.inc.php';
        Bootstrap::start();

        $this->db = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->db->connect();

        //init params
        $this->path      = $this->ROOT . '/lib/Utils/converter_checker';
        $resultSet       = $this->db->fetch_array( self::selectAllNotOffline );

        if( empty($resultSet) ){
            self::_prettyEcho( "------------------------------------" );
            self::_prettyEcho( "************* WARNING **************" );
            self::_prettyEcho( "------------------------------------" );
            $this->alertForEmptyPool();
            die(1);
        }

        foreach ( $resultSet as $conv ) {

//            self::$ipLog = $conv[ 'ip_converter' ];

            $this->resultSet[ $conv[ 'ip_converter' ] ] = $conv;

            $this->host_machine_map[ $conv[ 'ip_converter' ] ] = array(
                'ip_machine_host'   => $conv[ 'ip_machine_host' ],
                'machine_host_user' => $conv[ 'machine_host_user' ],
                'machine_host_pass' => $conv[ 'machine_host_pass' ],
                'instance_name'     => $conv[ 'instance_name' ],
            );

//            self::_prettyEcho( "Retrieving Processes Info on " . $conv[ 'ip_converter' ] );
//            $converter_json_top = self::getNodeProcessInfo( $conv[ 'ip_converter' ] );
//            if ( !empty( $converter_json_top ) ) {
//                $this->convertersTop[ $conv[ 'ip_converter' ] ] = array(
//                    'converter_load'     => $converter_json_top[ 0 ],
//                    'converter_json_top' => $converter_json_top[ 1 ]
//                );
//            }

        }

        $this->converterFactory = new FileFormatConverter();

    }

    protected static function _prettyEcho( $msg, $pad = 0 ) {
        echo "[ " . date( DATE_RFC822 ) . " | " . str_pad( self::$ipLog, 14, " ", STR_PAD_BOTH ) . " ] - " . str_pad( "", $pad, " ", STR_PAD_LEFT ) . $msg . "\n";
    }

    public function performCheck() {

        $lockHandler = fopen( "$this->path/_flock.lock", 'w+' );
        if( !flock( $lockHandler, LOCK_EX | LOCK_NB ) ){
            self::_prettyEcho( "*************************************************************" );
            self::_prettyEcho( "*********** Another Instance Running. SKIP CHECK. ***********" );
            self::_prettyEcho( "*************************************************************" );
            return;
        }

        //clean
        #self::_prettyEcho( "Removing tmp files" );
        $this->_deleteDir( "$this->path/$this->converted_dir/" );
        #self::_prettyEcho( "Recreating tmp dir" );
        mkdir( "$this->path/$this->converted_dir/", 0777, true );
        self::_prettyEcho( "" );

        foreach ( $this->resultSet as $dbRow ) {

            self::$ipLog = $dbRow[ 'ip_converter' ];

            self::_prettyEcho( "Start test on " . $dbRow[ 'ip_converter' ] );

            $this->_hideRow( $dbRow[ 'ip_converter' ] );

//
//            $this->_checkJavaProcessMonitor( $dbRow[ 'ip_converter' ] );
//
//            //Java Process Monitor Check Failure
//            if( in_array( $dbRow[ 'ip_converter' ], $this->setForReboot ) ){
//
//                $this->forceReboot( $dbRow[ 'ip_converter' ] );
//
//                self::_prettyEcho( "" );
//                self::_prettyEcho( "*********************" );
//                self::_prettyEcho( "**** TEST FAILED ****" );
//                self::_prettyEcho( "*********************" );
//                self::_prettyEcho( "---------------------------------" );
//                //next machine
//                continue;
//
//            }


            $failed_converter = $this->_performTestConversion( $dbRow[ 'ip_converter' ] );

            if ( !empty( $failed_converter ) ) {
                $this->_OnFailedConversion( $dbRow[ 'ip_converter' ] );
                self::_prettyEcho( "" );
                self::_prettyEcho( "*********************" );
                self::_prettyEcho( "**** TEST FAILED ****" );
                self::_prettyEcho( "*********************" );
            } else {
                $this->_OnSuccessfulConversion( $dbRow[ 'ip_converter' ] );
                self::_prettyEcho( "OK" );
            }

            self::_prettyEcho( "------------------------------------" );

        }

        $this->alertForEmptyPool();

        $this->performRebooting();

        fclose( $lockHandler );
        unlink( "$this->path/_flock.lock" );

        self::_prettyEcho( "----------- Released Lock ----------" );
        self::_prettyEcho( "------------------------------------" );

    }

    /**
     * Check for an Empty pool and send Alert Notification
     *
     */
    public function alertForEmptyPool(){

        $result = $this->db->query_first( self::selectAllLikeMatecat );
        $count = array_pop( $result );

        if( $count == 0 ){

            self::_prettyEcho( "************************************" );
            self::_prettyEcho( "********* CRITICAL STATUS **********" );
            self::_prettyEcho( "************************************" );
            self::_prettyEcho( "**** NO ACTIVE CONVERTERS FOUND ****" );
            self::_prettyEcho( "************************************" );
            self::_prettyEcho( "------------------------------------" );

            $msg = "<pre>\n\n ************************************"
                 . "\n ********* CRITICAL STATUS **********"
                 . "\n ************************************"
                  . "\n **** NO ACTIVE CONVERTERS FOUND ****"
                 . "\n ************************************"
                 . "\n</pre>";

            Utils::sendErrMailReport( $msg );

        } else {
            self::_prettyEcho( "ACTIVES: " . $count );
            self::_prettyEcho( "------------------------------------" );
        }


    }

    /**
     * Send Reboot Command to all Server in $this->setForReboot
     *
     */
    public function performRebooting( ){

        //to be safe, we could send reboot command twice
        $this->setForReboot = array_unique( $this->setForReboot );

        self::_prettyEcho( "****** Found " . count( $this->setForReboot ) . " to be rebooted ******" );

        foreach ( $this->setForReboot as $ip_to_reboot ) {

            $ret = $this->_rebootHost( $ip_to_reboot );

            $message = implode( "\n", $ret[ 1 ] );

            if ( $ret[ 0 ] == 0 ) {
                $status = "OK";
            } else {
                $status = "KO";
            }

            Utils::sendErrMailReport( "CONVERTER VM $ip_to_reboot locked.\n\n Trying to restart $status:  $message" );
            self::_prettyEcho( "> " . $ip_to_reboot . " down, executed the following:", 4 );
            foreach ( $ret[ 1 ] as $message ) {
                $chunk = explode( "\n", $message );
                foreach ( $chunk as $msg ) {
                    self::_prettyEcho( "> $msg", 4 );
                }
            }

        }

    }

    /**
     * Start/Stop/Reboot an Host
     *
     * @param        $ip_converter
     * @param string $command
     *
     * @return array
     */
    protected function _rebootHost( $ip_converter, $command = "restart" ) {

        $cmd = array();
        $ret = array();
        switch ( $command ) {
            case 'start':
                $cmd[ ] = "screen -d -m -S " . $this->host_machine_map[ $ip_converter ][ 'instance_name' ] . " VBoxHeadless --startvm '" . $this->host_machine_map[ $ip_converter ][ 'instance_name' ] . "'";
                break;
            case 'stop':
                $cmd[ ] = " VBoxManage controlvm '" . $this->host_machine_map[ $ip_converter ][ 'instance_name' ] . "' poweroff";
                break;
            case 'restart':
            default:
                $cmd[ ] = " VBoxManage controlvm '" . $this->host_machine_map[ $ip_converter ][ 'instance_name' ] . "' poweroff";
                $cmd[ ] = "screen -d -m -S " . $this->host_machine_map[ $ip_converter ][ 'instance_name' ] . " VBoxHeadless --startvm '" . $this->host_machine_map[ $ip_converter ][ 'instance_name' ] . "'";
        }

        self::_prettyEcho( "Connecting to " . $this->host_machine_map[ $ip_converter ][ 'ip_machine_host' ] );

        $conn       = ssh2_connect( $this->host_machine_map[ $ip_converter ][ 'ip_machine_host' ], 22 );
        $authResult = ssh2_auth_password( $conn, $this->host_machine_map[ $ip_converter ][ 'machine_host_user' ], $this->host_machine_map[ $ip_converter ][ 'machine_host_pass' ] );

        if ( !$authResult ) {
            $ret[ ] = "Incorrect password !!!\n";

            return array( -1, $ret );
        } else {
            $ret[ ] = "Password OK\n";

            self::_prettyEcho( "Sending Reboot Command to " . $ip_converter );

            foreach ( $cmd as $c ) {
                $stream = ssh2_exec( $conn, $c );
                stream_set_blocking( $stream, true );
                $stream_out  = ssh2_fetch_stream( $stream, SSH2_STREAM_STDIO );
                $ret_content = stream_get_contents( $stream_out );
                sleep(15);
                fclose( $stream );
                $ret[ ] = "command is $c";
                $ret[ ] = $ret_content;

            }
        }

        return array( 0, $ret );

    }

    /**
     * Set a converter as Not Active and flag it to be rebooted
     *
     * @param $ip_converter
     */
    public function forceReboot( $ip_converter ){

        $this->resultSet[ $ip_converter ][ 'status_reboot' ] = 1;
        $this->resultSet[ $ip_converter ][ 'status_active' ] = 0;
        $this->resultSet[ $ip_converter ][ 'last_update' ]   = 'NOW()';
        $this->db->update( 'converters', $this->resultSet[ $ip_converter ], " ip_converter = '$ip_converter' LIMIT 1" );

        $this->_setLogEntryFailure( $ip_converter );

        $this->setForReboot[ ] = $ip_converter;

        self::_prettyEcho( "> *** SET FOR REBOOT....", 4 );

    }

    protected function _setLogEntry( $ip_converter, $success ){
        $logRow = sprintf( self::insertLogRow, $this->resultSet[ $ip_converter ][ 'id' ], $success );
        $this->db->query( $logRow );
    }

    protected function _setLogEntrySuccess( $ip_converter ){
        $this->_setLogEntry( $ip_converter, 1 );
    }

    protected function _setLogEntryFailure( $ip_converter ){
        $this->_setLogEntry( $ip_converter, 0 );
    }

    protected function _OnSuccessfulConversion( $ip_converter ) {

        if ( $this->resultSet[ $ip_converter ][ 'status_active' ] == 1 ) {

            $this->resultSet[ $ip_converter ][ 'last_update' ] = 'NOW()';
            $this->db->update( 'converters', $this->resultSet[ $ip_converter ], " ip_converter = '$ip_converter' LIMIT 1" );

            $this->_setLogEntrySuccess( $ip_converter );


        } elseif ( $this->resultSet[ $ip_converter ][ 'status_active' ] == 0
            && $this->resultSet[ $ip_converter ][ 'status_reboot' ] == 1
        ) {

            $this->resultSet[ $ip_converter ][ 'status_active' ] = 1;
            $this->resultSet[ $ip_converter ][ 'status_reboot' ] = 0;
            $this->resultSet[ $ip_converter ][ 'last_update' ]   = 'NOW()';
            $this->db->update( 'converters', $this->resultSet[ $ip_converter ], " ip_converter = '$ip_converter' LIMIT 1" );

            $this->_setLogEntrySuccess( $ip_converter );

            self::_prettyEcho( "> *** REBOOT COMPLETED ", 4 );

        } elseif ( $this->resultSet[ $ip_converter ][ 'status_active' ] == 0
            && $this->resultSet[ $ip_converter ][ 'status_reboot' ] == 0
        ) {

            $selectLastTwoLogs_beforeUpdate = sprintf(
                self::selectLastTwoLogs_beforeUpdate,
                $this->resultSet[ $ip_converter ][ 'id' ],
                $this->resultSet[ $ip_converter ][ 'last_update' ]
            );

            $lastTwoLogs = $this->db->fetch_array( $selectLastTwoLogs_beforeUpdate );

            foreach ( $lastTwoLogs as $conversions ) {
                $lastTwoLogs[ 'history' ][ ] = $conversions[ 'test_passed' ];
            }
            $lastTwoLogs = array_unique( $lastTwoLogs[ 'history' ] );

            //there are only successes, allow for pooling
            if ( count( $lastTwoLogs ) == 1 && $lastTwoLogs[ 0 ] == 1 ) {
                $this->resultSet[ $ip_converter ][ 'status_active' ] = 1;

                $msg = "\n\n *** CONVERTER $ip_converter ACTIVATED -> " . $this->host_machine_map[ $ip_converter ][ 'instance_name' ];
                Utils::sendErrMailReport( $msg );

                self::_prettyEcho( "> *** PERMISSION GRANTED ", 4 );
            }

            $this->resultSet[ $ip_converter ][ 'last_update' ] = 'NOW()';
            $this->db->update( 'converters', $this->resultSet[ $ip_converter ], " ip_converter = '$ip_converter' LIMIT 1" );

            $this->_setLogEntrySuccess( $ip_converter );

        }

    }

    protected function _OnFailedConversion( $ip_converter ) {

        if ( $this->resultSet[ $ip_converter ][ 'status_active' ] == 1 ) {

            $this->resultSet[ $ip_converter ][ 'status_active' ] = 0;
            $this->resultSet[ $ip_converter ][ 'last_update' ]   = 'NOW()';
            $this->db->update( 'converters', $this->resultSet[ $ip_converter ], " ip_converter = '$ip_converter' LIMIT 1" );

            $this->_setLogEntryFailure( $ip_converter );

            $msg = "\n\n *** CONVERTER $ip_converter REMOVED FROM POOL -> " . $this->host_machine_map[ $ip_converter ][ 'instance_name' ]
                . "\n         - instance_name:   " . $this->host_machine_map[ $ip_converter ][ 'instance_name' ]
                . "\n         - ip_machine_host: " . $this->host_machine_map[ $ip_converter ][ 'ip_machine_host' ]
                . "\n";

            Utils::sendErrMailReport( $msg );

            self::_prettyEcho( "> *** REMOVED FROM POOL", 4 );

        } elseif ( $this->resultSet[ $ip_converter ][ 'status_active' ] == 0
            && $this->resultSet[ $ip_converter ][ 'status_reboot' ] == 0
        ) {

            $selectLastTenLogs_beforeLastUpdate = sprintf(
                self::selectLastTenLogs_beforeLastUpdate,
                $this->resultSet[ $ip_converter ][ 'id' ],
                $this->resultSet[ $ip_converter ][ 'last_update' ]
            );

            $_lastTenLogs = $this->db->fetch_array( $selectLastTenLogs_beforeLastUpdate );

            $lastTenLogs = array();
            foreach ( $_lastTenLogs as $conversions ) {
                $lastTenLogs[ 'history' ][ ] = $conversions[ 'test_passed' ];
            }
            $lastTenLogs = array_unique( $lastTenLogs[ 'history' ] );

            //there are only failures
            if ( count( $lastTenLogs ) == 1 && $lastTenLogs[ 0 ] == 0 && count( $_lastTenLogs ) == 9 ) {
                //set Reboot status and set for reboot
                $this->resultSet[ $ip_converter ][ 'status_reboot' ] = 1;
                $this->setForReboot[ ]                               = $ip_converter;
                self::_prettyEcho( "> *** SET FOR REBOOT....", 4 );
            }

            $this->resultSet[ $ip_converter ][ 'status_active' ] = 0;
            $this->resultSet[ $ip_converter ][ 'last_update' ]   = 'NOW()';
            $this->db->update( 'converters', $this->resultSet[ $ip_converter ], " ip_converter = '$ip_converter' LIMIT 1" );

            $this->_setLogEntryFailure( $ip_converter );

        } elseif ( $this->resultSet[ $ip_converter ][ 'status_active' ] == 0
            && $this->resultSet[ $ip_converter ][ 'status_reboot' ] == 1
        ) {

            $this->_checkForRebootFailed( $ip_converter );

            $this->_setLogEntryFailure( $ip_converter );

        }

    }

    protected function _hideRow( $ip_converter ) {

        $hideRow = sprintf( self::hideRow, $ip_converter );

        //hide row to matecat
        $this->db->query( $hideRow );
        self::_prettyEcho( "Hidden Row " . $ip_converter );

    }

    /**
     * This check for reboot status, if it is at least the third check failed after a reboot command,
     * it return a failure
     *
     * @param $ip_converter
     *
     * @return mixed
     */
    protected function _checkForRebootFailed( $ip_converter ){

        $selectLogs_afterLastUpdate = sprintf(
            self::selectLogs_afterLastUpdate,
            $this->resultSet[ $ip_converter ][ 'id' ],
            $this->resultSet[ $ip_converter ][ 'last_update' ]
        );

        $failedConversionsAfterRebootLogs = $this->db->fetch_array( $selectLogs_afterLastUpdate );

        //if there are failed conversions test after the reboot time ( long reboot )
        if( count( $failedConversionsAfterRebootLogs ) != 0 ){

            $rebootTime             = new DateTime( $this->resultSet[ $ip_converter ][ 'last_update' ] );
            $thisTimeFailure = new DateTime();

            //if this failure happened 10 minutes after reboot time
            if( $thisTimeFailure->modify('-10 minutes') >= $rebootTime ){

                self::_prettyEcho( "> *** FAILED REBOOT FOUND....", 4 );

                $msg = "\n\n *** FAILED REBOOT STATUS FOUND FOR CONVERTER $ip_converter -> " . $this->host_machine_map[ $ip_converter ][ 'instance_name' ]
                        . "\n         - instance_name:   " . $this->host_machine_map[ $ip_converter ][ 'instance_name' ]
                        . "\n         - ip_machine_host: " . $this->host_machine_map[ $ip_converter ][ 'ip_machine_host' ]
                        . "\n"
                        . "\n"
                        . "\n *** WARNING: THIS MACHINE IS SET AS OFFLINE, WILL NEVER PUT BACK IN POOL ***";

                //send Alert report
                Utils::sendErrMailReport( $msg );

                self::_prettyEcho( "> *** MESSAGE SENT....", 4 );

                $this->resultSet[ $ip_converter ][ 'status_active' ]  = 0;
                $this->resultSet[ $ip_converter ][ 'status_reboot' ]  = 0;
                $this->resultSet[ $ip_converter ][ 'status_offline' ] = 1;
                $this->db->update( 'converters', $this->resultSet[ $ip_converter ], " ip_converter = '$ip_converter' LIMIT 1" );

                return $ip_converter;

            }

        }

    }
//
//    protected function _checkJavaProcessMonitor( $ip_converter ){
//
//        if ( !isset( $this->convertersTop[ $ip_converter ] ) ) {
//            self::_prettyEcho( "> Request service not available on port 8082, ProcessMonitor Down. Skip Check. Set For Reboot.", 4 );
//
//            //is this the third check after a reboot command?
//            //there is a reboot failure status??
//            //reboot again
//            if( $this->_checkForRebootFailed( $ip_converter ) ) {
//                $this->setForReboot[ ] = $ip_converter;
//            }
//
//        } else {
//
//            $winword_total_load = 0;
//            foreach( $this->convertersTop[ $ip_converter ]['converter_json_top'] as $process ){
//                if( stripos( $process[4], 'WINWORD.EXE' ) !== FALSE ){
//                    $winword_total_load += $process[0];
//                }
//            }
//
//            if( $winword_total_load > 60 ){
//                self::_prettyEcho( "> *** Found harmful instance, SET FOR REBOOT....", 4 );
//                $this->setForReboot[ ] = $ip_converter;
//            }
//
//            //encode in json format for store
//            $this->convertersTop[ $ip_converter ]['converter_json_top'] = json_encode( $this->convertersTop[ $ip_converter ]['converter_json_top'] );
//
//        }
//
//    }

    protected function _performTestConversion( $ip_converter ) {

        self::_prettyEcho( "> Attempt to convert files to detect service failures", 4 );

        //get files for test
        $iterator = new DirectoryIterator( "$this->path/$this->original_dir/" );

        /**
         * @var $fileInfo DirectoryIterator
         */
        foreach ( $iterator as $fileInfo ) {

            if ( $fileInfo->isDot() || $fileInfo->isDir() ) {
                continue;
            }

            self::_prettyEcho( "> Sending " . $fileInfo->getPathname(), 4 );

            $t_start = microtime( true );

            //get extension
            //PHP 5.2 LACK THIS METHOD .... ADDED IN PHP 5.3.6 ....
            //$ext = $fileInfo->getExtension();
            $ext = pathinfo( $fileInfo->getFilename(), PATHINFO_EXTENSION );

            //get path
            $file_path = $fileInfo->getPathname();

            $this->converterFactory->sendErrorReport = false;

            //10 seconds of timeout, average time is less than 5 seconds
            $this->converterFactory->setCurlOpt( array( 'CURLOPT_TIMEOUT' => 20 ) );

            self::_prettyEcho( "> Trying conversion on " . $ip_converter, 4 );

            $convertResult = $this->converterFactory->convertToSdlxliff( $file_path, $this->source_lang, $this->target_language, $ip_converter );

            $elapsed_time = microtime( true ) - $t_start;


            if ( !$convertResult[ 'isSuccess' ] ) {
                self::_prettyEcho( "> Conversion failed in " . $elapsed_time . " seconds because of " . $convertResult[ 'errorMessage' ], 4 );

                return $ip_converter;
            }

            self::_prettyEcho( "> Done in " . $elapsed_time . " seconds", 4 );
            self::_prettyEcho( "> Performing content check", 4 );

            if ( !file_exists( "$this->path/$this->converted_dir/$ip_converter" ) ) {
                mkdir( "$this->path/$this->converted_dir/$ip_converter", 0777, true );
            }

            //now not needed
            //file_put_contents( "$this->path/$this->converted_dir/$ip_converter/{$fileInfo->getFilename()}.sdlxliff", $convertResult[ 'xliffContent' ] );

            $template_xliffContent = file_get_contents( "$this->path/$this->template_dir/{$fileInfo->getFilename()}.sdlxliff" );


            //compare files: file lenght is weak, but necessary (because transunit-ids change and file blob changes from machine to machine) and sufficient (the text should really be the same)

            //get lenghts
            $template_len  = strlen( $template_xliffContent );
            $converted_len = strlen( $convertResult[ 'xliffContent' ] );

            self::_prettyEcho( "> Original " . $fileInfo->getFilename() . ".sdlxliff Expected Length: " . $template_len, 4 );
            self::_prettyEcho( "> Received " . $fileInfo->getFilename() . ".sdlxliff Obtained Length: " . $converted_len, 4 );

            $max = max( $template_len, $converted_len );
            $min = min( $template_len, $converted_len );

            //compare distance
            $diff = $min / $max;

            if ( $diff < 0.97 ) {
                self::_prettyEcho( "> Size Mismatch, conversion is failed...", 4 );

                //file differ too much, conversion failed
                return $ip_converter;
            }

            return null;

        }

    }

//    /**
//     * check process list of a single node by ip
//     *
//     * @param $ip
//     *
//     * @return mixed
//     */
//    public static function getNodeProcessInfo( &$ip ) {
//
//        $outData   = array();
//        $processes = array();
//        $top       = 0;
//
//        //since sometimes it can fail, try again util we get something meaningful
//        $ch = curl_init( "$ip:8082" );
//        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
//        curl_setopt( $ch, CURLOPT_TIMEOUT, 5 ); //we can wait max 5 seconds per try
//
//        $trials = 0;
//        while ( $trials < 3 ) {
//
//            $result     = curl_exec( $ch );
//            $curl_errno = curl_errno( $ch );
//            $curl_error = curl_error( $ch );
//
//            $processes = json_decode( $result, true );
//
//            if ( empty( $result ) || empty( $processes ) ) {
//                self::_prettyEcho( "> Failed to get process list, re-try", 4 );
//                $trials++;
//                usleep( 500 * 1000 );
//            } else {
//                break;
//            }
//
//        }
//
//        //close
//        curl_close( $ch );
//
//        if ( !empty( $result ) && !empty( $processes ) ) {
//
//            //sum up total machine load
//            foreach ( $processes as $process ) {
//                $top += @$process[ 0 ];
//            }
//
//            //zero load is impossible (at least, there should be the java monitor); try again
//            if ( 0 == $top ) {
//                log::doLog( "> Suspicious zero load for $ip, recursive call", 4 );
//                usleep( 500 * 1000 ); //500ms
//                $outData = self::getNodeProcessInfo( $ip );
//            } else {
//                $outData = array( 10 * (float)$top, $processes );
//                self::_prettyEcho( "> Found a top of: " . 10 * (float)$top, 4 );
//            }
//
//        }
//
//        return $outData;
//
//    }

    protected static function _deleteDir( $dirPath ) {

        $iterator = new DirectoryIterator( $dirPath );

        foreach ( $iterator as $fileInfo ) {
            if ( $fileInfo->isDot() ) {
                continue;
            }
            if ( $fileInfo->isDir() ) {
                self::_deleteDir( $fileInfo->getPathname() );
            } else {
                unlink( $fileInfo->getPathname() );
            }
        }
        rmdir( $iterator->getPath() );

    }

}

$test = new ConvertersMonitor();
$test->performCheck();
