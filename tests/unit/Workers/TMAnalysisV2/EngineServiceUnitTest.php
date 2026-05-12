<?php

namespace unit\Workers\TMAnalysisV2;

use Exception;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineResolverInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\EngineService;
use Utils\Engines\AbstractEngine;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\TaskRunner\Exceptions\NotSupportedMTException;
use Utils\TaskRunner\Exceptions\ReQueueException;

class EngineServiceUnitTest extends AbstractTest
{
    private function makeEngineService(AbstractEngine $tmEngine, ?AbstractEngine $mtEngine = null): EngineService
    {
        $resolver = $this->createStub(EngineResolverInterface::class);
        $resolver->method('getInstance')->willReturnCallback(function (int $id) use ($tmEngine, $mtEngine) {
            if ($id === 900) {
                return $tmEngine;
            }

            return $mtEngine ?? $tmEngine;
        });

        return new EngineService($resolver);
    }

    private function makeFeatureSet(): FeatureSet
    {
        return $this->createStub(FeatureSet::class);
    }

    private function makeTmConfig(array $overrides = []): array
    {
        return array_merge([
            'id_tms'                 => 900,
            'segment'                => 'Hello',
            'source'                 => 'en-US',
            'target'                 => 'it-IT',
            'get_mt'                 => true,
            'mt_qe_workflow_enabled' => false,
        ], $overrides);
    }

    private function makeMtConfig(array $overrides = []): array
    {
        return array_merge([
            'id_mt_engine' => 901,
            'segment'      => 'Hello',
            'source'       => 'en-US',
            'target'       => 'it-IT',
        ], $overrides);
    }

    private function makeEngineStub($returnValue): AbstractEngine
    {
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigStruct')->willReturn([]);
        $engine->method('setFeatureSet')->willReturnSelf();
        $engine->method('setMTPenalty')->willReturnSelf();
        $engine->method('setAnalysis')->willReturnSelf();
        $engine->method('setSkipAnalysis')->willReturnSelf();
        $engine->method('get')->willReturn($returnValue);

        return $engine;
    }

    // ── getTMMatches: non-GetMemoryResponse paths ──────────────────────

    #[Test]
    public function getTMMatches_null_response_with_get_mt_throws_requeue(): void
    {
        $engine = $this->makeEngineStub(null);
        $service = $this->makeEngineService($engine);

        $this->expectException(ReQueueException::class);
        $this->expectExceptionMessage('Empty field received even if MT was requested');

        $service->getTMMatches($this->makeTmConfig(['get_mt' => true]), $this->makeFeatureSet(), null);
    }

    #[Test]
    public function getTMMatches_null_response_without_get_mt_returns_empty(): void
    {
        $engine = $this->makeEngineStub(null);
        $service = $this->makeEngineService($engine);

        $result = $service->getTMMatches($this->makeTmConfig(['get_mt' => false]), $this->makeFeatureSet(), null);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getTMMatches_empty_array_returns_empty(): void
    {
        $engine = $this->makeEngineStub([]);
        $service = $this->makeEngineService($engine);

        $result = $service->getTMMatches($this->makeTmConfig(['get_mt' => false]), $this->makeFeatureSet(), null);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getTMMatches_raw_array_of_arrays_normalizes_matches(): void
    {
        $rawMatches = [
            ['match' => 85, 'translation' => 'Ciao', 'ICE' => false],
            ['match' => 90, 'translation' => 'Salve', 'ICE' => false],
        ];
        $engine = $this->makeEngineStub($rawMatches);
        $service = $this->makeEngineService($engine);

        $result = $service->getTMMatches($this->makeTmConfig(['get_mt' => false]), $this->makeFeatureSet(), null);

        $this->assertCount(2, $result);
        $this->assertEquals('Ciao', $result[0]['translation']);
        $this->assertEquals('Salve', $result[1]['translation']);
    }

    #[Test]
    public function getTMMatches_flat_array_wraps_as_single_match(): void
    {
        $flatMatch = ['match' => 85, 'translation' => 'Ciao', 'ICE' => false];
        $engine = $this->makeEngineStub($flatMatch);
        $service = $this->makeEngineService($engine);

        $result = $service->getTMMatches($this->makeTmConfig(['get_mt' => false]), $this->makeFeatureSet(), null);

        $this->assertCount(1, $result);
        $this->assertEquals('Ciao', $result[0]['translation']);
    }

    // ── getTMMatches: GetMemoryResponse error paths ────────────────────

    #[Test]
    public function getTMMatches_response_with_error_throws_requeue(): void
    {
        $response = new GetMemoryResponse([
            'matches'        => [],
            'responseStatus' => 200,
            'responseDetails' => 'OK',
        ]);
        $response->error = new \Utils\Engines\Results\ErrorResponse(['code' => -1, 'message' => 'Something failed']);

        $engine = $this->makeEngineStub($response);
        $service = $this->makeEngineService($engine);

        $this->expectException(ReQueueException::class);
        $this->expectExceptionMessage('Error from Matches. NULL received.');

        $service->getTMMatches($this->makeTmConfig(), $this->makeFeatureSet(), null);
    }

    #[Test]
    public function getTMMatches_mt_not_supported_throws_not_supported_mt(): void
    {
        $response = new GetMemoryResponse([
            'matches'        => [],
            'responseStatus' => 200,
            'responseDetails' => 'OK',
        ]);
        $response->mtLangSupported = false;

        $engine = $this->makeEngineStub($response);
        $service = $this->makeEngineService($engine);

        $this->expectException(NotSupportedMTException::class);

        $service->getTMMatches($this->makeTmConfig(), $this->makeFeatureSet(), null);
    }

    #[Test]
    public function getTMMatches_valid_response_returns_matches(): void
    {
        $response = new GetMemoryResponse([
            'matches'         => [
                [
                    'id'               => '1',
                    'segment'          => 'Hello',
                    'translation'      => 'Ciao',
                    'match'            => 0.85,
                    'quality'          => 74,
                    'created-by'       => 'TM-User',
                    'last-updated-by'  => 'TM-User',
                    'create-date'      => '2024-01-01 12:00:00',
                    'last-update-date' => '2024-01-01 12:00:00',
                    'key'              => 'k1',
                    'ICE'              => false,
                    'tm_properties'    => null,
                ],
            ],
            'responseStatus'  => 200,
            'responseDetails' => 'OK',
        ]);

        $engine = $this->makeEngineStub($response);
        $service = $this->makeEngineService($engine);

        $result = $service->getTMMatches($this->makeTmConfig(), $this->makeFeatureSet(), null);

        $this->assertNotEmpty($result);
    }

    // ── getMTTranslation paths ─────────────────────────────────────────

    #[Test]
    public function getMTTranslation_returns_plain_array_result(): void
    {
        $mtResult = ['translation' => 'Ciao mondo', 'match' => '75', 'created_by' => 'MT!'];
        $mtEngine = $this->makeEngineStub($mtResult);
        $service = $this->makeEngineService($this->createStub(AbstractEngine::class), $mtEngine);

        $result = $service->getMTTranslation($this->makeMtConfig(), $this->makeFeatureSet(), null, false);

        $this->assertEquals('Ciao mondo', $result['translation']);
    }

    #[Test]
    public function getMTTranslation_response_with_status_400_returns_empty(): void
    {
        $response = new GetMemoryResponse([
            'matches'         => [],
            'responseStatus'  => 500,
            'responseDetails' => 'Internal error',
        ]);

        $mtEngine = $this->makeEngineStub($response);
        $service = $this->makeEngineService($this->createStub(AbstractEngine::class), $mtEngine);

        $result = $service->getMTTranslation($this->makeMtConfig(), $this->makeFeatureSet(), null, false);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMTTranslation_response_with_status_200_returns_empty_when_no_matches_key(): void
    {
        // get_matches_as_array returns flat array — $result['matches'][0] doesn't exist → []
        $response = new GetMemoryResponse([
            'matches'         => [
                [
                    'id'               => '1',
                    'segment'          => 'Hello',
                    'translation'      => 'Ciao',
                    'match'            => 0.80,
                    'quality'          => 74,
                    'created-by'       => 'MT!',
                    'last-updated-by'  => 'MT!',
                    'create-date'      => '2024-01-01 12:00:00',
                    'last-update-date' => '2024-01-01 12:00:00',
                    'key'              => '',
                    'ICE'              => false,
                    'tm_properties'    => null,
                ],
            ],
            'responseStatus'  => 200,
            'responseDetails' => 'OK',
        ]);

        $mtEngine = $this->makeEngineStub($response);
        $service = $this->makeEngineService($this->createStub(AbstractEngine::class), $mtEngine);

        $result = $service->getMTTranslation($this->makeMtConfig(), $this->makeFeatureSet(), null, false);

        // get_matches_as_array(1) returns flat numerically-indexed array,
        // code accesses $mt_result['matches'][0] which doesn't exist → []
        $this->assertSame([], $result);
    }

    #[Test]
    public function getMTTranslation_error_code_in_result_returns_empty(): void
    {
        $mtResult = ['error' => ['code' => -1, 'message' => 'quota exceeded']];
        $mtEngine = $this->makeEngineStub($mtResult);
        $service = $this->makeEngineService($this->createStub(AbstractEngine::class), $mtEngine);

        $result = $service->getMTTranslation($this->makeMtConfig(), $this->makeFeatureSet(), null, false);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMTTranslation_exception_returns_empty_array(): void
    {
        $mtEngine = $this->createStub(AbstractEngine::class);
        $mtEngine->method('getConfigStruct')->willReturn([]);
        $mtEngine->method('setFeatureSet')->willReturnSelf();
        $mtEngine->method('setMTPenalty')->willReturnSelf();
        $mtEngine->method('setAnalysis')->willReturnSelf();
        $mtEngine->method('setSkipAnalysis')->willReturnSelf();
        $mtEngine->method('get')->willThrowException(new Exception('Connection timeout'));

        $service = $this->makeEngineService($this->createStub(AbstractEngine::class), $mtEngine);

        $result = $service->getMTTranslation($this->makeMtConfig(), $this->makeFeatureSet(), null, false);

        $this->assertSame([], $result);
    }
}
