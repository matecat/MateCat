<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 20/11/25
 * Time: 18:14
 *
 */

namespace Utils\Logger;

use Monolog\Formatter\JsonFormatter;
use Utils\Logger\Handlers\CloudWatch\CloudWatchHandlerProvider;
use Utils\Logger\Handlers\ProviderInterface;
use Utils\Registry\AppConfig;

class HandlersProvider
{
    public static function loadWithName(string $handlerName): array
    {
        $handlers = [];
        if (!empty(AppConfig::$MONOLOG_HANDLER_PROVIDERS)) {
            foreach (AppConfig::$MONOLOG_HANDLER_PROVIDERS as $handlerProviderClass) {
                if (class_exists($handlerProviderClass)) {
                    $provider = new $handlerProviderClass();
                    if (!$provider instanceof ProviderInterface) {
                        continue; // skip silently
                    }
                    /**
                     * Add this var definition here to allow the IDE to recognize the usage of the concrete class
                     * @var CloudWatchHandlerProvider $provider
                     */
                    $handler = new ($provider->getHandlerClassName())(...$provider->getHandlerParams($handlerName));
                    $handler->setFormatter(new JsonFormatter());
                    $handlers[] = $handler;
                }
            }
        }
        return $handlers;
    }
}