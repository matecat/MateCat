<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use Utils\LQA\QA;
use View\API\V2\Json\QAWarning;

#[CoversClass(QAWarning::class)]
class QAWarningTest extends AbstractTest
{
    /**
     * Concrete subclass exposing pushErrorSegment for testing.
     */
    private function makeConcreteWarning(): QAWarning
    {
        return new class extends QAWarning {
            public function init(): void
            {
                $this->structure = [];
            }

            public function callPushErrorSegment(string $errorType, ?int $errorCategory, mixed $content): void
            {
                $this->pushErrorSegment($errorType, $errorCategory, $content);
            }

            public function getStructure(): array
            {
                return $this->structure;
            }
        };
    }

    public function testPushErrorSegmentCreatesCategory(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::ERROR, QA::ERR_SIZE_RESTRICTION, 'seg1');

        $structure = $warning->getStructure();
        $this->assertArrayHasKey(QA::ERROR, $structure);
        $this->assertArrayHasKey('Categories', $structure[QA::ERROR]);
        $this->assertArrayHasKey(QAWarning::SIZE_CATEGORY, $structure[QA::ERROR]['Categories']);
        $this->assertContains('seg1', $structure[QA::ERROR]['Categories'][QAWarning::SIZE_CATEGORY]);
    }

    public function testPushErrorSegmentMismatchCategory(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::WARNING, QA::ERR_SPACE_MISMATCH, 'seg2');

        $structure = $warning->getStructure();
        $this->assertArrayHasKey(QAWarning::MISMATCH_CATEGORY, $structure[QA::WARNING]['Categories']);
        $this->assertContains('seg2', $structure[QA::WARNING]['Categories'][QAWarning::MISMATCH_CATEGORY]);
    }

    public function testPushErrorSegmentIcuCategory(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::INFO, QA::ERR_ICU_VALIDATION, 'seg3');

        $structure = $warning->getStructure();
        $this->assertArrayHasKey(QAWarning::ICU_CATEGORY, $structure[QA::INFO]['Categories']);
    }

    public function testPushErrorSegmentFuzzyCategory(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::WARNING, QA::ERR_FUZZY_UNCHANGED, 'seg6');

        $structure = $warning->getStructure();
        $this->assertArrayHasKey(QAWarning::FUZZY_CATEGORY, $structure[QA::WARNING]['Categories']);
        $this->assertContains('seg6', $structure[QA::WARNING]['Categories'][QAWarning::FUZZY_CATEGORY]);
    }

    public function testPushErrorSegmentDefaultsToTagsCategory(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::ERROR, QA::ERR_TAG_ID, 'seg4');

        $structure = $warning->getStructure();
        $this->assertArrayHasKey(QAWarning::TAGS_CATEGORY, $structure[QA::ERROR]['Categories']);
    }

    public function testPushErrorSegmentNullCategoryDefaultsToTags(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::ERROR, null, 'seg5');

        $structure = $warning->getStructure();
        $this->assertArrayHasKey(QAWarning::TAGS_CATEGORY, $structure[QA::ERROR]['Categories']);
    }

    public function testPushErrorSegmentDeduplicatesContent(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::ERROR, QA::ERR_SIZE_RESTRICTION, 'seg1');
        $warning->callPushErrorSegment(QA::ERROR, QA::ERR_SIZE_RESTRICTION, 'seg1');

        $structure = $warning->getStructure();
        $this->assertCount(1, $structure[QA::ERROR]['Categories'][QAWarning::SIZE_CATEGORY]);
    }

    public function testPushErrorSegmentAccumulatesDistinctContent(): void
    {
        $warning = $this->makeConcreteWarning();
        $warning->init();
        $warning->callPushErrorSegment(QA::ERROR, QA::ERR_SIZE_RESTRICTION, 'seg1');
        $warning->callPushErrorSegment(QA::ERROR, QA::ERR_SIZE_RESTRICTION, 'seg2');

        $structure = $warning->getStructure();
        $this->assertCount(2, $structure[QA::ERROR]['Categories'][QAWarning::SIZE_CATEGORY]);
    }
}
