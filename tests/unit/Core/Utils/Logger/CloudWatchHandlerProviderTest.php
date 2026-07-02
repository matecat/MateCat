<?php

namespace Matecat\Core\Utils\Logger;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\Handlers\CloudWatchHandlerProvider;
use Utils\Registry\AppConfig;

class CloudWatchHandlerProviderTest extends AbstractTest
{
    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = AppConfig::$ENV;
        AppConfig::$ENV = 'test';
    }

    protected function tearDown(): void
    {
        AppConfig::$ENV = $this->originalEnv;
        parent::tearDown();
    }

    #[Test]
    public function getHandlerClassNameReturnsCloudWatch(): void
    {
        $provider = new CloudWatchHandlerProvider();
        $this->assertSame(\PhpNexus\Cwh\Handler\CloudWatch::class, $provider->getHandlerClassName());
    }

    #[Test]
    public function getHandlerParamsReturnsExpectedKeys(): void
    {
        $client = $this->createStub(CloudWatchLogsClient::class);
        $provider = new CloudWatchHandlerProvider($client);

        $params = $provider->getHandlerParams('test.log', []);

        $this->assertSame($client, $params['client']);
        $this->assertStringContainsString('matecat-test-', $params['group']);
        $this->assertSame('test', $params['stream']);
        $this->assertSame(30, $params['retention']);
        $this->assertArrayHasKey('batchSize', $params);
        $this->assertArrayHasKey('tags', $params);
        $this->assertArrayHasKey('rpsLimit', $params);
        $this->assertArrayHasKey('cacheItemPool', $params);
    }

    #[Test]
    public function getHandlerParamsMergesConfigurationParams(): void
    {
        $client = $this->createStub(CloudWatchLogsClient::class);
        $provider = new CloudWatchHandlerProvider($client);

        $params = $provider->getHandlerParams('test.log', ['retention' => 90]);

        $this->assertSame(90, $params['retention']);
    }

    #[Test]
    public function getHandlerParamsDecodesJsonTags(): void
    {
        $client = $this->createStub(CloudWatchLogsClient::class);
        $provider = new CloudWatchHandlerProvider($client);

        $params = $provider->getHandlerParams('test.log', ['tags' => '{"Env":"prod"}']);

        $this->assertSame(['Env' => 'prod'], $params['tags']);
    }

    #[Test]
    public function getClientConfigReturnsDefaultRegion(): void
    {
        AppConfig::$AWS_REGION = null;
        AppConfig::$AWS_ACCESS_KEY_ID = null;
        AppConfig::$AWS_SECRET_KEY = null;

        $provider = new CloudWatchHandlerProvider($this->createStub(CloudWatchLogsClient::class));
        $ref = new \ReflectionMethod($provider, 'getClientConfig');
        $config = $ref->invoke($provider);

        $this->assertSame('latest', $config['version']);
        $this->assertSame('eu-central-1', $config['region']);
        $this->assertArrayNotHasKey('credentials', $config);
    }

    #[Test]
    public function getClientConfigIncludesCredentialsWhenSet(): void
    {
        AppConfig::$AWS_REGION = 'us-west-2';
        AppConfig::$AWS_ACCESS_KEY_ID = 'test-key';
        AppConfig::$AWS_SECRET_KEY = 'test-secret';

        $provider = new CloudWatchHandlerProvider($this->createStub(CloudWatchLogsClient::class));
        $ref = new \ReflectionMethod($provider, 'getClientConfig');
        $config = $ref->invoke($provider);

        $this->assertSame('us-west-2', $config['region']);
        $this->assertSame('test-key', $config['credentials']['key']);
        $this->assertSame('test-secret', $config['credentials']['secret']);

        AppConfig::$AWS_REGION = null;
        AppConfig::$AWS_ACCESS_KEY_ID = null;
        AppConfig::$AWS_SECRET_KEY = null;
    }

    #[Test]
    public function getClientConfigOmitsCredentialsWhenPartial(): void
    {
        AppConfig::$AWS_ACCESS_KEY_ID = 'key-only';
        AppConfig::$AWS_SECRET_KEY = null;

        $provider = new CloudWatchHandlerProvider($this->createStub(CloudWatchLogsClient::class));
        $ref = new \ReflectionMethod($provider, 'getClientConfig');
        $config = $ref->invoke($provider);

        $this->assertArrayNotHasKey('credentials', $config);

        AppConfig::$AWS_ACCESS_KEY_ID = null;
    }

    #[Test]
    public function setFormatterSetsJsonFormatter(): void
    {
        $provider = new CloudWatchHandlerProvider();
        $handler = new \Monolog\Handler\StreamHandler('php://memory');

        $provider->setFormatter($handler);

        $this->assertInstanceOf(\Monolog\Formatter\JsonFormatter::class, $handler->getFormatter());
    }
}
