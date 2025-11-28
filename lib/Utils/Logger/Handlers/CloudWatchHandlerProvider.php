<?php

namespace Utils\Logger\Handlers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use PhpNexus\Cwh\Handler\CloudWatch;
use Utils\Registry\AppConfig;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 20/11/25
 * Time: 18:19
 *
 */
class CloudWatchHandlerProvider implements ProviderInterface
{

    private static ?CloudWatchLogsClient $CLIENT = null;

    public function getHandlerClassName(): string
    {
        return CloudWatch::class;
    }

    public function getHandlerParams(string $name, array $configurationParams): array
    {
        $tags = json_decode($configurationParams['tags'] ?? 'null', true);
        if (!empty($tags)) {
            $configurationParams['tags'] = $tags;
        }

        /**
         * CloudWatchLogsClient $client,
         * string $group,
         * string $stream,
         * int | null $retention = 14,
         * int $batchSize = 10000,
         * array $tags = [],
         * int | string | Level $level = Level::Debug,
         * bool $bubble = true,
         * bool $createGroup = true,
         * bool $createStream = true,
         * int $rpsLimit = 0
         */
        return array_merge(
            [
                'client' => self::getClient(),
                'group' => 'matecat-' . (getenv('ENV') ?: 'local') . '-' . (explode('-', gethostname())[0] ?? 'base') . '-node',
                'stream' => pathinfo($name, PATHINFO_FILENAME),
                'retention' => 30,
                'batchSize' => AppConfig::$IS_DAEMON_INSTANCE ? 1 : 10000,
                'tags' => ['Project' => 'matecat'],
                'rpsLimit' => AppConfig::$IS_DAEMON_INSTANCE ? 50 : 0,
            ],
            $configurationParams
        );
    }

    private static function getClient(): CloudWatchLogsClient
    {
        if (empty(self::$CLIENT)) {
            // init the client
            $awsRegion = AppConfig::$AWS_REGION ?? 'eu-central-1';

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

    /**
     * @inheritDoc
     */
    public function setFormatter(AbstractProcessingHandler $handler): void
    {
        $handler->setFormatter(new JsonFormatter());
    }

}