<?php

namespace Matecat\Core\Features\ReviewExtended;

use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
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

    /**
     * The second revision is the last phase that exists, so nothing above it can be a source page.
     * Without this an arbitrary request parameter became an arbitrary source page.
     */
    #[Test]
    public function revisionNumberToSourcePage_rejectsARevisionNumberBeyondTheKnownPhases(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReviewUtils::revisionNumberToSourcePage(3);
    }

    #[Test]
    public function revisionNumberToSourcePage_rejectsANegativeRevisionNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReviewUtils::revisionNumberToSourcePage(-1);
    }
}
