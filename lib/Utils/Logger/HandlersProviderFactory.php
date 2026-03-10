<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 20/11/25
 * Time: 18:14
 *
 */

namespace Utils\Logger;

use Throwable;
use Utils\Logger\Handlers\CloudWatchHandlerProvider;
use Utils\Logger\Handlers\ElasticSearchHandlerProvider;
use Utils\Logger\Handlers\ProviderInterface;
use Utils\Logger\Handlers\StreamHandlerProvider;
use Utils\Registry\AppConfig;

/**
 * Factory class for managing and creating handler instances dynamically based on configuration and provided handler names.
 */
class HandlersProviderFactory
{
    public static function loadWithName(string $handlerName): array
    {
        $handlers = [];
        if (!empty(AppConfig::$MONOLOG_HANDLERS)) {
            foreach (AppConfig::$MONOLOG_HANDLERS as $handlerProvider => $handlerProviderConfiguration) {
                if (class_exists(__NAMESPACE__ . '\Handlers\\' . $handlerProvider)) {
                    $handlerProviderClassName = __NAMESPACE__ . '\Handlers\\' . $handlerProvider;
                    $provider = new $handlerProviderClassName();
                    if (!$provider instanceof ProviderInterface) {
                        continue; // skip silently
                    }
                    try {
                        /**
                         * Add this var definition here to allow the IDE to recognize the usage of the concrete class
                         * @var ProviderInterface|CloudWatchHandlerProvider|ElasticSearchHandlerProvider|StreamHandlerProvider $provider
                         */
                        $handler = new ($provider->getHandlerClassName())(...$provider->getHandlerParams($handlerName, $handlerProviderConfiguration ?? []));
                        $provider->setFormatter($handler);
                        $handlers[] = $handler;
                    } catch (Throwable) {
                    }
                }
            }
        }
        return $handlers;
    }
}