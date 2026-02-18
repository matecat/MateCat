<?php

namespace unit\LQA;

use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA;
use Utils\LQA\QA\ErrorManager;
use Utils\LQA\QA\SizeRestrictionChecker;

class SizeRestrictionCheckerTest extends AbstractTest
{
    private SizeRestrictionChecker $sizeRestrictionChecker;
    private ErrorManager $errorManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->errorManager = new ErrorManager();
        $this->sizeRestrictionChecker = new SizeRestrictionChecker($this->errorManager);
    }

    // ========== Constant Tests ==========

    #[Test]
    public function sizeRestrictionConstantValue(): void
    {
        $this->assertEquals('sizeRestriction', SizeRestrictionChecker::SIZE_RESTRICTION);
    }

    // ========== No Restriction Tests ==========

    #[Test]
    public function checkSizeRestrictionWithNoCharactersCountDoesNotAddError(): void
    {
        $this->sizeRestrictionChecker->setCharactersCount(0);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionWithNoLimitDoesNotAddError(): void
    {
        // When no limit is provided (second parameter null), no error is added
        $this->sizeRestrictionChecker->setCharactersCount(100);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== Zero Limit Tests ==========

    #[Test]
    public function checkSizeRestrictionWithZeroLimitMeansNoRestriction(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 0;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(1000, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        // meta_value = 0 means no restriction
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== Within Limit Tests ==========

    #[Test]
    public function checkSizeRestrictionWithinLimitDoesNotAddError(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 100;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(50, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionExactlyAtLimitDoesNotAddError(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 100;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(100, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== Exceeds Limit Tests ==========

    #[Test]
    public function checkSizeRestrictionExceedsLimitAddsError(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 100;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(150, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(ErrorManager::ERR_SIZE_RESTRICTION, $errors[0]->outcome);
    }

    #[Test]
    public function checkSizeRestrictionExceedsByOneAddsError(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 100;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(101, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionSignificantlyOverLimitAddsError(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 50;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(500, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    // ========== Edge Cases ==========

    #[Test]
    public function checkSizeRestrictionWithSmallLimitOneCharacterUnder(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 5;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(4, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionWithSmallLimitOneCharacterOver(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 5;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(6, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function setCharactersCountCanBeCalledMultipleTimes(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 100;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        // First set within limit
        $this->sizeRestrictionChecker->setCharactersCount(50, $limit);

        // Override with value over limit
        $limit2 = new SegmentMetadataStruct();
        $limit2->meta_value = 100;
        $limit2->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(150, $limit2);

        $this->sizeRestrictionChecker->checkSizeRestriction();

        // Second value should be used
        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionWithLargeLimitAndLargeCount(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 10000;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(9999, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionErrorHasCorrectOutcome(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 10;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        $this->sizeRestrictionChecker->setCharactersCount(20, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_SIZE_RESTRICTION, $errors[0]->outcome);
    }

    #[Test]
    public function checkSizeRestrictionWithZeroCharacterCountNoError(): void
    {
        $limit = new SegmentMetadataStruct();
        $limit->meta_value = 100;
        $limit->meta_key = QA::SIZE_RESTRICTION;

        // Zero characters is falsy so early return
        $this->sizeRestrictionChecker->setCharactersCount(0, $limit);
        $this->sizeRestrictionChecker->checkSizeRestriction();

        // Zero count is treated as "no count set"
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionWithNoCharacterCountSetNoErrors(): void
    {
        $newChecker = new SizeRestrictionChecker($this->errorManager);
        $newChecker->checkSizeRestriction();

        // Should not throw exception
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionWithWrongLimitSetNoErrors(): void
    {
        $this->sizeRestrictionChecker->setCharactersCount(10, new SegmentMetadataStruct(['meta_key' => 'wrong']));
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkSizeRestrictionWithNegativeLimitSetErrors(): void
    {
        $this->sizeRestrictionChecker->setCharactersCount(10, new SegmentMetadataStruct(['meta_key' => QA::SIZE_RESTRICTION, 'meta_value' => -1]));
        $this->sizeRestrictionChecker->checkSizeRestriction();

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

}
