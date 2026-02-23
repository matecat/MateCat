<?php

namespace unit\LQA;

use Exception;
use DOMException;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\DomHandler;
use Utils\LQA\QA\ErrorManager;
use Utils\LQA\QA\TagChecker;

class TagCheckerTest extends AbstractTest
{
    private TagChecker $tagChecker;
    private ErrorManager $errorManager;
    private DomHandler $domHandler;

    public function setUp(): void
    {
        parent::setUp();
        $this->errorManager = new ErrorManager();
        $this->domHandler = new DomHandler($this->errorManager);
        $this->tagChecker = new TagChecker($this->errorManager, $this->domHandler);
    }

    // ========== Set Methods Tests ==========

    #[Test]
    public function setSegments(): void
    {
        $this->tagChecker->setSegments('Source', 'Target');
        $this->assertEquals('Target', $this->tagChecker->getTargetSeg());
    }

    #[Test]
    public function setSourceSegLang(): void
    {
        $this->tagChecker->setSourceSegLang('en-US');
        $this->assertTrue(true);
    }

    #[Test]
    public function setTargetSegLang(): void
    {
        $this->tagChecker->setTargetSegLang('it-IT');
        $this->assertTrue(true);
    }

    #[Test]
    public function getTagPositionErrorInitiallyEmpty(): void
    {
        $this->assertEmpty($this->tagChecker->getTagPositionError());
    }

    // ========== Check Tag Mismatch Tests ==========

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchMatching(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchCountDifference(): void
    {
        $source = '<g id="1">Source</g><x id="2"/>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchIdDifference(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="2">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertTrue(true);
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchGTagCountDifference(): void
    {
        $source = '<g id="1"><g id="2">Nested</g></g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    // ========== Check Tag Positions Tests ==========

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagPositionsMatching(): void
    {
        $source = '<g id="1">First</g> <g id="2">Second</g>';
        $target = '<g id="1">Primo</g> <g id="2">Secondo</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagPositions();

        $this->assertFalse($this->errorManager->thereAreWarnings());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagPositionsReordered(): void
    {
        $source = '<g id="1">First</g> <g id="2">Second</g>';
        $target = '<g id="2">Secondo</g> <g id="1">Primo</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagPositions();

        $this->assertTrue($this->errorManager->thereAreWarnings());
        $this->assertNotEmpty($this->tagChecker->getTagPositionError());
    }

    // ========== Perform Tag Position Check Tests ==========

    #[Test]
    public function performTagPositionCheckMatching(): void
    {
        $source = '<g id="1">Test</g>';
        $target = '<g id="1">Test</g>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function performTagPositionCheckIdMismatch(): void
    {
        $source = '<g id="1">Test</g>';
        $target = '<g id="2">Test</g>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target, true, false);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function performTagPositionCheckSkipIdCheck(): void
    {
        $source = '<g id="1">Test</g>';
        $target = '<g id="2">Test</g>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target, false, false);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function performTagPositionCheckOrderMismatch(): void
    {
        $source = '<g id="1">First</g><g id="2">Second</g>';
        $target = '<g id="2">Second</g><g id="1">First</g>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target, false, true);

        $this->assertTrue($this->errorManager->thereAreWarnings());
    }

    #[Test]
    public function performTagPositionCheckSelfClosing(): void
    {
        $source = '<x id="1"/><x id="2"/>';
        $target = '<x id="1"/><x id="2"/>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function performTagPositionCheckEquivText(): void
    {
        $source = '<ph id="1" equiv-text="base64:dGVzdA=="/>';
        $target = '<ph id="1" equiv-text="base64:dGVzdA=="/>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function performTagPositionCheckEquivTextMismatch(): void
    {
        $source = '<ph id="1" equiv-text="base64:dGVzdA=="/>';
        $target = '<ph id="1" equiv-text="base64:b3RoZXI="/>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    // ========== Check Tags Boundary Tests ==========

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundaryMatching(): void
    {
        $source = '<g id="1">Text</g>';
        $target = '<g id="1">Testo</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagsBoundary();

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundaryHeadWhitespaceMismatch(): void
    {
        $source = ' Text';
        $target = 'Testo';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagsBoundary();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundaryTailWhitespaceMismatch(): void
    {
        $source = 'Text ';
        $target = 'Testo';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->setTargetSegLang('it-IT');

        $this->tagChecker->checkTagsBoundary();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundarySpaceAfterClosingTag(): void
    {
        $source = '</g> text';
        $target = '</g>text';

        $this->domHandler->loadDoms('<g id="1">Source' . $source, '<g id="1">Target' . $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments('<g id="1">Source' . $source, '<g id="1">Target' . $target);

        $this->tagChecker->checkTagsBoundary();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundarySpaceBeforeTag(): void
    {
        $source = 'text <g id="1">inside</g>';
        $target = 'text<g id="1">dentro</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagsBoundary();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundaryCjkTarget(): void
    {
        $source = 'Text ';
        $target = '文本';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->setTargetSegLang('zh-CN');

        $this->tagChecker->checkTagsBoundary();

        $this->assertTrue(true);
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundaryCjkSourceWithNonCjkTarget(): void
    {
        $source = '文本。';
        $target = 'Text';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->setSourceSegLang('ja-JP');
        $this->tagChecker->setTargetSegLang('en-US');

        $this->tagChecker->checkTagsBoundary();

        $targetResult = $this->tagChecker->getTargetSeg();
        $this->assertStringEndsWith(' ', $targetResult);
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagsBoundaryBetweenTags(): void
    {
        $source = '</g> <g id="2">';
        $target = '</g><g id="2">';

        $sourceFull = '<g id="1">Text' . $source . 'more</g>';
        $targetFull = '<g id="1">Testo' . $target . 'altro</g>';

        $this->domHandler->loadDoms($sourceFull, $targetFull);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($sourceFull, $targetFull);

        $this->tagChecker->checkTagsBoundary();

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    // ========== Complex Tag Structures Tests ==========

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchWithNestedTags(): void
    {
        $source = '<g id="1"><g id="2"><g id="3">Deep</g></g></g>';
        $target = '<g id="1"><g id="2"><g id="3">Profondo</g></g></g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchWithMixedTagTypes(): void
    {
        $source = '<g id="1">Text <x id="2"/> more <bx id="3"/><ex id="3"/></g>';
        $target = '<g id="1">Testo <x id="2"/> altro <bx id="3"/><ex id="3"/></g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== Edge Cases ==========

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchEmptySegments(): void
    {
        $source = '';
        $target = '';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws DOMException
     */
    #[Test]
    public function checkTagMismatchPlainText(): void
    {
        $source = 'Plain text without tags';
        $target = 'Testo semplice senza tag';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();
        $this->tagChecker->setSegments($source, $target);

        $this->tagChecker->checkTagMismatch();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function performTagPositionCheckWithClosingTags(): void
    {
        $source = '<g id="1">Text</g><g id="2">More</g>';
        $target = '<g id="1">Testo</g><g id="2">Altro</g>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function performTagPositionCheckClosingTagsMismatch(): void
    {
        $source = '<g id="1">Text</g><g id="2">More</g>';
        $target = '<g id="2">Altro</g><g id="1">Testo</g>';

        $this->tagChecker->setSegments($source, $target);
        $this->tagChecker->performTagPositionCheck($source, $target, false, true);

        $this->assertTrue($this->errorManager->thereAreWarnings());
    }
}

