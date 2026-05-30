<?php

namespace unit\Workers\Analysis;

use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use TestHelpers\AbstractTest;
use Utils\AsyncTasks\Workers\Analysis\Health;

class HealthTest extends AbstractTest
{
    #[Test]
    public function fastAnalysisIsRunningReturnsTrueWhenListNotEmpty(): void
    {
        $redis = $this->createMock(Client::class);
        $redis->method('__call')->willReturn('some-pid');

        $this->assertTrue(Health::fastAnalysisIsRunning($redis));
    }

    #[Test]
    public function fastAnalysisIsRunningReturnsFalseWhenEmpty(): void
    {
        $redis = $this->createMock(Client::class);
        $redis->method('__call')->willReturn(null);

        $this->assertFalse(Health::fastAnalysisIsRunning($redis));
    }

    #[Test]
    public function tmAnalysisIsRunningReturnsTrueWhenSet(): void
    {
        $redis = $this->createMock(Client::class);
        $redis->method('__call')->willReturn('1');

        $this->assertTrue(Health::tmAnalysisIsRunning($redis));
    }

    #[Test]
    public function tmAnalysisIsRunningReturnsFalseWhenNotSet(): void
    {
        $redis = $this->createMock(Client::class);
        $redis->method('__call')->willReturn(null);

        $this->assertFalse(Health::tmAnalysisIsRunning($redis));
    }
}
