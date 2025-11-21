<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 20/11/25
 * Time: 17:10
 *
 */
namespace Utils\Logger\Handlers\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Utils\Registry\AppConfig;

class CloudWatchClient
{
    private static ?CloudWatchLogsClient $CLIENT = null;

    public static function getClient(): CloudWatchLogsClient
    {
        if (empty(self::$CLIENT)) {
            // init the client
            $awsRegion = AppConfig::$AWS_REGION;

            $config = [
                'version' => 'latest',
                'region' => $awsRegion,
            ];

            if (null !== AppConfig::$AWS_ACCESS_KEY_ID and null !== AppConfig::$AWS_SECRET_KEY) {
                $config['credentials'] = [
                    'key' => AppConfig::$AWS_ACCESS_KEY_ID,
                    'secret' => AppConfig::$AWS_SECRET_KEY,
                ];
            }

            self::$CLIENT = new CloudWatchLogsClient($config);
        }

        return self::$CLIENT;
    }


}