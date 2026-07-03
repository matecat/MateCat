<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 17.01
 *
 */

namespace Utils\AsyncTasks\Workers\Analysis;

use Exception;
use Predis\Client;
use Utils\Logger\LoggerFactory;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

/**
 * Class Analysis_Manager
 *
 * Should be the final class when daemons will be refactored
 *
 */
class Health
{

    public static function fastAnalysisIsRunning(Client $redisHandler): bool
    {
        $fastList = $redisHandler->srandmember(RedisKeys::FAST_PID_SET);

        return !empty($fastList);
    }

    public static function tmAnalysisIsRunning(Client $redisHandler): bool
    {
        return (bool)$redisHandler->get(RedisKeys::VOLUME_ANALYSIS_PID);
    }

    /**
     *
     * @return bool
     */
    public static function thereIsAMisconfiguration(?Client $redisClient = null): bool
    {
        try {
            $redisHandler = $redisClient ?? (new RedisHandler())->getConnection();

            return (AppConfig::$VOLUME_ANALYSIS_ENABLED && !self::fastAnalysisIsRunning($redisHandler) && !self::tmAnalysisIsRunning($redisHandler));
        } catch (Exception) {
            $msg = "****** No REDIS instances found. ******";
            LoggerFactory::doJsonLog($msg);

            return false;
        }
    }
}