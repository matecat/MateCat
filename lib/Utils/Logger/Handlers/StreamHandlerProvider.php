<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/11/25
 * Time: 14:01
 *
 */

namespace Utils\Logger\Handlers;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\StreamHandler;
use Utils\Logger\LoggerFactory;

class StreamHandlerProvider implements ProviderInterface
{

    /**
     * @inheritDoc
     */
    public function getHandlerClassName(): string
    {
        return StreamHandler::class;
    }

    /**
     * @inheritDoc
     */
    public function getHandlerParams(string $name, array $configurationParams): array
    {
        return [
            'stream' => LoggerFactory::getFileNamePath($name)
        ];
    }

    /**
     * @inheritDoc
     */
    public function setFormatter(AbstractProcessingHandler $handler): void
    {
        $handler->setFormatter(new JsonFormatter());
    }

}