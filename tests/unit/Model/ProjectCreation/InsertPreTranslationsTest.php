<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\TranslationTuple;
use Model\Xliff\DTO\XliffRuleInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockException;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\SegmentStorageService::insertPreTranslations()}.
 *
 * Verifies:
 * - Empty translation structs are skipped
 * - Happy path: builds correct SQL values from pre-computed QA scalars on tuples
 * - QA error scalars map correctly to SQL values
 * - Final state sets create_2_pass_review
 * - Non-final state does not set create_2_pass_review
 * - Multiple translation tuples are all processed
 * - JSON-encoded payable rates are decoded
 */
class InsertPreTranslationsTest extends AbstractTest
{
    private ProjectStructure $projectStructure;
    private JobStruct $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->job = new JobStruct([
            'id'         => 99,
            'password'   => 'abc123',
            'id_project' => 42,
            'source'     => 'en-US',
            'target'     => 'it-IT',
        ]);

        $this->projectStructure = new ProjectStructure([
            'id_project'         => 42,
            'source_language'    => 'en-US',
            'create_date'        => '2025-03-19 10:00:00',
            'translations'       => [],
            'segments_metadata'  => [],
            'create_2_pass_review' => false,
            'array_jobs'         => ['payable_rates' => [99 => ['NO_MATCH' => 100]]],
            'result'             => ['errors' => [], 'data' => []],
        ]);
    }

    /**
     * Build the service under test with injectable stubs.
     *
     * @throws MockException
     */
    private function buildService(
        ?ProjectManagerModel $pmModel = null,
    ): TestableSegmentStorageService {
        $service = new TestableSegmentStorageService(
            $this->createStub(IDatabase::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MatecatLogger::class),
            $pmModel ?? $this->createStub(ProjectManagerModel::class),
        );

        return $service;
    }

    /**
     * Create a TranslationTuple with all mutable fields set.
     */
    private function makeTuple(
        string              $target = 'translated text',
        string              $source = 'source text',
        int                 $rawWordCount = 10,
        int                 $segmentId = 1,
        string              $segmentHash = 'hash123',
        int                 $fileId = 1,
        ?int                $mrkPosition = null,
        ?XliffRuleInterface $rule = null,
        ?string             $state = null,
        string              $translationLayer0 = 'normalized',
        string              $suggestionLayer0 = 'normalized',
        string              $serializedErrors = '',
        int                 $warning = 0,
    ): TranslationTuple {
        $tuple = new TranslationTuple($target, $source, $rawWordCount, $rule ?? $this->createStub(XliffRuleInterface::class), $mrkPosition, $state);
        $tuple->segmentId        = $segmentId;
        $tuple->segmentHash      = $segmentHash;
        $tuple->fileId           = $fileId;
        $tuple->internalId       = 'tu-1';
        $tuple->translationLayer0 = $translationLayer0;
        $tuple->suggestionLayer0  = $suggestionLayer0;
        $tuple->serializedErrors  = $serializedErrors;
        $tuple->warning           = $warning;

        return $tuple;
    }

    /**
     * Build a stub XliffRuleInterface.
     *
     * @throws MockException
     */
    private function buildRuleStub(
        string $editorStatus = 'TRANSLATED',
        string $matchType = 'ICE',
        float  $eqWordCount = 0.0,
        float  $stdWordCount = 10.0,
    ): XliffRuleInterface {
        $rule = $this->createStub(XliffRuleInterface::class);
        $rule->method('asEditorStatus')->willReturn($editorStatus);
        $rule->method('asMatchType')->willReturn($matchType);
        $rule->method('asEquivalentWordCount')->willReturn($eqWordCount);
        $rule->method('asStandardWordCount')->willReturn($stdWordCount);

        return $rule;
    }

    // ── Test 1: Empty translation structs skipped ────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function skipsEmptyTranslationStructs(): void
    {
        $this->projectStructure->translations = [
            'tu-1' => [],
            'tu-2' => [],
        ];

        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->never())->method('insertPreTranslations');

        $service = $this->buildService(pmModel: $pmModel);

        $service->insertPreTranslations($this->job, $this->projectStructure);

        $this->assertFalse($this->projectStructure->create_2_pass_review);
    }

    // ── Test 2: Happy path — single translation ─────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function happyPathSingleTranslation(): void
    {
        $rule = $this->buildRuleStub();

        $tuple = $this->makeTuple(
            rule: $rule,
            translationLayer0: 'normalized translation',
            suggestionLayer0: 'normalized translation',
        );

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->once())
            ->method('insertPreTranslations')
            ->with($this->callback(function (array $values): bool {
                $this->assertCount(1, $values);
                $row = $values[0];

                $this->assertSame(1, $row['id_segment']);
                $this->assertSame(99, $row['id_job']);
                $this->assertSame('hash123', $row['segment_hash']);
                $this->assertSame('TRANSLATED', $row['status']);
                $this->assertSame('normalized translation', $row['translation']);
                $this->assertSame('normalized translation', $row['suggestion']);
                $this->assertSame(0, $row['locked']);
                $this->assertSame('ICE', $row['match_type']);
                $this->assertSame(0.0, $row['eq_word_count']);
                $this->assertSame('', $row['serialized_errors_list']);
                $this->assertSame(0, $row['warning']);
                $this->assertNull($row['suggestion_match']);
                $this->assertSame(10.0, $row['standard_word_count']);
                $this->assertSame(0, $row['version_number']);

                return true;
            }));

        $service = $this->buildService(pmModel: $pmModel);

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 3: QA error scalars map to SQL values ──────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function mapsQAErrorScalarsToSqlValues(): void
    {
        $rule = $this->buildRuleStub();

        $tuple = $this->makeTuple(
            rule: $rule,
            translationLayer0: 'raw target with errors',
            suggestionLayer0: 'raw target with errors',
            serializedErrors: '{"errors":["tag mismatch"]}',
            warning: 1,
        );

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->once())
            ->method('insertPreTranslations')
            ->with($this->callback(function (array $values): bool {
                $row = $values[0];

                $this->assertSame('raw target with errors', $row['translation']);
                $this->assertSame('raw target with errors', $row['suggestion']);
                $this->assertSame('{"errors":["tag mismatch"]}', $row['serialized_errors_list']);
                $this->assertSame(1, $row['warning']);

                return true;
            }));

        $service = $this->buildService(pmModel: $pmModel);

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 4: Final state → create_2_pass_review = true ───────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function setsCreateSecondPassReviewWhenStateFinal(): void
    {
        $rule = $this->buildRuleStub();

        $tuple = $this->makeTuple(rule: $rule, state: 'final');

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $service = $this->buildService();

        $this->assertFalse($this->projectStructure->create_2_pass_review);

        $service->insertPreTranslations($this->job, $this->projectStructure);

        $this->assertTrue($this->projectStructure->create_2_pass_review);
    }

    // ── Test 5: Non-final state → create_2_pass_review stays false ──

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function doesNotSetCreateSecondPassReviewForNonFinalState(): void
    {
        $rule = $this->buildRuleStub();

        $tuple = $this->makeTuple(rule: $rule, state: 'translated');

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $service = $this->buildService();

        $service->insertPreTranslations($this->job, $this->projectStructure);

        $this->assertFalse($this->projectStructure->create_2_pass_review);
    }

    // ── Test 6: Multiple translation tuples ──────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function processesMultipleTranslationTuples(): void
    {
        $rule = $this->buildRuleStub();

        $tuple1 = $this->makeTuple(segmentHash: 'hash1', rule: $rule);
        $tuple2 = $this->makeTuple(segmentId: 2, segmentHash: 'hash2', rule: $rule);

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple1],
            'tu-2' => [0 => $tuple2],
        ];

        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->once())
            ->method('insertPreTranslations')
            ->with($this->callback(function (array $values): bool {
                $this->assertCount(2, $values);
                $this->assertSame(1, $values[0]['id_segment']);
                $this->assertSame(2, $values[1]['id_segment']);

                return true;
            }));

        $service = $this->buildService(pmModel: $pmModel);

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 7: JSON-encoded payable rates are decoded ───────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function decodesJsonPayableRatesWhenString(): void
    {
        $payableRates = ['NO_MATCH' => 100, 'ICE' => 0];

        $this->projectStructure->array_jobs = [
            'payable_rates' => [99 => json_encode($payableRates)],
        ];

        /** @var XliffRuleInterface&MockObject $rule */
        $rule = $this->createMock(XliffRuleInterface::class);
        $rule->method('asEditorStatus')->willReturn('TRANSLATED');
        $rule->method('asMatchType')->willReturn('ICE');
        $rule->expects($this->once())
            ->method('asEquivalentWordCount')
            ->with(10, $payableRates)
            ->willReturn(0.0);
        $rule->expects($this->once())
            ->method('asStandardWordCount')
            ->with(10, $payableRates)
            ->willReturn(10.0);

        $this->projectStructure->translations = [
            'tu-1' => [0 => $this->makeTuple(rule: $rule)],
        ];

        $service = $this->buildService();

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }
}
