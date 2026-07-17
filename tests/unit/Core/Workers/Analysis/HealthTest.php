<?php

namespace Matecat\Core\Workers\Analysis;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use Utils\AsyncTasks\Workers\Analysis\Health;
use Utils\Registry\AppConfig;

class HealthTest extends AbstractTest
{
    #[Test]
    public function fastAnalysisIsRunningReturnsTrueWhenListNotEmpty(): void
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturn('some-pid');

        $this->assertTrue(Health::fastAnalysisIsRunning($redis));
    }

    #[Test]
    public function fastAnalysisIsRunningReturnsFalseWhenEmpty(): void
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturn(null);

        $this->assertFalse(Health::fastAnalysisIsRunning($redis));
    }

    #[Test]
    public function tmAnalysisIsRunningReturnsTrueWhenSet(): void
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturn('1');

        $this->assertTrue(Health::tmAnalysisIsRunning($redis));
    }

    #[Test]
    public function tmAnalysisIsRunningReturnsFalseWhenNotSet(): void
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturn(null);

        $this->assertFalse(Health::tmAnalysisIsRunning($redis));
    }

    // ─── thereIsAMisconfiguration() ───

    #[Test]
    public function thereIsAMisconfigurationReturnsTrueWhenNothingRunning(): void
    {
        $originalEnabled = AppConfig::$VOLUME_ANALYSIS_ENABLED;
        AppConfig::$VOLUME_ANALYSIS_ENABLED = true;

        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturn(null);

        $result = Health::thereIsAMisconfiguration($redis);

        AppConfig::$VOLUME_ANALYSIS_ENABLED = $originalEnabled;

        $this->assertTrue($result);
    }

    #[Test]
    public function thereIsAMisconfigurationReturnsFalseWhenDisabled(): void
    {
        $originalEnabled = AppConfig::$VOLUME_ANALYSIS_ENABLED;
        AppConfig::$VOLUME_ANALYSIS_ENABLED = false;

        $redis = $this->createStub(Client::class);

        $result = Health::thereIsAMisconfiguration($redis);

        AppConfig::$VOLUME_ANALYSIS_ENABLED = $originalEnabled;

        $this->assertFalse($result);
    }

    #[Test]
    public function thereIsAMisconfigurationReturnsFalseOnException(): void
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willThrowException(new \Exception('Redis down'));

        $this->assertFalse(Health::thereIsAMisconfiguration($redis));
    }

    #[Test]
    public function thereIsAMisconfigurationReturnsFalseWhenAnalysisRunning(): void
    {
        $originalEnabled = AppConfig::$VOLUME_ANALYSIS_ENABLED;
        AppConfig::$VOLUME_ANALYSIS_ENABLED = true;

        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturn('some-pid');

        $result = Health::thereIsAMisconfiguration($redis);

        AppConfig::$VOLUME_ANALYSIS_ENABLED = $originalEnabled;

        $this->assertFalse($result);
    }
}
