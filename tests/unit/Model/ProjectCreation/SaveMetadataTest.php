<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\ProjectCreation\ProjectMetadataService;
use Model\ProjectCreation\ProjectStructure;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Xliff\DTO\XliffRulesModel;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
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
    public function testAllDaoSetCallsUseCorrectProjectId(): void
    {
        // Set a metadata key so there is at least one option to persist
        $this->projectStructure->metadata = [
            'some_key' => 'some_value',
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

        // pretranslate_101 always exists (DTO default = 1), plus subfiltering_handlers
        $metadata = $this->getSinglePersistedMetadataMap();
        self::assertCount(2, $metadata);

        $keys = array_keys($metadata);
        self::assertContains(ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value, $keys);
        self::assertContains(ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value, $keys);
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
    // Engine extra keys (mmt_glossaries, deepl_formality, etc.)
    // =========================================================================

    #[Test]
    public function testEngineExtraKeysArePersistedFromProjectStructure(): void
    {
        $this->projectStructure->mmt_glossaries = 'glossary_123';
        $this->projectStructure->deepl_formality = 'more';
        $this->projectStructure->lara_style = 'formal';

        $this->service->save($this->projectStructure, $this->features);

        self::assertSame('glossary_123', $this->getPersistedValue('mmt_glossaries'));
        self::assertSame('more', $this->getPersistedValue('deepl_formality'));
        self::assertSame('formal', $this->getPersistedValue('lara_style'));
    }

    #[Test]
    public function testEngineExtraKeysAreNotPersistedWhenEmpty(): void
    {
        // These keys are not set in projectStructure, so they should not
        // appear in the DAO bulkSet() calls
        $this->service->save($this->projectStructure, $this->features);

        $calls = $this->findDaoCallsByKey('mmt_glossaries');
        self::assertEmpty($calls, 'mmt_glossaries should not be persisted when empty');

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
            'custom_key_1' => 'value_1',
            'custom_key_2' => 'value_2',
            'custom_key_3' => 'value_3',
        ];

        $this->service->save($this->projectStructure, $this->features);

        // 3 custom keys + 1 pretranslate_101 (DTO default) + 1 subfiltering_handlers = 5 total calls
        $metadata = $this->getSinglePersistedMetadataMap();
        self::assertCount(5, $metadata);

        self::assertSame('value_1', $this->getPersistedValue('custom_key_1'));
        self::assertSame('value_2', $this->getPersistedValue('custom_key_2'));
        self::assertSame('value_3', $this->getPersistedValue('custom_key_3'));

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
        $this->projectStructure->mmt_glossaries = 'gl_abc';
        $this->projectStructure->subfiltering_handlers = '[{"name":"handler1"}]';
        $this->projectStructure->metadata = [
            'existing_option' => 'kept',
        ];

        $this->service->save($this->projectStructure, $this->features);

        // Verify all expected keys are present
        // Boolean true is coerced to '1' by DAO set() string type hint
        self::assertSame('1', $this->getPersistedValue(ProjectsMetadataMarshaller::FROM_API->value));
        self::assertSame('0', $this->getPersistedValue(ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value));
        self::assertSame('gl_abc', $this->getPersistedValue('mmt_glossaries'));
        self::assertSame('[{"name":"handler1"}]', $this->getPersistedValue(ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value));
        self::assertSame('kept', $this->getPersistedValue('existing_option'));
    }
}
