<?php

namespace unit\Model\ProjectManager;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Xliff\XliffConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Collections\RecursiveArrayObject;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectManager\ProjectManager::saveMetadata()}.
 *
 * Tests verify that metadata from `$this->projectStructure` is correctly
 * collected, transformed, and persisted via `ProjectsMetadataDao::set()`.
 *
 * @see REFACTORING_PLAN.md — Step 0c
 */
class SaveMetadataTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private string $originalFileStorageMethod;

    /**
     * Collected calls to the mocked ProjectsMetadataDao::set().
     * Each entry is [int $id_project, string $key, mixed $value].
     *
     * @var array<int, array{0: int, 1: string, 2: mixed}>
     */
    private array $daoSetCalls = [];

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

        // Create a stub ProjectsMetadataDao that records set() calls
        $this->daoSetCalls = [];
        $stubDao = $this->createStub(ProjectsMetadataDao::class);
        $stubDao->method('set')
            ->willReturnCallback(function (int $idProject, string $key, mixed $value): bool {
                $this->daoSetCalls[] = [$idProject, $key, $value];

                return true;
            });
        $this->pm->setProjectsMetadataDao($stubDao);

        // Set defaults needed by saveMetadata()
        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([]));
        $this->pm->setProjectStructureValue('sanitize_project_options', false);
        $this->pm->setProjectStructureValue(
            JobsMetadataDao::SUBFILTERING_HANDLERS,
            '[]'
        );
    }

    public function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
        parent::tearDown();
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Find all DAO set() calls for a given key.
     *
     * @return array<int, array{0: int, 1: string, 2: mixed}>
     */
    private function findDaoCallsByKey(string $key): array
    {
        return array_values(
            array_filter(
                $this->daoSetCalls,
                static fn(array $call) => $call[1] === $key
            )
        );
    }

    /**
     * Get the value that was persisted for a given key (first match).
     */
    private function getPersistedValue(string $key): mixed
    {
        $calls = $this->findDaoCallsByKey($key);
        self::assertNotEmpty($calls, "Expected at least one set() call for key '$key'");

        return $calls[0][2];
    }

    // =========================================================================
    // SUBFILTERING_HANDLERS — always persisted
    // =========================================================================

    #[Test]
    public function testSubfilteringHandlersIsAlwaysPersisted(): void
    {
        $this->pm->setProjectStructureValue(
            JobsMetadataDao::SUBFILTERING_HANDLERS,
            '["handler_a"]'
        );

        $this->pm->callSaveMetadata();

        $calls = $this->findDaoCallsByKey('subfiltering_handlers');
        self::assertNotEmpty($calls, 'subfiltering_handlers must always be persisted');
        self::assertSame('["handler_a"]', $calls[0][2]);
    }

    #[Test]
    public function testAllDaoSetCallsUseCorrectProjectId(): void
    {
        // Set a metadata key so there is at least one option to persist
        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([
            'some_key' => 'some_value',
        ]));

        $this->pm->callSaveMetadata();

        foreach ($this->daoSetCalls as $call) {
            self::assertSame(999, $call[0], 'All set() calls must use project id 999');
        }
    }

    // =========================================================================
    // Empty metadata — only subfiltering_handlers is persisted
    // =========================================================================

    #[Test]
    public function testEmptyMetadataOnlyPersistsSubfilteringHandlers(): void
    {
        // metadata is already empty by default (RecursiveArrayObject wrapping [])
        $this->pm->callSaveMetadata();

        // Only the unconditional subfiltering_handlers call should be made
        self::assertCount(1, $this->daoSetCalls);
        self::assertSame('subfiltering_handlers', $this->daoSetCalls[0][1]);
    }

    // =========================================================================
    // FROM_API flag
    // =========================================================================

    #[Test]
    public function testFromApiFlagIsPersistedWhenSet(): void
    {
        $this->pm->setProjectStructureValue(ProjectsMetadataDao::FROM_API, true);

        $this->pm->callSaveMetadata();

        // Boolean true is coerced to '1' by PHP's string type hint on set()
        self::assertSame('1', $this->getPersistedValue('from_api'));
    }

    #[Test]
    public function testFromApiFlagIsNotPersistedWhenFalse(): void
    {
        $this->pm->setProjectStructureValue(ProjectsMetadataDao::FROM_API, false);

        $this->pm->callSaveMetadata();

        $calls = $this->findDaoCallsByKey('from_api');
        self::assertEmpty($calls, 'from_api should not be persisted when false');
    }

    // =========================================================================
    // XLIFF_PARAMETERS — XliffConfigTemplateStruct JSON encoding
    // =========================================================================

    #[Test]
    public function testXliffParametersIsJsonEncodedWhenStruct(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $struct->id = 42;
        $struct->name = 'test-template';
        $struct->uid = 7;

        $this->pm->setProjectStructureValue(ProjectsMetadataDao::XLIFF_PARAMETERS, $struct);

        $this->pm->callSaveMetadata();

        $persisted = $this->getPersistedValue('xliff_parameters');
        $decoded = json_decode($persisted, true);
        self::assertSame(42, $decoded['id']);
        self::assertSame('test-template', $decoded['name']);
        self::assertSame(7, $decoded['uid']);
    }

    #[Test]
    public function testXliffParametersIsNotPersistedWhenNotStruct(): void
    {
        // When xliff_parameters is not an XliffConfigTemplateStruct, it should
        // not be added to options (the key won't exist in metadata)
        $this->pm->setProjectStructureValue(ProjectsMetadataDao::XLIFF_PARAMETERS, 'not-a-struct');

        $this->pm->callSaveMetadata();

        $calls = $this->findDaoCallsByKey('xliff_parameters');
        self::assertEmpty($calls, 'xliff_parameters should not be persisted when not an XliffConfigTemplateStruct');
    }

    // =========================================================================
    // PRETRANSLATE_101
    // =========================================================================

    #[Test]
    public function testPretranslate101IsPersistedWhenSet(): void
    {
        $this->pm->setProjectStructureValue(ProjectsMetadataDao::PRETRANSLATE_101, '1');

        $this->pm->callSaveMetadata();

        self::assertSame('1', $this->getPersistedValue('pretranslate_101'));
    }

    // =========================================================================
    // MT QE workflow — JSON-encoding of parameters
    // =========================================================================

    #[Test]
    public function testMtQeWorkflowParametersAreJsonEncodedWhenEnabled(): void
    {
        $params = ['model' => 'comet', 'threshold' => 0.8];

        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([
            ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED => true,
            ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS => $params,
        ]));

        $this->pm->callSaveMetadata();

        $persisted = $this->getPersistedValue('mt_qe_workflow_parameters');
        self::assertSame(json_encode($params), $persisted);
    }

    #[Test]
    public function testMtQeWorkflowParametersAreNotJsonEncodedWhenDisabled(): void
    {
        $params = ['model' => 'comet', 'threshold' => 0.8];

        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([
            ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED => false,
            ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS => $params,
        ]));

        $this->pm->callSaveMetadata();

        // The parameters key should still be persisted (it's in metadata)
        // but NOT json_encoded — it should be the original RecursiveArrayObject
        $calls = $this->findDaoCallsByKey('mt_qe_workflow_parameters');
        if (!empty($calls)) {
            // If persisted, it should not be a JSON string
            self::assertNotSame(json_encode($params), $calls[0][2]);
        }
    }

    // =========================================================================
    // FILTERS_EXTRACTION_PARAMETERS — JSON encoding
    // =========================================================================

    #[Test]
    public function testFiltersExtractionParametersAreJsonEncoded(): void
    {
        $filterParams = ['segmentation' => 'sentence', 'keep_formatting' => true];

        $this->pm->setProjectStructureValue(
            ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS,
            $filterParams
        );

        $this->pm->callSaveMetadata();

        $persisted = $this->getPersistedValue('filters_extraction_parameters');
        self::assertSame(json_encode($filterParams), $persisted);
    }

    #[Test]
    public function testFiltersExtractionParametersNotPersistedWhenEmpty(): void
    {
        $this->pm->setProjectStructureValue(
            ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS,
            null
        );

        $this->pm->callSaveMetadata();

        $calls = $this->findDaoCallsByKey('filters_extraction_parameters');
        self::assertEmpty($calls);
    }

    // =========================================================================
    // Engine extra keys (mmt_glossaries, deepl_formality, etc.)
    // =========================================================================

    #[Test]
    public function testEngineExtraKeysArePersistedFromProjectStructure(): void
    {
        $this->pm->setProjectStructureValue('mmt_glossaries', 'glossary_123');
        $this->pm->setProjectStructureValue('deepl_formality', 'more');
        $this->pm->setProjectStructureValue('lara_style', 'formal');

        $this->pm->callSaveMetadata();

        self::assertSame('glossary_123', $this->getPersistedValue('mmt_glossaries'));
        self::assertSame('more', $this->getPersistedValue('deepl_formality'));
        self::assertSame('formal', $this->getPersistedValue('lara_style'));
    }

    #[Test]
    public function testEngineExtraKeysAreNotPersistedWhenEmpty(): void
    {
        // These keys are not set in projectStructure, so they should not
        // appear in the DAO set() calls
        $this->pm->callSaveMetadata();

        $calls = $this->findDaoCallsByKey('mmt_glossaries');
        self::assertEmpty($calls, 'mmt_glossaries should not be persisted when empty');

        $calls = $this->findDaoCallsByKey('deepl_formality');
        self::assertEmpty($calls, 'deepl_formality should not be persisted when empty');
    }

    // =========================================================================
    // sanitize_project_options branch
    // =========================================================================

    #[Test]
    public function testSanitizeProjectOptionsIsAppliedWhenEnabled(): void
    {
        // Enable sanitization
        $this->pm->setProjectStructureValue('sanitize_project_options', true);

        // Set metadata with an invalid segmentation_rule that the sanitizer
        // should strip, and a valid speech2text that should pass through
        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([
            'speech2text' => true,
            'segmentation_rule' => 'invalid_value',
        ]));

        $this->pm->callSaveMetadata();

        // speech2text should be persisted (sanitizer casts bool→int→string '1')
        $speech2textCalls = $this->findDaoCallsByKey('speech2text');
        self::assertNotEmpty($speech2textCalls, 'speech2text should be persisted');
        self::assertSame('1', $speech2textCalls[0][2]);

        // segmentation_rule with invalid value should be removed by sanitizer
        $segRuleCalls = $this->findDaoCallsByKey('segmentation_rule');
        self::assertEmpty($segRuleCalls, 'invalid segmentation_rule should be stripped by sanitizer');
    }

    #[Test]
    public function testSanitizeProjectOptionsIsSkippedWhenDisabled(): void
    {
        // sanitize_project_options is already false from setUp()
        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([
            'segmentation_rule' => 'invalid_value',
        ]));

        $this->pm->callSaveMetadata();

        // Without sanitization, the invalid value passes through unchanged
        $segRuleCalls = $this->findDaoCallsByKey('segmentation_rule');
        self::assertNotEmpty($segRuleCalls, 'segmentation_rule should be persisted when sanitizer is off');
        self::assertSame('invalid_value', $segRuleCalls[0][2]);
    }

    // =========================================================================
    // Metadata from options are all persisted via set()
    // =========================================================================

    #[Test]
    public function testAllMetadataOptionsArePersistedViaSet(): void
    {
        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([
            'custom_key_1' => 'value_1',
            'custom_key_2' => 'value_2',
            'custom_key_3' => 'value_3',
        ]));

        $this->pm->callSaveMetadata();

        // 3 custom keys + 1 subfiltering_handlers = 4 total calls
        self::assertCount(4, $this->daoSetCalls);

        self::assertSame('value_1', $this->getPersistedValue('custom_key_1'));
        self::assertSame('value_2', $this->getPersistedValue('custom_key_2'));
        self::assertSame('value_3', $this->getPersistedValue('custom_key_3'));
    }

    // =========================================================================
    // Combined scenario — multiple features together
    // =========================================================================

    #[Test]
    public function testCombinedMetadataScenario(): void
    {
        $this->pm->setProjectStructureValue(ProjectsMetadataDao::FROM_API, true);
        $this->pm->setProjectStructureValue(ProjectsMetadataDao::PRETRANSLATE_101, '0');
        $this->pm->setProjectStructureValue('mmt_glossaries', 'gl_abc');
        $this->pm->setProjectStructureValue(
            JobsMetadataDao::SUBFILTERING_HANDLERS,
            '[{"name":"handler1"}]'
        );
        $this->pm->setProjectStructureValue('metadata', new RecursiveArrayObject([
            'existing_option' => 'kept',
        ]));

        $this->pm->callSaveMetadata();

        // Verify all expected keys are present
        // Boolean true is coerced to '1' by DAO set() string type hint
        self::assertSame('1', $this->getPersistedValue('from_api'));
        self::assertSame('0', $this->getPersistedValue('pretranslate_101'));
        self::assertSame('gl_abc', $this->getPersistedValue('mmt_glossaries'));
        self::assertSame('[{"name":"handler1"}]', $this->getPersistedValue('subfiltering_handlers'));
        self::assertSame('kept', $this->getPersistedValue('existing_option'));
    }
}
