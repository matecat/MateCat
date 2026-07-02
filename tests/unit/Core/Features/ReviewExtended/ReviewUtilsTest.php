<?php

namespace Matecat\Core\Features\ReviewExtended;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class ReviewUtilsTest extends AbstractTest
{
    // ─────────────────────────────────────────────────────────────────
    // sourcePageToTranslationStatus
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function sourcePageToTranslationStatus_returnsNullWhenNumberIsNull(): void
    {
        $this->assertNull(ReviewUtils::sourcePageToTranslationStatus(null));
    }

    #[Test]
    public function sourcePageToTranslationStatus_returnsNullWhenNumberIsZero(): void
    {
        $this->assertNull(ReviewUtils::sourcePageToTranslationStatus(0));
    }

    #[Test]
    public function sourcePageToTranslationStatus_returnsTranslatedForSourcePageTranslate(): void
    {
        $this->assertSame(
            TranslationStatus::STATUS_TRANSLATED,
            ReviewUtils::sourcePageToTranslationStatus(SourcePages::SOURCE_PAGE_TRANSLATE)
        );
    }

    #[Test]
    public function sourcePageToTranslationStatus_returnsApprovedForSourcePageRevision(): void
    {
        $this->assertSame(
            TranslationStatus::STATUS_APPROVED,
            ReviewUtils::sourcePageToTranslationStatus(SourcePages::SOURCE_PAGE_REVISION)
        );
    }

    #[Test]
    public function sourcePageToTranslationStatus_returnsApproved2ForSourcePageRevision2(): void
    {
        $this->assertSame(
            TranslationStatus::STATUS_APPROVED2,
            ReviewUtils::sourcePageToTranslationStatus(SourcePages::SOURCE_PAGE_REVISION_2)
        );
    }

    #[Test]
    public function sourcePageToTranslationStatus_returnsNullForUnknownSourcePage(): void
    {
        $this->assertNull(ReviewUtils::sourcePageToTranslationStatus(99));
    }

    // ─────────────────────────────────────────────────────────────────
    // revisionNumberToSourcePage
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function revisionNumberToSourcePage_returnsOneWhenNumberIsNull(): void
    {
        $this->assertSame(1, ReviewUtils::revisionNumberToSourcePage(null));
    }

    #[Test]
    public function revisionNumberToSourcePage_returnsOneWhenNumberIsZero(): void
    {
        $this->assertSame(1, ReviewUtils::revisionNumberToSourcePage(0));
    }

    #[Test]
    public function revisionNumberToSourcePage_returnsNumberPlusOneForPositiveInput(): void
    {
        $this->assertSame(2, ReviewUtils::revisionNumberToSourcePage(1));
        $this->assertSame(3, ReviewUtils::revisionNumberToSourcePage(2));
    }

    // ─────────────────────────────────────────────────────────────────
    // sourcePageToRevisionNumber
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function sourcePageToRevisionNumber_returnsNullWhenResultWouldBeLessThanOne(): void
    {
        $this->assertNull(ReviewUtils::sourcePageToRevisionNumber(1));
    }

    #[Test]
    public function sourcePageToRevisionNumber_returnsNullWhenNumberIsNull(): void
    {
        $this->assertNull(ReviewUtils::sourcePageToRevisionNumber(null));
    }

    #[Test]
    public function sourcePageToRevisionNumber_returnsOneForSourcePage2(): void
    {
        $this->assertSame(1, ReviewUtils::sourcePageToRevisionNumber(2));
    }

    #[Test]
    public function sourcePageToRevisionNumber_returnsTwoForSourcePage3(): void
    {
        $this->assertSame(2, ReviewUtils::sourcePageToRevisionNumber(3));
    }

    // ─────────────────────────────────────────────────────────────────
    // filterLQAModelLimit
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function filterLQAModelLimit_returnsIndexedValueWhenOffsetExists(): void
    {
        $lqaModel = $this->createStub(\Model\LQA\ModelStruct::class);
        // sourcePage=2 => index 0 => limit[0]
        $lqaModel->method('getLimit')->willReturn([10, 20]);

        $this->assertSame(10, ReviewUtils::filterLQAModelLimit($lqaModel, 2));
    }

    #[Test]
    public function filterLQAModelLimit_returnsLastValueWhenOffsetMissing(): void
    {
        $lqaModel = $this->createStub(\Model\LQA\ModelStruct::class);
        // sourcePage=99 => index 97 => missing => end($limit) = 20
        $lqaModel->method('getLimit')->willReturn([10, 20]);

        $this->assertSame(20, ReviewUtils::filterLQAModelLimit($lqaModel, 99));
    }

    #[Test]
    public function filterLQAModelLimit_returnsIntForSourcePage3(): void
    {
        $lqaModel = $this->createStub(\Model\LQA\ModelStruct::class);
        // sourcePage=3 => index 1 => limit[1]
        $lqaModel->method('getLimit')->willReturn([10, 20]);

        $this->assertSame(20, ReviewUtils::filterLQAModelLimit($lqaModel, 3));
    }

    // ─────────────────────────────────────────────────────────────────
    // validRevisionNumbers
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function validRevisionNumbers_returnsFilteredRevisionNumbers(): void
    {
        $chunk = $this->createStub(JobStruct::class);

        $review1 = $this->createStub(ChunkReviewStruct::class);
        $review1->source_page = SourcePages::SOURCE_PAGE_REVISION; // 2 → revision 1
        $review2 = $this->createStub(ChunkReviewStruct::class);
        $review2->source_page = SourcePages::SOURCE_PAGE_REVISION_2; // 3 → revision 2

        $dao = $this->createMock(ChunkReviewDao::class);
        $dao->expects($this->once())
            ->method('findChunkReviews')
            ->with($chunk)
            ->willReturn([$review1, $review2]);

        $utils = new ReviewUtils($dao);
        $result = $utils->validRevisionNumbers($chunk);

        $this->assertSame([1, 2], $result);
    }

    #[Test]
    public function validRevisionNumbers_filtersOutNullRevisionNumbers(): void
    {
        $chunk = $this->createStub(JobStruct::class);

        $review1 = $this->createStub(ChunkReviewStruct::class);
        $review1->source_page = SourcePages::SOURCE_PAGE_TRANSLATE; // 1 → null (filtered out)
        $review2 = $this->createStub(ChunkReviewStruct::class);
        $review2->source_page = SourcePages::SOURCE_PAGE_REVISION; // 2 → revision 1

        $dao = $this->createStub(ChunkReviewDao::class);
        $dao->method('findChunkReviews')->willReturn([$review1, $review2]);

        $utils = new ReviewUtils($dao);
        $result = $utils->validRevisionNumbers($chunk);

        $this->assertSame([1], $result);
    }

    #[Test]
    public function validRevisionNumbers_returnsEmptyArrayWhenNoReviews(): void
    {
        $chunk = $this->createStub(JobStruct::class);

        $dao = $this->createStub(ChunkReviewDao::class);
        $dao->method('findChunkReviews')->willReturn([]);

        $utils = new ReviewUtils($dao);
        $result = $utils->validRevisionNumbers($chunk);

        $this->assertSame([], $result);
    }
}
