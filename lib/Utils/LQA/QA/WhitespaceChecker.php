<?php

namespace Utils\LQA\QA;

use DOMNodeList;
use Throwable;

/**
 * Handles whitespace consistency validation between source and target segments.
 *
 * This class is responsible for:
 * - Checking leading/trailing whitespace consistency in tag content
 * - Validating tab character placement (head/tail)
 * - Checking carriage return/newline consistency
 * - Validating whitespace around XLIFF tags
 *
 * Whitespace issues are important because:
 * - Incorrect spacing can affect document rendering
 * - Missing/extra spaces around tags may break formatting
 * - Different newline handling between source/target can cause issues
 *
 * @package Utils\LQA\QA
 */
class WhitespaceChecker
{
    /** @var ErrorManager Error manager for reporting whitespace errors */
    protected ErrorManager $errorManager;

    /** @var DomHandler DOM handler for structure access */
    protected DomHandler $domHandler;

    /** @var string Source segment for whitespace analysis */
    protected string $sourceSeg;

    /** @var string Target segment for whitespace analysis */
    protected string $targetSeg;

    /**
     * Creates a new WhitespaceChecker instance.
     *
     * @param ErrorManager $errorManager Error manager for reporting errors
     * @param DomHandler $domHandler DOM handler for structure access
     */
    public function __construct(ErrorManager $errorManager, DomHandler $domHandler)
    {
        $this->errorManager = $errorManager;
        $this->domHandler = $domHandler;
    }

    /**
     * Sets the source and target segments for analysis.
     *
     * @param string $sourceSeg The source segment
     * @param string $targetSeg The target segment
     * @return void
     */
    public function setSegments(string $sourceSeg, string $targetSeg): void
    {
        $this->sourceSeg = $sourceSeg;
        $this->targetSeg = $targetSeg;
    }

    /**
     * Checks content consistency between source and target DOM nodes.
     *
     * Iterates through all DOM elements and validates that whitespace
     * patterns in text content match between source and target.
     *
     * @param DOMNodeList $srcNodeList Source segment DOM nodes
     * @param DOMNodeList $trgNodeList Target segment DOM nodes
     * @return void
     */
    public function checkContentConsistency(DOMNodeList $srcNodeList, DOMNodeList $trgNodeList): void
    {
        $srcDomMap = $this->domHandler->getSrcDomMap();
        $trgDomMap = $this->domHandler->getTrgDomMap();
        $trgTagReference = ['node_idx' => -1];

        foreach ($srcDomMap['DOMElement'] as $srcTagReference) {
            if (in_array($srcTagReference['name'], ['x', 'bx', 'ex', 'ph'])) {
                continue;
            }

            try {
                if (!is_null($srcTagReference['parent_id'])) {
                    $srcNode = $this->domHandler->queryDOMElement($this->domHandler->getSrcDom(), $srcTagReference);
                    $srcNodeContent = $srcNode->textContent;

                    foreach ($trgDomMap['DOMElement'] as $k => $elements) {
                        if ($elements['id'] == $srcTagReference['id']) {
                            $trgTagReference = $trgDomMap['DOMElement'][$k];
                        }
                    }

                    $trgNode = $this->domHandler->queryDOMElement($this->domHandler->getTrgDom(), $trgTagReference);
                    $trgNodeContent = $trgNode->textContent;
                } else {
                    $srcNode = $srcNodeList->item($srcTagReference['node_idx']);
                    if ($srcNode !== null) {
                        $srcNodeContent = $srcNode->textContent;
                    }

                    foreach ($trgDomMap['DOMElement'] as $k => $elements) {
                        if ($elements['id'] == $srcTagReference['id']) {
                            $trgTagReference = $trgDomMap['DOMElement'][$k];
                        }
                    }

                    $trgTagPos = $trgTagReference['node_idx'];
                    $trgNode = $trgNodeList->item($trgTagPos);
                    if ($trgNode !== null) {
                        $trgNodeContent = $trgNode->textContent;
                    }
                }

                $domSrcNodeString = $srcNode->ownerDocument->saveXML($srcNode);

                if (isset($trgNodeContent) && isset($srcNodeContent)) {
                    if (!preg_match('/^<g[^>]+></', $domSrcNodeString)) {
                        $this->checkHeadWhiteSpaces($srcNodeContent, $trgNodeContent);
                    }

                    $this->checkTailWhiteSpaces($srcNodeContent, $trgNodeContent);
                    $this->checkHeadTabs($srcNodeContent, $trgNodeContent);
                    $this->checkTailTabs($srcNodeContent, $trgNodeContent);
                    $this->checkHeadCRNL($srcNodeContent, $trgNodeContent);
                    $this->checkTailCRNL($srcNodeContent, $trgNodeContent);
                }
            } catch (Throwable) {
                $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);
            }
        }
    }

    /**
     * Check for head whitespaces
     */
    protected function checkHeadWhiteSpaces(string $srcNodeContent, string $trgNodeContent): void
    {
        $srcNodeContent = $this->nbspToSpace($srcNodeContent);
        $trgNodeContent = $this->nbspToSpace($trgNodeContent);

        $headSrcWhiteSpaces = mb_stripos($srcNodeContent, " ", 0, 'utf-8');
        $headTrgWhiteSpaces = mb_stripos($trgNodeContent, " ", 0, 'utf-8');

        if (($headSrcWhiteSpaces === 0 || $headTrgWhiteSpaces === 0) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces) {
            $this->errorManager->addError(ErrorManager::ERR_WS_HEAD);
        }
    }

    /**
     * Check for trailing whitespaces
     */
    protected function checkTailWhiteSpaces(string $srcNodeContent, string $trgNodeContent): void
    {
        $srcNodeContent = $this->nbspToSpace($srcNodeContent);
        $trgNodeContent = $this->nbspToSpace($trgNodeContent);

        $srcLen = mb_strlen($srcNodeContent);
        $trgLen = mb_strlen($trgNodeContent);

        $trailingSrcChar = mb_substr($srcNodeContent, $srcLen - 1, 1, 'utf-8');
        $trailingTrgChar = mb_substr($trgNodeContent, $trgLen - 1, 1, 'utf-8');
        if (($trailingSrcChar == " " || $trailingTrgChar == " ") && $trailingSrcChar != $trailingTrgChar) {
            $this->errorManager->addError(ErrorManager::ERR_WS_TAIL);
        }
    }

    /**
     * Check for head tabs
     */
    protected function checkHeadTabs(string $srcNodeContent, string $trgNodeContent): void
    {
        $headSrcTabs = mb_stripos($srcNodeContent, "\t", 0, 'utf-8');
        $headTrgTabs = mb_stripos($trgNodeContent, "\t", 0, 'utf-8');
        if (($headSrcTabs === 0 || $headTrgTabs === 0) && $headSrcTabs !== $headTrgTabs) {
            $this->errorManager->addError(ErrorManager::ERR_TAB_HEAD);
        }
    }

    /**
     * Check for trailing tabs
     */
    protected function checkTailTabs(string $srcNodeContent, string $trgNodeContent): void
    {
        $srcLen = mb_strlen($srcNodeContent);
        $trgLen = mb_strlen($trgNodeContent);

        $trailingSrcChar = mb_substr($srcNodeContent, $srcLen - 1, 1, 'utf-8');
        $trailingTrgChar = mb_substr($trgNodeContent, $trgLen - 1, 1, 'utf-8');
        if (($trailingSrcChar == "\t" || $trailingTrgChar == "\t") && $trailingSrcChar != $trailingTrgChar) {
            $this->errorManager->addError(ErrorManager::ERR_TAB_TAIL);
        }
    }

    /**
     * Check for head carriage return / new line
     */
    protected function checkHeadCRNL(string $srcNodeContent, string $trgNodeContent): void
    {
        $headSrcCRNL = mb_split('^[\r\n]+', $srcNodeContent);
        $headTrgCRNL = mb_split('^[\r\n]+', $trgNodeContent);
        if ((count($headSrcCRNL) > 1 || count($headTrgCRNL) > 1) && $headSrcCRNL[0] !== $headTrgCRNL[0]) {
            $this->errorManager->addError(ErrorManager::ERR_CR_HEAD);
        }
    }

    /**
     * Check for tail carriage return / new line
     */
    protected function checkTailCRNL(string $srcNodeContent, string $trgNodeContent): void
    {
        $headSrcCRNL = mb_split('[\r\n]+$', $srcNodeContent);
        $headTrgCRNL = mb_split('[\r\n]+$', $trgNodeContent);
        if ((count($headSrcCRNL) > 1 || count($headTrgCRNL) > 1) && end($headSrcCRNL) !== end($headTrgCRNL)) {
            $this->errorManager->addError(ErrorManager::ERR_CR_TAIL);
        }
    }

    /**
     * Check for newline consistency
     */
    public function checkNewLineConsistency(): void
    {
        $newlinePlaceholder = ContentPreprocessor::getNewlinePlaceholder();
        $nrOfNewLinesInSource = mb_substr_count($this->sourceSeg, $newlinePlaceholder);
        $nrOfNewLinesInTarget = mb_substr_count($this->targetSeg, $newlinePlaceholder);

        for ($i = 0; $i < abs($nrOfNewLinesInSource - $nrOfNewLinesInTarget); $i++) {
            $this->errorManager->addError(ErrorManager::ERR_NEWLINE_MISMATCH);
        }
    }

    /**
     * Perform a replacement of all non-breaking spaces with a simple space char
     */
    protected function nbspToSpace(string $s): string
    {
        return preg_replace("/\x{a0}/u", chr(0x20), $s);
    }

}

