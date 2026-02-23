<?php

namespace unit\LQA;

use DOMDocument;
use DOMException;
use Exception;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\DomHandler;
use Utils\LQA\QA\ErrorManager;

class DomHandlerTest extends AbstractTest
{
    private DomHandler $domHandler;
    private ErrorManager $errorManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->errorManager = new ErrorManager();
        $this->domHandler = new DomHandler($this->errorManager);
    }

    // ========== Load DOM Tests ==========

    #[Test]
    public function loadDomValidXml(): void
    {
        $xml = '<g id="1">Hello World</g>';
        $dom = $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $this->assertInstanceOf(DOMDocument::class, $dom);
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomEmptyString(): void
    {
        $dom = $this->domHandler->loadDom('', ErrorManager::ERR_SOURCE);

        $this->assertInstanceOf(DOMDocument::class, $dom);
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomPlainText(): void
    {
        $dom = $this->domHandler->loadDom('Hello World', ErrorManager::ERR_SOURCE);

        $this->assertInstanceOf(DOMDocument::class, $dom);
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomInvalidXml(): void
    {
        $xml = '<g id="1">Hello World'; // Missing closing tag
        $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomUnclosedXTag(): void
    {
        $xml = '<x id="1">'; // x tag should be self-closing
        $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $errors = $this->errorManager->getErrors();
        $found = false;
        foreach ($errors as $err) {
            if ($err->outcome === ErrorManager::ERR_UNCLOSED_X_TAG) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should detect unclosed x tag');
    }

    #[Test]
    public function loadDomUnclosedGTag(): void
    {
        $xml = '<g id="1">Test<g id="2">Nested'; // Missing closing g tags
        $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomWithSelfClosingTag(): void
    {
        $xml = '<x id="1"/>';
        $dom = $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $this->assertInstanceOf(DOMDocument::class, $dom);
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomWithNestedTags(): void
    {
        $xml = '<g id="1"><g id="2">Nested</g></g>';
        $dom = $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $this->assertInstanceOf(DOMDocument::class, $dom);
        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== Load DOMs Tests ==========

    #[Test]
    public function loadDomsValid(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);

        $this->assertFalse($this->errorManager->thereAreErrors());
        $this->assertNotNull($this->domHandler->getSrcDom());
        $this->assertNotNull($this->domHandler->getTrgDom());
    }

    #[Test]
    public function loadDomsWithSourceError(): void
    {
        $source = '<g id="1">Unclosed'; // Invalid
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomsWithTargetError(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Unclosed'; // Invalid

        $this->domHandler->loadDoms($source, $target);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    // ========== Prepare DOM Structures Tests ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function prepareDomStructures(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();

        $this->assertInstanceOf(\DOMNodeList::class, $srcNodeList);
        $this->assertInstanceOf(\DOMNodeList::class, $trgNodeList);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function prepareDomStructuresPopulatesMaps(): void
    {
        $source = '<g id="1">Source</g><x id="2"/>';
        $target = '<g id="1">Target</g><x id="2"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();
        $trgMap = $this->domHandler->getTrgDomMap();

        $this->assertNotEmpty($srcMap['DOMElement']);
        $this->assertNotEmpty($trgMap['DOMElement']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function prepareDomStructuresWithNormalizedTrgDom(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $this->assertNotNull($this->domHandler->getNormalizedTrgDOM());
    }

    // ========== DOM Map Tests ==========

    #[Test]
    public function getSrcDomMapEmpty(): void
    {
        $map = $this->domHandler->getSrcDomMap();

        $this->assertArrayHasKey('elemCount', $map);
        $this->assertArrayHasKey('DOMElement', $map);
        $this->assertArrayHasKey('DOMText', $map);
        $this->assertArrayHasKey('g', $map);
        $this->assertArrayHasKey('x', $map);
        $this->assertArrayHasKey('bx', $map);
        $this->assertArrayHasKey('ex', $map);
        $this->assertArrayHasKey('ph', $map);
    }

    #[Test]
    public function getTrgDomMapEmpty(): void
    {
        $map = $this->domHandler->getTrgDomMap();

        $this->assertArrayHasKey('elemCount', $map);
        $this->assertArrayHasKey('refID', $map);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function domMapCountsElements(): void
    {
        $source = '<g id="1">Text1</g><g id="2">Text2</g><x id="3"/>';
        $target = '<g id="1">Testo1</g><g id="2">Testo2</g><x id="3"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(3, $srcMap['DOMElement']);
        $this->assertCount(2, $srcMap['g']);
        $this->assertCount(1, $srcMap['x']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function domMapTracksRefId(): void
    {
        $source = '<g id="1">Text</g>';
        $target = '<g id="1">Testo</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertArrayHasKey('1', $srcMap['refID']);
        $this->assertEquals('g', $srcMap['refID']['1']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function domMapTracksPhTag(): void
    {
        $source = '<ph id="mtc_1" equiv-text="base64:Jmx0Ow=="/>';
        $target = '<ph id="mtc_1" equiv-text="base64:Jmx0Ow=="/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(1, $srcMap['ph']);
    }

    // ========== Get Tag Diff Tests ==========

    #[Test]
    public function getTagDiffNoMismatch(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertEmpty($diff['source']);
        $this->assertEmpty($diff['target']);
    }

    #[Test]
    public function getTagDiffWithMissingTagInTarget(): void
    {
        $source = '<g id="1">Source</g><x id="2"/>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertNotEmpty($diff['source']);
        $this->assertContains('<x id="2"/>', $diff['source']);
    }

    #[Test]
    public function getTagDiffWithExtraTagInTarget(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g><x id="2"/>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertNotEmpty($diff['target']);
        $this->assertContains('<x id="2"/>', $diff['target']);
    }

    #[Test]
    public function getTagDiffWithMismatchedIds(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="2">Target</g>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertNotEmpty($diff['source']);
        $this->assertNotEmpty($diff['target']);
    }

    #[Test]
    public function getTagDiffWithClosingTags(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target'; // Missing closing tag

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertNotEmpty($diff['source']);
    }

    // ========== Query DOM Element Tests ==========

    #[Test]
    public function queryDomElement(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $tagRef = ['id' => '1'];
        $node = $this->domHandler->queryDOMElement($this->domHandler->getSrcDom(), $tagRef);

        $this->assertInstanceOf(\DOMNode::class, $node);
    }

    #[Test]
    public function queryDomElementNotFound(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $tagRef = ['id' => '999'];
        $node = $this->domHandler->queryDOMElement($this->domHandler->getSrcDom(), $tagRef);

        $this->assertInstanceOf(\DOMNode::class, $node);
    }

    // ========== Reset DOM Maps Tests ==========

    #[Test]
    public function resetDomMaps(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        // Maps should be populated
        $this->assertNotEmpty($this->domHandler->getSrcDomMap()['DOMElement']);

        // Reset maps
        $this->domHandler->resetDOMMaps();

        $srcMap = $this->domHandler->getSrcDomMap();
        $this->assertEquals(0, $srcMap['elemCount']);
        $this->assertEmpty($srcMap['DOMElement']);
    }

    // ========== Setters/Getters Tests ==========

    #[Test]
    public function setTrgDom(): void
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->loadXML('<root>Test</root>');

        $this->domHandler->setTrgDom($dom);

        $this->assertSame($dom, $this->domHandler->getTrgDom());
    }

    #[Test]
    public function setNormalizedTrgDom(): void
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->loadXML('<root>Test</root>');

        $this->domHandler->setNormalizedTrgDOM($dom);

        // Should be a clone, not the same instance
        $this->assertNotSame($dom, $this->domHandler->getNormalizedTrgDOM());
        $this->assertEquals(
            $dom->saveXML(),
            $this->domHandler->getNormalizedTrgDOM()->saveXML()
        );
    }

    #[Test]
    public function getMalformedXmlStructsInitialState(): void
    {
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertArrayHasKey('source', $diff);
        $this->assertArrayHasKey('target', $diff);
        $this->assertEmpty($diff['source']);
        $this->assertEmpty($diff['target']);
    }

    // ========== Complex Tag Structure Tests ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function loadDomsWithBxExTags(): void
    {
        $source = '<bx id="1"/><ex id="1"/>';
        $target = '<bx id="1"/><ex id="1"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(1, $srcMap['bx']);
        $this->assertCount(1, $srcMap['ex']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function loadDomsWithNestedGTags(): void
    {
        $source = '<g id="1"><g id="2">Nested</g></g>';
        $target = '<g id="1"><g id="2">Nidificato</g></g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(2, $srcMap['g']);
        $this->assertCount(2, $srcMap['DOMElement']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function loadDomsWithMixedContent(): void
    {
        $source = 'Text <g id="1">inside tag</g> more text <x id="2"/> end';
        $target = 'Testo <g id="1">nel tag</g> altro testo <x id="2"/> fine';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(2, $srcMap['DOMElement']);
        $this->assertNotEmpty($srcMap['DOMText']);
    }

    // ========== FeatureSet Tests ==========

    #[Test]
    public function setFeatureSetWithNull(): void
    {
        $this->domHandler->setFeatureSet(null);

        // Should not throw, verify by loading a valid DOM
        $source = '<g id="1">Test</g>';
        $target = '<g id="1">Test</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== PH Tag Special Handling Tests ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsWithPhTagAndEquivText(): void
    {
        // PH tag with mtc_ prefix and equiv-text should decode base64 for refID
        $source = '<ph id="mtc_1" equiv-text="base64:Jmx0Ow=="/>';
        $target = '<ph id="mtc_1" equiv-text="base64:Jmx0Ow=="/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(1, $srcMap['ph']);
        // The decoded base64 value "&lt;" should be used as refID key
        $this->assertArrayHasKey('&lt;', $srcMap['refID']);
        $this->assertEquals('ph', $srcMap['refID']['&lt;']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsWithPhTagWithoutMatchingPattern(): void
    {
        // PH tag without mtc_ prefix - should use elementID as refID
        $source = '<ph id="1" equiv-text="base64:dGVzdA=="/>';
        $target = '<ph id="1" equiv-text="base64:dGVzdA=="/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(1, $srcMap['ph']);
        // Should use element ID as refID since pattern doesn't match
        $this->assertArrayHasKey('1', $srcMap['refID']);
        $this->assertEquals('ph', $srcMap['refID']['1']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsWithMultiplePhTags(): void
    {
        $source = '<ph id="mtc_1" equiv-text="base64:Jmx0Ow=="/><ph id="mtc_2" equiv-text="base64:Jmd0Ow=="/>';
        $target = '<ph id="mtc_1" equiv-text="base64:Jmx0Ow=="/><ph id="mtc_2" equiv-text="base64:Jmd0Ow=="/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(2, $srcMap['ph']);
        // Both decoded values should be in refID
        $this->assertArrayHasKey('&lt;', $srcMap['refID']);
        $this->assertArrayHasKey('&gt;', $srcMap['refID']);
    }

    // ========== Tag Diff Edge Cases ==========

    #[Test]
    public function getTagDiffWithMultipleMissingTags(): void
    {
        $source = '<g id="1">A</g><g id="2">B</g><x id="3"/>';
        $target = '<g id="1">A</g>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        // Missing g id=2, </g> for id=2, and x id=3
        $this->assertGreaterThanOrEqual(2, count($diff['source']));
        $this->assertEmpty($diff['target']);
    }

    #[Test]
    public function getTagDiffWithExtraTags(): void
    {
        $source = '<g id="1">A</g>';
        $target = '<g id="1">A</g><g id="2">B</g><x id="3"/>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertEmpty($diff['source']);
        // Extra g id=2, </g> for id=2, and x id=3
        $this->assertGreaterThanOrEqual(2, count($diff['target']));
    }

    #[Test]
    public function getTagDiffWithClosingTagMismatch(): void
    {
        $source = '<g id="1">A</g></extra>';
        $target = '<g id="1">A</g>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertContains('</extra>', $diff['source']);
    }

    #[Test]
    public function getTagDiffWithMismatchedClosingTags(): void
    {
        $source = '<g id="1">A</g></div>';
        $target = '<g id="1">A</g></span>';

        $this->domHandler->getTagDiff($source, $target);
        $diff = $this->domHandler->getMalformedXmlStructs();

        $this->assertContains('</div>', $diff['source']);
        $this->assertContains('</span>', $diff['target']);
    }

    // ========== Load DOM Error Handling Tests ==========

    #[Test]
    public function loadDomWithTargetErrorType(): void
    {
        $xml = '<g id="1">Unclosed';
        $this->domHandler->loadDom($xml, ErrorManager::ERR_TARGET);

        $errors = $this->errorManager->getErrors();
        $found = false;
        foreach ($errors as $err) {
            if ($err->outcome === ErrorManager::ERR_TARGET ||
                $err->outcome === ErrorManager::ERR_UNCLOSED_G_TAG) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    #[Test]
    public function loadDomsTriggersGetTagDiffOnError(): void
    {
        $source = '<g id="1">Source</g><x id="2"/>';
        $target = '<g id="1">Unclosed'; // Invalid XML

        $this->domHandler->loadDoms($source, $target);

        // getTagDiff should have been called due to error
        $diff = $this->domHandler->getMalformedXmlStructs();

        // Source has tags that target doesn't (since target is malformed)
        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    // ========== Query DOM Element Tests ==========

    #[Test]
    public function queryDomElementWithEmptyId(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $tagRef = []; // No id provided
        $node = $this->domHandler->queryDOMElement($this->domHandler->getSrcDom(), $tagRef);

        // Should return empty DOMNode when not found
        $this->assertInstanceOf(\DOMNode::class, $node);
    }

    #[Test]
    public function queryDomElementFindsElement(): void
    {
        $source = '<g id="test123">Source</g>';
        $target = '<g id="test123">Target</g>';

        $this->domHandler->loadDoms($source, $target);

        $tagRef = ['id' => 'test123'];
        $node = $this->domHandler->queryDOMElement($this->domHandler->getSrcDom(), $tagRef);

        $this->assertInstanceOf(\DOMElement::class, $node);
        $this->assertEquals('g', $node->nodeName);
    }

    // ========== Prepare DOM Structures Edge Cases ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function prepareDomStructuresCreatesNormalizedDom(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);

        $this->assertNull($this->domHandler->getNormalizedTrgDOM());

        $this->domHandler->prepareDOMStructures();

        $this->assertNotNull($this->domHandler->getNormalizedTrgDOM());
        $this->assertInstanceOf(DOMDocument::class, $this->domHandler->getNormalizedTrgDOM());
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function prepareDomStructuresCalledTwiceDoesNotRemapIfAlreadyMapped(): void
    {
        $source = '<g id="1">Source</g>';
        $target = '<g id="1">Target</g>';

        $this->domHandler->loadDoms($source, $target);

        // First call
        $this->domHandler->prepareDOMStructures();
        $srcMapFirst = $this->domHandler->getSrcDomMap();

        // Second call - should not remap if already mapped
        $this->domHandler->prepareDOMStructures();
        $srcMapSecond = $this->domHandler->getSrcDomMap();

        $this->assertEquals($srcMapFirst['elemCount'], $srcMapSecond['elemCount']);
    }

    // ========== DOMText Mapping Tests ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsTracksDomTextNodes(): void
    {
        $source = 'Text before <g id="1">inside</g> text after';
        $target = 'Testo prima <g id="1">dentro</g> testo dopo';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Should have DOMText entries
        $this->assertNotEmpty($srcMap['DOMText']);

        // Check that text content is tracked
        $hasTextBefore = false;
        foreach ($srcMap['DOMText'] as $textNode) {
            if (strpos($textNode['content'], 'Text before') !== false) {
                $hasTextBefore = true;
                break;
            }
        }
        $this->assertTrue($hasTextBefore, 'Should track text content before tags');
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsTracksParentId(): void
    {
        $source = '<g id="parent"><g id="child">Nested</g></g>';
        $target = '<g id="parent"><g id="child">Nidificato</g></g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Find child element and verify parent_id
        $childFound = false;
        foreach ($srcMap['DOMElement'] as $elem) {
            if ($elem['id'] === 'child') {
                $this->assertEquals('parent', $elem['parent_id']);
                $childFound = true;
                break;
            }
        }
        $this->assertTrue($childFound, 'Child element should be found with parent_id');
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsTracksInnerHtml(): void
    {
        $source = '<g id="1">Content with <x id="2"/> nested tag</g>';
        $target = '<g id="1">Contenuto con <x id="2"/> tag annidato</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Find the g element and check innerHTML
        foreach ($srcMap['DOMElement'] as $elem) {
            if ($elem['id'] === '1' && $elem['name'] === 'g') {
                $this->assertStringContainsString('<x id="2"/>', $elem['innerHTML']);
                break;
            }
        }
    }

    // ========== Error Recovery Tests ==========

    #[Test]
    public function loadDomWithMultipleErrors(): void
    {
        // XML with multiple issues - should detect the first specific error
        $xml = '<x id="1"><g id="2">Nested unclosed';
        $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        // Should have errors
        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function loadDomsResetsMapOnEachCall(): void
    {
        // First load
        $source1 = '<g id="1">First</g><g id="2">Second</g>';
        $target1 = '<g id="1">Primo</g><g id="2">Secondo</g>';

        $this->domHandler->loadDoms($source1, $target1);
        $this->domHandler->prepareDOMStructures();

        $firstMap = $this->domHandler->getSrcDomMap();
        $this->assertCount(2, $firstMap['g']);

        // Create new handler for clean state
        $this->errorManager = new ErrorManager();
        $this->domHandler = new DomHandler($this->errorManager);

        // Second load with different content
        $source2 = '<g id="3">Only one</g>';
        $target2 = '<g id="3">Solo uno</g>';

        $this->domHandler->loadDoms($source2, $target2);
        $this->domHandler->prepareDOMStructures();

        $secondMap = $this->domHandler->getSrcDomMap();
        $this->assertCount(1, $secondMap['g']);
    }

    // ========== getSrcDom and Initial State Tests ==========

    #[Test]
    public function getSrcDomReturnsNullBeforeLoad(): void
    {
        $freshHandler = new DomHandler(new ErrorManager());
        $this->assertNull($freshHandler->getSrcDom());
    }

    #[Test]
    public function getTrgDomReturnsNullBeforeLoad(): void
    {
        $freshHandler = new DomHandler(new ErrorManager());
        $this->assertNull($freshHandler->getTrgDom());
    }

    #[Test]
    public function getNormalizedTrgDomReturnsNullBeforePrepare(): void
    {
        $source = '<g id="1">Test</g>';
        $target = '<g id="1">Test</g>';

        $this->domHandler->loadDoms($source, $target);

        // Before prepareDOMStructures, should be null
        $this->assertNull($this->domHandler->getNormalizedTrgDOM());
    }

    // ========== All Tag Types Tests ==========

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsTracksAllTagTypes(): void
    {
        $source = '<g id="1">G tag</g><x id="2"/><bx id="3"/><ex id="4"/><ph id="5"/>';
        $target = '<g id="1">G tag</g><x id="2"/><bx id="3"/><ex id="4"/><ph id="5"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        $this->assertCount(1, $srcMap['g']);
        $this->assertCount(1, $srcMap['x']);
        $this->assertCount(1, $srcMap['bx']);
        $this->assertCount(1, $srcMap['ex']);
        $this->assertCount(1, $srcMap['ph']);
        $this->assertCount(5, $srcMap['DOMElement']);
    }

    /**
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mapElementsTracksNodeIndex(): void
    {
        $source = '<g id="1">First</g><g id="2">Second</g>';
        $target = '<g id="1">Primo</g><g id="2">Secondo</g>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Check that node_idx is tracked
        $this->assertEquals(0, $srcMap['DOMElement'][0]['node_idx']);
        $this->assertEquals(1, $srcMap['DOMElement'][1]['node_idx']);
    }

    // ========== checkUnclosedTag Edge Cases ==========

    #[Test]
    public function loadDomDetectsUnclosedXTagWithAttributes(): void
    {
        $xml = '<x id="1" class="test">'; // x with extra attributes, not self-closed
        $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $errors = $this->errorManager->getErrors();
        $found = false;
        foreach ($errors as $err) {
            if ($err->outcome === ErrorManager::ERR_UNCLOSED_X_TAG) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should detect unclosed x tag with attributes');
    }

    #[Test]
    public function loadDomDetectsUnclosedGTagWithContent(): void
    {
        $xml = '<g id="1">Content without closing tag';
        $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $errors = $this->errorManager->getErrors();
        $found = false;
        foreach ($errors as $err) {
            if ($err->outcome === ErrorManager::ERR_UNCLOSED_G_TAG) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should detect unclosed g tag');
    }

    #[Test]
    public function loadDomWithValidSelfClosingXTag(): void
    {
        $xml = '<x id="1" class="test"/>';
        $this->domHandler->loadDom($xml, ErrorManager::ERR_SOURCE);

        $this->assertFalse($this->errorManager->thereAreErrors());
    }

    // ========== FeatureSet Exclusion Tests ==========

    /**
     * Test that setFeatureSet accepts a FeatureSet object
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function setFeatureSetWithMockFeatureSet(): void
    {
        // Create a mock FeatureSet that returns exclusion tags
        $mockFeatureSet = $this->createMock(FeatureSet::class);
        $mockFeatureSet->method('filter')
            ->with('injectExcludedTagsInQa', [])
            ->willReturn(['<br>', '<hr>']);

        $this->domHandler->setFeatureSet($mockFeatureSet);

        $source = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/>';  // base64 of "<br>"
        $target = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        // The element should be excluded from the map
        $srcMap = $this->domHandler->getSrcDomMap();

        // When element is excluded, ph array should be empty
        $this->assertEmpty($srcMap['ph']);
    }

    /**
     * Test element not excluded when dataRef is missing
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function elementNotExcludedWhenDataRefMissing(): void
    {
        $mockFeatureSet = $this->createMock(FeatureSet::class);
        $mockFeatureSet->method('filter')
            ->with('injectExcludedTagsInQa', [])
            ->willReturn(['<br>']);

        $this->domHandler->setFeatureSet($mockFeatureSet);

        // Element with equiv-text but NO dataRef should NOT be excluded
        $source = '<ph id="1" equiv-text="base64:PGJyPg=="/>';  // base64 of "<br>", no dataRef
        $target = '<ph id="1" equiv-text="base64:PGJyPg=="/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Element should be included because dataRef is missing
        $this->assertCount(1, $srcMap['ph']);
    }

    /**
     * Test element not excluded when equiv-text doesn't match exclusion list
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function elementNotExcludedWhenEquivTextNotInExclusionList(): void
    {
        $mockFeatureSet = $this->createMock(FeatureSet::class);
        $mockFeatureSet->method('filter')
            ->with('injectExcludedTagsInQa', [])
            ->willReturn(['<br>']);

        $this->domHandler->setFeatureSet($mockFeatureSet);

        // Element with different equiv-text value
        $source = '<ph id="1" equiv-text="base64:PGhyPg==" dataRef="d1"/>';  // base64 of "<hr>" - not in list
        $target = '<ph id="1" equiv-text="base64:PGhyPg==" dataRef="d1"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Element should be included because equiv-text doesn't match exclusion list
        $this->assertCount(1, $srcMap['ph']);
    }

    /**
     * Test element without equiv-text attribute
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function elementWithoutEquivTextIncluded(): void
    {
        $mockFeatureSet = $this->createMock(FeatureSet::class);
        $mockFeatureSet->method('filter')
            ->with('injectExcludedTagsInQa', [])
            ->willReturn(['<br>']);

        $this->domHandler->setFeatureSet($mockFeatureSet);

        // Element with dataRef but no equiv-text
        $source = '<ph id="1" dataRef="d1"/>';
        $target = '<ph id="1" dataRef="d1"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Element should be included because equiv-text is missing
        $this->assertCount(1, $srcMap['ph']);
    }

    /**
     * Test empty exclusion list from FeatureSet
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function emptyExclusionListIncludesAllElements(): void
    {
        $mockFeatureSet = $this->createMock(FeatureSet::class);
        $mockFeatureSet->method('filter')
            ->with('injectExcludedTagsInQa', [])
            ->willReturn([]);  // Empty exclusion list

        $this->domHandler->setFeatureSet($mockFeatureSet);

        $source = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/>';
        $target = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Element should be included because exclusion list is empty
        $this->assertCount(1, $srcMap['ph']);
    }

    /**
     * Test multiple elements with some excluded
     * @throws DOMException
     * @throws Exception
     */
    #[Test]
    public function mixedElementsWithSomeExcluded(): void
    {
        $mockFeatureSet = $this->createMock(FeatureSet::class);
        $mockFeatureSet->method('filter')
            ->with('injectExcludedTagsInQa', [])
            ->willReturn(['<br>']);

        $this->domHandler->setFeatureSet($mockFeatureSet);

        // First element will be excluded, second will be included
        $source = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/><ph id="2" equiv-text="base64:PGhyPg=="/>';
        $target = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/><ph id="2" equiv-text="base64:PGhyPg=="/>';

        $this->domHandler->loadDoms($source, $target);
        $this->domHandler->prepareDOMStructures();

        $srcMap = $this->domHandler->getSrcDomMap();

        // Only element with id="2" should be included (id="1" is excluded)
        $this->assertCount(1, $srcMap['ph']);
        $this->assertEquals('2', $srcMap['ph'][0]);
    }
}

