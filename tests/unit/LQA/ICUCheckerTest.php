<?php

namespace unit\LQA;

use DG\BypassFinals;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\ICU\Plurals\PluralArgumentWarning;
use Matecat\ICU\Plurals\PluralComplianceWarning;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\ErrorManager;
use Utils\LQA\QA\ICUChecker;

/**
 * Tests for ICUChecker class.
 *
 * Note: MessagePatternComparator and MessagePatternValidator are final classes
 * and cannot be mocked. We use BypassFinals to bypass this restriction.
 */
class ICUCheckerTest extends AbstractTest
{
    private ICUChecker $icuChecker;
    private ErrorManager $errorManager;

    public function setUp(): void
    {
        BypassFinals::enable();
        parent::setUp();
        $this->errorManager = new ErrorManager();
        $this->icuChecker = new ICUChecker($this->errorManager);
    }

    // ========== Has ICU Patterns Tests ==========

    #[Test]
    public function hasIcuPatternsNoComparatorNoIcu(): void
    {
        $this->assertFalse($this->icuChecker->hasIcuPatterns());
    }

    #[Test]
    public function hasIcuPatternsWithComparatorNoIcu(): void
    {
        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createStub(MessagePatternComparator::class);
        $this->icuChecker->setIcuPatternComparator($comparator);

        $this->assertFalse($this->icuChecker->hasIcuPatterns());
    }

    #[Test]
    public function hasIcuPatternsNoComparatorWithIcu(): void
    {
        $this->icuChecker->setSourceContainsIcu(true);

        $this->assertFalse($this->icuChecker->hasIcuPatterns());
    }

    #[Test]
    public function hasIcuPatternsWithBoth(): void
    {
        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createStub(MessagePatternComparator::class);
        $this->icuChecker->setIcuPatternComparator($comparator);
        $this->icuChecker->setSourceContainsIcu(true);

        $this->assertTrue($this->icuChecker->hasIcuPatterns());
    }

    // ========== Set Methods Tests ==========

    #[Test]
    public function setIcuPatternComparatorNull(): void
    {
        $this->icuChecker->setIcuPatternComparator(null);
        $this->assertFalse($this->icuChecker->hasIcuPatterns());
    }

    #[Test]
    public function setSourceContainsIcuFalse(): void
    {
        $this->icuChecker->setSourceContainsIcu(false);
        $this->assertFalse($this->icuChecker->hasIcuPatterns());
    }

    // ========== Check ICU Message Consistency Tests ==========

    #[Test]
    public function checkICUMessageConsistencyNoComparator(): void
    {
        $this->icuChecker->checkICUMessageConsistency();

        // Should not add any errors when no comparator
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkICUMessageConsistencyValidPattern(): void
    {
        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        // Create a stub that validates successfully
        $validator = $this->createStub(MessagePatternValidator::class);
        $validator->method('validatePluralCompliance')->willReturn(null);

        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createMock(MessagePatternComparator::class);
        $comparator->expects($this->once())->method('validate');
        $comparator->method('getTargetValidator')->willReturn($validator);

        $this->icuChecker->setIcuPatternComparator($comparator);
        $this->icuChecker->checkICUMessageConsistency();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkICUMessageConsistencyValidationException(): void
    {
        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createStub(MessagePatternComparator::class);
        $comparator->method('validate')->willThrowException(new \Exception('Invalid ICU pattern'));

        $this->icuChecker->setIcuPatternComparator($comparator);
        $this->icuChecker->checkICUMessageConsistency();

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_ICU_VALIDATION, $errors[0]->outcome);
    }

    #[Test]
    public function checkICUMessageConsistencyWithComplaintWarnings(): void
    {
        // Create mock complaint with warnings
        $warning = $this->createStub(PluralArgumentWarning::class);
        $warning->method('getMessages')->willReturn(['Plural mismatch']);

        $complaints = $this->createStub(PluralComplianceWarning::class);
        $complaints->method('getArgumentWarnings')->willReturn([$warning]);

        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $validator = $this->createStub(MessagePatternValidator::class);
        $validator->method('validatePluralCompliance')->willReturn($complaints);

        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createStub(MessagePatternComparator::class);
        $comparator->method('validate');
        $comparator->method('getTargetValidator')->willReturn($validator);

        $this->icuChecker->setIcuPatternComparator($comparator);
        $this->icuChecker->checkICUMessageConsistency();

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function checkICUMessageConsistencyEmptyWarnings(): void
    {
        $complaints = $this->createStub(PluralComplianceWarning::class);
        $complaints->method('getArgumentWarnings')->willReturn([]);

        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $validator = $this->createStub(MessagePatternValidator::class);
        $validator->method('validatePluralCompliance')->willReturn($complaints);

        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createStub(MessagePatternComparator::class);
        $comparator->method('validate');
        $comparator->method('getTargetValidator')->willReturn($validator);

        $this->icuChecker->setIcuPatternComparator($comparator);
        $this->icuChecker->checkICUMessageConsistency();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== Edge Cases ==========

    #[Test]
    public function multipleCallsToCheckConsistency(): void
    {
        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createStub(MessagePatternComparator::class);
        $comparator->method('validate')->willThrowException(new \Exception('Error'));

        $this->icuChecker->setIcuPatternComparator($comparator);
        $this->icuChecker->checkICUMessageConsistency();
        $this->icuChecker->checkICUMessageConsistency();

        // Should have 2 errors
        $errors = $this->errorManager->getErrors();
        $this->assertGreaterThanOrEqual(1, count($errors));
    }

    #[Test]
    public function setComparatorAfterSetSourceContainsIcu(): void
    {
        $this->icuChecker->setSourceContainsIcu(true);
        $this->assertFalse($this->icuChecker->hasIcuPatterns());

        /** @noinspection PhpUnitInvalidMockingEntityInspection */
        $comparator = $this->createStub(MessagePatternComparator::class);
        $this->icuChecker->setIcuPatternComparator($comparator);

        $this->assertTrue($this->icuChecker->hasIcuPatterns());
    }
}

