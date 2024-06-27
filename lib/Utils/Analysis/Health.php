<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 17.01
 *
 */

namespace Analysis;

use Analysis\Queue\RedisKeys;
use INIT;
use Log;
use Predis\Client;
use RedisHandler;

/**
 * Class Analysis_Manager
 *
 * Should be the final class when daemons will be refactored
 *
 */
class Health {

    public static function fastAnalysisIsRunning( $redisHandler ): bool {

        /**
         * @var $redisHandler Client
         */

        $fastList = $redisHandler->srandmember( RedisKeys::FAST_PID_SET );

        return !empty( $fastList );

    }

    public static function tmAnalysisIsRunning( $redisHandler ): bool {

        /**
         * @var $redisHandler Client
         */

        return (bool)$redisHandler->get( RedisKeys::VOLUME_ANALYSIS_PID );

    }

    /**
     *
     * @return bool
     */
    public static function thereIsAMisconfiguration(): bool {

        try {
            $redisHandler = new RedisHandler();
            $redisHandler = $redisHandler->getConnection();
            return ( INIT::$VOLUME_ANALYSIS_ENABLED && !self::fastAnalysisIsRunning( $redisHandler ) && !self::tmAnalysisIsRunning( $redisHandler ) );
        } catch ( \Exception $ex ) {
            $msg = "****** No REDIS instances found. ******";
            Log::doJsonLog( $msg );

            return false;
        }

    }
}