<?php

namespace unit\Model\ProjectManager;

use ArrayObject;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Collections\RecursiveArrayObject;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectManager\ProjectManager::saveJobsMetadata()}.
 *
 * Tests verify that job-level metadata from `$projectStructure` is correctly
 * collected, transformed, and persisted via `JobsMetadataDao::set()`.
 *
 * @see REFACTORING_PLAN.md — Step 0d
 */
class SaveJobsMetadataTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private string $originalFileStorageMethod;
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

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->originalFileStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $featureSet = new FeatureSet();
        /** @var MateCatFilter $filter */
        $filter = MateCatFilter::getInstance($featureSet, 'en-US', 'it-IT');
        $filesMetadataDao = $this->createStub(MetadataDao::class);
        $logger = $this->createStub(MatecatLogger::class);

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest($filter, $featureSet, $filesMetadataDao, $logger);

        // Create a stub JobsMetadataDao that records set() calls
        $this->daoSetCalls = [];
        $stubDao = $this->createStub(JobsMetadataDao::class);
        $stubDao->method('set')
            ->willReturnCallback(function (int $idJob, string $password, string $key, mixed $value): null {
                $this->daoSetCalls[] = [$idJob, $password, $key, $value];

                return null;
            });
        $this->pm->setJobsMetadataDao($stubDao);

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

    public function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
        parent::tearDown();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an ArrayObject simulating the relevant slice of $projectStructure.
     * SUBFILTERING_HANDLERS is always required (unconditional).
     */
    private function buildProjectStructure(array $extras = []): ArrayObject
    {
        $base = [
            JobsMetadataDao::SUBFILTERING_HANDLERS => '[]',
        ];

        return new ArrayObject(array_merge($base, $extras));
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
        $ps = $this->buildProjectStructure();
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey(JobsMetadataDao::SUBFILTERING_HANDLERS);
        $this->assertCount(1, $calls);
        $this->assertSame('[]', $calls[0][3]);
    }

    #[Test]
    public function testSubfilteringHandlersWithNonEmptyValue(): void
    {
        $handlers = json_encode([['handler' => 'some_handler']]);
        $ps = $this->buildProjectStructure([
            JobsMetadataDao::SUBFILTERING_HANDLERS => $handlers,
        ]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey(JobsMetadataDao::SUBFILTERING_HANDLERS);
        $this->assertCount(1, $calls);
        $this->assertSame($handlers, $calls[0][3]);
    }

    // =========================================================================
    // All DAO calls use correct job ID and password
    // =========================================================================

    #[Test]
    public function testAllDaoSetCallsUseCorrectJobIdAndPassword(): void
    {
        $ps = $this->buildProjectStructure([
            'public_tm_penalty'           => '5',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'source',
            'tm_prioritization'           => true,
        ]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $this->assertGreaterThan(1, count($this->daoSetCalls));
        $this->assertAllCallsUseJobCredentials();
    }

    // =========================================================================
    // Empty project structure — only SUBFILTERING_HANDLERS persisted
    // =========================================================================

    #[Test]
    public function testEmptyProjectStructureOnlyPersistsSubfilteringHandlers(): void
    {
        $ps = $this->buildProjectStructure();
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $this->assertCount(1, $this->daoSetCalls);
        $this->assertSame(JobsMetadataDao::SUBFILTERING_HANDLERS, $this->daoSetCalls[0][2]);
    }

    // =========================================================================
    // public_tm_penalty
    // =========================================================================

    #[Test]
    public function testPublicTmPenaltyIsPersistedWhenSet(): void
    {
        $ps = $this->buildProjectStructure(['public_tm_penalty' => '15']);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('public_tm_penalty');
        $this->assertCount(1, $calls);
        $this->assertSame('15', $calls[0][3]);
    }

    #[Test]
    public function testPublicTmPenaltyIsNotPersistedWhenNotSet(): void
    {
        $ps = $this->buildProjectStructure();
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('public_tm_penalty');
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // character_counter_count_tags — truthy → "1", falsy → "0"
    // =========================================================================

    #[Test]
    public function testCharacterCounterCountTagsTruthyPersistsOne(): void
    {
        $ps = $this->buildProjectStructure(['character_counter_count_tags' => true]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('character_counter_count_tags');
        $this->assertCount(1, $calls);
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterCountTagsFalsyPersistsZero(): void
    {
        $ps = $this->buildProjectStructure(['character_counter_count_tags' => false]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('character_counter_count_tags');
        $this->assertCount(1, $calls);
        $this->assertSame('0', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterCountTagsNotPersistedWhenNotSet(): void
    {
        $ps = $this->buildProjectStructure();
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('character_counter_count_tags');
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // character_counter_mode
    // =========================================================================

    #[Test]
    public function testCharacterCounterModeIsPersistedWhenSet(): void
    {
        $ps = $this->buildProjectStructure(['character_counter_mode' => 'source']);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('character_counter_mode');
        $this->assertCount(1, $calls);
        $this->assertSame('source', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterModeIsNotPersistedWhenNotSet(): void
    {
        $ps = $this->buildProjectStructure();
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('character_counter_mode');
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // tm_prioritization — truthy → 1 (int), falsy → 0 (int)
    // =========================================================================

    #[Test]
    public function testTmPrioritizationTruthyPersistsOne(): void
    {
        $ps = $this->buildProjectStructure(['tm_prioritization' => true]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('tm_prioritization');
        $this->assertCount(1, $calls);
        // The code passes `1` (int) but the DAO's `string $value` type-hint
        // coerces it to "1" before our callback receives it.
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testTmPrioritizationFalsyPersistsZero(): void
    {
        $ps = $this->buildProjectStructure(['tm_prioritization' => false]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('tm_prioritization');
        $this->assertCount(1, $calls);
        // The code passes `0` (int) but the DAO's `string $value` type-hint
        // coerces it to "0".
        $this->assertSame('0', $calls[0][3]);
    }

    #[Test]
    public function testTmPrioritizationNotPersistedWhenNotSet(): void
    {
        $ps = $this->buildProjectStructure();
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('tm_prioritization');
        $this->assertCount(0, $calls);
    }

    // =========================================================================
    // dialect_strict — JSON-decoded, only matching lang is persisted
    // =========================================================================

    #[Test]
    public function testDialectStrictPersistsMatchingLanguageValue(): void
    {
        $dialectJson = json_encode(['it-IT' => 'strict_value', 'fr-FR' => 'other_value']);
        $ps = $this->buildProjectStructure(['dialect_strict' => $dialectJson]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('dialect_strict');
        $this->assertCount(1, $calls);
        $this->assertSame('strict_value', $calls[0][3]);
    }

    #[Test]
    public function testDialectStrictDoesNotPersistNonMatchingLanguage(): void
    {
        $dialectJson = json_encode(['fr-FR' => 'french_value', 'de-DE' => 'german_value']);
        $ps = $this->buildProjectStructure(['dialect_strict' => $dialectJson]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('dialect_strict');
        $this->assertCount(0, $calls);
    }

    #[Test]
    public function testDialectStrictTrimsWhitespaceForMatching(): void
    {
        // The code does trim($lang) === trim($newJob->target), so whitespace
        // around the key should still match
        $dialectJson = json_encode([' it-IT ' => 'trimmed_value']);
        $ps = $this->buildProjectStructure(['dialect_strict' => $dialectJson]);

        // Also set target with trailing whitespace to test trim on both sides
        $this->job->target = ' it-IT ';
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('dialect_strict');
        $this->assertCount(1, $calls);
        $this->assertSame('trimmed_value', $calls[0][3]);
    }

    #[Test]
    public function testDialectStrictNotPersistedWhenNotSet(): void
    {
        $ps = $this->buildProjectStructure();
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('dialect_strict');
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
        $ps = $this->buildProjectStructure(['dialect_strict' => $dialectJson]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('dialect_strict');
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
        $ps = $this->buildProjectStructure([
            'public_tm_penalty'           => '10',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'target',
            'tm_prioritization'           => true,
            'dialect_strict'              => $dialectJson,
            JobsMetadataDao::SUBFILTERING_HANDLERS => $handlers,
        ]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        // Should have 6 DAO calls total:
        // public_tm_penalty, character_counter_count_tags, character_counter_mode,
        // tm_prioritization, dialect_strict, subfiltering_handlers
        $this->assertCount(6, $this->daoSetCalls);

        // Verify each key
        $this->assertCount(1, $this->findDaoCallsByKey('public_tm_penalty'));
        $this->assertSame('10', $this->findDaoCallsByKey('public_tm_penalty')[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey('character_counter_count_tags'));
        $this->assertSame('1', $this->findDaoCallsByKey('character_counter_count_tags')[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey('character_counter_mode'));
        $this->assertSame('target', $this->findDaoCallsByKey('character_counter_mode')[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey('tm_prioritization'));
        $this->assertSame('1', $this->findDaoCallsByKey('tm_prioritization')[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey('dialect_strict'));
        $this->assertSame('strict', $this->findDaoCallsByKey('dialect_strict')[0][3]);

        $this->assertCount(1, $this->findDaoCallsByKey(JobsMetadataDao::SUBFILTERING_HANDLERS));
        $this->assertSame($handlers, $this->findDaoCallsByKey(JobsMetadataDao::SUBFILTERING_HANDLERS)[0][3]);

        // All calls use correct credentials
        $this->assertAllCallsUseJobCredentials();
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function testCharacterCounterCountTagsWithIntegerOnePersistsOne(): void
    {
        // The code uses ternary `? "1" : "0"` — integer 1 is truthy
        $ps = $this->buildProjectStructure(['character_counter_count_tags' => 1]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('character_counter_count_tags');
        $this->assertCount(1, $calls);
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testCharacterCounterCountTagsWithIntegerZeroPersistsZero(): void
    {
        // Integer 0 is falsy
        $ps = $this->buildProjectStructure(['character_counter_count_tags' => 0]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('character_counter_count_tags');
        $this->assertCount(1, $calls);
        $this->assertSame('0', $calls[0][3]);
    }

    #[Test]
    public function testTmPrioritizationWithStringOnePersistsOne(): void
    {
        // String "1" is truthy — ternary will produce int 1, coerced to "1"
        $ps = $this->buildProjectStructure(['tm_prioritization' => '1']);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('tm_prioritization');
        $this->assertCount(1, $calls);
        $this->assertSame('1', $calls[0][3]);
    }

    #[Test]
    public function testDialectStrictWithEmptyJsonObjectPersistsNothing(): void
    {
        $ps = $this->buildProjectStructure(['dialect_strict' => '{}']);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        $calls = $this->findDaoCallsByKey('dialect_strict');
        $this->assertCount(0, $calls);
    }

    #[Test]
    public function testDaoCallOrderMatchesCodeOrder(): void
    {
        $dialectJson = json_encode(['it-IT' => 'yes']);
        $ps = $this->buildProjectStructure([
            'public_tm_penalty'           => '5',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'source',
            'tm_prioritization'           => true,
            'dialect_strict'              => $dialectJson,
        ]);
        $this->pm->callSaveJobsMetadata($this->job, $ps);

        // The code processes keys in this order:
        // 1. public_tm_penalty
        // 2. character_counter_count_tags
        // 3. character_counter_mode
        // 4. tm_prioritization
        // 5. dialect_strict
        // 6. subfiltering_handlers (always last, unconditional)
        $keys = array_map(fn(array $call) => $call[2], $this->daoSetCalls);
        $this->assertSame([
            'public_tm_penalty',
            'character_counter_count_tags',
            'character_counter_mode',
            'tm_prioritization',
            'dialect_strict',
            JobsMetadataDao::SUBFILTERING_HANDLERS,
        ], $keys);
    }
}
