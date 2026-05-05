<?php

namespace unit\Workers;

use Exception;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\FeaturesBase\FeatureSet;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysisWorker;
use Utils\Engines\AbstractEngine;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\ErrorResponse;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\Matches;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\NotSupportedMTException;
use Utils\TaskRunner\Exceptions\ReQueueException;
class TMAnalysisWorkerTest extends AbstractTest
{

    private TMAnalysisWorker $worker;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $amqStub = self::getStubBuilder(AMQHandler::class)->getStub();
        $this->worker = new TMAnalysisWorker($amqStub);

        $featureSet = new FeatureSet();
        $ref = new ReflectionProperty(TMAnalysisWorker::class, 'featureSet');
        $ref->setValue($this->worker, $featureSet);

        $observerRef = new ReflectionProperty(AbstractWorker::class, '_observer');
        $observerRef->setValue($this->worker, []);
    }

    // ─────────────────────────────────────────────────────────────────
    // _getTM() tests
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function getTM_ThrowsReQueueException_WhenResponseHasError(): void
    {
        $response = new GetMemoryResponse(null);
        $response->error = new ErrorResponse(['code' => 500, 'message' => 'Internal Server Error']);

        $engine = $this->createMockEngine($response);
        $queueElement = $this->createQueueElement();

        $this->expectException(ReQueueException::class);
        $this->invokeGetTM($engine, ['get_mt' => true], $queueElement);
    }

    #[Test]
    public function getTM_ThrowsNotSupportedMTException_WhenMtLangNotSupported(): void
    {
        $response = new GetMemoryResponse(null);
        $response->mtLangSupported = false;

        $engine = $this->createMockEngine($response);
        $queueElement = $this->createQueueElement();

        $this->expectException(NotSupportedMTException::class);
        $this->invokeGetTM($engine, ['get_mt' => false], $queueElement);
    }

    #[Test]
    public function getTM_ThrowsReQueueException_WhenMatchesEmptyAndGetMtTrue(): void
    {
        $response = new GetMemoryResponse([
            'responseData'    => 'OK',
            'responseStatus'  => 200,
            'responseDetails' => '',
            'mtLangSupported' => true,
            'matches'         => [],
        ]);

        $engine = $this->createMockEngine($response);
        $queueElement = $this->createQueueElement();

        $this->expectException(ReQueueException::class);
        $this->invokeGetTM($engine, ['get_mt' => true], $queueElement);
    }

    #[Test]
    public function getTM_ReturnsGetMemoryResponse_WhenMatchesEmptyAndGetMtFalse(): void
    {
        $response = new GetMemoryResponse([
            'responseData'    => 'OK',
            'responseStatus'  => 200,
            'responseDetails' => '',
            'mtLangSupported' => true,
            'matches'         => [],
        ]);

        $engine = $this->createMockEngine($response);
        $queueElement = $this->createQueueElement();

        $result = $this->invokeGetTM($engine, ['get_mt' => false], $queueElement);

        $this->assertInstanceOf(GetMemoryResponse::class, $result);
        $this->assertEmpty($result->matches);
    }

    #[Test]
    public function getTM_ReturnsMatchesArray_WhenMatchesExist(): void
    {
        $response = new GetMemoryResponse([
            'responseData'    => 'OK',
            'responseStatus'  => 200,
            'responseDetails' => '',
            'mtLangSupported' => true,
            'matches'         => [
                [
                    'id'               => '12345',
                    'segment'          => 'Hello world',
                    'translation'      => 'Ciao mondo',
                    'match'            => 1.0,
                    'created-by'       => 'TestUser',
                    'create-date'      => '2024-01-01 12:00:00',
                    'last-update-date' => '2024-01-01 12:00:00',
                    'quality'          => 74,
                    'usage-count'      => 1,
                    'subject'          => '',
                    'reference'        => '',
                    'last-updated-by'  => '',
                    'tm_properties'    => '[]',
                    'key'              => 'TESTKEY',
                    'ICE'              => true,
                    'source_note'      => null,
                    'target_note'      => '',
                    'penalty'          => null,
                    'prop'             => '[]',
                ],
            ],
        ]);

        $engine = $this->createMockEngine($response);
        $queueElement = $this->createQueueElement();

        $result = $this->invokeGetTM($engine, ['get_mt' => false], $queueElement);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('match', $result[0]);
        $this->assertEquals('100%', $result[0]['match']);
    }

    // ─────────────────────────────────────────────────────────────────
    // _getMT() tests
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function getMT_ReturnsEmptyArray_WhenResponseStatusIs400OrAbove(): void
    {
        $response = new GetMemoryResponse([
            'responseData'    => '',
            'responseStatus'  => 500,
            'responseDetails' => 'Server Error',
            'mtLangSupported' => true,
            'matches'         => [],
        ]);

        $engine = $this->createMockMTEngine($response);
        $queueElement = $this->createQueueElement();

        $result = $this->invokeGetMT($engine, ['get_mt' => false], $queueElement, null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function getMT_ReturnsEmptyArray_WhenResponseHasError(): void
    {
        $response = new GetMemoryResponse([
            'responseData'    => '',
            'responseStatus'  => 200,
            'responseDetails' => '',
            'mtLangSupported' => true,
            'matches'         => [],
        ]);
        $response->error = new ErrorResponse(['code' => 429, 'message' => 'Rate limit']);

        $engine = $this->createMockMTEngine($response);
        $queueElement = $this->createQueueElement();

        $result = $this->invokeGetMT($engine, ['get_mt' => false], $queueElement, null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function getMT_ReturnsFirstMatch_WhenResponseHasMatches(): void
    {
        $match = new Matches([
            'id'               => '99',
            'raw_segment'      => 'Hello',
            'raw_translation'  => 'Hola',
            'match'            => '87%',
            'created-by'       => 'MT!',
            'create-date'      => '2024-01-01 12:00:00',
            'last-update-date' => '2024-01-01 12:00:00',
            'quality'          => 70,
            'usage-count'      => 0,
            'subject'          => '',
            'reference'        => '',
            'last-updated-by'  => '',
            'tm_properties'    => '[]',
            'key'              => '',
            'ICE'              => false,
            'source_note'      => null,
            'target_note'      => '',
            'penalty'          => null,
            'prop'             => [],
        ]);

        $response = new GetMemoryResponse(null);
        $response->matches = [$match];
        $response->responseStatus = 200;

        $engine = $this->createMockMTEngine($response);
        $queueElement = $this->createQueueElement();

        $result = $this->invokeGetMT($engine, ['get_mt' => false], $queueElement, null);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('created_by', $result);
        $this->assertEquals('MT!', $result['created_by']);
    }

    #[Test]
    public function getMT_ReturnsEmptyArray_WhenEngineThrowsException(): void
    {
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigStruct')->willReturn([]);
        $engine->method('get')->willThrowException(new Exception('Connection timeout'));

        $queueElement = $this->createQueueElement();

        $result = $this->invokeGetMT($engine, ['get_mt' => false], $queueElement, null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ─────────────────────────────────────────────────────────────────
    // __filterTMMatches() tests
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function filterTMMatches_ReturnsAllMatches_WhenMtQeWorkflowDisabled(): void
    {
        $matches = [
            ['match' => '95%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
            ['match' => '80%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
            ['match' => '50%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
        ];

        $result = $this->invokeFilterTMMatches($matches, false, null);

        $this->assertCount(3, $result);
    }

    #[Test]
    public function filterTMMatches_ReturnsEmpty_WhenAnalysisIgnore101IsTrue(): void
    {
        $matches = [
            ['match' => '101%', InternalMatchesConstants::TM_ICE => true, 'created_by' => 'TM'],
            ['match' => '100%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
        ];

        $config = new MTQEWorkflowParams(['analysis_ignore_101' => true, 'analysis_ignore_100' => false]);

        $result = $this->invokeFilterTMMatches($matches, true, $config);

        $this->assertEmpty($result);
    }

    #[Test]
    public function filterTMMatches_FiltersNonIce100Matches_WhenAnalysisIgnore100IsTrue(): void
    {
        $matches = [
            ['match' => '101%', InternalMatchesConstants::TM_ICE => true, 'created_by' => 'TM'],
            ['match' => '100%', InternalMatchesConstants::TM_ICE => true, 'created_by' => 'TM'],
            ['match' => '100%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
        ];

        $config = new MTQEWorkflowParams(['analysis_ignore_101' => false, 'analysis_ignore_100' => true]);

        $result = $this->invokeFilterTMMatches($matches, true, $config);

        $this->assertCount(2, $result);
        foreach ($result as $match) {
            $this->assertTrue(
                (int)$match['match'] > 100 || $match[InternalMatchesConstants::TM_ICE] === true
            );
        }
    }

    #[Test]
    public function filterTMMatches_FiltersMatchesBelow100_WhenMtQeEnabled(): void
    {
        $matches = [
            ['match' => '101%', InternalMatchesConstants::TM_ICE => true, 'created_by' => 'TM'],
            ['match' => '100%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
            ['match' => '99%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
            ['match' => '85%', InternalMatchesConstants::TM_ICE => false, 'created_by' => 'TM'],
        ];

        $config = new MTQEWorkflowParams(['analysis_ignore_101' => false, 'analysis_ignore_100' => false]);

        $result = $this->invokeFilterTMMatches($matches, true, $config);

        $this->assertCount(2, $result);
        foreach ($result as $match) {
            $this->assertGreaterThanOrEqual(100, (int)$match['match']);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // _sortMatches() tests (via MatchesComparator trait)
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function sortMatches_AppendsAndSortsByScoreDesc(): void
    {
        $mt_result = ['match' => '87%', 'created_by' => 'MT!', 'ICE' => false];
        $matches = [
            ['match' => '75%', 'created_by' => 'TM User', 'ICE' => false],
            ['match' => '95%', 'created_by' => 'TM User', 'ICE' => false],
        ];

        $result = $this->invokeSortMatches($mt_result, $matches);

        $this->assertCount(3, $result);
        $this->assertEquals('95%', $result[0]['match']);
        $this->assertEquals('87%', $result[1]['match']);
        $this->assertEquals('75%', $result[2]['match']);
    }

    #[Test]
    public function sortMatches_ReturnsMatchesUnchanged_WhenMtResultEmpty(): void
    {
        $matches = [
            ['match' => '85%', 'created_by' => 'TM User', 'ICE' => false],
            ['match' => '95%', 'created_by' => 'TM User', 'ICE' => false],
        ];

        $result = $this->invokeSortMatches([], $matches);

        $this->assertCount(2, $result);
        $this->assertEquals('95%', $result[0]['match']);
        $this->assertEquals('85%', $result[1]['match']);
    }

    #[Test]
    public function sortMatches_ICEMatchesTakePriority_WhenScoresEqual(): void
    {
        $mt_result = [];
        $matches = [
            ['match' => '100%', 'created_by' => 'TM User', 'ICE' => false],
            ['match' => '100%', 'created_by' => 'TM User', 'ICE' => true],
        ];

        $result = $this->invokeSortMatches($mt_result, $matches);

        $this->assertTrue($result[0]['ICE']);
        $this->assertFalse($result[1]['ICE']);
    }

    #[Test]
    public function sortMatches_MTMatchesTakePriority_WhenScoresEqualAndNoICE(): void
    {
        $mt_result = [];
        $matches = [
            ['match' => '85%', 'created_by' => 'TM User', 'ICE' => false],
            ['match' => '85%', 'created_by' => 'MT!', 'ICE' => false],
        ];

        $result = $this->invokeSortMatches($mt_result, $matches);

        $this->assertStringContainsString('MT', $result[0]['created_by']);
    }

    // ─────────────────────────────────────────────────────────────────
    // getHighestNotMT_OrPickTheFirstOne() tests
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function getHighestNotMT_ReturnsFirstNonMTMatchAbove75(): void
    {
        $matches = [
            ['match' => '87%', 'created_by' => 'MT!', 'ICE' => false],
            ['match' => '80%', 'created_by' => 'TM User', 'ICE' => false],
            ['match' => '75%', 'created_by' => 'TM User2', 'ICE' => false],
        ];

        $result = $this->invokeGetHighestNotMT($matches);

        $this->assertEquals('TM User', $result['created_by']);
        $this->assertEquals('80%', $result['match']);
    }

    #[Test]
    public function getHighestNotMT_ReturnsFirstMatch_WhenAllAreMT(): void
    {
        $matches = [
            ['match' => '87%', 'created_by' => 'MT!', 'ICE' => false],
            ['match' => '80%', 'created_by' => 'MT! DeepL', 'ICE' => false],
        ];

        $result = $this->invokeGetHighestNotMT($matches);

        $this->assertEquals('MT!', $result['created_by']);
    }

    #[Test]
    public function getHighestNotMT_ReturnsFirstMatch_WhenAllNonMTBelow75(): void
    {
        $matches = [
            ['match' => '87%', 'created_by' => 'MT!', 'ICE' => false],
            ['match' => '70%', 'created_by' => 'TM User', 'ICE' => false],
        ];

        $result = $this->invokeGetHighestNotMT($matches);

        $this->assertEquals('MT!', $result['created_by']);
    }

    // ─────────────────────────────────────────────────────────────────
    // mergeJsonErrors() tests
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function mergeJsonErrors_ReturnsEmptyString_WhenBothEmpty(): void
    {
        $result = $this->invokeMergeJsonErrors('', '');
        $this->assertSame('', $result);
    }

    #[Test]
    public function mergeJsonErrors_ReturnsFirst_WhenSecondEmpty(): void
    {
        $json = '{"errors":["too long"]}';
        $result = $this->invokeMergeJsonErrors($json, '');
        $this->assertSame($json, $result);
    }

    #[Test]
    public function mergeJsonErrors_ReturnsSecond_WhenFirstEmpty(): void
    {
        $json = '{"errors":["missing tag"]}';
        $result = $this->invokeMergeJsonErrors('', $json);
        $this->assertSame($json, $result);
    }

    #[Test]
    public function mergeJsonErrors_MergesBoth_WhenNeitherEmpty(): void
    {
        $json1 = '{"errors":["too long"]}';
        $json2 = '{"errors":["missing tag"]}';
        $result = $this->invokeMergeJsonErrors($json1, $json2);

        $decoded = json_decode($result, true);
        $this->assertCount(2, $decoded['errors']);
        $this->assertContains('too long', $decoded['errors']);
        $this->assertContains('missing tag', $decoded['errors']);
    }

    // ─────────────────────────────────────────────────────────────────
    // _lockAndPreTranslateStatusCheck() tests
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function lockCheck_SetsApprovedAndLocked_ForICE100Match(): void
    {
        $tm_data = [
            'suggestion_match' => '100%',
            'match_type'       => InternalMatchesConstants::TM_ICE,
        ];

        $params = new Params([]);
        $params->target = 'fr-FR';
        $params->pretranslate_100 = false;
        $params->mt_qe_workflow_enabled = false;

        $result = $this->invokeLockAndPreTranslateStatusCheck($tm_data, $params);

        $this->assertEquals('APPROVED', $result['status']);
        $this->assertTrue($result['locked']);
    }

    #[Test]
    public function lockCheck_SetsTranslatedNotLocked_ForPretranslate100(): void
    {
        $tm_data = [
            'suggestion_match' => '100%',
            'match_type'       => InternalMatchesConstants::TM_100,
        ];

        $params = new Params([]);
        $params->target = 'it-IT';
        $params->pretranslate_100 = true;
        $params->mt_qe_workflow_enabled = false;

        $result = $this->invokeLockAndPreTranslateStatusCheck($tm_data, $params);

        $this->assertEquals('TRANSLATED', $result['status']);
        $this->assertFalse($result['locked']);
    }

    #[Test]
    public function lockCheck_DoesNotSetStatus_For95Match(): void
    {
        $tm_data = [
            'suggestion_match' => '95%',
            'match_type'       => InternalMatchesConstants::TM_95_99,
        ];

        $params = new Params([]);
        $params->target = 'it-IT';
        $params->pretranslate_100 = true;
        $params->mt_qe_workflow_enabled = false;

        $result = $this->invokeLockAndPreTranslateStatusCheck($tm_data, $params);

        $this->assertArrayNotHasKey('status', $result);
        $this->assertArrayNotHasKey('locked', $result);
    }

    #[Test]
    public function lockCheck_SetsApprovedForICE_MT_WhenMtQeEnabled(): void
    {
        $tm_data = [
            'suggestion_match' => '101%',
            'match_type'       => InternalMatchesConstants::ICE_MT,
        ];

        $params = new Params([]);
        $params->target = 'it-IT';
        $params->pretranslate_100 = false;
        $params->mt_qe_workflow_enabled = true;

        $result = $this->invokeLockAndPreTranslateStatusCheck($tm_data, $params);

        $this->assertEquals('APPROVED', $result['status']);
        $this->assertFalse($result['locked']);
    }

    // ─────────────────────────────────────────────────────────────────
    // isMtMatch() tests (from MatchesComparator trait)
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('mtMatchProvider')]
    public function isMtMatch_DetectsCorrectly(array $match, bool $expected): void
    {
        $result = $this->worker->isMtMatch($match);
        $this->assertSame($expected, $result);
    }

    public static function mtMatchProvider(): array
    {
        return [
            'MT! prefix'             => [['created_by' => 'MT!'], true],
            'MT in middle'           => [['created_by' => 'Google MT! Translate'], true],
            'case insensitive'       => [['created_by' => 'mt!'], true],
            'TM User (not MT)'       => [['created_by' => 'TM User'], false],
            'empty created_by'       => [['created_by' => ''], false],
            'missing created_by key' => [[], false],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helper methods
    // ─────────────────────────────────────────────────────────────────

    private function createMockEngine(GetMemoryResponse $response): MyMemory
    {
        $engine = $this->createStub(MyMemory::class);
        $engine->method('getConfigStruct')->willReturn([]);
        $engine->method('get')->willReturn($response);

        return $engine;
    }

    private function createMockMTEngine(GetMemoryResponse $response): AbstractEngine
    {
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigStruct')->willReturn([]);
        $engine->method('get')->willReturn($response);

        return $engine;
    }

    private function createQueueElement(): QueueElement
    {
        $queueElement = new QueueElement();
        $queueElement->params = new Params([]);
        $queueElement->params->id_segment = 1;
        $queueElement->params->id_job = 1;
        $queueElement->params->pid = 1;
        $queueElement->params->mt_quality_value_in_editor = 85;
        $queueElement->params->mt_qe_workflow_enabled = false;
        $queueElement->params->tm_keys = '[]';

        return $queueElement;
    }

    /**
     * @throws ReflectionException
     */
    private function invokeGetTM(MyMemory $engine, array $config, QueueElement $queueElement): mixed
    {
        $method = new ReflectionMethod(TMAnalysisWorker::class, '_getTM');

        return $method->invoke($this->worker, $engine, $config, $queueElement);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeGetMT(AbstractEngine $engine, array $config, QueueElement $queueElement, ?MTQEWorkflowParams $mtQeConfig): mixed
    {
        $method = new ReflectionMethod(TMAnalysisWorker::class, '_getMT');

        return $method->invoke($this->worker, $engine, $config, $queueElement, $mtQeConfig);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeFilterTMMatches(array $matches, bool $mtQeEnabled, ?MTQEWorkflowParams $config): array
    {
        $method = new ReflectionMethod(TMAnalysisWorker::class, '__filterTMMatches');

        return $method->invoke($this->worker, $matches, $mtQeEnabled, $config);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeSortMatches(array $mtResult, array $matches): array
    {
        $method = new ReflectionMethod(TMAnalysisWorker::class, '_sortMatches');

        return $method->invoke($this->worker, $mtResult, $matches);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeGetHighestNotMT(array $matches): mixed
    {
        $ref = new ReflectionProperty(TMAnalysisWorker::class, '_matches');
        $ref->setValue($this->worker, $matches);

        $method = new ReflectionMethod(TMAnalysisWorker::class, 'getHighestNotMT_OrPickTheFirstOne');

        return $method->invoke($this->worker);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeMergeJsonErrors(string $err1, string $err2): string|false
    {
        $method = new ReflectionMethod(TMAnalysisWorker::class, 'mergeJsonErrors');

        return $method->invoke($this->worker, $err1, $err2);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeLockAndPreTranslateStatusCheck(array $tmData, Params $params): array
    {
        $method = new ReflectionMethod(TMAnalysisWorker::class, '_lockAndPreTranslateStatusCheck');

        return $method->invoke($this->worker, $tmData, $params);
    }
}
