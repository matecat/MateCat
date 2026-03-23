<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;


/**
 * Unit tests for {@see \Model\ProjectCreation\JobCreationService::saveJobsMetadata()}.
 *
 * Tests verify that job-level metadata from ProjectStructure DTO
 * is correctly collected, transformed, and persisted via `JobsMetadataDao::bulkSet()`.
 *
 * @see REFACTORING_PLAN.md — Step 0d
 */
class SaveJobsMetadataTest extends AbstractTest
{
    private TestableJobCreationService $service;
    private ProjectStructure $projectStructure;
    private JobStruct $job;

    private int $capturedIdJob = 0;
    private string $capturedPassword = '';

    /** @var array<string, string> */
    private array $capturedMetadata = [];

    private const JOB_ID       = 42;
    private const JOB_PASSWORD = 'abc123';

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $featureSet = $this->createStub(FeatureSet::class);
        $logger = $this->createStub(MatecatLogger::class);

        $this->service = new TestableJobCreationService($featureSet, $logger);

        $this->projectStructure = new ProjectStructure([
            'id_project' => 999,
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'private_tm_key' => [],
            'result' => ['errors' => []],
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => '[]',
        ]);

        $this->capturedIdJob = 0;
        $this->capturedPassword = '';
        $this->capturedMetadata = [];

        $stubDao = $this->createStub(JobsMetadataDao::class);
        $stubDao->method('bulkSet')
            ->willReturnCallback(function (int $idJob, string $password, array $metadata): void {
                $this->capturedIdJob = $idJob;
                $this->capturedPassword = $password;
                $this->capturedMetadata = $metadata;
            });
        $this->service->setJobsMetadataDao($stubDao);

        $this->job             = new JobStruct();
        $this->job->id         = self::JOB_ID;
        $this->job->password   = self::JOB_PASSWORD;
        $this->job->id_project = 999;
        $this->job->source     = 'en-US';
        $this->job->target     = 'it-IT';
        $this->job->job_first_segment = 1;
        $this->job->job_last_segment  = 10;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function setConfigAndSave(array $extras = []): void
    {
        foreach ($extras as $key => $value) {
            $this->projectStructure->$key = $value;
        }
        $this->service->callSaveJobsMetadata($this->job, $this->projectStructure);
    }

    // =========================================================================
    // SUBFILTERING_HANDLERS — always persisted unconditionally
    // =========================================================================

    #[Test]
    public function testSubfilteringHandlersIsAlwaysPersisted(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayHasKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $this->capturedMetadata);
        $this->assertSame('[]', $this->capturedMetadata[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value]);
    }

    #[Test]
    public function testSubfilteringHandlersWithNonEmptyValue(): void
    {
        $handlers = json_encode([['handler' => 'some_handler']]);
        $this->setConfigAndSave([
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => $handlers,
        ]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $this->capturedMetadata);
        $this->assertSame($handlers, $this->capturedMetadata[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value]);
    }

    // =========================================================================
    // bulkSet() uses correct job ID and password
    // =========================================================================

    #[Test]
    public function testBulkSetUsesCorrectJobIdAndPassword(): void
    {
        $this->setConfigAndSave([
            'public_tm_penalty'           => '5',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'source',
            'tm_prioritization'           => true,
        ]);

        $this->assertSame(self::JOB_ID, $this->capturedIdJob);
        $this->assertSame(self::JOB_PASSWORD, $this->capturedPassword);
    }

    // =========================================================================
    // Empty project structure — only SUBFILTERING_HANDLERS persisted
    // =========================================================================

    #[Test]
    public function testEmptyProjectStructureOnlyPersistsSubfilteringHandlers(): void
    {
        $this->setConfigAndSave();

        $this->assertCount(1, $this->capturedMetadata);
        $this->assertArrayHasKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $this->capturedMetadata);
    }

    // =========================================================================
    // public_tm_penalty
    // =========================================================================

    #[Test]
    public function testPublicTmPenaltyIsPersistedWhenSet(): void
    {
        $this->setConfigAndSave(['public_tm_penalty' => '15']);

        $this->assertArrayHasKey(JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, $this->capturedMetadata);
        $this->assertSame('15', $this->capturedMetadata[JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value]);
    }

    #[Test]
    public function testPublicTmPenaltyIsNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, $this->capturedMetadata);
    }

    // =========================================================================
    // character_counter_count_tags — truthy → "1", falsy → "0"
    // =========================================================================

    #[Test]
    public function testCharacterCounterCountTagsTruthyPersistsOne(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => true]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value, $this->capturedMetadata);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value]);
    }

    #[Test]
    public function testCharacterCounterCountTagsFalsyPersistsZero(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => false]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value, $this->capturedMetadata);
        $this->assertSame('0', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value]);
    }

    #[Test]
    public function testCharacterCounterCountTagsNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value, $this->capturedMetadata);
    }

    // =========================================================================
    // character_counter_mode
    // =========================================================================

    #[Test]
    public function testCharacterCounterModeIsPersistedWhenSet(): void
    {
        $this->setConfigAndSave(['character_counter_mode' => 'source']);

        $this->assertArrayHasKey(JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value, $this->capturedMetadata);
        $this->assertSame('source', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value]);
    }

    #[Test]
    public function testCharacterCounterModeIsNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value, $this->capturedMetadata);
    }

    // =========================================================================
    // tm_prioritization — truthy → 1 (int), falsy → 0 (int)
    // =========================================================================

    #[Test]
    public function testTmPrioritizationTruthyPersistsOne(): void
    {
        $this->setConfigAndSave(['tm_prioritization' => true]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value, $this->capturedMetadata);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::TM_PRIORITIZATION->value]);
    }

    #[Test]
    public function testTmPrioritizationFalsyPersistsZero(): void
    {
        $this->setConfigAndSave(['tm_prioritization' => false]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value, $this->capturedMetadata);
        $this->assertSame('0', $this->capturedMetadata[JobsMetadataMarshaller::TM_PRIORITIZATION->value]);
    }

    #[Test]
    public function testTmPrioritizationNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value, $this->capturedMetadata);
    }

    // =========================================================================
    // dialect_strict — JSON-decoded, only matching lang is persisted
    // =========================================================================

    #[Test]
    public function testDialectStrictPersistsMatchingLanguageValue(): void
    {
        $dialectJson = json_encode(['it-IT' => 'strict_value', 'fr-FR' => 'other_value']);
        $this->setConfigAndSave(['dialect_strict' => $dialectJson]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
        $this->assertSame('strict_value', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
    }

    #[Test]
    public function testDialectStrictDoesNotPersistNonMatchingLanguage(): void
    {
        $dialectJson = json_encode(['fr-FR' => 'french_value', 'de-DE' => 'german_value']);
        $this->setConfigAndSave(['dialect_strict' => $dialectJson]);

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
    }

    #[Test]
    public function testDialectStrictTrimsWhitespaceForMatching(): void
    {
        $dialectJson = json_encode([' it-IT ' => 'trimmed_value']);

        $this->job->target = ' it-IT ';
        $this->setConfigAndSave(['dialect_strict' => $dialectJson]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
        $this->assertSame('trimmed_value', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
    }

    #[Test]
    public function testDialectStrictNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
    }

    #[Test]
    public function testDialectStrictWithMultipleLanguagesOnlyPersistsMatch(): void
    {
        $dialectJson = json_encode([
            'en-US' => 'english_value',
            'it-IT' => 'italian_value',
            'fr-FR' => 'french_value',
        ]);
        $this->setConfigAndSave(['dialect_strict' => $dialectJson]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
        $this->assertSame('italian_value', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
    }

    // =========================================================================
    // Combined scenario
    // =========================================================================

    #[Test]
    public function testCombinedScenarioWithAllOptions(): void
    {
        $dialectJson = json_encode(['it-IT' => 'strict']);
        $handlers = json_encode([['handler' => 'xliff']]);
        $this->setConfigAndSave([
            'public_tm_penalty'           => '10',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'target',
            'tm_prioritization'           => true,
            'dialect_strict'              => $dialectJson,
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => $handlers,
        ]);

        $this->assertCount(6, $this->capturedMetadata);

        $this->assertSame('10', $this->capturedMetadata[JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value]);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value]);
        $this->assertSame('target', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value]);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::TM_PRIORITIZATION->value]);
        $this->assertSame('strict', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
        $this->assertSame($handlers, $this->capturedMetadata[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value]);

        $this->assertSame(self::JOB_ID, $this->capturedIdJob);
        $this->assertSame(self::JOB_PASSWORD, $this->capturedPassword);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function testCharacterCounterCountTagsWithIntegerOnePersistsOne(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => 1]);

        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value]);
    }

    #[Test]
    public function testCharacterCounterCountTagsWithIntegerZeroPersistsZero(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => 0]);

        $this->assertSame('0', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value]);
    }

    #[Test]
    public function testTmPrioritizationWithStringOnePersistsOne(): void
    {
        $this->setConfigAndSave(['tm_prioritization' => '1']);

        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::TM_PRIORITIZATION->value]);
    }

    #[Test]
    public function testDialectStrictWithEmptyJsonObjectPersistsNothing(): void
    {
        $this->setConfigAndSave(['dialect_strict' => '{}']);

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
    }

    #[Test]
    public function testMetadataKeyOrderMatchesCodeOrder(): void
    {
        $dialectJson = json_encode(['it-IT' => 'yes']);
        $this->setConfigAndSave([
            'public_tm_penalty'           => '5',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'source',
            'tm_prioritization'           => true,
            'dialect_strict'              => $dialectJson,
        ]);

        $this->assertSame([
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value,
            JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value,
            JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value,
            JobsMetadataMarshaller::TM_PRIORITIZATION->value,
            JobsMetadataMarshaller::DIALECT_STRICT->value,
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value,
        ], array_keys($this->capturedMetadata));
    }
}
