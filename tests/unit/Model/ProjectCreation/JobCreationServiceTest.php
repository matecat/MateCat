<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\PayableRates\CustomPayableRateStruct;
use Model\ProjectCreation\JobCreationService;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

class JobCreationServiceTest extends AbstractTest
{
    private JobCreationService $service;
    private FeatureSet $featureSet;
    private MatecatLogger $logger;

    public function setUp(): void
    {
        parent::setUp();
        $this->featureSet = $this->createStub(FeatureSet::class);
        $this->logger = $this->createStub(MatecatLogger::class);
        $this->service = new JobCreationService($this->featureSet, $this->logger);
    }

    /**
     * Invoke a private/protected method on JobCreationService for unit testing.
     *
     * @throws ReflectionException
     */
    private function invokeMethod(string $methodName, array $args): mixed
    {
        $ref = new ReflectionClass(JobCreationService::class);
        $method = $ref->getMethod($methodName);

        return $method->invoke($this->service, ...$args);
    }

    private function makeProjectStructure(array $overrides = []): ProjectStructure
    {
        return new ProjectStructure(array_merge([
            'id_project' => 999,
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'private_tm_key' => [],
            'result' => ['errors' => []],
        ], $overrides));
    }

    // =========================================================================
    // resolvePayableRates — 4 branches
    // =========================================================================

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testResolvePayableRatesMtQeWorkflowTakesPriority(): void
    {
        $mtQeRates = ['rate1' => 0.5, 'rate2' => 0.8];
        $ps = $this->makeProjectStructure([
            'mt_qe_workflow_payable_rate' => $mtQeRates,
            'payable_rate_model' => ['some' => 'model'], // should be ignored
        ]);

        [$rates, $template] = $this->invokeMethod('resolvePayableRates', [$ps, 'it-IT']);

        $this->assertSame(json_encode($mtQeRates), $rates);
        $this->assertNull($template);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testResolvePayableRatesFromModelObject(): void
    {
        // When payable_rate_model is set (not mt_qe_workflow), it should
        // hydrate a CustomPayableRateStruct and call getPayableRates()
        $modelData = [
            'version' => 1,
            'payable_rate_template_name' => 'test_model',
            'breakdowns' => [
                'default' => [
                    'NO_MATCH' => 100,
                    'ICE' => 0,
                    'ICE_MT' => 0,
                ],
            ],
        ];
        $ps = $this->makeProjectStructure([
            'mt_qe_workflow_payable_rate' => null,
            'payable_rate_model' => $modelData,
        ]);

        [$rates, $template] = $this->invokeMethod('resolvePayableRates', [$ps, 'it-IT']);

        $this->assertIsString($rates);
        $this->assertInstanceOf(CustomPayableRateStruct::class, $template);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testResolvePayableRatesDefaultBranch(): void
    {
        // When nothing is set, falls through to PayableRates::getPayableRates
        // and applies features->filter()
        $this->featureSet->method('filter')
            ->willReturnCallback(fn($name, $rates) => $rates);

        $ps = $this->makeProjectStructure([
            'mt_qe_workflow_payable_rate' => null,
            'payable_rate_model' => null,
            'payable_rate_model_id' => null,
        ]);

        [$rates, $template] = $this->invokeMethod('resolvePayableRates', [$ps, 'it-IT']);

        $this->assertIsString($rates);
        $this->assertNull($template);
        // Verify it's valid JSON
        $this->assertNotNull(json_decode($rates, true));
    }

    // =========================================================================
    // buildTmKeysJson
    // =========================================================================

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBuildTmKeysJsonWithNoKeys(): void
    {
        $ps = $this->makeProjectStructure(['private_tm_key' => []]);

        $result = $this->invokeMethod('buildTmKeysJson', [$ps]);

        $this->assertSame('[]', $result);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBuildTmKeysJsonMapsKeysCorrectly(): void
    {
        $ps = $this->makeProjectStructure([
            'id_project' => 42,
            'private_tm_key' => [
                [
                    'key' => 'abc123',
                    'name' => 'My Key',
                    'r' => true,
                    'w' => true,
                    'penalty' => 5,
                ],
            ],
        ]);

        $result = $this->invokeMethod('buildTmKeysJson', [$ps]);

        $decoded = json_decode($result, true);
        $this->assertCount(1, $decoded);
        $this->assertSame('abc123', $decoded[0]['key']);
        $this->assertSame('My Key', $decoded[0]['name']);
        $this->assertTrue($decoded[0]['tm']);
        $this->assertTrue($decoded[0]['glos']);
        $this->assertTrue($decoded[0]['owner']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBuildTmKeysJsonReplacesPidPlaceholder(): void
    {
        $ps = $this->makeProjectStructure([
            'id_project' => 42,
            'private_tm_key' => [
                [
                    'key' => 'key1',
                    'name' => '{{pid}}',
                    'r' => true,
                    'w' => true,
                ],
            ],
        ]);

        $result = $this->invokeMethod('buildTmKeysJson', [$ps]);

        $this->assertStringNotContainsString('{{pid}}', $result);
        $this->assertStringContainsString('42', $result);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBuildTmKeysJsonDefaultPenaltyIsZero(): void
    {
        $ps = $this->makeProjectStructure([
            'id_project' => 1,
            'private_tm_key' => [
                [
                    'key' => 'key1',
                    'name' => 'test',
                    'r' => true,
                    'w' => false,
                    // no 'penalty' key
                ],
            ],
        ]);

        $result = $this->invokeMethod('buildTmKeysJson', [$ps]);

        $decoded = json_decode($result, true);
        $this->assertSame(0, $decoded[0]['penalty']);
    }

    // =========================================================================
    // buildJobStruct
    // =========================================================================

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBuildJobStructPopulatesAllFields(): void
    {
        $ps = $this->makeProjectStructure([
            'id_project' => 42,
            'source_language' => 'en-US',
            'tms_engine' => 1,
            'target_language_mt_engine_association' => ['it-IT' => 2],
            'job_subject' => 'general',
            'owner' => 'owner@example.com',
            'only_private' => false,
        ]);

        $minMax = ['job_first_segment' => 100, 'job_last_segment' => 200];

        $job = $this->invokeMethod('buildJobStruct', [
            $ps, 'it-IT', '{"rate":1}', '[]', $minMax, 5000,
        ]);

        $this->assertInstanceOf(JobStruct::class, $job);
        $this->assertSame(42, $job->id_project);
        $this->assertSame('en-US', $job->source);
        $this->assertSame('it-IT', $job->target);
        $this->assertSame(1, $job->id_tms);
        $this->assertSame(2, $job->id_mt_engine);
        $this->assertSame('general', $job->subject);
        $this->assertSame('owner@example.com', $job->owner);
        $this->assertSame(100, $job->job_first_segment);
        $this->assertSame(200, $job->job_last_segment);
        $this->assertSame('[]', $job->tm_keys);
        $this->assertSame('{"rate":1}', $job->payable_rates);
        $this->assertSame(5000, $job->total_raw_wc);
        $this->assertNotEmpty($job->password);
        $this->assertNotEmpty($job->create_date);
        $this->assertNotEmpty($job->last_update);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBuildJobStructDefaultTmsEngineIsOne(): void
    {
        $ps = $this->makeProjectStructure([
            'id_project' => 1,
            'source_language' => 'en-US',
            'tms_engine' => null,
            'target_language_mt_engine_association' => ['it-IT' => 1],
            'job_subject' => 'general',
            'owner' => 'test@test.com',
            'only_private' => false,
        ]);

        $minMax = ['job_first_segment' => 1, 'job_last_segment' => 10];
        $job = $this->invokeMethod('buildJobStruct', [
            $ps, 'it-IT', '{}', '[]', $minMax, 100,
        ]);

        $this->assertSame(1, $job->id_tms);
    }

    // =========================================================================
    // updateJobTracking
    // =========================================================================

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testUpdateJobTrackingPopulatesArrayJobs(): void
    {
        $ps = $this->makeProjectStructure();
        $ps->array_jobs = [
            'job_list' => [],
            'job_pass' => [],
            'job_segments' => [],
            'job_languages' => [],
            'payable_rates' => [],
        ];

        $job = new JobStruct();
        $job->id = 42;
        $job->password = 'pass123';
        $job->target = 'it-IT';

        $minMax = ['job_first_segment' => 1, 'job_last_segment' => 10];

        $this->invokeMethod('updateJobTracking', [$ps, $job, '{"rate":1}', $minMax]);

        $this->assertContains(42, $ps->array_jobs['job_list']);
        $this->assertContains('pass123', $ps->array_jobs['job_pass']);
        $this->assertSame($minMax, $ps->array_jobs['job_segments']['42-pass123']);
        $this->assertSame('42:it-IT', $ps->array_jobs['job_languages'][42]);
        $this->assertSame('{"rate":1}', $ps->array_jobs['payable_rates'][42]);
    }

    // =========================================================================
    // associatePayableRateModel
    // =========================================================================

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testAssociatePayableRateModelSkipsWhenNoModelId(): void
    {
        $ps = $this->makeProjectStructure(['payable_rate_model_id' => null]);
        $job = new JobStruct();
        $job->id = 1;

        // Should not throw — just returns early
        $this->invokeMethod('associatePayableRateModel', [$job, $ps, null]);
        $this->assertTrue(true); // no exception = pass
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testAssociatePayableRateModelSkipsWhenNoTemplate(): void
    {
        $ps = $this->makeProjectStructure(['payable_rate_model_id' => 99]);
        $job = new JobStruct();
        $job->id = 1;

        // Template is null — should not try to call assocModelToJob
        $this->invokeMethod('associatePayableRateModel', [$job, $ps, null]);
        $this->assertTrue(true);
    }

    // =========================================================================
    // createJobsForTargetLanguages — validation
    // =========================================================================

    #[Test]
    public function testCreateJobsThrowsWhenNoSegments(): void
    {
        $ps = $this->makeProjectStructure();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Job cannot be created. No segments found!');

        $this->service->createJobsForTargetLanguages($ps, [], 100);
    }

    #[Test]
    public function testCreateJobsThrowsWhenMissingFirstSegment(): void
    {
        $ps = $this->makeProjectStructure();

        $this->expectException(Exception::class);

        $this->service->createJobsForTargetLanguages(
            $ps,
            ['job_last_segment' => 10],
            100
        );
    }

    #[Test]
    public function testCreateJobsThrowsWhenMissingLastSegment(): void
    {
        $ps = $this->makeProjectStructure();

        $this->expectException(Exception::class);

        $this->service->createJobsForTargetLanguages(
            $ps,
            ['job_first_segment' => 1],
            100
        );
    }
}
