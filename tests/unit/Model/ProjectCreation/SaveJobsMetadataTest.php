<?php

namespace unit\Model\ProjectCreation;

use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;


/**
 * Unit tests for {@see \Model\ProjectCreation\JobCreationService::saveJobsMetadata()}.
 *
 * Tests verify that job-level metadata from ProjectStructure DTO
 * is correctly collected, transformed, and persisted via `JobsMetadataDao::set()`.
 *
 * @see REFACTORING_PLAN.md — Step 0d
 */
class SaveJobsMetadataTest extends AbstractTest
{
    private TestableJobCreationService $service;
    private ProjectStructure $projectStructure;
    private JobStruct $job;

    /**
     * Collected calls to the mocked JobsMetadataDao::set().
     * Each entry is [int $id_job, string $password, string $key, mixed $value].
     *
     * @var array<int, array{0: int, 1: string, 2: string, 3: mixed}>
     */
    private array $daoSetCalls = [];

    private const JOB_ID       = 42;
    private const JOB_PASSWORD = 'abc123';

    public function setUp(): void
    {
        parent::setUp();

        $featureSet = $this->createStub(FeatureSet::class);
        $logger = $this->createStub(MatecatLogger::class);

        $this->service = new TestableJobCreationService($featureSet, $logger);

        // Build a ProjectStructure with defaults
        $this->projectStructure = new ProjectStructure([
            'id_project' => 999,
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'private_tm_key' => [],
            'result' => ['errors' => []],
            // Default subfiltering_handlers (always required)
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => '[]',
        ]);

        // Create a stub JobsMetadataDao that records set() calls
        $this->daoSetCalls = [];
        $stubDao = $this->createStub(JobsMetadataDao::class);
        $stubDao->method('set')
            ->willReturnCallback(function (int $idJob, string $password, string $key, mixed $value): null {
                $this->daoSetCalls[] = [$idJob, $password, $key, $value];

                return null;
            });
        $this->service->setJobsMetadataDao($stubDao);

        // Build a minimal JobStruct
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

    /**
     * Set values on the projectStructure, then invoke saveJobsMetadata.
     */
    private function setConfigAndSave(array $extras = []): void
    {
        foreach ($extras as $key => $value) {
            $this->projectStructure->$key = $value;
        }
        $this->service->callSaveJobsMetadata($this->job, $this->projectStructure);
    }

    /**
     * Find all DAO set() calls for a given key.
     *
     * @return array<int, array{0: int, 1: string, 2: string, 3: mixed}>
     */
    private function findDaoCallsByKey(string $key): array
    {
        return array_values(
            array_filter(
                $this->daoSetCalls,
                static fn(array $call) => $call[2] === $key
            )
        );
    }

    /**
     * Assert that every DAO set() call used the correct job ID and password.
     */
    private function assertAllCallsUseJobCredentials(): void
    {
        foreach ($this->daoSetCalls as $i => $call) {
            $this->assertSame(self::JOB_ID, $call[0], "Call #{$i} should use job ID " . self::JOB_ID);
            $this->assertSame(self::JOB_PASSWORD, $call[1], "Call #{$i} should use job password");
        }
    }

    // =========================================================================
    // SUBFILTERING_HANDLERS — always persisted unconditionally
    // =========================================================================

    #[Test]
    public function testSubfilteringHandlersIsAlwaysPersisted(): void
    {
        $this->setConfigAndSave();

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value);
        $this->assertCount(1, $calls);
        $this->assertSame('[]', $calls[0][3]);
    }

    #[Test]
    public function testSubfilteringHandlersWithNonEmptyValue(): void
    {
        $handlers = json_encode([['handler' => 'some_handler']]);
        $this->setConfigAndSave([
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => $handlers,
        ]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value);
        $this->assertCount(1, $calls);
        $this->assertSame($handlers, $calls[0][3]);
    }

    // =========================================================================
    // All DAO calls use correct job ID and password
    // =========================================================================

    #[Test]
    public function testAllDaoSetCallsUseCorrectJobIdAndPassword(): void
    {
        $this->setConfigAndSave([
            'public_tm_penalty'           => '5',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'source',
            'tm_prioritization'           => true,
        ]);

        $this->assertGreaterThan(1, count($this->daoSetCalls));
        $this->assertAllCallsUseJobCredentials();
    }

    // =========================================================================
    // Empty project structure — only SUBFILTERING_HANDLERS persisted
    // =========================================================================

    #[Test]
    public function testEmptyProjectStructureOnlyPersistsSubfilteringHandlers(): void
    {
        $this->setConfigAndSave();

        $this->assertCount(1, $this->daoSetCalls);
        $this->assertSame(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $this->daoSetCalls[0][2]);
    }

    // =========================================================================
    // public_tm_penalty
    // =========================================================================

    #[Test]
    public function testPublicTmPenaltyIsPersistedWhenSet(): void
    {
        $this->setConfigAndSave(['public_tm_penalty' => '15']);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value);
        $this->assertCount(1, $calls);
        $this->assertSame('15', $calls[0][3]);
    }

    #[Test]
    public function testPublicTmPenaltyIsNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value);
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // character_counter_count_tags — truthy → "1", falsy → "0"
    // =========================================================================

    #[Test]
    public function testCharacterCounterCountTagsTruthyPersistsOne(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => true]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value);
        $this->assertCount(1, $calls);
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterCountTagsFalsyPersistsZero(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => false]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value);
        $this->assertCount(1, $calls);
        $this->assertSame('0', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterCountTagsNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value);
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // character_counter_mode
    // =========================================================================

    #[Test]
    public function testCharacterCounterModeIsPersistedWhenSet(): void
    {
        $this->setConfigAndSave(['character_counter_mode' => 'source']);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value);
        $this->assertCount(1, $calls);
        $this->assertSame('source', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterModeIsNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value);
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // tm_prioritization — truthy → 1 (int), falsy → 0 (int)
    // =========================================================================

    #[Test]
    public function testTmPrioritizationTruthyPersistsOne(): void
    {
        $this->setConfigAndSave(['tm_prioritization' => true]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value);
        $this->assertCount(1, $calls);
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testTmPrioritizationFalsyPersistsZero(): void
    {
        $this->setConfigAndSave(['tm_prioritization' => false]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value);
        $this->assertCount(1, $calls);
        $this->assertSame('0', $calls[0][3]);
    }

    #[Test]
    public function testTmPrioritizationNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value);
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // dialect_strict — JSON-decoded, only matching lang is persisted
    // =========================================================================

    #[Test]
    public function testDialectStrictPersistsMatchingLanguageValue(): void
    {
        $dialectJson = json_encode(['it-IT' => 'strict_value', 'fr-FR' => 'other_value']);
        $this->setConfigAndSave(['dialect_strict' => $dialectJson]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value);
        $this->assertCount(1, $calls);
        $this->assertSame('strict_value', $calls[0][3]);
    }

    #[Test]
    public function testDialectStrictDoesNotPersistNonMatchingLanguage(): void
    {
        $dialectJson = json_encode(['fr-FR' => 'french_value', 'de-DE' => 'german_value']);
        $this->setConfigAndSave(['dialect_strict' => $dialectJson]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value);
        $this->assertCount(0, $calls);
    }

    #[Test]
    public function testDialectStrictTrimsWhitespaceForMatching(): void
    {
        $dialectJson = json_encode([' it-IT ' => 'trimmed_value']);

        // Also set target with trailing whitespace to test trim on both sides
        $this->job->target = ' it-IT ';
        $this->setConfigAndSave(['dialect_strict' => $dialectJson]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value);
        $this->assertCount(1, $calls);
        $this->assertSame('trimmed_value', $calls[0][3]);
    }

    #[Test]
    public function testDialectStrictNotPersistedWhenNotSet(): void
    {
        $this->setConfigAndSave();

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value);
        $this->assertCount(0, $calls);
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

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value);
        $this->assertCount(1, $calls);
        $this->assertSame('italian_value', $calls[0][3]);
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

        // Should have 6 DAO calls total:
        // public_tm_penalty, character_counter_count_tags, character_counter_mode,
        // tm_prioritization, dialect_strict, subfiltering_handlers
        $this->assertCount(6, $this->daoSetCalls);

        // Verify each key
        $this->assertCount(1, $this->findDaoCallsByKey(JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value));
        $this->assertSame('10', $this->findDaoCallsByKey(JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value)[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value));
        $this->assertSame('1', $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value)[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value));
        $this->assertSame('target', $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value)[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value));
        $this->assertSame('1', $this->findDaoCallsByKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value)[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value));
        $this->assertSame('strict', $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value)[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value));
        $this->assertSame($handlers, $this->findDaoCallsByKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value)[0][3]);

        // All calls use correct credentials
        $this->assertAllCallsUseJobCredentials();
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function testCharacterCounterCountTagsWithIntegerOnePersistsOne(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => 1]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value);
        $this->assertCount(1, $calls);
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterCountTagsWithIntegerZeroPersistsZero(): void
    {
        $this->setConfigAndSave(['character_counter_count_tags' => 0]);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value);
        $this->assertCount(1, $calls);
        $this->assertSame('0', $calls[0][3]);
    }

    #[Test]
    public function testTmPrioritizationWithStringOnePersistsOne(): void
    {
        $this->setConfigAndSave(['tm_prioritization' => '1']);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::TM_PRIORITIZATION->value);
        $this->assertCount(1, $calls);
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testDialectStrictWithEmptyJsonObjectPersistsNothing(): void
    {
        $this->setConfigAndSave(['dialect_strict' => '{}']);

        $calls = $this->findDaoCallsByKey(JobsMetadataMarshaller::DIALECT_STRICT->value);
        $this->assertCount(0, $calls);
    }

    #[Test]
    public function testDaoCallOrderMatchesCodeOrder(): void
    {
        $dialectJson = json_encode(['it-IT' => 'yes']);
        $this->setConfigAndSave([
            'public_tm_penalty'           => '5',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'source',
            'tm_prioritization'           => true,
            'dialect_strict'              => $dialectJson,
        ]);

        // The code processes keys in this order:
        // 1. public_tm_penalty
        // 2. character_counter_count_tags
        // 3. character_counter_mode
        // 4. tm_prioritization
        // 5. dialect_strict
        // 6. subfiltering_handlers (always last, unconditional)
        $keys = array_map(fn(array $call) => $call[2], $this->daoSetCalls);
        $this->assertSame([
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value,
            JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value,
            JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value,
            JobsMetadataMarshaller::TM_PRIORITIZATION->value,
            JobsMetadataMarshaller::DIALECT_STRICT->value,
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value,
        ], $keys);
    }
}
