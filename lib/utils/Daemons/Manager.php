<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 17.01
 * 
 */

/**
 * Class Daemons_Manager
 *
 * Should be the final class when daemons will refactored
 *
 */
class Daemons_Manager {

    public static function fastAnalysisIsRunning(){
        $fastPid = (int)file_get_contents( Constants_Daemons::PATH_TO_DAEMONS . DIRECTORY_SEPARATOR . Constants_Daemons::PID_FOLDER . DIRECTORY_SEPARATOR . Constants_Daemons::FAST_PID_FILE );
        return self::isRunningProcess( $fastPid );
    }

    public static function tmAnalysisIsRunning(){
        $tmPid = (int)file_get_contents( Constants_Daemons::PATH_TO_DAEMONS . DIRECTORY_SEPARATOR . Constants_Daemons::PID_FOLDER . DIRECTORY_SEPARATOR . Constants_Daemons::TM_MASTER_PID_FILE );
        return self::isRunningProcess( $tmPid );
    }

    public static function isRunningProcess( $pid ) {
        if ( file_exists( "/proc/$pid" ) ) {
            return true;
        }
        return false;
    }

    /**
     * WARNING on 2 frontend web server or in an architecture where the daemons runs in a place different from the web server
     * this should be put in a shared location ( memcache/redis/ntfs/mysql ) and a service should be
     * queried for know that number
     *
     * @return bool
     */
    public static function thereIsAMisconfiguration(){
        return ( INIT::$VOLUME_ANALYSIS_ENABLED && !Daemons_Manager::fastAnalysisIsRunning() && !Daemons_Manager::tmAnalysisIsRunning() );
    }

} 