<?php

namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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
        $db = $this->createStub(IDatabase::class);

        $this->service = new TestableJobCreationService($featureSet, $logger, $db);

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
    // SUBFILTERING_HANDLERS
    // =========================================================================

    #[Test]
    public function testSubfilteringHandlersIsNotPersistedWhenEmptyJsonArray(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $this->capturedMetadata);
    }

    #[Test]
    public function testSubfilteringHandlersIsNotPersistedWhenNull(): void
    {
        $this->setConfigAndSave([
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => null,
        ]);

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $this->capturedMetadata);
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
    // Empty project structure — persists nothing
    // =========================================================================

    #[Test]
    public function testEmptyProjectStructurePersistsNothing(): void
    {
        $this->setConfigAndSave();

        $this->assertCount(0, $this->capturedMetadata);
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
        $this->setConfigAndSave(['dialect_strict' => ['it-IT' => true, 'fr-FR' => false]]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
    }

    #[Test]
    public function testDialectStrictDoesNotPersistNonMatchingLanguage(): void
    {
        $this->setConfigAndSave(['dialect_strict' => ['fr-FR' => true, 'de-DE' => true]]);

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
    }

    #[Test]
    public function testDialectStrictTrimsWhitespaceForMatching(): void
    {
        $this->job->target = ' it-IT ';
        $this->setConfigAndSave(['dialect_strict' => [' it-IT ' => true]]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
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
        $this->setConfigAndSave(['dialect_strict' => [
            'en-US' => true,
            'it-IT' => true,
            'fr-FR' => false,
        ]]);

        $this->assertArrayHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
    }

    // =========================================================================
    // Combined scenario
    // =========================================================================

    #[Test]
    public function testCombinedScenarioWithAllOptions(): void
    {
        $handlers = json_encode([['handler' => 'xliff']]);
        $this->setConfigAndSave([
            'public_tm_penalty'           => '10',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'target',
            'tm_prioritization'           => true,
            'dialect_strict'              => ['it-IT' => true],
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => $handlers,
        ]);

        $this->assertCount(6, $this->capturedMetadata);

        $this->assertSame('10', $this->capturedMetadata[JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value]);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value]);
        $this->assertSame('target', $this->capturedMetadata[JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value]);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::TM_PRIORITIZATION->value]);
        $this->assertSame('1', $this->capturedMetadata[JobsMetadataMarshaller::DIALECT_STRICT->value]);
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
        $this->setConfigAndSave(['dialect_strict' => []]);

        $this->assertArrayNotHasKey(JobsMetadataMarshaller::DIALECT_STRICT->value, $this->capturedMetadata);
    }

    #[Test]
    public function testMetadataKeyOrderMatchesCodeOrder(): void
    {
        $this->setConfigAndSave([
            'public_tm_penalty'           => '5',
            'character_counter_count_tags' => true,
            'character_counter_mode'       => 'source',
            'tm_prioritization'           => true,
            'dialect_strict'              => ['it-IT' => true],
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value => json_encode([['handler' => 'xliff']]),
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

    // =========================================================================
    // MT settings (moved off project metadata so the owner can change them later)
    // =========================================================================

    #[Test]
    #[DataProvider('engineMtSettingProvider')]
    public function testEngineMtSettingIsPersistedFromProjectStructure(string $key, mixed $value, string $expected): void
    {
        $this->setConfigAndSave([$key => $value]);

        $this->assertArrayHasKey($key, $this->capturedMetadata);
        $this->assertSame($expected, $this->capturedMetadata[$key]);
    }

    public static function engineMtSettingProvider(): array
    {
        return [
            'deepl_formality'         => ['deepl_formality', 'prefer_more', 'prefer_more'],
            'deepl_id_glossary'       => ['deepl_id_glossary', 'gl-1', 'gl-1'],
            'deepl_engine_type'       => ['deepl_engine_type', 'latency_optimized', 'latency_optimized'],
            'lara_style'              => ['lara_style', 'creative', 'creative'],
            'lara_style_guideline_id' => ['lara_style_guideline_id', 'guideline-3', 'guideline-3'],
            'lara_glossaries'         => ['lara_glossaries', '["a","b"]', '["a","b"]'],
            'mmt_glossaries'          => ['mmt_glossaries', '[1,2]', '[1,2]'],
            'intento_provider'        => ['intento_provider', 'ai.text.translate.google', 'ai.text.translate.google'],
            'intento_routing'         => ['intento_routing', 'best_quality', 'best_quality'],
            // job_metadata.value is a string column, so a bool has to be normalised on the way in.
            'ignore case true'        => ['mmt_ignore_glossary_case', true, '1'],
            'ignore case false'       => ['mmt_ignore_glossary_case', false, '0'],
        ];
    }

    #[Test]
    public function testMtQualityValueInEditorIsReadFromTheMetadataBlob(): void
    {
        // Both creation controllers put the threshold in the metadata blob rather than on a
        // ProjectStructure property of its own, unlike every other MT setting.
        $this->projectStructure->metadata = [
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '90',
        ];

        $this->setConfigAndSave();

        $this->assertSame('90', $this->capturedMetadata[JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value]);
    }

    #[Test]
    public function testMtQualityValueInEditorIsNormalisedToAnInteger(): void
    {
        $this->projectStructure->metadata = [
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => 85,
        ];

        $this->setConfigAndSave();

        $this->assertSame('85', $this->capturedMetadata[JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value]);
    }

    #[Test]
    public function testMtQualityValueInEditorIsNotPersistedWhenAbsentFromTheBlob(): void
    {
        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value,
            $this->capturedMetadata
        );
    }

    #[Test]
    public function testMtQualityValueInEditorIsNotPersistedWhenNotNumeric(): void
    {
        $this->projectStructure->metadata = [
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '',
        ];

        $this->setConfigAndSave();

        $this->assertArrayNotHasKey(
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value,
            $this->capturedMetadata
        );
    }

    /**
     * Only the parameters of the engine the project actually uses are copied onto the structure, so
     * most MT settings are null at this point. Writing them anyway would put an empty row in
     * job_metadata, and an empty row is found by the resolver — it would shadow the project-metadata
     * fallback instead of falling through to it.
     */
    #[Test]
    public function testUnsetMtSettingsProduceNoRows(): void
    {
        $this->setConfigAndSave();

        foreach (JobsMetadataMarshaller::mtSettings() as $key) {
            $this->assertArrayNotHasKey($key, $this->capturedMetadata, "'$key' must not be persisted when unset");
        }
    }

    #[Test]
    public function testEmptyStringMtSettingProducesNoRow(): void
    {
        $this->setConfigAndSave(['deepl_formality' => '']);

        $this->assertArrayNotHasKey('deepl_formality', $this->capturedMetadata);
    }

    #[Test]
    public function testMtSettingsArePersistedAlongsideTheJobOnlyKeys(): void
    {
        $this->projectStructure->metadata = [
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '70',
        ];

        $this->setConfigAndSave([
            'public_tm_penalty' => '5',
            'tm_prioritization' => true,
            'lara_style'        => 'fluid',
        ]);

        $this->assertSame([
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value => '5',
            JobsMetadataMarshaller::TM_PRIORITIZATION->value => '1',
            JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '70',
            JobsMetadataMarshaller::LARA_STYLE->value => 'fluid',
        ], $this->capturedMetadata);
    }

    #[Test]
    public function testBulkSetStillTargetsTheJobCredentialWhenOnlyMtSettingsAreSet(): void
    {
        $this->setConfigAndSave(['deepl_formality' => 'default']);

        $this->assertSame(self::JOB_ID, $this->capturedIdJob);
        $this->assertSame(self::JOB_PASSWORD, $this->capturedPassword);
    }
}
