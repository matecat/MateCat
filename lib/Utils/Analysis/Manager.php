<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 17.01
 * 
 */

/**
 * Class Analysis_Manager
 *
 * Should be the final class when daemons will refactored
 *
 */
class Analysis_Manager {

    public static function fastAnalysisIsRunning( $redisHandler ){

        /**
         * @var $redisHandler Predis\Client
         */

        $fastList = $redisHandler->lrange( Constants_AnalysisRedisKeys::FAST_PID_LIST, 0 , -1 );
        return !empty( $fastList );

    }

    public static function tmAnalysisIsRunning( $redisHandler ){

        /**
         * @var $redisHandler Predis\Client
         */

        return (bool)$redisHandler->get( Constants_AnalysisRedisKeys::VOLUME_ANALYSIS_PID );

    }

    /**
     *
     * @return bool
     */
    public static function thereIsAMisconfiguration(){

        try {
            $redisHandler = new Predis\Client( INIT::$REDIS_SERVERS );
            return ( INIT::$VOLUME_ANALYSIS_ENABLED && !Analysis_Manager::fastAnalysisIsRunning( $redisHandler ) && !Analysis_Manager::tmAnalysisIsRunning( $redisHandler ) );
        } catch ( Exception $ex ){
            $msg = "****** No REDIS instances found. ******";
            Log::doLog( $msg );
            return false;
        }

    }

} 