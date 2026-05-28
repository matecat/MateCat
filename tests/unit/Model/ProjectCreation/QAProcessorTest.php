<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\TranslationTuple;
use Model\Xliff\DTO\XliffRuleInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockException;
use TestHelpers\AbstractTest;
use Utils\LQA\QA;

/**
 * Unit tests for {@see \Model\ProjectCreation\QAProcessor}.
 *
 * Verifies:
 * - No-errors path: uses getTrgNormalized(), no warning
 * - Errors path: uses getTargetSeg(), sets warning and serialized errors
 * - Empty translation structs are skipped
 * - Multiple tuples are all processed
 * - Layer conversions are applied correctly (L0→L1 for input, L1→L0 for output)
 */
class QAProcessorTest extends AbstractTest
{
    private ProjectStructure $projectStructure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectStructure = new ProjectStructure([
            'id_project'      => 42,
            'source_language'  => 'en-US',
            'create_date'      => '2025-03-19 10:00:00',
            'translations'     => [],
            'segments_metadata' => [],
            'result'           => ['errors' => [], 'data' => []],
        ]);
    }

    /**
     * Build the processor under test with injectable QA stub.
     *
     * The filter stub prefixes conversions so tests can verify layer transforms:
     * - fromLayer0ToLayer1: prepends 'L1:'
     * - fromLayer1ToLayer0: prepends 'L0:'
     *
     * @throws MockException
     */
    private function buildProcessor(?QA $qa = null, bool $icuEnabled = false): TestableQAProcessor
    {
        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer1')
            ->willReturnCallback(fn(string $s) => 'L1:' . $s);
        $filter->method('fromLayer1ToLayer0')
            ->willReturnCallback(fn(string $s) => 'L0:' . $s);

        $processor = new TestableQAProcessor(
            $filter,
            $this->createStub(FeatureSet::class),
            $icuEnabled,
        );

        if ($qa !== null) {
            $processor->setQA($qa);
        }

        return $processor;
    }

    /**
     * Create a TranslationTuple with mutable fields set.
     */
    private function makeTuple(
        string $target = 'target text',
        string $source = 'source text',
        int    $rawWordCount = 10,
    ): TranslationTuple {
        $rule = $this->createStub(XliffRuleInterface::class);
        $tuple = new TranslationTuple($target, $source, $rawWordCount, $rule);
        $tuple->segmentId   = 1;
        $tuple->segmentHash = 'hash123';
        $tuple->fileId      = 1;

        return $tuple;
    }

    /**
     * Build a stub QA.
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
        $qa->method('performConsistencyCheck')->willReturn([]);
        $qa->method('thereAreErrors')->willReturn($hasErrors);
        $qa->method('getTrgNormalized')->willReturn($normalizedTarget);
        $qa->method('getTargetSeg')->willReturn($targetSeg);
        $qa->method('getErrorsJSON')->willReturn($errorsJson);

        return $qa;
    }

    // ── Test 1: No errors → uses normalized target ──────────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function setsNormalizedTargetWhenNoErrors(): void
    {
        $qa = $this->buildQAStub(
            normalizedTarget: 'normalized translation',
        );

        $tuple = $this->makeTuple();
        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $processor = $this->buildProcessor($qa);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        // L0: prefix from fromLayer1ToLayer0 stub
        $this->assertSame('L0:normalized translation', $tuple->translationLayer0);
        $this->assertSame('L0:normalized translation', $tuple->suggestionLayer0);
        $this->assertSame('', $tuple->serializedErrors);
        $this->assertSame(0, $tuple->warning);
    }

    // ── Test 2: Errors → uses raw target, sets warning ──────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function usesTargetSegAndSetsWarningWhenErrors(): void
    {
        $qa = $this->buildQAStub(
            hasErrors: true,
            targetSeg: 'raw target with errors',
            errorsJson: '{"errors":["tag mismatch"]}',
        );

        $tuple = $this->makeTuple();
        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $processor = $this->buildProcessor($qa);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        $this->assertSame('L0:raw target with errors', $tuple->translationLayer0);
        $this->assertSame('L0:raw target with errors', $tuple->suggestionLayer0);
        $this->assertSame('{"errors":["tag mismatch"]}', $tuple->serializedErrors);
        $this->assertSame(1, $tuple->warning);
    }

    // ── Test 3: Empty structs are skipped ────────────────────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function skipsEmptyTranslationStructs(): void
    {
        $this->projectStructure->translations = [
            'tu-1' => [],
            'tu-2' => [],
        ];

        // No QA injected — if process() tried to call createQA(), it would
        // hit the real constructor which would be fine, but the point is
        // the loop body is never entered.
        $processor = $this->buildProcessor();
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        // No exception — empty structs silently skipped
        $this->assertTrue(true);
    }

    // ── Test 4: Multiple tuples are all processed ────────────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function processesMultipleTuples(): void
    {
        $qa = $this->buildQAStub(normalizedTarget: 'norm');

        $tuple1 = $this->makeTuple(source: 'src1', target: 'tgt1');
        $tuple2 = $this->makeTuple(source: 'src2', target: 'tgt2');
        $tuple2->segmentId = 2;

        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple1],
            'tu-2' => [0 => $tuple2],
        ];

        $processor = $this->buildProcessor($qa);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        $this->assertSame('L0:norm', $tuple1->translationLayer0);
        $this->assertSame('L0:norm', $tuple2->translationLayer0);
        $this->assertSame(0, $tuple1->warning);
        $this->assertSame(0, $tuple2->warning);
    }

    // ── Test 5: Layer conversions are applied ────────────────────────

    /**
     * Verifies that source/target are converted L0→L1 before QA,
     * and the result is converted L1→L0 for storage.
     *
     * @throws MockException
     */
    #[Test]
    public function appliesLayerConversions(): void
    {
        $qa = $this->createStub(QA::class);
        $qa->method('setFeatureSet')->willReturnSelf();
        $qa->method('performConsistencyCheck')->willReturn([]);
        $qa->method('thereAreErrors')->willReturn(false);
        // Return exactly what was passed as target to QA constructor
        // (which is the L1-converted target)
        $qa->method('getTrgNormalized')->willReturn('L1:target text');
        $qa->method('getTargetSeg')->willReturn('L1:target text');
        $qa->method('getErrorsJSON')->willReturn('');

        $tuple = $this->makeTuple(source: 'my source', target: 'target text');
        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $processor = $this->buildProcessor($qa);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        // fromLayer1ToLayer0('L1:target text') → 'L0:L1:target text'
        $this->assertSame('L0:L1:target text', $tuple->translationLayer0);
    }

    // ── Test 6: ICU enabled + ICU source → comparator passed to createQA ─

    /**
     * @throws MockException
     */
    #[Test]
    public function passesComparatorWhenIcuEnabledAndSourceContainsIcu(): void
    {
        $qa = $this->buildQAStub(normalizedTarget: 'norm');

        $icuSource = '{count, plural, one{# item} other{# items}}';
        $icuTarget = '{count, plural, one{# elemento} other{# elementi}}';

        $tuple = $this->makeTuple(source: $icuSource, target: $icuTarget);
        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $processor = $this->buildProcessor($qa, icuEnabled: true);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        $this->assertNotNull($processor->lastComparator);
        $this->assertTrue($processor->lastSourceContainsIcu);
    }

    // ── Test 7: ICU disabled + ICU source → no comparator ────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function skipsIcuDetectionWhenIcuDisabled(): void
    {
        $qa = $this->buildQAStub(normalizedTarget: 'norm');

        $icuSource = '{count, plural, one{# item} other{# items}}';
        $icuTarget = '{count, plural, one{# elemento} other{# elementi}}';

        $tuple = $this->makeTuple(source: $icuSource, target: $icuTarget);
        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $processor = $this->buildProcessor($qa, icuEnabled: false);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        $this->assertNull($processor->lastComparator);
        $this->assertFalse($processor->lastSourceContainsIcu);
    }

    // ── Test 8: ICU enabled + non-ICU source → no comparator ─────────

    /**
     * @throws MockException
     */
    #[Test]
    public function skipsIcuDetectionWhenSourceHasNoIcuPatterns(): void
    {
        $qa = $this->buildQAStub(normalizedTarget: 'norm');

        $tuple = $this->makeTuple(source: 'plain source text', target: 'plain target text');
        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        $processor = $this->buildProcessor($qa, icuEnabled: true);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        $this->assertNull($processor->lastComparator);
        $this->assertFalse($processor->lastSourceContainsIcu);
    }

    // ── Test 9: ICU enabled + broken target → QA reports ICU errors ──

    #[Test]
    public function reportsIcuValidationErrorsForBrokenTarget(): void
    {
        $icuSource = '{count, plural, one{# item} other{# items}}';
        $brokenTarget = '{count, plural, one{# elemento}}';

        $tuple = $this->makeTuple(source: $icuSource, target: $brokenTarget);
        $this->projectStructure->translations = [
            'tu-1' => [0 => $tuple],
        ];

        // No QA stub — uses real QA so ICU validation actually runs
        $processor = $this->buildProcessor(icuEnabled: true);
        $processor->process($this->projectStructure, 'en-US', 'it-IT');

        $this->assertSame(1, $tuple->warning);
        $this->assertNotEmpty($tuple->serializedErrors);
    }
}
