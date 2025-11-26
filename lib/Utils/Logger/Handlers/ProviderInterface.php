<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 20/11/25
 * Time: 18:20
 *
 */

namespace Utils\Logger\Handlers;

use Monolog\Handler\AbstractProcessingHandler;

interface ProviderInterface
{
    /**
     * @return class-string
     */
    public function getHandlerClassName(): string;

    /**
     * @return array configuration params for the handler
     */
    public function getHandlerParams(string $name, array $configurationParams): array;

    /**
     * @param AbstractProcessingHandler $handler
     * @return void
     */
    public function setFormatter(AbstractProcessingHandler $handler): void;
}