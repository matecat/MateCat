<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 25/11/25
 * Time: 12:08
 *
 */

namespace Utils\Logger\Handlers;

use DateTime;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\ElasticsearchHandler;

class ElasticSearchHandlerProvider implements ProviderInterface
{

    /**
     * @inheritDoc
     */
    public function getHandlerClassName(): string
    {
        return ElasticsearchHandler::class;
    }

    /**
     * @inheritDoc
     * @throws AuthenticationException
     */
    public function getHandlerParams(string $name, array $configurationParams): array
    {
        $hosts = explode(',', $configurationParams['hosts']);
        $client = ClientBuilder::create()->setHosts($hosts)->build();
        return [
            'client' => $client,
            'options' => [
                'index' => 'matecat-' . (new DateTime())->format('Y.m')
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function setFormatter(AbstractProcessingHandler $handler): void
    {
        // No formatter needed
    }

}