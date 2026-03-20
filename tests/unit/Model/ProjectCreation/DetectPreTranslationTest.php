<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectStructure;
use Model\Xliff\DTO\XliffRuleInterface;
use Model\Xliff\DTO\XliffRulesModel;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Tests for {@see \Model\ProjectCreation\SegmentExtractor::detectPreTranslation()}
 * exercised through the {@see TestableSegmentExtractor} wrapper.
 *
 * Covers the decision logic: feature gate, state extraction, isTranslated
 * delegation, empty-target guard, and filter application.
 */
class DetectPreTranslationTest extends AbstractTest
{
    private ProjectStructure $projectStructure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectStructure = new ProjectStructure([
            'id_project'         => 42,
            'source_language'    => 'en-US',
            'create_date'        => '2025-03-19 10:00:00',
            'translations'       => [],
            'segments_metadata'  => [],
            'current_xliff_info' => [1 => ['version' => 1]],
            'result'             => ['errors' => [], 'data' => []],
        ]);
    }

    // ── Test cases ──────────────────────────────────────────────────

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function returnsNullWhenPreTranslationsDisabled(): void
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('filter')->willReturn(false);

        $extractor = $this->buildExtractor(features: $features);

        $result = $extractor->callDetectPreTranslation(
            'source text',
            'target text',
            $this->makeTransUnit(),
            1,
            null,
            $this->projectStructure,
        );

        self::assertNull($result);
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function returnsNullWhenTargetEmptyAfterStripping(): void
    {
        [$rulesModel] = $this->buildRuleStubs();

        $extractor = $this->buildExtractor(xliffRulesModel: $rulesModel);

        $result = $extractor->callDetectPreTranslation(
            'source text',
            '   ',
            $this->makeTransUnit(),
            1,
            null,
            $this->projectStructure,
        );

        self::assertNull($result);
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function returnsNullWhenRuleSaysNotTranslated(): void
    {
        [$rulesModel] = $this->buildRuleStubs(isTranslated: false);

        $extractor = $this->buildExtractor(xliffRulesModel: $rulesModel);

        $result = $extractor->callDetectPreTranslation(
            'source text',
            'target text',
            $this->makeTransUnit(),
            1,
            null,
            $this->projectStructure,
        );

        self::assertNull($result);
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function returnsFilteredTargetWhenValid(): void
    {
        [$rulesModel] = $this->buildRuleStubs();

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromRawXliffToLayer0')->willReturn('filtered target');

        $extractor = $this->buildExtractor(filter: $filter, xliffRulesModel: $rulesModel);

        $result = $extractor->callDetectPreTranslation(
            'source text',
            'target text',
            $this->makeTransUnit(),
            1,
            null,
            $this->projectStructure,
        );

        self::assertSame(['target' => 'filtered target'], $result);
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function resolvesRuleWithCorrectArguments(): void
    {
        $rule = $this->createStub(XliffRuleInterface::class);
        $rule->method('isTranslated')->willReturn(true);

        $rulesModel = $this->createMock(XliffRulesModel::class);
        $rulesModel->expects($this->once())
            ->method('getMatchingRule')
            ->with(1, 'translated', 'exact-match')
            ->willReturn($rule);

        $extractor = $this->buildExtractor(xliffRulesModel: $rulesModel);

        $extractor->callDetectPreTranslation(
            'source text',
            'target text',
            $this->makeTransUnit(state: 'translated', stateQualifier: 'exact-match'),
            1,
            null,
            $this->projectStructure,
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function usesSegTargetAttributesWithPosition(): void
    {
        $rule = $this->createStub(XliffRuleInterface::class);
        $rule->method('isTranslated')->willReturn(true);

        $rulesModel = $this->createMock(XliffRulesModel::class);
        $rulesModel->expects($this->once())
            ->method('getMatchingRule')
            ->with(1, 'signed-off', null)
            ->willReturn($rule);

        $transUnit = $this->makeTransUnit();
        $transUnit['seg-target'] = [
            0 => ['attr' => ['state' => 'signed-off']],
        ];

        $extractor = $this->buildExtractor(xliffRulesModel: $rulesModel);

        $extractor->callDetectPreTranslation(
            'source text',
            'target text',
            $transUnit,
            1,
            0,
            $this->projectStructure,
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function usesXliffVersionFromProjectStructure(): void
    {
        $this->projectStructure->current_xliff_info[5] = ['version' => 2];

        $rule = $this->createStub(XliffRuleInterface::class);
        $rule->method('isTranslated')->willReturn(true);

        $rulesModel = $this->createMock(XliffRulesModel::class);
        $rulesModel->expects($this->once())
            ->method('getMatchingRule')
            ->with(2, null, null)
            ->willReturn($rule);

        $extractor = $this->buildExtractor(xliffRulesModel: $rulesModel);

        $extractor->callDetectPreTranslation(
            'source text',
            'target text',
            $this->makeTransUnit(),
            5,
            null,
            $this->projectStructure,
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    #[Test]
    public function callsFilterWithRawTargetContent(): void
    {
        [$rulesModel] = $this->buildRuleStubs();

        $filter = $this->createMock(MateCatFilter::class);
        $filter->expects($this->once())
            ->method('fromRawXliffToLayer0')
            ->with('raw target content')
            ->willReturn('layer0 target');

        $extractor = $this->buildExtractor(filter: $filter, xliffRulesModel: $rulesModel);

        $result = $extractor->callDetectPreTranslation(
            'source text',
            'raw target content',
            $this->makeTransUnit(),
            1,
            null,
            $this->projectStructure,
        );

        self::assertSame('layer0 target', $result['target']);
    }

    // ── Helper methods ──────────────────────────────────────────────

    private function buildExtractor(
        ?FeatureSet      $features = null,
        ?MateCatFilter   $filter = null,
        ?XliffRulesModel $xliffRulesModel = null,
    ): TestableSegmentExtractor {
        $features        ??= $this->createDefaultFeaturesStub();
        $filter          ??= $this->createDefaultFilterStub();
        $metadataDao       = $this->createStub(MetadataDao::class);
        $logger            = $this->createStub(MatecatLogger::class);

        if ($xliffRulesModel !== null) {
            $this->projectStructure->xliff_parameters = $xliffRulesModel;
        }

        return new TestableSegmentExtractor(
            $this->projectStructure,
            $filter,
            $features,
            $metadataDao,
            $logger,
        );
    }

    private function createDefaultFilterStub(): MateCatFilter
    {
        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromRawXliffToLayer0')->willReturnArgument(0);

        return $filter;
    }

    private function createDefaultFeaturesStub(): FeatureSet
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('filter')->willReturnArgument(1);

        return $features;
    }

    /**
     * @return array{0: XliffRulesModel, 1: XliffRuleInterface}
     */
    private function buildRuleStubs(bool $isTranslated = true): array
    {
        $rule = $this->createStub(XliffRuleInterface::class);
        $rule->method('isTranslated')->willReturn($isTranslated);

        $rulesModel = $this->createStub(XliffRulesModel::class);
        $rulesModel->method('getMatchingRule')->willReturn($rule);

        return [$rulesModel, $rule];
    }

    private function makeTransUnit(?string $state = null, ?string $stateQualifier = null): array
    {
        $targetAttr = [];
        if ($state !== null) {
            $targetAttr['state'] = $state;
        }
        if ($stateQualifier !== null) {
            $targetAttr['state-qualifier'] = $stateQualifier;
        }

        return [
            'target' => [
                'attr' => $targetAttr,
            ],
        ];
    }
}
