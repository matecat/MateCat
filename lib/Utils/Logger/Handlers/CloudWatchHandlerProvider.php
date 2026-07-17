<?php

namespace Utils\Logger\Handlers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use PhpNexus\Cwh\Handler\CloudWatch;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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

    public function __construct(?CloudWatchLogsClient $client = null)
    {
        if ($client !== null) {
            self::$CLIENT = $client;
        }
    }

    public function getHandlerClassName(): string
    {
        return CloudWatch::class;
    }

    /**
     * @param string $name
     * @param array<string, mixed> $configurationParams
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
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
         * ?CacheItemPoolInterface $cacheItemPool = null,
         */
        return array_merge(
            [
                'client' => $this->getClient(),
                'group' => 'matecat-' . (AppConfig::$ENV ?: 'local') . '-' . (explode('-', gethostname() ?: '')[0] ?? 'base') . '-node',
                'stream' => pathinfo($name, PATHINFO_FILENAME),
                'retention' => 30,
                'batchSize' => AppConfig::$IS_DAEMON_INSTANCE ? 1 : 10000,
                'tags' => ['Project' => 'matecat'],
                'rpsLimit' => AppConfig::$IS_DAEMON_INSTANCE ? 50 : 0,
                'cacheItemPool' => new FilesystemAdapter(),
                'cacheItemTtl' => 60 * 60 * 24
            ],
            $configurationParams
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getClientConfig(): array
    {
        $awsRegion = AppConfig::$AWS_REGION ?? 'eu-central-1';
        $config = [
            'version' => 'latest',
            'region' => $awsRegion,
        ];

        if (null !== AppConfig::$AWS_ACCESS_KEY_ID && null !== AppConfig::$AWS_SECRET_KEY) {
            $config['credentials'] = [
                'key' => AppConfig::$AWS_ACCESS_KEY_ID,
                'secret' => AppConfig::$AWS_SECRET_KEY,
            ];
        }

        return $config;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getClient(): CloudWatchLogsClient
    {
        if (self::$CLIENT === null) {
            self::$CLIENT = new CloudWatchLogsClient($this->getClientConfig());
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