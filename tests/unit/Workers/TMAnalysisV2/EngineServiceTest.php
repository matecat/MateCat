<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\EngineService;

class EngineServiceTest extends AbstractTest
{
    private function engineServicePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/EngineService.php');
        $this->assertNotFalse($path, 'EngineService.php must exist at the expected path.');

        return $path;
    }

    private function readSource(string $path): string
    {
        $source = file_get_contents($path);
        $this->assertNotFalse($source, 'EngineService.php source must be readable.');

        return $source;
    }

    #[Test]
    public function test_service_can_be_instantiated(): void
    {
        $service = new EngineService();
        $this->assertInstanceOf(EngineService::class, $service);
        $this->assertInstanceOf(EngineServiceInterface::class, $service);
    }

    #[Test]
    public function test_implements_engine_service_interface_in_source(): void
    {
        $source = $this->readSource($this->engineServicePath());
        $this->assertStringContainsString(
            'implements EngineServiceInterface',
            $source,
            'EngineService class declaration must implement EngineServiceInterface.'
        );
    }

    #[Test]
    public function test_getTMMatches_uses_engine_resolver_for_engine_creation(): void
    {
        $source = $this->readSource($this->engineServicePath());
        $this->assertStringContainsString(
            '$this->engineResolver->getInstance(',
            $source,
            'EngineService::getTMMatches must use injected EngineResolverInterface for engine creation.'
        );
    }

    #[Test]
    public function test_getTMMatches_throws_ReQueueException_on_errors(): void
    {
        $source = $this->readSource($this->engineServicePath());

        $this->assertStringContainsString(
            'ReQueueException',
            $source,
            'EngineService source must reference ReQueueException.'
        );

        $this->assertStringContainsString(
            'throw new ReQueueException(',
            $source,
            'EngineService::getTMMatches must throw ReQueueException on engine errors.'
        );

        $methodStart = strpos($source, 'function getTMMatches(');
        $this->assertNotFalse($methodStart, 'getTMMatches method must exist in EngineService.');

        $throwPos = strpos($source, 'throw new ReQueueException(', $methodStart);
        $this->assertNotFalse(
            $throwPos,
            'ReQueueException must be thrown within getTMMatches() method body.'
        );
    }

    #[Test]
    public function test_getMTTranslation_uses_try_catch_log_and_swallow_pattern(): void
    {
        $source = $this->readSource($this->engineServicePath());

        $methodStart = strpos($source, 'function getMTTranslation(');
        $this->assertNotFalse($methodStart, 'getMTTranslation method must exist in EngineService.');

        $tryPos = strpos($source, 'try {', $methodStart);
        $this->assertNotFalse(
            $tryPos,
            'getMTTranslation must wrap engine calls in a try block.'
        );

        $catchPos = strpos($source, 'catch (Exception', $methodStart);
        $this->assertNotFalse(
            $catchPos,
            'getMTTranslation must catch Exception to implement log-and-swallow pattern.'
        );
    }

    #[Test]
    public function test_getMTTranslation_logs_exception_via_LoggerFactory(): void
    {
        $source = $this->readSource($this->engineServicePath());

        $methodStart = strpos($source, 'function getMTTranslation(');
        $this->assertNotFalse($methodStart, 'getMTTranslation method must exist in EngineService.');

        $logPos = strpos($source, 'LoggerFactory::doJsonLog(', $methodStart);
        $this->assertNotFalse(
            $logPos,
            'getMTTranslation must log exceptions via LoggerFactory::doJsonLog() in the catch block.'
        );
    }

    #[Test]
    public function test_getMTTranslation_returns_empty_array_on_exception(): void
    {
        $source = $this->readSource($this->engineServicePath());

        $this->assertStringContainsString(
            '$mt_result = [];',
            $source,
            'getMTTranslation must initialise $mt_result as an empty array so the exception path returns [].'
        );
        $this->assertStringContainsString(
            'return $mt_result;',
            $source,
            'getMTTranslation must return $mt_result (empty array on exception path).'
        );
    }

    #[Test]
    public function test_filterTMMatches_private_method_exists(): void
    {
        $source = $this->readSource($this->engineServicePath());
        $this->assertStringContainsString(
            'private function __filterTMMatches(',
            $source,
            '__filterTMMatches private method must exist in EngineService.'
        );
    }
}
