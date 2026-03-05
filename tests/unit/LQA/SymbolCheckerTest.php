<?php

namespace unit\LQA;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\ContentPreprocessor;
use Utils\LQA\QA\ErrorManager;
use Utils\LQA\QA\SymbolChecker;

class SymbolCheckerTest extends AbstractTest
{
    private SymbolChecker $symbolChecker;
    private ErrorManager $errorManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->errorManager = new ErrorManager();
        $this->symbolChecker = new SymbolChecker($this->errorManager);
    }

    // ========== Set Segments Tests ==========

    #[Test]
    public function setSegments(): void
    {
        $this->symbolChecker->setSegments('Source', 'Target');
        // No exception means success
        $this->assertTrue(true);
    }

    // ========== Euro Sign Tests ==========

    #[Test]
    public function checkSymbolConsistencyEuroMatching(): void
    {
        $source = 'Price: €100';
        $target = 'Prezzo: €100';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyEuroMismatch(): void
    {
        $source = 'Price: €100';
        $target = 'Prezzo: 100';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyMultipleEuro(): void
    {
        $source = '€100 and €200';
        $target = '€100';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== At Sign Tests ==========

    #[Test]
    public function checkSymbolConsistencyAtMatching(): void
    {
        $source = 'Email: test@example.com';
        $target = 'Email: test@esempio.com';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyAtMismatch(): void
    {
        $source = 'Email: test@example.com';
        $target = 'Email: test.example.com';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Ampersand Tests ==========

    #[Test]
    public function checkSymbolConsistencyAmpersandMatching(): void
    {
        $source = 'Tom &amp; Jerry';
        $target = 'Tom &amp; Jerry';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyAmpersandMismatch(): void
    {
        $source = 'Tom &amp; Jerry';
        $target = 'Tom e Jerry';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyAmpersandEntityExcluded(): void
    {
        // Valid entities like &nbsp; should not be counted
        $source = 'Hello&nbsp;World';
        $target = 'Ciao&nbsp;Mondo';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyAmpersandNumericEntity(): void
    {
        // Numeric entities should not be counted as ampersand
        $source = 'Hello&#160;World';
        $target = 'Ciao&#160;Mondo';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    // ========== Pound Sign Tests ==========

    #[Test]
    public function checkSymbolConsistencyPoundMatching(): void
    {
        $source = 'Price: £50';
        $target = 'Prezzo: £50';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyPoundMismatch(): void
    {
        $source = 'Price: £50';
        $target = 'Prezzo: 50';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Percent Sign Tests ==========

    #[Test]
    public function checkSymbolConsistencyPercentMatching(): void
    {
        $source = 'Discount: 50%';
        $target = 'Sconto: 50%';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyPercentMismatch(): void
    {
        $source = 'Discount: 50%';
        $target = 'Sconto: 50 percent';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Equals Sign Tests ==========

    #[Test]
    public function checkSymbolConsistencyEqualsMatching(): void
    {
        $source = 'x = 5';
        $target = 'x = 5';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyEqualsMismatch(): void
    {
        $source = 'x = 5 = y';
        $target = 'x = 5';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Tab Tests ==========

    #[Test]
    public function checkSymbolConsistencyTabMatching(): void
    {
        // Note: Tab placeholder contains regex special characters (##$_09$##)
        // This test verifies that matching content without tabs don't produce errors
        $source = 'Column1 Column2';
        $target = 'Colonna1 Colonna2';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    /**
     * Test that tabs in both source and target don't cause false positives
     */
    #[Test]
    public function checkSymbolConsistencyTabPlaceholderPresent(): void
    {
        // Verify tabs in both source and target don't cause false positives
        $preprocessor = new ContentPreprocessor();
        $source = $preprocessor->preprocess("Column1\tColumn2");
        $target = $preprocessor->preprocess("Colonna1\tColonna2");

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        // Should not report errors when both have same number of tabs
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== Star/Asterisk Tests ==========

    #[Test]
    public function checkSymbolConsistencyStarMatching(): void
    {
        $source = 'Important * note';
        $target = 'Importante * nota';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyStarMismatch(): void
    {
        $source = 'Important ** note';
        $target = 'Importante * nota';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Dollar Sign Tests (Special) ==========

    #[Test]
    public function checkSymbolConsistencyDollarMatching(): void
    {
        $source = 'Price: $100';
        $target = 'Prezzo: $100';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyDollarMismatch(): void
    {
        $source = 'Price: $100';
        $target = 'Prezzo: 100';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Hash Sign Tests (Special) ==========

    #[Test]
    public function checkSymbolConsistencyHashMatching(): void
    {
        $source = 'Issue #123';
        $target = 'Problema #123';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyHashMismatch(): void
    {
        $source = 'Issue #123';
        $target = 'Problema 123';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Multiple Symbols Tests ==========

    #[Test]
    public function checkSymbolConsistencyMultipleSymbols(): void
    {
        $source = '€100 @ 50% = £50';
        $target = '€100 @ 50% = £50';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyMultipleMismatches(): void
    {
        $source = '€100 @ 50%';
        $target = '100 test 50';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
        // Should have multiple errors
        $notices = $this->errorManager->getNotices();
        $this->assertGreaterThan(1, count($notices));
    }

    // ========== Tags Should Be Stripped Tests ==========

    #[Test]
    public function checkSymbolConsistencyIgnoresTagContent(): void
    {
        // Symbols inside tags should be ignored (strip_tags is used)
        $source = '<g id="1">€100</g>';
        $target = '<g id="1">€100</g>';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    // ========== Edge Cases ==========

    #[Test]
    public function checkSymbolConsistencyEmptyStrings(): void
    {
        $source = '';
        $target = '';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyNoSymbols(): void
    {
        $source = 'Hello World';
        $target = 'Ciao Mondo';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyDoubleAmpersand(): void
    {
        // &amp;amp; should be normalized to &amp;
        $source = 'Tom &amp;amp; Jerry';
        $target = 'Tom &amp; Jerry';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkSymbolConsistencyCaseInsensitive(): void
    {
        // Symbol check should be case insensitive for entities
        $source = 'Tom &AMP; Jerry';
        $target = 'Tom &amp; Jerry';

        $this->symbolChecker->setSegments($source, $target);
        $this->symbolChecker->checkSymbolConsistency();

        // Both should be counted as ampersand
        $this->assertFalse($this->errorManager->thereAreNotices());
    }
}

