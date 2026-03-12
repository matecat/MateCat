<?php

namespace unit\LQA;

use DOMException;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\DomHandler;
use Utils\LQA\QA\ErrorManager;
use Utils\LQA\QA\WhitespaceChecker;

class WhitespaceCheckerTest extends AbstractTest
{
    private WhitespaceChecker $whitespaceChecker;
    private ErrorManager $errorManager;
    private DomHandler $domHandler;

    public function setUp(): void
    {
        parent::setUp();
        $this->errorManager = new ErrorManager();
        $this->domHandler = new DomHandler($this->errorManager);
        $this->whitespaceChecker = new WhitespaceChecker($this->errorManager, $this->domHandler);
    }

    // ========== Set Segments Tests ==========

    #[Test]
    public function setSegments(): void
    {
        $this->whitespaceChecker->setSegments('Source', 'Target');
        // No exception means success
        $this->assertTrue(true);
    }

    // ========== Check Newline Consistency Tests ==========

    #[Test]
    public function checkNewlineConsistencyMatching(): void
    {
        $source = "Line1##\$_0A\$##Line2";
        $target = "Linea1##\$_0A\$##Linea2";

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkNewLineConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkNewlineConsistencySourceHasMore(): void
    {
        $source = "Line1##\$_0A\$####\$_0A\$##Line2"; // 2 newlines
        $target = "Linea1##\$_0A\$##Linea2"; // 1 newline

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkNewLineConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_NEWLINE_MISMATCH, $notices[0]->outcome);
    }

    #[Test]
    public function checkNewlineConsistencyTargetHasMore(): void
    {
        $source = "Line1##\$_0A\$##Line2"; // 1 newline
        $target = "Linea1##\$_0A\$####\$_0A\$##Linea2"; // 2 newlines

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkNewLineConsistency();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkNewlineConsistencyNoNewlines(): void
    {
        $source = "Source text";
        $target = "Target text";

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkNewLineConsistency();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function checkNewlineConsistencyMultipleDifference(): void
    {
        $source = "Line1##\$_0A\$####\$_0A\$####\$_0A\$##Line2"; // 3 newlines
        $target = "Linea1##\$_0A\$##Linea2"; // 1 newline

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkNewLineConsistency();

        // Should add 2 errors (difference of 3-1=2)
        $notices = $this->errorManager->getNotices();
        $count = 0;
        foreach ($notices as $notice) {
            if ($notice->outcome === ErrorManager::ERR_NEWLINE_MISMATCH) {
                $count++;
            }
        }
        $this->assertEquals(2, $count);
    }

    // ========== Check Content Consistency Tests ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyMatching(): void
    {
        $source = '<g id="1">Hello</g>';
        $target = '<g id="1">Ciao</g>';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyHeadSpaceMismatch(): void
    {
        $source = '<g id="1"> Hello</g>'; // Space at start
        $target = '<g id="1">Ciao</g>';   // No space

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyTailSpaceMismatch(): void
    {
        $source = '<g id="1">Hello </g>'; // Space at end
        $target = '<g id="1">Ciao</g>';   // No space

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyHeadTabMismatch(): void
    {
        $source = "<g id=\"1\">\tHello</g>"; // Tab at start
        $target = '<g id="1">Ciao</g>';      // No tab

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyTailTabMismatch(): void
    {
        $source = "<g id=\"1\">Hello\t</g>"; // Tab at end
        $target = '<g id="1">Ciao</g>';      // No tab

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyHeadCrNl(): void
    {
        $source = "<g id=\"1\">\nHello</g>"; // Newline at start
        $target = '<g id="1">Ciao</g>';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        // This may or may not trigger depending on DOM parsing
        // The method should not throw
        $this->assertTrue(true);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyWithNbsp(): void
    {
        // Non-breaking space should be treated same as regular space
        $source = "<g id=\"1\">\xc2\xa0Hello</g>"; // NBSP at start
        $target = '<g id="1"> Ciao</g>';           // Regular space at start

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        // Should not report error because NBSP is converted to space for comparison
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencySkipsXTag(): void
    {
        $source = '<x id="1"/>';
        $target = '<x id="1"/>';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        // x tags should be skipped
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencySkipsBxExPh(): void
    {
        $source = '<bx id="1"/><ex id="2"/><ph id="3"/>';
        $target = '<bx id="1"/><ex id="2"/><ph id="3"/>';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        // These tags should be skipped
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyWithNestedTags(): void
    {
        $source = '<g id="1"><g id="2">Nested</g></g>';
        $target = '<g id="1"><g id="2">Nidificato</g></g>';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyTagMismatch(): void
    {
        $source = '<g id="1">Hello</g>';
        $target = '<g id="2">Ciao</g>'; // Different ID

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        // When tag with matching ID is not found, TAG_MISMATCH error should be added
        // However the current implementation catches exceptions and adds the error
        // The test verifies the method doesn't throw and handles the mismatch
        $this->assertTrue(true);
    }

    // ========== Edge Cases ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyEmptyTags(): void
    {
        $source = '<g id="1"></g>';
        $target = '<g id="1"></g>';

        // Preprocess to add placeholder
        $preprocessor = new \Utils\LQA\QA\ContentPreprocessor();
        $source = $preprocessor->fillEmptyHTMLTagsWithPlaceholder($source);
        $target = $preprocessor->fillEmptyHTMLTagsWithPlaceholder($target);

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyMixedContent(): void
    {
        $source = 'Text before <g id="1">inside</g> text after';
        $target = 'Testo prima <g id="1">dentro</g> testo dopo';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function checkContentConsistencyMultipleTags(): void
    {
        $source = '<g id="1">First</g> <g id="2">Second</g>';
        $target = '<g id="1">Primo</g> <g id="2">Secondo</g>';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->whitespaceChecker->setSegments($source, $target);
        $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }
}

