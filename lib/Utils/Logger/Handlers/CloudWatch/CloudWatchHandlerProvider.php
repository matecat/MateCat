<?php

namespace Utils\Logger\Handlers\CloudWatch;

use PhpNexus\Cwh\Handler\CloudWatch;
use Utils\Logger\Handlers\ProviderInterface;
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
    public function getHandlerClassName(): string
    {
        return CloudWatch::class;
    }

    public function getHandlerParams(string $name): array
    {
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
        return [
            'client' => CloudWatchClient::getClient(),
            'group' => 'matecat-' . (getenv('ENV') ?: 'local') . '-' . (explode('-', gethostname())[0] ?? 'base') . '-node',
            'stream' => pathinfo($name, PATHINFO_FILENAME),
            'retention' => 30,
            'batchSize' => AppConfig::$IS_DAEMON_INSTANCE ? 1 : 10000,
            'tags' => ['Project' => 'matecat'],
            'rpsLimit' => AppConfig::$IS_DAEMON_INSTANCE ? 50 : 0,
        ];
    }

}