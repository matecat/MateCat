<?php

use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Constants\TranslationStatus;
use Utils\AsyncTasks\Workers\GetContributionWorker;
use Utils\ActiveMQ\AMQHandler;
use Utils\Contribution\GetContributionRequest;
use Utils\Engines\AbstractEngine;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

class GetContributionWorkerTest extends AbstractTest
{
    private const int TEST_PROJECT_ID = 990001;
    private const int TEST_FILE_ID = 990002;
    private const int TEST_JOB_ID = 990003;
    private const int TEST_SEGMENT_ID = 990004;

    private GetContributionWorker $worker;

    public function setUp(): void
    {
        parent::setUp();
        $this->worker = new GetContributionWorker(
            self::getStubBuilder(AMQHandler::class)->getStub()
        );

        $this->cleanupDbFixtures();
    }

    public function tearDown(): void
    {
        $this->cleanupDbFixtures();
        parent::tearDown();
    }

    #[Test]
    public function test_tokenizeSourceSearch_returns_patterns_for_simple_text(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, 'Hello world');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        foreach ($result as $pattern => $replacement) {
            $this->assertIsString($pattern);
            $this->assertIsString($replacement);
            $this->assertStringContainsString('#{', $replacement);
            $this->assertStringContainsString('}#', $replacement);
        }
    }

    #[Test]
    public function test_tokenizeSourceSearch_strips_punctuation(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, 'Hello, world. (test)');

        $values = array_values($result);
        $found_hello = false;
        $found_world = false;
        $found_test  = false;
        foreach ($values as $v) {
            if (str_contains($v, '#{Hello}#')) {
                $found_hello = true;
            }
            if (str_contains($v, '#{world}#')) {
                $found_world = true;
            }
            if (str_contains($v, '#{test}#')) {
                $found_test = true;
            }
        }
        $this->assertTrue($found_hello, 'Should find Hello token');
        $this->assertTrue($found_world, 'Should find world token');
        $this->assertTrue($found_test, 'Should find test token');
    }

    #[Test]
    public function test_tokenizeSourceSearch_sorts_patterns_by_length_desc(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, 'a beautiful day');

        $keys    = array_keys($result);
        $lengths = array_map('strlen', $keys);

        for ($i = 0; $i < count($lengths) - 1; $i++) {
            $this->assertGreaterThanOrEqual($lengths[$i + 1], $lengths[$i],
                'Patterns should be sorted by length descending');
        }
    }

    #[Test]
    public function test_tokenizeSourceSearch_handles_empty_string(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, '');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function test_tokenizeSourceSearch_handles_html_entities(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, '&lt;tag&gt; content');

        $this->assertIsArray($result);
        $values = array_values($result);
        $found_content = false;
        foreach ($values as $v) {
            if (str_contains($v, '#{content}#')) {
                $found_content = true;
            }
        }
        $this->assertTrue($found_content, 'Should find content token');
    }

    #[Test]
    public function test_formatConcordanceValues_applies_regex_highlighting(): void
    {
        $method = new ReflectionMethod($this->worker, '_formatConcordanceValues');

        $regularExpressions = [
            '|(\s{1})?Hello(\s{1})?|ui' => '$1#{Hello}#$2',
        ];

        $result = $method->invoke($this->worker, 'Hello world', 'Target text', $regularExpressions);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertStringContainsString('#{Hello}#', $result[0]);
        $this->assertEquals('Target text', $result[1]);
    }

    #[Test]
    public function test_formatConcordanceValues_strips_tags_from_source(): void
    {
        $method = new ReflectionMethod($this->worker, '_formatConcordanceValues');

        $result = $method->invoke($this->worker, '<b>Bold</b> text', 'target', []);

        $this->assertEquals('Bold text', $result[0]);
        $this->assertEquals('target', $result[1]);
    }

    #[Test]
    public function test_formatConcordanceValues_collapses_multiple_spaces(): void
    {
        $method = new ReflectionMethod($this->worker, '_formatConcordanceValues');

        $result = $method->invoke($this->worker, 'Too   many    spaces', 'target', []);

        $this->assertStringNotContainsString('  ', $result[0]);
    }

    #[Test]
    public function test_matchRewrite_sets_ICE_MT_for_high_score(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.95, 'match' => '95%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('ICE_MT', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_does_not_modify_low_score(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.5, 'match' => '50%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('50%', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_does_not_modify_empty_score(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['match' => '80%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('80%', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_boundary_score_0_9_triggers(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.9, 'match' => '90%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('ICE_MT', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_score_just_below_0_9_does_not_trigger(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.89, 'match' => '89%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('89%', $result['match']);
    }

    #[Test]
    public function test_sortByLenDesc_longer_string_comes_first(): void
    {
        $method = new ReflectionMethod($this->worker, '_sortByLenDesc');

        $result = $method->invoke($this->worker, 'beautiful', 'a');

        $this->assertEquals(-1, $result);
    }

    #[Test]
    public function test_sortByLenDesc_shorter_string_comes_last(): void
    {
        $method = new ReflectionMethod($this->worker, '_sortByLenDesc');

        $result = $method->invoke($this->worker, 'a', 'beautiful');

        $this->assertEquals(1, $result);
    }

    #[Test]
    public function test_sortByLenDesc_equal_length_returns_zero(): void
    {
        $method = new ReflectionMethod($this->worker, '_sortByLenDesc');

        $result = $method->invoke($this->worker, 'abc', 'def');

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function test_issetSourceAndTarget_returns_true_when_both_are_present(): void
    {
        $method = new ReflectionMethod($this->worker, 'issetSourceAndTarget');

        $result = $method->invoke($this->worker, [
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_issetSourceAndTarget_returns_false_when_target_is_empty(): void
    {
        $method = new ReflectionMethod($this->worker, 'issetSourceAndTarget');

        $result = $method->invoke($this->worker, [
            'source' => 'en-US',
            'target' => '',
        ]);

        $this->assertFalse($result);
    }

    #[Test]
    public function test_extractAvailableKeysForUser_returns_readable_tm_keys(): void
    {
        $request = $this->makeBaseRequest([
            'userRole' => 'translator',
        ]);
        $request->setJobStruct(new JobStruct([
            'id' => self::TEST_JOB_ID,
            'id_project' => self::TEST_PROJECT_ID,
            'password' => 'pw',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_first_segment' => self::TEST_SEGMENT_ID,
            'job_last_segment' => self::TEST_SEGMENT_ID,
            'tm_keys' => json_encode([
                [
                    'tm' => true,
                    'glos' => false,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'Main',
                    'key' => 'AAAAAAAA',
                    'r' => true,
                    'w' => true,
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null,
                    'penalty' => 0,
                ],
                [
                    'tm' => true,
                    'glos' => false,
                    'owner' => true,
                    'uid_transl' => null,
                    'uid_rev' => null,
                    'name' => 'Second',
                    'key' => 'BBBBBBBB',
                    'r' => true,
                    'w' => false,
                    'r_transl' => null,
                    'w_transl' => null,
                    'r_rev' => null,
                    'w_rev' => null,
                    'source' => null,
                    'target' => null,
                    'penalty' => 0,
                ],
            ], JSON_THROW_ON_ERROR),
        ]));

        $method = new ReflectionMethod($this->worker, '_extractAvailableKeysForUser');
        $keys = $method->invoke($this->worker, $request);

        $this->assertSame(['AAAAAAAA', 'BBBBBBBB'], $keys);
    }

    #[Test]
    public function process_throws_end_queue_exception_for_non_queue_element(): void
    {
        $invalidQueueElement = new class extends AbstractElement {
        };

        $this->expectException(EndQueueException::class);
        $this->worker->process($invalidQueueElement);
    }

    #[Test]
    public function process_builds_request_and_calls_exec(): void
    {
        $spyWorker = new ProcessSpyGetContributionWorker(self::getStubBuilder(AMQHandler::class)->getStub());

        $queueElement = new QueueElement();
        $queueElement->classLoad = GetContributionWorker::class;
        $params = $this->makeBaseRequest()->toArray();
        unset($params['forcedTMEngine'], $params['forcedMTEngine']);
        $queueElement->params = new Params($params);

        $spyWorker->process($queueElement);

        $this->assertSame(1, $spyWorker->execCount);
        $this->assertInstanceOf(GetContributionRequest::class, $spyWorker->capturedRequest);
    }

    #[Test]
    public function test_publishPayload_formats_payload_and_rewrites_mt_created_by(): void
    {
        $worker = new WorkerHarnessGetContributionWorker(self::getStubBuilder(AMQHandler::class)->getStub());
        $request = $this->makeBaseRequest([
            'segmentId' => 42,
            'concordanceSearch' => false,
        ]);

        $featureSet = new FeatureSet();

        $method = new ReflectionMethod($worker, '_publishPayload');
        $method->invoke(
            $worker,
            [[
                'created_by' => 'MT!',
                'segment' => 'line&#10;one',
                'translation' => 'ciao&#10;mondo',
                'match' => '90%',
                'score' => 0.9,
                'raw_translation' => 'ciao',
                'memory_key' => 'k',
            ]],
            $request,
            $featureSet,
            'it-IT',
            false
        );

        $this->assertCount(1, $worker->publishedPayloads);
        $payload = $worker->publishedPayloads[0];

        $this->assertSame('contribution', $payload['_type']);
        $this->assertSame('42', $payload['data']['payload']['id_segment']);
        $this->assertSame(EngineConstants::MT, $payload['data']['payload']['matches'][0]['created_by']);
    }

    #[Test]
    public function test_publishPayload_uses_concordance_and_cross_language_types(): void
    {
        $worker = new WorkerHarnessGetContributionWorker(self::getStubBuilder(AMQHandler::class)->getStub());
        $featureSet = new FeatureSet();

        $concordanceRequest = $this->makeBaseRequest(['concordanceSearch' => true]);
        $crossLangRequest = $this->makeBaseRequest(['concordanceSearch' => false]);

        $method = new ReflectionMethod($worker, '_publishPayload');
        $method->invoke($worker, [], $concordanceRequest, $featureSet, 'it-IT', false);
        $method->invoke($worker, [], $crossLangRequest, $featureSet, 'it-IT', true);

        $this->assertSame('concordance', $worker->publishedPayloads[0]['_type']);
        $this->assertSame('cross_language_matches', $worker->publishedPayloads[1]['_type']);
    }

    #[Test]
    public function test_getMatches_returns_tm_matches_from_tm_engine(): void
    {
        $request = $this->makeBaseRequest();
        $request->setJobStruct(new JobStruct([
            'id' => self::TEST_JOB_ID,
            'id_project' => self::TEST_PROJECT_ID,
            'password' => 'pw',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_first_segment' => self::TEST_SEGMENT_ID,
            'job_last_segment' => self::TEST_SEGMENT_ID,
            'id_tms' => 1,
            'id_mt_engine' => 1,
            'tm_keys' => '[]',
            'only_private_tm' => 0,
        ]));

        $tmEngine = $this->getMockBuilder(MyMemory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfigStruct', 'setMTPenalty', 'get'])
            ->getMock();

        $tmEngine->expects($this->once())->method('getConfigStruct')->willReturn([]);
        $tmEngine->expects($this->once())->method('setMTPenalty')->willReturnSelf();
        $tmEngine->expects($this->once())->method('get')->with($this->callback(function (array $config): bool {
            return $config['source'] === 'en-US' && $config['target'] === 'it-IT';
        }))->willReturn(new GetMemoryResponse([
            'responseStatus' => 200,
            'matches' => [[
                'id' => 1,
                'segment' => 'Hello',
                'translation' => 'Ciao',
                'match' => 0.95,
                'created-by' => 'MyMemory',
                'last-update-date' => '2025-01-01 10:00:00',
                'create-date' => '2025-01-01 10:00:00',
                'tm_properties' => '[]',
                'target_note' => '',
            ]],
        ]));

        $request->forcedTMEngine = $tmEngine;

        $method = new ReflectionMethod($this->worker, '_getMatches');
        [$mt, $matches] = $method->invoke($this->worker, $request, $request->getJobStruct(), 'it-IT', new FeatureSet(), false);

        $this->assertSame([], $mt);
        $this->assertCount(1, $matches);
        $this->assertSame('95%', $matches[0]['match']);
    }

    #[Test]
    public function test_getMatches_calls_mt_engine_when_direct_mt_is_enabled(): void
    {
        $request = $this->makeBaseRequest([
            'mt_quality_value_in_editor' => 100,
        ]);
        $request->setJobStruct(new JobStruct([
            'id' => self::TEST_JOB_ID,
            'id_project' => self::TEST_PROJECT_ID,
            'password' => 'pw',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_first_segment' => self::TEST_SEGMENT_ID,
            'job_last_segment' => self::TEST_SEGMENT_ID,
            'id_tms' => 0,
            'id_mt_engine' => 2,
            'tm_keys' => '[]',
        ]));

        $mtEngine = $this->getMockBuilder(MyMemory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfigStruct', 'setMTPenalty', 'get'])
            ->getMock();

        $mtEngine->expects($this->once())->method('getConfigStruct')->willReturn([]);
        $mtEngine->expects($this->once())->method('setMTPenalty')->willReturnSelf();
        $mtEngine->expects($this->once())->method('get')->with($this->callback(function (array $config): bool {
            return isset($config['pid'], $config['segid'], $config['session']);
        }))->willReturn(new GetMemoryResponse([
            'responseStatus' => 200,
            'matches' => [[
                'id' => 2,
                'segment' => 'Hello',
                'translation' => 'Ciao MT',
                'match' => 0.91,
                'created-by' => 'MT',
                'last-update-date' => '2025-01-01 10:00:00',
                'create-date' => '2025-01-01 10:00:00',
                'tm_properties' => '[]',
                'target_note' => '',
            ]],
        ]));

        $request->forcedMTEngine = $mtEngine;

        $method = new ReflectionMethod($this->worker, '_getMatches');
        [$mt, $matches] = $method->invoke($this->worker, $request, $request->getJobStruct(), 'it-IT', new FeatureSet(), false);

        $this->assertNotEmpty($mt);
        $this->assertSame([], $matches);
        $this->assertSame('91%', $mt['match']);
    }

    #[Test]
    public function test_getMatches_inverts_direction_for_concordance_from_target(): void
    {
        $request = $this->makeBaseRequest([
            'concordanceSearch' => true,
            'fromTarget' => true,
        ]);
        $request->setJobStruct(new JobStruct([
            'id' => self::TEST_JOB_ID,
            'id_project' => self::TEST_PROJECT_ID,
            'password' => 'pw',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_first_segment' => self::TEST_SEGMENT_ID,
            'job_last_segment' => self::TEST_SEGMENT_ID,
            'id_tms' => 1,
            'id_mt_engine' => 1,
            'tm_keys' => '[]',
        ]));

        $tmEngine = $this->getMockBuilder(MyMemory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfigStruct', 'setMTPenalty', 'get'])
            ->getMock();

        $tmEngine->method('getConfigStruct')->willReturn([]);
        $tmEngine->method('setMTPenalty')->willReturnSelf();
        $tmEngine->expects($this->once())->method('get')->with($this->callback(function (array $config): bool {
            return $config['source'] === 'it-IT' && $config['target'] === 'en-US';
        }))->willReturn(new GetMemoryResponse(['responseStatus' => 200, 'matches' => []]));

        $request->forcedTMEngine = $tmEngine;

        $method = new ReflectionMethod($this->worker, '_getMatches');
        $method->invoke($this->worker, $request, $request->getJobStruct(), 'it-IT', new FeatureSet(), false);

        $this->assertTrue(true);
    }

    #[Test]
    public function test_execGetContribution_publishes_primary_and_cross_language_payloads(): void
    {
        $worker = new WorkerHarnessGetContributionWorker(self::getStubBuilder(AMQHandler::class)->getStub());
        $worker->queueMatchResult([], [[
            'match' => '90%',
            'score' => 0.9,
            'created_by' => 'Google',
            'memory_key' => 'k1',
            'segment' => 'Hello world',
            'translation' => 'Ciao mondo',
            'raw_translation' => 'Ciao mondo',
        ]]);
        $worker->queueMatchResult([], [[
            'match' => '80%',
            'score' => 0.8,
            'created_by' => 'Google',
            'memory_key' => 'k2',
            'segment' => 'Hello world',
            'translation' => 'Bonjour monde',
            'raw_translation' => 'Bonjour monde',
        ]]);

        $request = $this->makeBaseRequest([
            'concordanceSearch' => false,
            'segmentId' => null,
            'crossLangTargets' => ['fr-FR', ''],
            'resultNum' => 2,
        ]);

        $method = new ReflectionMethod($worker, '_execGetContribution');
        $method->invoke($worker, $request);

        $this->assertCount(2, $worker->publishedPayloads);
        $this->assertSame('contribution', $worker->publishedPayloads[0]['_type']);
        $this->assertSame('cross_language_matches', $worker->publishedPayloads[1]['_type']);
    }

    #[Test]
    public function test_normalizeMTMatches_applies_concordance_format_and_rewrites_high_score(): void
    {
        $request = $this->makeBaseRequest([
            'concordanceSearch' => true,
            'fromTarget' => false,
        ]);
        $request->contexts = [
            'segment' => 'Hello',
            'context_before' => null,
            'context_after' => null,
        ];

        $matches = [[
            'match' => '90%',
            'score' => 0.95,
            'created_by' => 'Google',
            'memory_key' => 'abc',
            'segment' => '<b>Hello</b>  world',
            'translation' => '<i>Ciao</i>',
            'raw_translation' => 'Ciao',
        ]];

        $this->worker->normalizeMTMatches($matches, $request, new FeatureSet());

        $this->assertSame('ICE_MT', $matches[0]['match']);
        $this->assertStringContainsString('#{Hello}#', $matches[0]['segment']);
        $this->assertSame('Ciao', $matches[0]['translation']);
    }

    #[Test]
    public function test_updateAnalysisSuggestion_updates_new_translation_row(): void
    {
        $this->seedDbFixtures(TranslationStatus::STATUS_NEW);

        $request = $this->makeBaseRequest([
            'segmentId' => self::TEST_SEGMENT_ID,
        ]);
        $request->setJobStruct(new JobStruct([
            'id' => self::TEST_JOB_ID,
            'id_project' => self::TEST_PROJECT_ID,
            'password' => 'pw',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_first_segment' => self::TEST_SEGMENT_ID,
            'job_last_segment' => self::TEST_SEGMENT_ID,
            'tm_keys' => '[]',
        ]));

        $matches = [[
            'created_by' => 'MT!',
            'raw_translation' => 'Traduzione proposta',
            'match' => '98%',
            'segment' => 'Hello',
            'translation' => 'Traduzione proposta',
        ]];

        $method = new ReflectionMethod($this->worker, 'updateAnalysisSuggestion');
        $method->invoke($this->worker, $matches, $request);

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare('SELECT suggestion, suggestion_match, suggestion_source, suggestions_array FROM segment_translations WHERE id_segment = ? AND id_job = ?');
        $stmt->execute([self::TEST_SEGMENT_ID, self::TEST_JOB_ID]);
        $row = $stmt->fetch();

        $this->assertSame('Traduzione proposta', $row['suggestion']);
        $this->assertSame(98, (int)$row['suggestion_match']);
        $this->assertSame(InternalMatchesConstants::MT, $row['suggestion_source']);
        $this->assertNotEmpty($row['suggestions_array']);
    }

    #[Test]
    public function test_updateAnalysisSuggestion_skips_when_translation_is_not_new(): void
    {
        $this->seedDbFixtures(TranslationStatus::STATUS_TRANSLATED);

        $request = $this->makeBaseRequest([
            'segmentId' => self::TEST_SEGMENT_ID,
        ]);
        $request->setJobStruct(new JobStruct([
            'id' => self::TEST_JOB_ID,
            'id_project' => self::TEST_PROJECT_ID,
            'password' => 'pw',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_first_segment' => self::TEST_SEGMENT_ID,
            'job_last_segment' => self::TEST_SEGMENT_ID,
            'tm_keys' => '[]',
        ]));

        $matches = [[
            'created_by' => 'MT!',
            'raw_translation' => 'Ignored update',
            'match' => '97%',
            'segment' => 'Hello',
            'translation' => 'Ignored update',
        ]];

        $method = new ReflectionMethod($this->worker, 'updateAnalysisSuggestion');
        $method->invoke($this->worker, $matches, $request);

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare('SELECT suggestion FROM segment_translations WHERE id_segment = ? AND id_job = ?');
        $stmt->execute([self::TEST_SEGMENT_ID, self::TEST_JOB_ID]);
        $row = $stmt->fetch();

        $this->assertNull($row['suggestion']);
    }

    private function makeBaseRequest(array $overrides = []): TestableGetContributionRequest
    {
        $request = new TestableGetContributionRequest();
        $request->id_client = 'client-test';
        $request->userRole = 'translator';
        $request->segmentId = 10;
        $request->resultNum = 3;
        $request->concordanceSearch = false;
        $request->fromTarget = false;
        $request->crossLangTargets = [];
        $request->id_file = self::TEST_FILE_ID;
        $request->id_job = self::TEST_JOB_ID;
        $request->password = 'pw';
        $request->translation = 'Translated text';
        $request->contexts = [
            'segment' => 'Hello world',
            'context_before' => 'before',
            'context_after' => 'after',
        ];
        $request->setJobStruct(new JobStruct([
            'id' => self::TEST_JOB_ID,
            'id_project' => self::TEST_PROJECT_ID,
            'password' => 'pw',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_first_segment' => self::TEST_SEGMENT_ID,
            'job_last_segment' => self::TEST_SEGMENT_ID,
            'tm_keys' => '[]',
            'id_tms' => 1,
            'id_mt_engine' => 1,
        ]));
        $request->setProjectStruct(new ProjectStruct([
            'id' => self::TEST_PROJECT_ID,
            'id_customer' => 'test@example.org',
            'password' => 'project-pw',
            'name' => 'Project',
            'create_date' => date('Y-m-d H:i:s'),
            'status_analysis' => 'DONE',
            'remote_ip_address' => '127.0.0.1',
        ]));
        $request->setUser(new UserStruct([
            'uid' => 999,
            'email' => 'translator@example.org',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]));

        foreach ($overrides as $key => $value) {
            $request->$key = $value;
        }

        return $request;
    }

    private function seedDbFixtures(string $translationStatus): void
    {
        $conn = Database::obtain()->getConnection();

        $conn->exec("INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" . self::TEST_PROJECT_ID . ", 'coverage@test.org', 'pw_project', 'Coverage Project', NOW(), 'DONE')");
        $conn->exec("INSERT INTO files (id, id_project, filename, source_language, mime_type) VALUES (" . self::TEST_FILE_ID . ", " . self::TEST_PROJECT_ID . ", 'coverage.xliff', 'en-US', 'application/xliff+xml')");
        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . self::TEST_JOB_ID . ", 'pw', " . self::TEST_PROJECT_ID . ", 'en-US', 'it-IT', " . self::TEST_SEGMENT_ID . ", " . self::TEST_SEGMENT_ID . ", 'coverage@test.org', '[]', NOW(), 0)");
        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count) VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_FILE_ID . ", '1', 'Hello world', 'hash-coverage', 2)");
        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date, match_type, suggestion_source, suggestion, suggestion_match, suggestions_array) VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_JOB_ID . ", 'hash-coverage', 'Ciao mondo', '" . $translationStatus . "', 0, NOW(), 'NO_MATCH', '', NULL, NULL, NULL)");
    }

    private function cleanupDbFixtures(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec('DELETE FROM segment_translations WHERE id_job = ' . self::TEST_JOB_ID);
        $conn->exec('DELETE FROM segments WHERE id = ' . self::TEST_SEGMENT_ID);
        $conn->exec('DELETE FROM jobs WHERE id = ' . self::TEST_JOB_ID);
        $conn->exec('DELETE FROM files WHERE id = ' . self::TEST_FILE_ID);
        $conn->exec('DELETE FROM projects WHERE id = ' . self::TEST_PROJECT_ID);
    }
}

class TestableGetContributionRequest extends GetContributionRequest
{
    public ?AbstractEngine $forcedTMEngine = null;
    public ?AbstractEngine $forcedMTEngine = null;

    public function getTMEngine(FeatureSet $featureSet): AbstractEngine
    {
        if ($this->forcedTMEngine !== null) {
            return $this->forcedTMEngine;
        }

        return parent::getTMEngine($featureSet);
    }

    public function getMTEngine(FeatureSet $featureSet): AbstractEngine
    {
        if ($this->forcedMTEngine !== null) {
            return $this->forcedMTEngine;
        }

        return parent::getMTEngine($featureSet);
    }
}

class WorkerHarnessGetContributionWorker extends GetContributionWorker
{
    /** @var array<int, array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}> */
    public array $queuedMatchResults = [];
    /** @var list<array<string, mixed>> */
    public array $publishedPayloads = [];
    /** @var list<array|string> */
    public array $logs = [];

    public function queueMatchResult(array $mt, array $matches): void
    {
        $this->queuedMatchResults[] = [$mt, $matches];
    }

    protected function _getMatches(GetContributionRequest $contributionStruct, JobStruct $jobStruct, string $targetLang, FeatureSet $featureSet, bool $isCrossLang = false): array
    {
        return array_shift($this->queuedMatchResults) ?? [[], []];
    }

    protected function publishToNodeJsClients($_object): void
    {
        $this->publishedPayloads[] = $_object;
    }

    protected function _doLog(array|string $msg): void
    {
        $this->logs[] = $msg;
    }
}

class ProcessSpyGetContributionWorker extends GetContributionWorker
{
    public int $execCount = 0;
    public ?GetContributionRequest $capturedRequest = null;

    protected function _execGetContribution(GetContributionRequest $contributionStruct): void
    {
        $this->execCount++;
        $this->capturedRequest = $contributionStruct;
    }

    protected function _checkDatabaseConnection(): void
    {
    }

    protected function _checkForReQueueEnd(QueueElement $queueElement): void
    {
    }
}
