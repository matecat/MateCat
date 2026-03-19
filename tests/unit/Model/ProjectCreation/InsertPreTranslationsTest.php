<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\TranslationTuple;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use Model\Xliff\DTO\XliffRuleInterface;
use Model\Xliff\DTO\XliffRulesModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockException;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\LQA\QA;

/**
 * Unit tests for {@see \Model\ProjectCreation\SegmentStorageService::insertPreTranslations()}.
 *
 * Verifies:
 * - Exception thrown when no chunks found
 * - Empty translation structs are skipped
 * - Segments not found in DAO are skipped
 * - Happy path: builds correct SQL values and calls insertPreTranslations
 * - QA errors: uses getTargetSeg(), sets warning flag
 * - Final state sets create_2_pass_review
 * - Non-final state does not set create_2_pass_review
 * - Multiple translation tuples are all processed
 * - JSON-encoded payable rates are decoded
 * - No insert call when all tuples are skipped
 */
class InsertPreTranslationsTest extends AbstractTest
{
    private ProjectStructure $projectStructure;
    private JobStruct $job;
    private JobStruct $chunk;

    /**
     */
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

        // Chunk returned by getChunksByJobId — needs source/target for QA lang setup
        $this->chunk = new JobStruct([
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
            'current_xliff_info' => [1 => ['version' => 1]],
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
        ?SegmentDao          $segmentDao = null,
        ?QA                  $qa = null,
        ?XliffRulesModel     $xliffRulesModel = null,
    ): TestableSegmentStorageService {
        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer1')->willReturnArgument(0);
        $filter->method('fromLayer1ToLayer0')->willReturnArgument(0);

        $service = new TestableSegmentStorageService(
            $this->createStub(IDatabase::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MatecatLogger::class),
            $filter,
            $pmModel ?? $this->createStub(ProjectManagerModel::class),
        );

        $service->setChunksByJobIdResult([$this->chunk]);

        if ($segmentDao !== null) {
            $service->setSegmentDao($segmentDao);
        }

        if ($qa !== null) {
            $service->setQA($qa);
        }

        if ($xliffRulesModel !== null) {
            $this->projectStructure->xliff_parameters = $xliffRulesModel;
        }

        return $service;
    }

    /**
     * Create a TranslationTuple with all mutable fields set.
     */
    private function makeTuple(
        string $target = 'translated text',
        int    $segmentId = 1,
        string $segmentHash = 'hash123',
        int    $fileId = 1,
        ?int   $mrkPosition = null,
        array  $transUnit = [],
    ): TranslationTuple {
        $tuple = new TranslationTuple($target, $transUnit, $mrkPosition);
        $tuple->segmentId   = $segmentId;
        $tuple->segmentHash = $segmentHash;
        $tuple->fileId      = $fileId;
        $tuple->internalId  = 'tu-1';

        return $tuple;
    }

    /**
     * Build a stub XliffRulesModel that returns a fixed XliffRuleInterface.
     *
     * @throws MockException
     */
    private function buildRuleStubs(
        string $editorStatus = 'TRANSLATED',
        string $matchType = 'ICE',
        float  $eqWordCount = 0.0,
        float  $stdWordCount = 10.0,
    ): array {
        $rule = $this->createStub(XliffRuleInterface::class);
        $rule->method('asEditorStatus')->willReturn($editorStatus);
        $rule->method('asMatchType')->willReturn($matchType);
        $rule->method('asEquivalentWordCount')->willReturn($eqWordCount);
        $rule->method('asStandardWordCount')->willReturn($stdWordCount);

        $rulesModel = $this->createStub(XliffRulesModel::class);
        $rulesModel->method('getMatchingRule')->willReturn($rule);

        return [$rulesModel, $rule];
    }

    /**
     * Build a stub QA that reports no errors and returns normalized target.
     *
     * @throws MockException
     */
    private function buildQAStub(
        bool   $hasErrors = false,
        string $normalizedTarget = 'normalized',
        string $targetSeg = 'raw target',
        string $errorsJson = '',
    ): QA {
        $qa = $this->createStub(QA::class);
        $qa->method('setFeatureSet')->willReturnSelf();
        $qa->method('thereAreErrors')->willReturn($hasErrors);
        $qa->method('getTrgNormalized')->willReturn($normalizedTarget);
        $qa->method('getTargetSeg')->willReturn($targetSeg);
        $qa->method('getErrorsJSON')->willReturn($errorsJson);

        return $qa;
    }

    /**
     * Build a SegmentDao stub that returns a SegmentStruct for getById.
     *
     * @throws MockException
     */
    private function buildSegmentDaoStub(
        string $segmentContent = 'source text',
        int    $rawWordCount = 10,
    ): SegmentDao {
        $segment = new SegmentStruct([
            'id'             => 1,
            'id_file'        => 1,
            'internal_id'    => 'tu-1',
            'segment'        => $segmentContent,
            'segment_hash'   => 'hash123',
            'raw_word_count' => $rawWordCount,
        ]);

        $dao = $this->createStub(SegmentDao::class);
        $dao->method('getById')->willReturn($segment);

        return $dao;
    }

    // ── Test 1: No chunks found ─────────────────────────────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function throwsExceptionWhenNoChunksFound(): void
    {
        $service = $this->buildService();
        $service->setChunksByJobIdResult([]); // override to empty

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No Job found!!!');

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 2: Empty translation structs skipped ────────────────────

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

        // No exception, no insert call — empty structs are silently skipped
        $this->assertFalse($this->projectStructure->create_2_pass_review);
    }

    // ── Test 3: Segment not found in DAO ────────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function skipsSegmentNotFoundInDao(): void
    {
        [$rulesModel] = $this->buildRuleStubs();

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getById')->willReturn(null);

        $this->projectStructure->translations = [
            'tu-1' => [0 => $this->makeTuple()],
        ];

        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->never())->method('insertPreTranslations');

        $service = $this->buildService(
            pmModel: $pmModel,
            segmentDao: $segmentDao,
            xliffRulesModel: $rulesModel,
        );

        $service->insertPreTranslations($this->job, $this->projectStructure);

        $this->assertFalse($this->projectStructure->create_2_pass_review);
    }

    // ── Test 4: Happy path — single translation ─────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function happyPathSingleTranslation(): void
    {
        [$rulesModel] = $this->buildRuleStubs();

        $qa = $this->buildQAStub(
            normalizedTarget: 'normalized translation',
        );

        $segmentDao = $this->buildSegmentDaoStub();

        $tuple = $this->makeTuple();

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

        $service = $this->buildService(
            pmModel: $pmModel,
            segmentDao: $segmentDao,
            qa: $qa,
            xliffRulesModel: $rulesModel,
        );

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 5: QA errors → uses getTargetSeg, sets warning ─────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function usesTargetSegWhenQAHasErrors(): void
    {
        [$rulesModel] = $this->buildRuleStubs();

        $qa = $this->buildQAStub(
            hasErrors: true,
            targetSeg: 'raw target with errors',
            errorsJson: '{"errors":["tag mismatch"]}',
        );

        $segmentDao = $this->buildSegmentDaoStub();

        $this->projectStructure->translations = [
            'tu-1' => [0 => $this->makeTuple()],
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

        $service = $this->buildService(
            pmModel: $pmModel,
            segmentDao: $segmentDao,
            qa: $qa,
            xliffRulesModel: $rulesModel,
        );

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 6: Final state → create_2_pass_review = true ───────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function setsCreateSecondPassReviewWhenStateFinal(): void
    {
        [$rulesModel] = $this->buildRuleStubs();
        $qa = $this->buildQAStub();
        $segmentDao = $this->buildSegmentDaoStub();

        // trans-unit with state='final'
        $tuple = $this->makeTuple(transUnit: [
            'target' => ['attr' => ['state' => 'final']],
        ]);

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $service = $this->buildService(
            segmentDao: $segmentDao,
            qa: $qa,
            xliffRulesModel: $rulesModel,
        );

        $this->assertFalse($this->projectStructure->create_2_pass_review);

        $service->insertPreTranslations($this->job, $this->projectStructure);

        $this->assertTrue($this->projectStructure->create_2_pass_review);
    }

    // ── Test 7: Non-final state → create_2_pass_review stays false ──

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function doesNotSetCreateSecondPassReviewForNonFinalState(): void
    {
        [$rulesModel] = $this->buildRuleStubs();
        $qa = $this->buildQAStub();
        $segmentDao = $this->buildSegmentDaoStub();

        // trans-unit with state='translated' (not final)
        $tuple = $this->makeTuple(transUnit: [
            'target' => ['attr' => ['state' => 'translated']],
        ]);

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $service = $this->buildService(
            segmentDao: $segmentDao,
            qa: $qa,
            xliffRulesModel: $rulesModel,
        );

        $service->insertPreTranslations($this->job, $this->projectStructure);

        $this->assertFalse($this->projectStructure->create_2_pass_review);
    }

    // ── Test 8: Multiple translation tuples ──────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function processesMultipleTranslationTuples(): void
    {
        [$rulesModel] = $this->buildRuleStubs();
        $qa = $this->buildQAStub();
        $segmentDao = $this->buildSegmentDaoStub();

        $tuple1 = $this->makeTuple(segmentHash: 'hash1');
        $tuple2 = $this->makeTuple(segmentId: 2, segmentHash: 'hash2');

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

        $service = $this->buildService(
            pmModel: $pmModel,
            segmentDao: $segmentDao,
            qa: $qa,
            xliffRulesModel: $rulesModel,
        );

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 9: JSON-encoded payable rates are decoded ───────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function decodesJsonPayableRatesWhenString(): void
    {
        $payableRates = ['NO_MATCH' => 100, 'ICE' => 0];

        // Store as JSON string — method should json_decode it
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

        $rulesModel = $this->createStub(XliffRulesModel::class);
        $rulesModel->method('getMatchingRule')->willReturn($rule);

        $qa = $this->buildQAStub();
        $segmentDao = $this->buildSegmentDaoStub();

        $this->projectStructure->translations = [
            'tu-1' => [0 => $this->makeTuple()],
        ];

        $service = $this->buildService(
            segmentDao: $segmentDao,
            qa: $qa,
            xliffRulesModel: $rulesModel,
        );

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }

    // ── Test 10: No insert when all tuples skipped ───────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function doesNotCallInsertWhenAllTuplesSkipped(): void
    {
        [$rulesModel] = $this->buildRuleStubs();

        // SegmentDao returns null for all lookups
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getById')->willReturn(null);

        $this->projectStructure->translations = [
            'tu-1' => [0 => $this->makeTuple()],
            'tu-2' => [0 => $this->makeTuple(segmentId: 2)],
        ];

        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->never())->method('insertPreTranslations');

        $service = $this->buildService(
            pmModel: $pmModel,
            segmentDao: $segmentDao,
            xliffRulesModel: $rulesModel,
        );

        $service->insertPreTranslations($this->job, $this->projectStructure);
    }
}
