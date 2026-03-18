<?php

namespace unit\Controllers;

use Controller\API\App\CreateProjectController;
use Controller\API\V1\NewController;
use Klein\Request;
use Klein\Response;
use Model\Filters\FiltersConfigTemplateStruct;
use Model\LQA\QAModelInterface;
use Model\ProjectCreation\ProjectStructure;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use stdClass;
use TestHelpers\AbstractTest;
use Utils\Constants\ProjectStatus;
use Utils\Engines\AbstractEngine;
use Utils\Registry\AppConfig;

/**
 * Testable subclass that exposes the protected buildProjectStructure() method.
 */
class TestableNewController extends NewController
{
    public function buildProjectStructure(
        array $request,
        array $filesFound,
        string $uploadToken,
        UserStruct $user,
        AbstractEngine $engine,
    ): ProjectStructure {
        return parent::buildProjectStructure($request, $filesFound, $uploadToken, $user, $engine);
    }
}

/**
 * Testable subclass that exposes the protected buildProjectStructure() method.
 */
class TestableCreateProjectController extends CreateProjectController
{
    public function buildProjectStructure(
        array $data,
        array $metadata,
        array $filesFound,
        string $uploadToken,
        UserStruct $user,
        AbstractEngine $engine,
        ?array $gdriveSession,
    ): ProjectStructure {
        return parent::buildProjectStructure($data, $metadata, $filesFound, $uploadToken, $user, $engine, $gdriveSession);
    }
}

/**
 * Tests for the extracted buildProjectStructure() methods in both
 * NewController and CreateProjectController.
 *
 * These are pure mapping methods — they take validated request data
 * and return a populated ProjectStructure DTO. No DB, no queue, no
 * file system access is required.
 */
class BuildProjectStructureTest extends AbstractTest
{
    private TestableNewController $newController;
    private TestableCreateProjectController $createProjectController;
    private UserStruct $user;
    private AbstractEngine $engine;

    /**
     * @throws ReflectionException
     */
    public function setUp(): void
    {
        parent::setUp();

        // Create controllers without invoking the constructor (which calls
        // identifyUser(), starts sessions, etc.)
        $refNew = new ReflectionClass(TestableNewController::class);
        $this->newController = $refNew->newInstanceWithoutConstructor();

        $refCreate = new ReflectionClass(TestableCreateProjectController::class);
        $this->createProjectController = $refCreate->newInstanceWithoutConstructor();

        // Stub UserStruct
        $this->user = $this->createStub(UserStruct::class);
        $this->user->uid = 42;
        $this->user->email = 'test@example.com';
        $this->user->method('getUid')->willReturn(42);
        $this->user->method('getEmail')->willReturn('test@example.com');

        // Stub AbstractEngine — returns no extra configuration parameters by default
        $this->engine = $this->createStub(AbstractEngine::class);
        $this->engine->method('getConfigurationParameters')->willReturn([]);
    }

    // ──────────────────────────────────────────────────────────────────
    // NewController::buildProjectStructure() tests
    // ──────────────────────────────────────────────────────────────────

    /**
     * Helper: build a minimal valid $request array for NewController.
     */
    private function makeNewControllerRequest(array $overrides = []): array
    {
        return array_merge([
            'project_name'                          => 'Test Project',
            'subject'                               => 'general',
            'private_tm_key'                        => [],
            'tm_prioritization'                     => null,
            'source_lang'                           => 'en-US',
            'target_lang'                           => 'fr-FR,de-DE',
            'mt_engine'                             => 1,
            'tms_engine'                            => 1,
            'metadata'                              => [],
            'public_tm_penalty'                     => null,
            'pretranslate_100'                      => 0,
            'pretranslate_101'                      => null,
            'get_public_matches'                    => true,
            'due_date'                              => null,
            'target_language_mt_engine_association'  => [],
            'instructions'                          => null,
            'character_counter_mode'                => null,
            'character_counter_count_tags'           => null,
            'subfiltering_handlers'                 => null,
            'lara_glossaries'                       => null,
            'lara_style'                            => null,
            'mmt_glossaries'                        => null,
            'qaModelTemplate'                       => null,
            'qaModel'                               => null,
            'mt_qe_workflow_payable_rate'            => null,
            'payableRateModelTemplate'              => null,
            'dialect_strict'                        => null,
            'filters_extraction_parameters'         => null,
            'xliff_parameters'                      => null,
            'mt_evaluation'                         => null,
            'project_features'                      => [],
        ], $overrides);
    }

    /**
     * Helper: build a minimal $filesFound array.
     */
    private function makeFilesFound(array $overrides = []): array
    {
        return array_merge([
            'arrayFiles'     => ['file1.xliff', 'file2.xliff'],
            'arrayFilesMeta' => [['size' => 1024], ['size' => 2048]],
        ], $overrides);
    }

    #[Test]
    public function newControllerSetsScalarFieldsCorrectly(): void
    {
        $request = $this->makeNewControllerRequest([
            'project_name'    => 'My Great Project',
            'subject'         => 'legal',
            'source_lang'     => 'it-IT',
            'target_lang'     => 'en-US,fr-FR',
            'mt_engine'       => 2,
            'tms_engine'      => 1,
            'pretranslate_100' => 1,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'upload-token-abc',
            $this->user,
            $this->engine,
        );

        $this->assertInstanceOf(ProjectStructure::class, $ps);
        $this->assertSame('My Great Project', $ps->project_name);
        $this->assertSame('legal', $ps->job_subject);
        $this->assertSame('it-IT', $ps->source_language);
        $this->assertSame(['en-US', 'fr-FR'], $ps->target_language);
        $this->assertSame(2, $ps->mt_engine);
        $this->assertSame(1, $ps->tms_engine);
        $this->assertSame(1, $ps->pretranslate_100);
        $this->assertSame('upload-token-abc', $ps->uploadToken);
    }

    #[Test]
    public function newControllerSetsFilesFromFilesFound(): void
    {
        $filesFound = $this->makeFilesFound([
            'arrayFiles'     => ['a.docx', 'b.pdf'],
            'arrayFilesMeta' => [['size' => 100], ['size' => 200]],
        ]);
        $request = $this->makeNewControllerRequest();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(['a.docx', 'b.pdf'], $ps->array_files);
        $this->assertSame([['size' => 100], ['size' => 200]], $ps->array_files_meta);
    }

    #[Test]
    public function newControllerSetsUserFields(): void
    {
        $request = $this->makeNewControllerRequest();
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertTrue($ps->userIsLogged);
        $this->assertSame(42, $ps->uid);
        $this->assertSame('test@example.com', $ps->id_customer);
        $this->assertSame('test@example.com', $ps->owner);
    }

    #[Test]
    public function newControllerSetsStatusNotReadyForAnalysis(): void
    {
        $request = $this->makeNewControllerRequest();
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS, $ps->status);
    }

    #[Test]
    public function newControllerSplitsTargetLanguageOnComma(): void
    {
        $request = $this->makeNewControllerRequest([
            'target_lang' => 'ja-JP,ko-KR,zh-CN',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(['ja-JP', 'ko-KR', 'zh-CN'], $ps->target_language);
    }

    #[Test]
    public function newControllerHandlesOnlyPrivateFromGetPublicMatches(): void
    {
        // get_public_matches = false => only_private = true (coerced to 1 by int property)
        $request = $this->makeNewControllerRequest([
            'get_public_matches' => false,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(1, $ps->only_private);
    }

    #[Test]
    public function newControllerHandlesOnlyPrivateWhenPublicMatchesTrue(): void
    {
        // get_public_matches = true => only_private = false (coerced to 0 by int property)
        $request = $this->makeNewControllerRequest([
            'get_public_matches' => true,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(0, $ps->only_private);
    }

    #[Test]
    public function newControllerSetsPretranslate101DefaultsTo1(): void
    {
        // pretranslate_101 not set => defaults to 1
        $request = $this->makeNewControllerRequest([
            'pretranslate_101' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(1, $ps->pretranslate_101);
    }

    #[Test]
    public function newControllerSetsPretranslate101WhenProvided(): void
    {
        $request = $this->makeNewControllerRequest([
            'pretranslate_101' => 0,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(0, $ps->pretranslate_101);
    }

    #[Test]
    public function newControllerPassesEngineConfigurationParameters(): void
    {
        // Engine declares two config parameters; request has values for both
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigurationParameters')->willReturn([
            'mmt_glossaries',
            'deepl_formality',
        ]);

        $request = $this->makeNewControllerRequest([
            'mmt_glossaries'  => 'glossary-123',
            'deepl_formality' => 'more',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $engine,
        );

        $this->assertSame('glossary-123', $ps->mmt_glossaries);
        $this->assertSame('more', $ps->deepl_formality);
    }

    #[Test]
    public function newControllerSkipsNullEngineConfigParameters(): void
    {
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigurationParameters')->willReturn([
            'mmt_glossaries',
        ]);

        $request = $this->makeNewControllerRequest([
            'mmt_glossaries' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $engine,
        );

        // The default from ProjectStructure should remain
        $this->assertNull($ps->mmt_glossaries);
    }

    #[Test]
    public function newControllerSetsOptionalFieldsWhenPresent(): void
    {
        // Engine must declare lara_glossaries, lara_style, mmt_glossaries
        // as configuration parameters so the generic loop picks them up.
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigurationParameters')->willReturn([
            'lara_glossaries',
            'lara_style',
            'mmt_glossaries',
        ]);

        $request = $this->makeNewControllerRequest([
            'lara_glossaries' => 'glossary-abc',
            'lara_style'      => 'formal',
            'mmt_glossaries'  => 'mmt-456',
            'dialect_strict'  => 'strict',
            'mt_evaluation'   => true,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $engine,
        );

        $this->assertSame('glossary-abc', $ps->lara_glossaries);
        $this->assertSame('formal', $ps->lara_style);
        $this->assertSame('mmt-456', $ps->mmt_glossaries);
        $this->assertSame('strict', $ps->dialect_strict);
        $this->assertTrue($ps->mt_evaluation);
    }

    #[Test]
    public function newControllerOmitsOptionalFieldsWhenFalsy(): void
    {
        // All optional fields are null/false/empty
        $request = $this->makeNewControllerRequest();
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        // These should remain at their ProjectStructure defaults (null)
        $this->assertNull($ps->lara_glossaries);
        $this->assertNull($ps->lara_style);
        $this->assertNull($ps->mmt_glossaries);
        $this->assertNull($ps->dialect_strict);
        $this->assertNull($ps->mt_evaluation);
        $this->assertNull($ps->filters_extraction_parameters);
        // xliff_parameters defaults to [] on ProjectStructure, not null
        $this->assertSame([], $ps->xliff_parameters);
    }

    #[Test]
    public function newControllerSetsProjectFeaturesFromRequest(): void
    {
        $features = ['feature_a', 'feature_b'];
        $request = $this->makeNewControllerRequest([
            'project_features' => $features,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame($features, $ps->project_features);
    }

    #[Test]
    public function newControllerSetsSubfilteringHandlers(): void
    {
        $request = $this->makeNewControllerRequest([
            'subfiltering_handlers' => 'twig,markup',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame('twig,markup', $ps->subfiltering_handlers);
    }

    #[Test]
    public function newControllerSetsPrivateTmKey(): void
    {
        $request = $this->makeNewControllerRequest([
            'private_tm_key' => [['key' => 'abc-key-123', 'name' => 'My TM']],
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame([['key' => 'abc-key-123', 'name' => 'My TM']], $ps->private_tm_key);
    }

    #[Test]
    public function newControllerSetsTmPrioritization(): void
    {
        $request = $this->makeNewControllerRequest([
            'tm_prioritization' => 'high',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame('high', $ps->tm_prioritization);
    }

    #[Test]
    public function newControllerSetsMetadata(): void
    {
        $meta = ['key1' => 'val1', 'key2' => 'val2'];
        $request = $this->makeNewControllerRequest([
            'metadata' => $meta,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame($meta, $ps->metadata);
    }

    #[Test]
    public function newControllerSetsPublicTmPenalty(): void
    {
        $request = $this->makeNewControllerRequest([
            'public_tm_penalty' => 15,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(15, $ps->public_tm_penalty);
    }

    #[Test]
    public function newControllerSetsOnlyPrivateToZeroWhenGetPublicMatchesNotSet(): void
    {
        // When get_public_matches key is absent entirely, isset() returns false => only_private = false (0)
        $request = $this->makeNewControllerRequest();
        unset($request['get_public_matches']);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(0, $ps->only_private);
    }

    #[Test]
    public function newControllerSetsUserIpFromServerContext(): void
    {
        // Simulate a server environment with REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $request = $this->makeNewControllerRequest();
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame('192.168.1.100', $ps->user_ip);

        unset($_SERVER['REMOTE_ADDR']);
    }

    #[Test]
    public function newControllerSetsDueDateToNullWhenEmpty(): void
    {
        $request = $this->makeNewControllerRequest([
            'due_date' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->due_date);
    }

    #[Test]
    public function newControllerSetsDueDateWhenProvided(): void
    {
        // Utils::mysqlTimestamp() expects a Unix timestamp
        $timestamp = strtotime('2026-06-15 10:30:00');
        $request = $this->makeNewControllerRequest([
            'due_date' => $timestamp,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        // Should be a formatted MySQL datetime string
        $this->assertSame('2026-06-15 10:30:00', $ps->due_date);
    }

    #[Test]
    public function newControllerSetsInstructions(): void
    {
        $instructions = ['segment_1' => 'Please translate carefully.'];
        $request = $this->makeNewControllerRequest([
            'instructions' => $instructions,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame($instructions, $ps->instructions);
    }

    #[Test]
    public function newControllerSetsInstructionsToNullWhenNotProvided(): void
    {
        $request = $this->makeNewControllerRequest([
            'instructions' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->instructions);
    }

    #[Test]
    public function newControllerSetsCharacterCounterModeWhenProvided(): void
    {
        $request = $this->makeNewControllerRequest([
            'character_counter_mode' => 'source',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame('source', $ps->character_counter_mode);
    }

    #[Test]
    public function newControllerSetsCharacterCounterCountTagsWhenProvided(): void
    {
        $request = $this->makeNewControllerRequest([
            'character_counter_count_tags' => true,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertTrue($ps->character_counter_count_tags);
    }

    #[Test]
    public function newControllerSetsQaModelTemplateWhenProvided(): void
    {
        $qaTemplateStub = $this->createStub(QAModelInterface::class);
        $qaTemplateStub->method('getDecodedModel')->willReturn(['categories' => ['style']]);

        $request = $this->makeNewControllerRequest([
            'qaModelTemplate' => $qaTemplateStub,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(['categories' => ['style']], $ps->qa_model_template);
    }

    #[Test]
    public function newControllerLeavesQaModelTemplateDefaultWhenFalsy(): void
    {
        $request = $this->makeNewControllerRequest([
            'qaModelTemplate' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->qa_model_template);
    }

    #[Test]
    public function newControllerSetsQaModelWhenProvided(): void
    {
        $qaModelStub = $this->createStub(QAModelInterface::class);
        $qaModelStub->method('getDecodedModel')->willReturn(['model' => 'data']);

        $request = $this->makeNewControllerRequest([
            'qaModel' => $qaModelStub,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(['model' => 'data'], $ps->qa_model);
    }

    #[Test]
    public function newControllerLeavesQaModelDefaultWhenFalsy(): void
    {
        $request = $this->makeNewControllerRequest([
            'qaModel' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->qa_model);
    }

    #[Test]
    public function newControllerSetsMtQeWorkflowPayableRateWhenProvided(): void
    {
        $request = $this->makeNewControllerRequest([
            'mt_qe_workflow_payable_rate' => 'rate-template-42',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame('rate-template-42', $ps->mt_qe_workflow_payable_rate);
    }

    #[Test]
    public function newControllerLeavesMtQeWorkflowPayableRateDefaultWhenFalsy(): void
    {
        $request = $this->makeNewControllerRequest([
            'mt_qe_workflow_payable_rate' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->mt_qe_workflow_payable_rate);
    }

    #[Test]
    public function newControllerSetsPayableRateModelIdWhenProvided(): void
    {
        $payableRateStub = new stdClass();
        $payableRateStub->id = 77;

        $request = $this->makeNewControllerRequest([
            'payableRateModelTemplate' => $payableRateStub,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(77, $ps->payable_rate_model_id);
    }

    #[Test]
    public function newControllerLeavesPayableRateModelIdDefaultWhenFalsy(): void
    {
        $request = $this->makeNewControllerRequest([
            'payableRateModelTemplate' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->payable_rate_model_id);
    }

    #[Test]
    public function newControllerSetsFiltersExtractionParametersWhenTruthy(): void
    {
        $filtersStruct = new FiltersConfigTemplateStruct();
        $filtersStruct->hydrateAllDto(['json' => ['extract_arrays' => true]]);

        $request = $this->makeNewControllerRequest([
            'filters_extraction_parameters' => $filtersStruct,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame($filtersStruct->jsonSerialize(), $ps->filters_extraction_parameters);
    }

    #[Test]
    public function newControllerSetsXliffParametersWhenTruthy(): void
    {
        $request = $this->makeNewControllerRequest([
            'xliff_parameters' => '{"xliff":"config"}',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame('{"xliff":"config"}', $ps->xliff_parameters);
    }

    #[Test]
    public function newControllerSetsMtEvaluationFalseWhenFalsy(): void
    {
        $request = $this->makeNewControllerRequest([
            'mt_evaluation' => false,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        // mt_evaluation defaults to null, falsy doesn't set it
        $this->assertNull($ps->mt_evaluation);
    }

    #[Test]
    public function newControllerSetsTargetLanguageMtEngineAssociation(): void
    {
        $assoc = ['fr-FR' => 3, 'de-DE' => 5];
        $request = $this->makeNewControllerRequest([
            'target_language_mt_engine_association' => $assoc,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame($assoc, $ps->target_language_mt_engine_association);
    }

    #[Test]
    public function newControllerSetsDueDateEmptyStringToNull(): void
    {
        // empty('') is true, so due_date should be null
        $request = $this->makeNewControllerRequest([
            'due_date' => '',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->due_date);
    }

    #[Test]
    public function newControllerSetsDueDateZeroToNull(): void
    {
        // empty(0) is true, so due_date should be null
        $request = $this->makeNewControllerRequest([
            'due_date' => 0,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->due_date);
    }

    #[Test]
    public function newControllerSetsHttpHost(): void
    {
        $request = $this->makeNewControllerRequest();
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(AppConfig::$HTTPHOST, $ps->HTTP_HOST);
    }

    #[Test]
    public function newControllerPretranslate100CoercesToInt(): void
    {
        // Truthy non-integer value should be coerced to 1
        $request = $this->makeNewControllerRequest([
            'pretranslate_100' => 'yes',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(1, $ps->pretranslate_100);
    }

    #[Test]
    public function newControllerPretranslate100CoercesFalsyToZero(): void
    {
        // Falsy value should be coerced to 0
        $request = $this->makeNewControllerRequest([
            'pretranslate_100' => '',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertSame(0, $ps->pretranslate_100);
    }

    #[Test]
    public function newControllerLaraGlossariesFalsyBranch(): void
    {
        // When lara_glossaries is falsy, it should NOT be set
        $request = $this->makeNewControllerRequest([
            'lara_glossaries' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->lara_glossaries);
    }

    #[Test]
    public function newControllerLaraStyleFalsyBranch(): void
    {
        $request = $this->makeNewControllerRequest([
            'lara_style' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->lara_style);
    }

    #[Test]
    public function newControllerMmtGlossariesFalsyBranch(): void
    {
        $request = $this->makeNewControllerRequest([
            'mmt_glossaries' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->mmt_glossaries);
    }

    #[Test]
    public function newControllerDialectStrictFalsyBranch(): void
    {
        $request = $this->makeNewControllerRequest([
            'dialect_strict' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $this->assertNull($ps->dialect_strict);
    }

    // ──────────────────────────────────────────────────────────────────
    // CreateProjectController::buildProjectStructure() tests
    // ──────────────────────────────────────────────────────────────────

    /**
     * Helper: build a minimal valid $data array for CreateProjectController.
     */
    private function makeCreateControllerData(array $overrides = []): array
    {
        return array_merge([
            'project_name'                          => 'Created Project',
            'private_tm_key'                        => [],
            'source_lang'                           => 'en-US',
            'target_lang'                           => 'it-IT',
            'job_subject'                           => 'general',
            'mt_engine'                             => 1,
            'tms_engine'                            => 1,
            'public_tm_penalty'                     => null,
            'pretranslate_100'                      => 0,
            'pretranslate_101'                      => 1,
            'dialect_strict'                        => null,
            'only_private'                          => false,
            'due_date'                              => null,
            'target_language_mt_engine_association'  => [],
            'tm_prioritization'                     => null,
            'character_counter_mode'                => null,
            'character_counter_count_tags'           => null,
            'subfiltering_handlers'                 => null,
            'filters_extraction_parameters'         => null,
            'xliff_parameters'                      => null,
            'qa_model_template'                     => null,
            'payable_rate_model_template'            => null,
            'project_features'                      => [],
        ], $overrides);
    }

    #[Test]
    public function createControllerSetsScalarFieldsCorrectly(): void
    {
        $data = $this->makeCreateControllerData([
            'project_name' => 'My Created Project',
            'job_subject'  => 'medical',
            'source_lang'  => 'de-DE',
            'target_lang'  => 'en-US,fr-FR',
            'mt_engine'    => 3,
        ]);
        $metadata = ['key' => 'value'];
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            $metadata,
            $filesFound,
            'upload-token-xyz',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertInstanceOf(ProjectStructure::class, $ps);
        $this->assertSame('My Created Project', $ps->project_name);
        $this->assertSame('medical', $ps->job_subject);
        $this->assertSame('de-DE', $ps->source_language);
        $this->assertSame(['en-US', 'fr-FR'], $ps->target_language);
        $this->assertSame(3, $ps->mt_engine);
        $this->assertSame('upload-token-xyz', $ps->uploadToken);
    }

    #[Test]
    public function createControllerSetsUserFields(): void
    {
        $data = $this->makeCreateControllerData();
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertTrue($ps->userIsLogged);
        $this->assertSame(42, $ps->uid);
        $this->assertSame('test@example.com', $ps->id_customer);
        $this->assertSame('test@example.com', $ps->owner);
    }

    #[Test]
    public function createControllerSetsStatusNotReadyForAnalysis(): void
    {
        $data = $this->makeCreateControllerData();
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS, $ps->status);
    }

    #[Test]
    public function createControllerSetsMetadata(): void
    {
        $metadata = ['custom_key' => 'custom_value', 'other' => 123];
        $data = $this->makeCreateControllerData();
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            $metadata,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame($metadata, $ps->metadata);
    }

    #[Test]
    public function createControllerSetsGdriveSession(): void
    {
        $gdriveSession = ['service_account' => 'gdrive@test.com'];
        $data = $this->makeCreateControllerData();
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            $gdriveSession,
        );

        $this->assertNotNull($ps->session);
        $this->assertSame('gdrive@test.com', $ps->session['service_account']);
        // uid should be injected into the session
        $this->assertSame(42, $ps->session['uid']);
    }

    #[Test]
    public function createControllerNullGdriveSessionLeavesDefault(): void
    {
        $data = $this->makeCreateControllerData();
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertNull($ps->session);
    }

    #[Test]
    public function createControllerPassesEngineConfigurationParameters(): void
    {
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigurationParameters')->willReturn([
            'deepl_formality',
            'deepl_id_glossary',
        ]);

        $data = $this->makeCreateControllerData([
            'deepl_formality'   => 'less',
            'deepl_id_glossary' => 'gl-789',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $engine,
            null,
        );

        $this->assertSame('less', $ps->deepl_formality);
        $this->assertSame('gl-789', $ps->deepl_id_glossary);
    }

    #[Test]
    public function createControllerSkipsNullEngineConfigParameters(): void
    {
        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getConfigurationParameters')->willReturn([
            'deepl_formality',
        ]);

        $data = $this->makeCreateControllerData([
            'deepl_formality' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $engine,
            null,
        );

        $this->assertNull($ps->deepl_formality);
    }

    #[Test]
    public function createControllerSetsOptionalFieldsWhenPresent(): void
    {
        $data = $this->makeCreateControllerData([
            'dialect_strict'                => 'strict',
            'filters_extraction_parameters' => ['some' => 'param'],
            'xliff_parameters'              => '{"xliff": "param"}',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame('strict', $ps->dialect_strict);
        $this->assertSame(['some' => 'param'], $ps->filters_extraction_parameters);
        $this->assertSame('{"xliff": "param"}', $ps->xliff_parameters);
    }

    #[Test]
    public function createControllerDefaultsTmsEngineWhenMissing(): void
    {
        $data = $this->makeCreateControllerData();
        unset($data['tms_engine']);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(1, $ps->tms_engine);
    }

    #[Test]
    public function createControllerSetsProjectFeaturesFromData(): void
    {
        $features = ['feature_x'];
        $data = $this->makeCreateControllerData([
            'project_features' => $features,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame($features, $ps->project_features);
    }

    #[Test]
    public function createControllerSetsPayableRateModelFields(): void
    {
        // Create a stub with id property and jsonSerialize() support
        $payableRateStub = new class implements \JsonSerializable {
            public int $id = 99;

            public function jsonSerialize(): array
            {
                return ['id' => $this->id, 'breakdowns' => []];
            }
        };

        $data = $this->makeCreateControllerData([
            'payable_rate_model_template' => $payableRateStub,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame($payableRateStub->jsonSerialize(), $ps->payable_rate_model);
        $this->assertSame(99, $ps->payable_rate_model_id);
    }

    #[Test]
    public function createControllerLeavesPayableRateFieldsDefaultWhenFalsy(): void
    {
        $data = $this->makeCreateControllerData([
            'payable_rate_model_template' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertNull($ps->payable_rate_model);
        $this->assertNull($ps->payable_rate_model_id);
    }

    #[Test]
    public function createControllerSetsPrivateTmKey(): void
    {
        $data = $this->makeCreateControllerData([
            'private_tm_key' => [['key' => 'my-secret-key', 'name' => 'Secret TM']],
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame([['key' => 'my-secret-key', 'name' => 'Secret TM']], $ps->private_tm_key);
    }

    #[Test]
    public function createControllerSetsPublicTmPenalty(): void
    {
        $data = $this->makeCreateControllerData([
            'public_tm_penalty' => 20,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(20, $ps->public_tm_penalty);
    }

    #[Test]
    public function createControllerSetsPretranslateFields(): void
    {
        $data = $this->makeCreateControllerData([
            'pretranslate_100' => 1,
            'pretranslate_101' => 0,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(1, $ps->pretranslate_100);
        $this->assertSame(0, $ps->pretranslate_101);
    }

    #[Test]
    public function createControllerSetsOnlyPrivate(): void
    {
        $data = $this->makeCreateControllerData([
            'only_private' => true,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        // bool true coerced to int 1 by ProjectStructure's int property
        $this->assertSame(1, $ps->only_private);
    }

    #[Test]
    public function createControllerSetsDueDate(): void
    {
        $data = $this->makeCreateControllerData([
            'due_date' => '2026-12-25 00:00:00',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame('2026-12-25 00:00:00', $ps->due_date);
    }

    #[Test]
    public function createControllerSetsTargetLanguageMtEngineAssociation(): void
    {
        $assoc = ['it-IT' => 2, 'es-ES' => 4];
        $data = $this->makeCreateControllerData([
            'target_language_mt_engine_association' => $assoc,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame($assoc, $ps->target_language_mt_engine_association);
    }

    #[Test]
    public function createControllerSetsUserIpFromServerContext(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.50';

        $data = $this->makeCreateControllerData();
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame('10.0.0.50', $ps->user_ip);

        unset($_SERVER['REMOTE_ADDR']);
    }

    #[Test]
    public function createControllerSetsTmPrioritizationWhenProvided(): void
    {
        $data = $this->makeCreateControllerData([
            'tm_prioritization' => 'enabled',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame('enabled', $ps->tm_prioritization);
    }

    #[Test]
    public function createControllerSetsTmPrioritizationNullWhenEmpty(): void
    {
        $data = $this->makeCreateControllerData([
            'tm_prioritization' => '',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        // empty('') is true => null
        $this->assertNull($ps->tm_prioritization);
    }

    #[Test]
    public function createControllerSetsCharacterCounterModeWhenProvided(): void
    {
        $data = $this->makeCreateControllerData([
            'character_counter_mode' => 'source',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame('source', $ps->character_counter_mode);
    }

    #[Test]
    public function createControllerSetsCharacterCounterModeNullWhenEmpty(): void
    {
        $data = $this->makeCreateControllerData([
            'character_counter_mode' => '',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertNull($ps->character_counter_mode);
    }

    #[Test]
    public function createControllerSetsCharacterCounterCountTagsWhenProvided(): void
    {
        $data = $this->makeCreateControllerData([
            'character_counter_count_tags' => true,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertTrue($ps->character_counter_count_tags);
    }

    #[Test]
    public function createControllerSetsCharacterCounterCountTagsNullWhenEmpty(): void
    {
        $data = $this->makeCreateControllerData([
            'character_counter_count_tags' => '',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertNull($ps->character_counter_count_tags);
    }

    #[Test]
    public function createControllerSetsSubfilteringHandlers(): void
    {
        $data = $this->makeCreateControllerData([
            'subfiltering_handlers' => 'twig,markup',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame('twig,markup', $ps->subfiltering_handlers);
    }

    #[Test]
    public function createControllerLeavesFiltersExtractionParametersDefaultWhenFalsy(): void
    {
        $data = $this->makeCreateControllerData([
            'filters_extraction_parameters' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertNull($ps->filters_extraction_parameters);
    }

    #[Test]
    public function createControllerLeavesXliffParametersDefaultWhenFalsy(): void
    {
        $data = $this->makeCreateControllerData([
            'xliff_parameters' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame([], $ps->xliff_parameters);
    }

    #[Test]
    public function createControllerSetsQaModelTemplateWhenProvided(): void
    {
        $qaTemplateStub = $this->createStub(QAModelInterface::class);
        $qaTemplateStub->method('getDecodedModel')->willReturn(['categories' => ['accuracy']]);

        $data = $this->makeCreateControllerData([
            'qa_model_template' => $qaTemplateStub,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(['categories' => ['accuracy']], $ps->qa_model_template);
    }

    #[Test]
    public function createControllerLeavesQaModelTemplateDefaultWhenFalsy(): void
    {
        $data = $this->makeCreateControllerData([
            'qa_model_template' => null,
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertNull($ps->qa_model_template);
    }

    #[Test]
    public function createControllerSetsHttpHost(): void
    {
        $data = $this->makeCreateControllerData();
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(AppConfig::$HTTPHOST, $ps->HTTP_HOST);
    }

    #[Test]
    public function createControllerSetsFilesFromFilesFound(): void
    {
        $filesFound = $this->makeFilesFound([
            'arrayFiles'     => ['doc1.xliff', 'doc2.xliff'],
            'arrayFilesMeta' => [['size' => 500], ['size' => 600]],
        ]);
        $data = $this->makeCreateControllerData();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(['doc1.xliff', 'doc2.xliff'], $ps->array_files);
        $this->assertSame([['size' => 500], ['size' => 600]], $ps->array_files_meta);
    }

    #[Test]
    public function createControllerSplitsTargetLanguageOnComma(): void
    {
        $data = $this->makeCreateControllerData([
            'target_lang' => 'ja-JP,ko-KR,zh-CN',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame(['ja-JP', 'ko-KR', 'zh-CN'], $ps->target_language);
    }

    #[Test]
    public function createControllerSetsDialectStrict(): void
    {
        $data = $this->makeCreateControllerData([
            'dialect_strict' => 'relaxed',
        ]);
        $filesFound = $this->makeFilesFound();

        $ps = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertSame('relaxed', $ps->dialect_strict);
    }

    // ──────────────────────────────────────────────────────────────────
    // Cross-cutting concerns (both controllers share these patterns)
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function bothControllersSetHttpHostFromAppConfig(): void
    {
        $request = $this->makeNewControllerRequest();
        $filesFound = $this->makeFilesFound();

        $ps1 = $this->newController->buildProjectStructure(
            $request,
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $data = $this->makeCreateControllerData();
        $ps2 = $this->createProjectController->buildProjectStructure(
            $data,
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        // Both should pick up AppConfig::$HTTPHOST (whatever its current value is)
        $this->assertSame($ps1->HTTP_HOST, $ps2->HTTP_HOST);
    }

    #[Test]
    public function bothControllersReturnProjectStructureInstance(): void
    {
        $filesFound = $this->makeFilesFound();

        $ps1 = $this->newController->buildProjectStructure(
            $this->makeNewControllerRequest(),
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
        );

        $ps2 = $this->createProjectController->buildProjectStructure(
            $this->makeCreateControllerData(),
            [],
            $filesFound,
            'tok',
            $this->user,
            $this->engine,
            null,
        );

        $this->assertInstanceOf(ProjectStructure::class, $ps1);
        $this->assertInstanceOf(ProjectStructure::class, $ps2);
    }
}
