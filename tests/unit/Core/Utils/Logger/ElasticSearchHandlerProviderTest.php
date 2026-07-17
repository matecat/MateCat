<?php

namespace Matecat\Core\Utils\Logger;

use Elastic\Elasticsearch\ClientInterface;
use Matecat\TestHelpers\AbstractTest;
use Monolog\Handler\ElasticsearchHandler;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\Handlers\ElasticSearchHandlerProvider;

class ElasticSearchHandlerProviderTest extends AbstractTest
{
    #[Test]
    public function getHandlerClassNameReturnsElasticsearchHandler(): void
    {
        $provider = new ElasticSearchHandlerProvider();
        $this->assertSame(ElasticsearchHandler::class, $provider->getHandlerClassName());
    }

    #[Test]
    public function getHandlerParamsReturnsClientAndOptions(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $provider = new ElasticSearchHandlerProvider($client);

        $params = $provider->getHandlerParams('test.log', ['hosts' => 'localhost:9200']);

        $this->assertSame($client, $params['client']);
        $this->assertArrayHasKey('options', $params);
        $this->assertStringContainsString('matecat-', $params['options']['index']);
    }

    #[Test]
    public function setFormatterIsNoOp(): void
    {
        $provider = new ElasticSearchHandlerProvider();
        $handler = $this->createStub(\Monolog\Handler\AbstractProcessingHandler::class);

        $provider->setFormatter($handler);
        $this->assertTrue(true);
    }
}
