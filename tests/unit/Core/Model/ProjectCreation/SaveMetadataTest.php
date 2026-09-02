<?php

namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\ProjectCreation\ProjectMetadataService;
use Model\ProjectCreation\ProjectStructure;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Xliff\DTO\XliffRulesModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see ProjectMetadataService::save()}.
 *
 * Tests verify that metadata from ProjectStructure is correctly
 * collected, transformed, and persisted via ProjectsMetadataDao::bulkSet().
 *
 * @see REFACTORING_PLAN.md — Step 0c
 */
class SaveMetadataTest extends AbstractTest
{
    private ProjectMetadataService $service;
    private ProjectStructure $projectStructure;
    private FeatureSet $features;

    /**
     * Collected calls to the mocked ProjectsMetadataDao::bulkSet().
     *
     * @var array<int, array{id_project: int, metadata: array<string, string>}>
     */
    private array $bulkSetCalls = [];

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->bulkSetCalls = [];
        $stubDao = $this->createStub(ProjectsMetadataDao::class);
        $stubDao->method('bulkSet')
            ->willReturnCallback(function (int $idProject, array $metadata): void {
                $this->bulkSetCalls[] = [
                    'id_project' => $idProject,
                    'metadata'   => $metadata,
                ];
            });

        $this->service = new ProjectMetadataService($stubDao, $this->createStub(MatecatLogger::class));

        $this->features = $this->createStub(FeatureSet::class);

        $this->projectStructure = new ProjectStructure([
            'id_project'      => 999,
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'metadata'        => [],
        ]);
        $this->projectStructure->subfiltering_handlers = '[]';
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function findDaoCallsByKey(string $key): array
    {
        return array_values(
            array_filter(
                $this->bulkSetCalls,
                static fn(array $call) => array_key_exists($key, $call['metadata'])
            )
        );
    }

    /**
     * Get the value that was persisted for a given key (first match).
     */
    private function getPersistedValue(string $key): mixed
    {
        $calls = $this->findDaoCallsByKey($key);
        self::assertNotEmpty($calls, "Expected at least one bulkSet() metadata map containing key '$key'");

        return $calls[0]['metadata'][$key];
    }

    private function getSinglePersistedMetadataMap(): array
    {
        self::assertCount(1, $this->bulkSetCalls, 'save() must invoke bulkSet() exactly once when metadata exists');

        return $this->bulkSetCalls[0]['metadata'];
    }

    // =========================================================================
    // SUBFILTERING_HANDLERS — always persisted
    // =========================================================================

    #[Test]
    public function testSubfilteringHandlersIsAlwaysPersisted(): void
    {
        $this->projectStructure->subfiltering_handlers = '["handler_a"]';

        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey(ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value);
        self::assertNotEmpty($calls, 'subfiltering_handlers must always be persisted');
        self::assertSame('["handler_a"]', $calls[0]['metadata'][ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value]);
    }

    #[Test]
    public function testSubfilteringHandlersIsNotPersistedWhenEmptyJsonArray(): void
    {
        $this->projectStructure->subfiltering_handlers = '[]';

        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey(ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value);
        self::assertEmpty($calls, 'subfiltering_handlers should NOT be persisted when it is "[]"');
    }

    #[Test]
    public function testAllDaoSetCallsUseCorrectProjectId(): void
    {
        // Set a metadata key so there is at least one option to persist
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value => '1',
        ];

        $this->service->save($this->projectStructure, $this->features);

        self::assertCount(1, $this->bulkSetCalls, 'save() must call bulkSet() once when metadata exists');

        foreach ($this->bulkSetCalls as $call) {
            self::assertSame(999, $call['id_project'], 'bulkSet() calls must use project id 999');
        }
    }

    // =========================================================================
    // Empty metadata — only subfiltering_handlers is persisted
    // =========================================================================

    #[Test]
    public function testEmptyMetadataOnlyPersistsSubfilteringHandlersAndDefaults(): void
    {
        // metadata is already empty by default in ProjectStructure
        $this->service->save($this->projectStructure, $this->features);

        // pretranslate_101 always exists (DTO default = 1)
        $metadata = $this->getSinglePersistedMetadataMap();
        self::assertCount(1, $metadata);

        $keys = array_keys($metadata);
        self::assertContains(ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value, $keys);
    }

    // =========================================================================
    // MANDATORY_ISSUES — no longer persisted at project level (dead write removed)
    // =========================================================================

    #[Test]
    public function testMandatoryIssuesIsNotPersistedAtProjectLevel(): void
    {
        $this->projectStructure->mandatory_issues = ['r1', 'r2'];

        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey('mandatory_issues');
        self::assertEmpty(
            $calls,
            'mandatory_issues must not be persisted in project_metadata; only the job-level copy is live'
        );
    }

    // =========================================================================
    // FROM_API flag
    // =========================================================================

    #[Test]
    public function testFromApiFlagIsPersistedWhenSet(): void
    {
        $this->projectStructure->from_api = true;

        $this->service->save($this->projectStructure, $this->features);

        // Boolean true is coerced to '1' by PHP's string type hint on set()
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::FROM_API->value));
    }

    #[Test]
    public function testFromApiFlagIsNotPersistedWhenFalse(): void
    {
        $this->projectStructure->from_api = false;

        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey(ProjectsMetadataMarshaller::FROM_API->value);
        self::assertEmpty($calls, 'from_api should not be persisted when false');
    }

    // =========================================================================
    // XLIFF_PARAMETERS — XliffRulesModel JSON encoding
    // =========================================================================

    #[Test]
    public function testXliffParametersIsJsonEncodedWhenStruct(): void
    {
        $model = XliffRulesModel::fromArray([
            XliffRulesModel::XLIFF_12 => [
                [
                    'states'   => ['translated'],
                    'analysis' => 'pre-translated',
                    'editor'   => 'translated',
                ],
            ],
        ]);

        $this->projectStructure->xliff_parameters = $model;

        $this->service->save($this->projectStructure, $this->features);

        $persisted = $this->getPersistedValue(ProjectsMetadataMarshaller::XLIFF_PARAMETERS->value);
        $decoded = json_decode($persisted, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey(XliffRulesModel::XLIFF_12, $decoded);
        self::assertCount(1, $decoded[XliffRulesModel::XLIFF_12]);

        $rule = $decoded[XliffRulesModel::XLIFF_12][0];
        self::assertSame(['translated'], $rule['states']);
        self::assertSame('pre-translated', $rule['analysis']);
        self::assertSame('translated', $rule['editor']);
    }

    #[Test]
    public function testXliffParametersIsNotPersistedWhenNotStruct(): void
    {
        // When xliff_parameters is not an XliffRulesModel with rules, it should
        // not be added to options (the key won't exist in metadata)
        $this->projectStructure->xliff_parameters = 'not-a-struct';

        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey(ProjectsMetadataMarshaller::XLIFF_PARAMETERS->value);
        self::assertEmpty($calls, 'xliff_parameters should not be persisted when not an XliffRulesModel with rules');
    }

    // =========================================================================
    // PRETRANSLATE_101
    // =========================================================================

    #[Test]
    public function testPretranslate101IsPersistedWhenSet(): void
    {
        $this->projectStructure->pretranslate_101 = '1';

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value));
    }

    // =========================================================================
    // MT QE workflow — JSON-encoding of parameters
    // =========================================================================

    #[Test]
    public function testMtQeWorkflowParametersAreJsonEncodedWhenEnabled(): void
    {
        $params = ['model' => 'comet', 'threshold' => 0.8];

        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value    => true,
            ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value => $params,
        ];

        $this->service->save($this->projectStructure, $this->features);

        $persisted = $this->getPersistedValue(ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value);
        self::assertSame(json_encode($params), $persisted);
    }

    #[Test]
    public function testMtQeWorkflowParametersAreNotJsonEncodedWhenDisabled(): void
    {
        $params = ['model' => 'comet', 'threshold' => 0.8];

        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value    => false,
            ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value => $params,
        ];

        $this->service->save($this->projectStructure, $this->features);

        // When workflow is disabled, raw array parameters are removed
        // to prevent passing a non-string value to MetadataDao::set()
        $calls = $this->findDaoCallsByKey(ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value);
        self::assertEmpty($calls, 'mt_qe_workflow_parameters should not be persisted when workflow is disabled');
    }

    // =========================================================================
    // FILTERS_EXTRACTION_PARAMETERS — JSON encoding
    // =========================================================================

    #[Test]
    public function testFiltersExtractionParametersAreJsonEncoded(): void
    {
        $filterParams = ['segmentation' => 'sentence', 'keep_formatting' => true];

        $this->projectStructure->filters_extraction_parameters = $filterParams;

        $this->service->save($this->projectStructure, $this->features);

        $persisted = $this->getPersistedValue(ProjectsMetadataMarshaller::FILTERS_EXTRACTION_PARAMETERS->value);
        self::assertSame(json_encode($filterParams), $persisted);
    }

    #[Test]
    public function testFiltersExtractionParametersNotPersistedWhenEmpty(): void
    {
        $this->projectStructure->filters_extraction_parameters = null;

        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey(ProjectsMetadataMarshaller::FILTERS_EXTRACTION_PARAMETERS->value);
        self::assertEmpty($calls);
    }

    // =========================================================================
    // Engine extra keys (enable_mt_analysis, mmt_activate_context_analyzer)
    // =========================================================================

    #[Test]
    public function testProjectWideEngineExtraKeysArePersistedFromProjectStructure(): void
    {
        $this->projectStructure->enable_mt_analysis = '1';
        $this->projectStructure->mmt_activate_context_analyzer = '1';

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame('1', $this->getPersistedValue('enable_mt_analysis'));
        self::assertSame('1', $this->getPersistedValue('mmt_activate_context_analyzer'));
    }

    /**
     * The MT tuning settings are written here as the project's creation-time base value. The owner's
     * later edits go to job metadata and win on read (@see \Model\Jobs\JobSettingsResolver), so
     * the two scopes are copy-on-write: this row is what every job that was never customised
     * resolves to, and it is what a revert of the job scope falls back on.
     */
    #[Test]
    #[DataProvider('jobScopedMtSettingProvider')]
    public function testMtSettingsArePersistedAtProjectLevelAsTheBaseValue(string $key, mixed $value, string $expected): void
    {
        $this->projectStructure->$key = $value;

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame(
            $expected,
            $this->getPersistedValue($key),
            "'$key' is the creation-time base value and must be written to project metadata"
        );
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function jobScopedMtSettingProvider(): array
    {
        // ProjectStructure declares these with real types, so the input has to be type-appropriate
        // and the expectation is the string the DAO is handed. mmt_ignore_glossary_case is the one
        // that is not a string: it is ?bool, and the write normalises it to '1'/'0'.
        $inputs = [
            JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value => [true, '1'],
        ];

        $cases = [];

        foreach (JobsMetadataMarshaller::mtSettings() as $key) {
            // The threshold is the only one that does not exist as a ProjectStructure property: it
            // travels inside the metadata blob, and the test below covers it on its own.
            if ($key === JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value) {
                continue;
            }

            [$value, $expected] = $inputs[$key] ?? ['some_value', 'some_value'];
            $cases[$key] = [$key, $value, $expected];
        }

        return $cases;
    }

    #[Test]
    public function testMtQualityValueInEditorIsPersistedAtProjectLevel(): void
    {
        // Unlike the engine parameters this one reaches the service inside the metadata blob, so it
        // rides through on $options rather than through the engine-key copy loop.
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '90',
        ];

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame(
            '90',
            $this->getPersistedValue(ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value),
            'the MT application threshold is the creation-time base value'
        );
    }

    /**
     * The guard the eager per-job write used to own, now that these keys go through the project
     * write again. `!empty()` is right for the project-wide engine params — there, falsy is the
     * absent default — but for the MT settings a threshold of 0 and mmt_ignore_glossary_case turned
     * off are answers the owner chose, and dropping them silently reverts to the engine default.
     */
    #[Test]
    public function testFalsyMtSettingsAreStillPersisted(): void
    {
        $this->projectStructure->mmt_ignore_glossary_case = false;
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '0',
        ];

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame(
            '0',
            $this->getPersistedValue(JobsMetadataMarshaller::MMT_IGNORE_GLOSSARY_CASE->value),
            'mmt_ignore_glossary_case = false is an answer, not an absence'
        );
        self::assertSame(
            '0',
            $this->getPersistedValue(ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value),
            'a threshold of 0 is an answer, not an absence'
        );
    }

    /**
     * The other half of the same guard: an unset key must produce no row at all, because an empty
     * value would be indistinguishable from a real one on read.
     */
    #[Test]
    public function testEmptyStringMtSettingsProduceNoRow(): void
    {
        $this->projectStructure->deepl_formality = '';

        $this->service->save($this->projectStructure, $this->features);

        self::assertEmpty(
            $this->findDaoCallsByKey(JobsMetadataMarshaller::DEEPL_FORMALITY->value),
            'an empty MT setting must not be persisted'
        );
    }

    #[Test]
    public function testEngineExtraKeysAreNotPersistedWhenEmpty(): void
    {
        // These keys are not set in projectStructure, so they should not
        // appear in the DAO bulkSet() calls
        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey('enable_mt_analysis');
        self::assertEmpty($calls, 'enable_mt_analysis should not be persisted when empty');

        $calls = $this->findDaoCallsByKey('deepl_formality');
        self::assertEmpty($calls, 'deepl_formality should not be persisted when empty');
    }

    // =========================================================================
    // =========================================================================
    // =========================================================================

    #[Test]
    public function testAllMetadataOptionsArePersistedViaBulkSet(): void
    {
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value                   => '1',
            ProjectsMetadataMarshaller::MT_EVALUATION->value                 => '0',
            ProjectsMetadataMarshaller::MMT_ACTIVATE_CONTEXT_ANALYZER->value => '1',
        ];

        $this->service->save($this->projectStructure, $this->features);

        // 3 metadata keys + 1 pretranslate_101 (DTO default) = 4 total
        $metadata = $this->getSinglePersistedMetadataMap();
        self::assertCount(4, $metadata);

        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::ICU_ENABLED->value));
        self::assertSame('0', $this->getPersistedValue(ProjectsMetadataMarshaller::MT_EVALUATION->value));
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::MMT_ACTIVATE_CONTEXT_ANALYZER->value));

        foreach ($metadata as $value) {
            self::assertIsString($value, 'All bulkSet() metadata values must be strings');
        }
    }

    // =========================================================================
    // Combined scenario — multiple features together
    // =========================================================================

    #[Test]
    public function testCombinedMetadataScenario(): void
    {
        $this->projectStructure->from_api = true;
        $this->projectStructure->pretranslate_101 = '0';
        $this->projectStructure->enable_mt_analysis = '1';
        $this->projectStructure->mmt_glossaries = 'gl_abc';
        $this->projectStructure->subfiltering_handlers = '[{"name":"handler1"}]';
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value => '1',
        ];

        $this->service->save($this->projectStructure, $this->features);

        // Verify all expected keys are present
        // Boolean true is coerced to '1' by DAO set() string type hint
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::FROM_API->value));
        self::assertSame('0', $this->getPersistedValue(ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value));
        self::assertSame('1', $this->getPersistedValue('enable_mt_analysis'));
        self::assertSame('[{"name":"handler1"}]', $this->getPersistedValue(ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value));
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::ICU_ENABLED->value));

        // Set alongside the rest: the MT settings are written here as the project's base value, and
        // a job row only appears if the owner later overrides one.
        self::assertSame('gl_abc', $this->getPersistedValue('mmt_glossaries'));
    }

    // =========================================================================
    // Storage-layer key guard
    // =========================================================================

    #[Test]
    public function testUnknownMetadataKeysAreStrippedBeforeBulkSet(): void
    {
        // Inject obviously unknown keys alongside a valid key
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value => '1',
            'injected_evil_key'                            => 'evil_value',
            'metadata_pollution'                           => 'polluted_value',
        ];

        $this->service->save($this->projectStructure, $this->features);

        // Unknown keys must not reach the DAO
        self::assertEmpty(
            $this->findDaoCallsByKey('injected_evil_key'),
            "'injected_evil_key' must not reach bulkSet()"
        );
        self::assertEmpty(
            $this->findDaoCallsByKey('metadata_pollution'),
            "'metadata_pollution' must not reach bulkSet()"
        );

        // The valid key must still be persisted
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::ICU_ENABLED->value));
    }

    #[Test]
    public function testValidProjectsMetadataMarshallerKeysPassThroughGuard(): void
    {
        // Use several canonical ProjectsMetadataMarshaller enum values
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value  => '1',
            ProjectsMetadataMarshaller::MT_EVALUATION->value => '0',
            ProjectsMetadataMarshaller::FROM_API->value      => '1',
        ];

        $this->service->save($this->projectStructure, $this->features);

        // All three must survive the guard and reach bulkSet()
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::ICU_ENABLED->value));
        self::assertSame('0', $this->getPersistedValue(ProjectsMetadataMarshaller::MT_EVALUATION->value));
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::FROM_API->value));
    }

    #[Test]
    public function testEngineExtraKeysPassThroughGuard(): void
    {
        // enable_mt_analysis and mmt_activate_context_analyzer are engine configuration parameter
        // keys that stay project-wide, and must be allowed through the guard
        $this->projectStructure->enable_mt_analysis = '1';
        $this->projectStructure->mmt_activate_context_analyzer = '1';

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame('1', $this->getPersistedValue('enable_mt_analysis'));
        self::assertSame('1', $this->getPersistedValue('mmt_activate_context_analyzer'));
    }

    #[Test]
    public function testSubfilteringHandlersKeyPassesThroughGuard(): void
    {
        // subfiltering_handlers is from JobsMetadataMarshaller and must be
        // explicitly allowed by the guard
        $this->projectStructure->subfiltering_handlers = '[{"name":"filter_a"}]';

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame(
            '[{"name":"filter_a"}]',
            $this->getPersistedValue(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value)
        );
    }

    #[Test]
    public function testLoggerDebugIsCalledWhenUnknownKeysAreStripped(): void
    {
        // Build a fresh service with a MOCK logger (not a stub) so we can assert on it
        $stubDao = $this->createStub(ProjectsMetadataDao::class);
        $stubDao->method('bulkSet')->willReturnCallback(function (int $idProject, array $metadata): void {
            $this->bulkSetCalls[] = ['id_project' => $idProject, 'metadata' => $metadata];
        });

        $loggerMock = $this->createMock(MatecatLogger::class);
        $loggerMock->expects(self::once())
            ->method('debug')
            ->with(self::stringContains('injected_evil_key'));

        $service = new ProjectMetadataService($stubDao, $loggerMock);

        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value => '1',
            'injected_evil_key'                            => 'evil_value',
        ];

        $service->save($this->projectStructure, $this->features);
    }

    #[Test]
    public function testLoggerIsNotCalledWhenAllKeysAreValid(): void
    {
        // Build a fresh service with a MOCK logger to assert debug() is never called
        $stubDao = $this->createStub(ProjectsMetadataDao::class);
        $stubDao->method('bulkSet')->willReturnCallback(function (int $idProject, array $metadata): void {
            $this->bulkSetCalls[] = ['id_project' => $idProject, 'metadata' => $metadata];
        });

        $loggerMock = $this->createMock(MatecatLogger::class);
        $loggerMock->expects(self::never())->method('debug');

        $service = new ProjectMetadataService($stubDao, $loggerMock);

        // Only valid keys — no unknown keys should trigger the guard
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value  => '1',
            ProjectsMetadataMarshaller::MT_EVALUATION->value => '0',
        ];

        $service->save($this->projectStructure, $this->features);
    }

    #[Test]
    public function testMixedValidAndUnknownKeysOnlyValidSurvive(): void
    {
        $this->projectStructure->metadata = [
            ProjectsMetadataMarshaller::ICU_ENABLED->value          => '1',
            ProjectsMetadataMarshaller::MMT_ACTIVATE_CONTEXT_ANALYZER->value => '1',
            'injected_evil_key'                                      => 'evil_value',
            'metadata_pollution'                                     => 'polluted_value',
        ];

        $this->service->save($this->projectStructure, $this->features);

        // Valid keys must survive
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::ICU_ENABLED->value));
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::MMT_ACTIVATE_CONTEXT_ANALYZER->value));

        // Unknown keys must be absent
        self::assertEmpty(
            $this->findDaoCallsByKey('injected_evil_key'),
            "'injected_evil_key' must not reach bulkSet()"
        );
        self::assertEmpty(
            $this->findDaoCallsByKey('metadata_pollution'),
            "'metadata_pollution' must not reach bulkSet()"
        );
    }
}
