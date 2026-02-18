<?php

namespace Utils\LQA\QA;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Exception;
use Model\FeaturesBase\FeatureSet;

/**
 * Handles DOM operations for XML/XLIFF segment analysis.
 *
 * This class is responsible for:
 * - Loading XML strings into DOMDocument objects with error handling
 * - Creating and maintaining DOM element maps for source and target
 * - Detecting malformed XML and specific tag errors (unclosed x/g tags)
 * - Providing tag difference analysis for error reporting
 * - Managing normalized target DOM for whitespace normalization output
 *
 * The DOM maps track:
 * - Element counts and positions
 * - Tag types (g, x, bx, ex, ph)
 * - Tag IDs and references
 * - Text nodes between tags
 *
 * @package Utils\LQA\QA
 */
class DomHandler
{
    /** @var DOMDocument|null Source segment DOM */
    protected ?DOMDocument $srcDom = null;

    /** @var DOMDocument|null Target segment DOM */
    protected ?DOMDocument $trgDom = null;

    /** @var DOMDocument|null Normalized target DOM for output */
    protected ?DOMDocument $normalizedTrgDOM = null;

    /** @var DOMNodeList|null Normalized target DOM node list */
    protected ?DOMNodeList $normalizedTrgDOMNodeList = null;

    /** @var array Source DOM element map */
    protected array $srcDomMap = [];

    /** @var array Target DOM element map */
    protected array $trgDomMap = [];

    /** @var array{source: array, target: array} Tag differences for malformed XML */
    protected array $malformedXmlStructDiff = ['source' => [], 'target' => []];

    /** @var FeatureSet|null Feature set for plugin customizations */
    protected ?FeatureSet $featureSet = null;

    /** @var ErrorManager Error manager for reporting DOM errors */
    protected ErrorManager $errorManager;

    /**
     * Creates a new DomHandler instance.
     *
     * @param ErrorManager $errorManager The error manager for reporting DOM errors
     */
    public function __construct(ErrorManager $errorManager)
    {
        $this->errorManager = $errorManager;
        $this->resetDOMMaps();
    }

    /**
     * Sets the feature set for plugin customizations.
     *
     * @param FeatureSet|null $featureSet The feature set instance
     * @return void
     */
    public function setFeatureSet(?FeatureSet $featureSet): void
    {
        $this->featureSet = $featureSet;
    }

    /**
     * Gets the source segment DOM.
     *
     * @return DOMDocument|null The source DOM or null if not loaded
     */
    public function getSrcDom(): ?DOMDocument
    {
        return $this->srcDom;
    }

    /**
     * Gets the target segment DOM.
     *
     * @return DOMDocument|null The target DOM or null if not loaded
     */
    public function getTrgDom(): ?DOMDocument
    {
        return $this->trgDom;
    }

    /**
     * Sets the target segment DOM.
     *
     * @param DOMDocument $dom The target DOM
     * @return void
     */
    public function setTrgDom(DOMDocument $dom): void
    {
        $this->trgDom = $dom;
    }

    /**
     * Gets the normalized target DOM for output.
     *
     * This DOM has whitespace normalized to match the source pattern.
     *
     * @return DOMDocument|null The normalized target DOM or null if not prepared
     */
    public function getNormalizedTrgDOM(): ?DOMDocument
    {
        return $this->normalizedTrgDOM;
    }

    public function setNormalizedTrgDOM(DOMDocument $dom): void
    {
        $this->normalizedTrgDOM = clone $dom;
    }

    public function getSrcDomMap(): array
    {
        return $this->srcDomMap;
    }

    public function getTrgDomMap(): array
    {
        return $this->trgDomMap;
    }


    public function getMalformedXmlStructs(): array
    {
        return $this->malformedXmlStructDiff;
    }

    /**
     * Load source and target segments into DOM
     */
    public function loadDoms(string $sourceSeg, string $targetSeg): void
    {
        $this->srcDom = $this->loadDom($sourceSeg, ErrorManager::ERR_SOURCE);
        $this->trgDom = $this->loadDom($targetSeg, ErrorManager::ERR_TARGET);

        if ($this->errorManager->thereAreErrors()) {
            $this->getTagDiff($sourceSeg, $targetSeg);
        }

        $this->resetDOMMaps();
    }

    /**
     * Load an XML String into DOMDocument Object and add error if not valid
     */
    public function loadDom(string $xmlString, int $targetErrorType): DOMDocument
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'utf-8');
        $trg_xml_valid = @$dom->loadXML("<root>$xmlString</root>", LIBXML_NOENT);

        if ($trg_xml_valid === false) {
            $errorList = libxml_get_errors();
            foreach ($errorList as $error) {
                if ($this->checkUnclosedTag("x", $xmlString, $error)) {
                    $this->errorManager->addError(ErrorManager::ERR_UNCLOSED_X_TAG);
                    return $dom;
                }

                if ($this->checkUnclosedTag("g", $xmlString, $error)) {
                    $this->errorManager->addError(ErrorManager::ERR_UNCLOSED_G_TAG);
                    return $dom;
                }
            }

            $this->errorManager->addError($targetErrorType);
        }

        return $dom;
    }

    private function checkUnclosedTag(string $tag, string $xmlString, $error): bool
    {
        $message = str_replace("\n", " ", $error->message);
        if ($error->code == 76 && preg_match('#<' . $tag . '[^/>]+>#', $xmlString) && preg_match('# ' . $tag . ' #', $message)) {
            return true;
        }
        return false;
    }

    /**
     * Reset the DOM Maps
     */
    public function resetDOMMaps(): void
    {
        $this->srcDomMap = ['elemCount' => 0, 'x' => [], 'bx' => [], 'ex' => [], 'g' => [], 'refID' => [], 'DOMElement' => [], 'DOMText' => [], 'ph' => []];
        $this->trgDomMap = ['elemCount' => 0, 'x' => [], 'bx' => [], 'ex' => [], 'g' => [], 'refID' => [], 'DOMElement' => [], 'DOMText' => [], 'ph' => []];
    }

    /**
     * Prepare DOM structures for analysis
     *
     * @throws DOMException
     * @throws Exception
     */
    public function prepareDOMStructures(): array
    {
        $srcNodeList = @$this->srcDom->getElementsByTagName('root')->item(0)->childNodes;
        $trgNodeList = @$this->trgDom->getElementsByTagName('root')->item(0)->childNodes;

        if (!$srcNodeList instanceof DOMNodeList || !$trgNodeList instanceof DOMNodeList) {
            throw new DOMException('Bad DOMNodeList');
        }

        // Create a dom node map
        $this->mapDom($srcNodeList, $trgNodeList);

        // Save normalized dom Element
        $this->normalizedTrgDOM = clone $this->trgDom;

        // Save normalized Dom Node list
        $this->normalizedTrgDOMNodeList = @$this->normalizedTrgDOM->getElementsByTagName('root')->item(0)->childNodes;

        return [$srcNodeList, $trgNodeList];
    }

    /**
     * Build a node map tree of XML source and XML target
     *
     * @throws Exception
     */
    protected function mapDom(DOMNodeList $srcNodeList, DOMNodeList $trgNodeList): array
    {
        if (empty($this->srcDomMap['elemCount']) || empty($this->trgDomMap['elemCount'])) {
            $this->mapElements($srcNodeList, $this->srcDomMap);
            $this->mapElements($trgNodeList, $this->trgDomMap);
        }

        return [$this->srcDomMap, $this->trgDomMap];
    }

    /**
     * Create a map of NodeTree walking recursively a DOMNodeList
     *
     * @throws Exception
     */
    protected function mapElements(DOMNodeList $elementList, array &$srcDomElements = [], int $depth = 0, ?string $parentID = null): void
    {
        $elementsListLen = $elementList->length;

        for ($i = 0; $i < $elementsListLen; $i++) {
            $element = $elementList->item($i);

            if ($element instanceof DOMElement) {
                $elementID = $element->getAttribute('id');

                if ($this->addThisElementToDomMap($element)) {
                    $plainRef = [
                        'type' => 'DOMElement',
                        'name' => $element->tagName,
                        'id' => $elementID,
                        'parent_id' => $parentID,
                        'node_idx' => $i,
                        'innerHTML' => $element->ownerDocument->saveXML($element),
                    ];

                    $srcDomElements['DOMElement'][] = $plainRef;
                    @$srcDomElements[$element->tagName][] = $elementID;

                    // Handle PH tags specially for content comparison
                    if ($element->tagName === 'ph') {
                        $innerHTML = $plainRef['innerHTML'];
                        $regex = "<ph id\s*=\s*[\"']mtc_[0-9]+[\"'] equiv-text\s*=\s*[\"']base64:([^\"']+)[\"']\s*/>";
                        preg_match_all($regex, $innerHTML, $html, PREG_SET_ORDER);

                        if (isset($html[0][1])) {
                            $html = base64_decode($html[0][1]);
                            @$srcDomElements['refID'][$html] = $element->tagName;
                        } else {
                            @$srcDomElements['refID'][$elementID] = $element->tagName;
                        }
                    } else {
                        @$srcDomElements['refID'][$elementID] = $element->tagName;
                    }

                    if ($element->hasChildNodes()) {
                        $this->mapElements($element->childNodes, $srcDomElements, $depth, $elementID);
                    }
                }
            } else {
                $plainRef = [
                    'type' => 'DOMText',
                    'name' => null,
                    'id' => null,
                    'parent_id' => $parentID,
                    'node_idx' => $i,
                    'content' => $elementList->item($i)->textContent,
                ];

                $srcDomElements['DOMText'][$depth++] = $plainRef;
            }

            $srcDomElements['elemCount']++;
        }
    }

    /**
     * Determine if an element should be added to DOM map
     *
     * @throws Exception
     */
    protected function addThisElementToDomMap(DOMElement $element): bool
    {
        $tagsToBeExcludedFromChecks = [];

        if (null !== $this->featureSet) {
            $tagsToBeExcludedFromChecks = $this->featureSet->filter('injectExcludedTagsInQa', []);
        }

        if (empty($tagsToBeExcludedFromChecks)) {
            return true;
        }

        return $this->elementIsToBeExcludedFromChecks($element, $tagsToBeExcludedFromChecks);
    }

    /**
     * Check if a tag element is contained in exclusion map
     */
    private function elementIsToBeExcludedFromChecks(DOMElement $element, array $tagsToBeExcludedFromChecks): bool
    {
        $elementHasDataRef = false;
        $elementValue = null;

        foreach ($element->attributes as $attribute) {
            if ($attribute->name === 'equiv-text') {
                $elementValue = base64_decode(str_replace('base64:', '', $attribute->value));
            }

            if ($attribute->name === 'dataRef') {
                $elementHasDataRef = true;
            }
        }

        return !(in_array($elementValue, $tagsToBeExcludedFromChecks) && $elementHasDataRef);
    }

    /**
     * Get deep information about xml loading failure for tag mismatch
     */
    public function getTagDiff(string $sourceSeg, string $targetSeg): void
    {
        preg_match_all('#(<[^>]+id\s*=[^>]+/?>)#', $sourceSeg, $matches);
        $malformedXmlSrcStruct = $matches[1];
        preg_match_all('#(<[^>]+id\s*=[^>]+/?>)#', $targetSeg, $matches);
        $malformedXmlTrgStruct = $matches[1];

        preg_match_all('#(</[a-zA-Z]+>)#', $sourceSeg, $matches);
        $_closingSrcTag = $matches[1];

        preg_match_all('#(</[a-zA-Z]+>)#', $targetSeg, $matches);
        $_closingTrgTag = $matches[1];

        $clonedSrc = $malformedXmlSrcStruct;
        $clonedTrg = $malformedXmlTrgStruct;

        foreach ($malformedXmlTrgStruct as $tag) {
            if (($pos = array_search($tag, $clonedSrc)) !== false) {
                unset($clonedSrc[$pos]);
            }
        }

        foreach ($malformedXmlSrcStruct as $tag) {
            if (($pos = array_search($tag, $clonedTrg)) !== false) {
                unset($clonedTrg[$pos]);
            }
        }

        $clonedClosingSrc = $_closingSrcTag;
        $clonedClosingTrg = $_closingTrgTag;
        foreach ($_closingTrgTag as $tag) {
            if (($pos = array_search($tag, $clonedClosingSrc)) !== false) {
                unset($clonedClosingSrc[$pos]);
            }
        }

        foreach ($_closingSrcTag as $tag) {
            if (($pos = array_search($tag, $clonedClosingTrg)) !== false) {
                unset($clonedClosingTrg[$pos]);
            }
        }

        $totalResult = ['source' => [], 'target' => []];
        $source_segments = array_merge($clonedSrc, $clonedClosingSrc);
        foreach ($source_segments as $source_segment) {
            $totalResult['source'][] = $source_segment;
        }

        $target_segments = array_merge($clonedTrg, $clonedClosingTrg);
        foreach ($target_segments as $target_segment) {
            $totalResult['target'][] = $target_segment;
        }

        $this->malformedXmlStructDiff = $totalResult;
    }

    /**
     * Find in a DOMDocument an Element by its Reference
     */
    public function queryDOMElement(DOMDocument $domDoc, array $TagReference): DOMNode
    {
        $xpath = new DOMXPath($domDoc);
        $query = '//*[@id="' . ($TagReference['id'] ?? '') . '"]';

        $Node = $xpath->query($query);

        return (($Node->length == 0 || !$Node) ? new DOMNode() : $Node->item(0));
    }
}

