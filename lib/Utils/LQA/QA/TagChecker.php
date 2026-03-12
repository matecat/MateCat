<?php

namespace Utils\LQA\QA;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Utils\LQA\QA;
use Utils\Tools\CatUtils;

/**
 * Handles tag-related validation checks for QA.
 *
 * This class is responsible for:
 * - Validating tag count matches between source and target
 * - Checking tag ID consistency
 * - Verifying tag ordering matches source
 * - Detecting whitespace issues at tag boundaries
 * - Handling CJK language-specific tag checks
 *
 * Tag validation covers XLIFF tags:
 * - `<g>` - Group/paired tags with content
 * - `<x>` - Self-closing placeholder tags
 * - `<bx>` - Begin paired placeholder
 * - `<ex>` - End paired placeholder
 * - `<ph>` - Placeholder tags
 *
 * @package Utils\LQA\QA
 */
class TagChecker
{
    /** @var ErrorManager Error manager for reporting tag errors */
    protected ErrorManager $errorManager;

    /** @var DomHandler DOM handler for tag structure access */
    protected DomHandler $domHandler;

    /** @var FeatureSet|null Feature set for plugin customizations */
    protected ?FeatureSet $featureSet = null;

    /** @var array Tags with position errors */
    protected array $tagPositionError = [];

    /** @var QA|null Parent QA instance for feature set callbacks */
    protected ?QA $qaInstance = null;

    /** @var string Source segment for tag analysis */
    protected string $sourceSeg;

    /** @var string Target segment for tag analysis (may be modified) */
    protected string $targetSeg;

    /** @var string|null Source language code for CJK handling */
    protected ?string $sourceSegLang = null;

    /** @var string|null Target language code for CJK handling */
    protected ?string $targetSegLang = null;

    /**
     * Creates a new TagChecker instance.
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
     * Sets the parent QA instance for feature set callbacks.
     *
     * @param QA $qa The parent QA instance
     * @return void
     */
    public function setQAInstance(QA $qa): void
    {
        $this->qaInstance = $qa;
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
     * Gets the target segment (may be modified during boundary checks).
     *
     * @return string The target segment
     */
    public function getTargetSeg(): string
    {
        return $this->targetSeg;
    }

    /**
     * Sets the source segment language code.
     *
     * @param string|null $lang Language code (e.g., 'en-US', 'ja-JP')
     * @return void
     */
    public function setSourceSegLang(?string $lang): void
    {
        $this->sourceSegLang = $lang;
    }

    /**
     * Sets the target segment language code.
     *
     * @param string|null $lang Language code (e.g., 'it-IT', 'zh-CN')
     * @return void
     */
    public function setTargetSegLang(?string $lang): void
    {
        $this->targetSegLang = $lang;
    }

    public function getTagPositionError(): array
    {
        return $this->tagPositionError;
    }

    /**
     * Check for tag mismatch
     *
     * @throws Exception
     */
    public function checkTagMismatch(): void
    {
        $srcDomMap = $this->domHandler->getSrcDomMap();
        $trgDomMap = $this->domHandler->getTrgDomMap();

        $targetNumDiff = $this->checkTagCountMismatch(
            count($srcDomMap['DOMElement']),
            count($trgDomMap['DOMElement'])
        );

        if ($targetNumDiff == 0) {
            $this->checkTagCountMismatch(count(@$srcDomMap['g']), count(@$trgDomMap['g']));
        }

        if ($targetNumDiff == 0 && (isset($srcDomMap['innerHTML']) || isset($trgDomMap['innerHTML']))) {
            $innerHtmlArray = array_diff_assoc($srcDomMap['innerHTML'], $trgDomMap['innerHTML']);
            $diffArray = array_diff_assoc($srcDomMap['refID'], $trgDomMap['refID']);
            if (!empty($innerHtmlArray) && !empty($diffArray) && !empty($trgDomMap['DOMElement'])) {
                $this->errorManager->addError(ErrorManager::ERR_TAG_ID);
            }
        }
    }

    /**
     * Check for tag count mismatch
     *
     * @throws Exception
     */
    protected function checkTagCountMismatch(int $srcNodeCount, int $trgNodeCount): int
    {
        if ($this->featureSet && $this->qaInstance) {
            $this->errorManager->addError($this->featureSet->filter('checkTagMismatch', ErrorManager::ERR_NONE, $this->qaInstance));
        }

        if ($srcNodeCount != $trgNodeCount) {
            $errorCode = ($this->featureSet && $this->qaInstance)
                ? $this->featureSet->filter('checkTagMismatch', ErrorManager::ERR_COUNT, $this->qaInstance)
                : ErrorManager::ERR_COUNT;
            $this->errorManager->addError($errorCode);
        }

        return $trgNodeCount - $srcNodeCount;
    }

    /**
     * Check for errors in tag position
     *
     * @throws Exception
     */
    public function checkTagPositions(): void
    {
        $customCheckTagPositions = ($this->featureSet && $this->qaInstance)
            ? $this->featureSet->filter('checkTagPositions', ErrorManager::ERR_NONE, $this->qaInstance)
            : ErrorManager::ERR_NONE;

        if ($customCheckTagPositions !== true) {
            $this->performTagPositionCheck($this->sourceSeg, $this->targetSeg);
        }
    }

    /**
     * Perform tag position check
     */
    public function performTagPositionCheck(string $source, string $target, bool $performIdCheck = true, bool $performTagPositionsCheck = true): void
    {
        $regexpMatch = '#<([^/]+?)>|<(/.+?)>|<([^>]+?)/>#';

        preg_match_all($regexpMatch, $source, $__complete_toCheckSrcStruct);
        $_opening_toCheckSrcStruct = $__complete_toCheckSrcStruct[1];
        $_closing_toCheckSrcStruct = $__complete_toCheckSrcStruct[2];
        $_selfClosing_toCheckSrcStruct = $__complete_toCheckSrcStruct[3];

        preg_match_all($regexpMatch, $target, $__complete_toCheckTrgStruct);
        $_opening_toCheckTrgStruct = $__complete_toCheckTrgStruct[1];
        $_closing_toCheckTrgStruct = $__complete_toCheckTrgStruct[2];
        $_selfClosing_toCheckTrgStruct = $__complete_toCheckTrgStruct[3];

        $_normalizedSrcTags = $this->normalizeTags($_opening_toCheckSrcStruct, $_selfClosing_toCheckSrcStruct);
        $_normalizedTrgTags = $this->normalizeTags($_opening_toCheckTrgStruct, $_selfClosing_toCheckTrgStruct);

        $srcAllTagId = $this->extractIdAttributes($__complete_toCheckSrcStruct[0]);
        $trgAllTagId = $this->extractIdAttributes($__complete_toCheckTrgStruct[0]);

        $srcTagEquivText = $this->extractEquivTextAttributes($_normalizedSrcTags);
        $trgTagEquivText = $this->extractEquivTextAttributes($_normalizedTrgTags);

        $this->checkContentAndAddTagMismatchError($srcTagEquivText, $trgTagEquivText, $__complete_toCheckTrgStruct[0]);

        if ($performIdCheck) {
            $this->checkContentAndAddTagMismatchError($srcAllTagId, $trgAllTagId, $__complete_toCheckTrgStruct[0]);
        }

        if (!$this->errorManager->thereAreErrors() && $performTagPositionsCheck) {
            $this->checkTagPositionsAndAddTagOrderError($_normalizedSrcTags, $_normalizedTrgTags);
            $this->checkTagPositionsAndAddTagOrderError($_closing_toCheckSrcStruct, $_closing_toCheckTrgStruct);
        }

        if ($this->errorManager->thereAreErrors()) {
            $this->domHandler->getTagDiff($this->sourceSeg, $this->targetSeg);
        }
    }

    private function normalizeTags(array $_opening_toCheck, array $_selfClosing_toCheck): array
    {
        $_normalizedTags = array_filter($_opening_toCheck, function ($v) {
            return !empty($v);
        });

        foreach ($_selfClosing_toCheck as $p => $v) {
            if (!empty($v)) {
                $_normalizedTags[$p] = $v;
            }
        }

        return $_normalizedTags;
    }

    private function extractIdAttributes(array $tags): array
    {
        $matches = [];

        foreach ($tags as $tag) {
            preg_match_all('/id\s*=\s*["\']([^"\']+)["\']\s*/', $tag, $idMatch);

            if (!empty($idMatch[1][0])) {
                $matches[] = $idMatch[1][0];
            }
        }

        return $matches;
    }

    private function extractEquivTextAttributes(array $tags): array
    {
        $matches = [];

        foreach ($tags as $tag) {
            preg_match_all('/equiv-text\s*=\s*["\']base64:([^"\']+)["\']\s*/', $tag, $equivTextMatch);

            if (!empty($equivTextMatch[1][0])) {
                $matches[] = $equivTextMatch[1][0];
            }
        }

        return $matches;
    }

    private function checkTagPositionsAndAddTagOrderError(array $src, array $trg): void
    {
        foreach ($trg as $pos => $value) {
            if ($value !== ($src[$pos] ?? null)) {
                if (!empty($value)) {
                    $this->errorManager->addError(ErrorManager::ERR_TAG_ORDER);
                    $this->tagPositionError[] = '&lt;' . $value . '&gt;';
                    return;
                }
            }
        }
    }

    private function checkContentAndAddTagMismatchError(array $src, array $trg, array $originalTargetValues): void
    {
        foreach ($trg as $pos => $value) {
            $index = array_search($value, $src);

            if ($index === false) {
                $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);
                $this->tagPositionError[] = '&lt;' . $originalTargetValues[$pos] . '&gt;';
                return;
            } else {
                unset($src[$index]);
            }
        }
    }

    /**
     * Check for tags boundary whitespace issues
     */
    public function checkTagsBoundary(): void
    {
        // Check first char line if tags are not present
        preg_match_all('#^[\s\t\x{a0}\r\n]+[^<]+#u', $this->sourceSeg, $source_tags);
        preg_match_all('#^[\s\t\x{a0}\r\n]+[^<]+#u', $this->targetSeg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if (count($source_tags) != count($target_tags)) {
            $num = abs(count($source_tags) - count($target_tags));
            for ($i = 0; $i < $num; $i++) {
                $this->errorManager->addError(ErrorManager::ERR_WS_HEAD);
            }
        }

        // Get special chars before a tag or after a closing g tag
        preg_match_all('#</g>[\s\t\x{a0}\r\n.,;!?]+|[\s\t\x{a0}\r\n]+<(?:(?:x|ph)[^>]+|[^/>]+)>#u', rtrim($this->sourceSeg), $source_tags);
        preg_match_all('#</g>[\s\t\x{a0}\r\n.,;!?]+|[\s\t\x{a0}\r\n]+<(?:(?:x|ph)[^>]+|[^/>]+)>#u', rtrim($this->targetSeg), $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];

        $this->checkWhiteSpaces($source_tags, $target_tags);

        // Get special chars after x or ph tags
        preg_match_all('#<(?:(?:x|ph)[^>]+|[^/>]+)>+[\s\t\x{a0}\r\n,.;!?]#u', $this->sourceSeg, $source_tags);
        preg_match_all('#<(?:(?:x|ph)[^>]+|[^/>]+)>+[\s\t\x{a0}\r\n,.;!?]#u', $this->targetSeg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];

        $this->checkWhiteSpaces($source_tags, $target_tags);

        // Get special chars between G TAGS
        preg_match_all('#</[^>]+>[\s\t\x{a0}\r\n]+.*<[^/>]+>#u', $this->sourceSeg, $source_tags);
        preg_match_all('#</[^>]+>[\s\t\x{a0}\r\n]+.*<[^/>]+>#u', $this->targetSeg, $target_tags);
        $source_tags = $source_tags[0];
        $target_tags = $target_tags[0];
        if (count($source_tags) != count($target_tags)) {
            $num = abs(count($source_tags) - count($target_tags));
            for ($i = 0; $i < $num; $i++) {
                $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_HEAD_TEXT);
            }
        }

        // Get special chars at end of line
        preg_match_all('/([\s\t\x{a0}\r\n]+)$/u', $this->sourceSeg, $source_tags);
        preg_match_all('/([\s\t\x{a0}\r\n]+)$/u', $this->targetSeg, $target_tags);

        if ((count($source_tags[0]) != count($target_tags[0])) && !empty($source_tags[0]) || $source_tags[1] != $target_tags[1]) {
            $sourceHasTrailingSpace = (strlen($this->sourceSeg) !== strlen(rtrim($this->sourceSeg)));

            if ($sourceHasTrailingSpace) {
                if (false === CatUtils::isCJ($this->targetSegLang)) {
                    $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_TAIL);
                    $this->targetSeg = rtrim($this->targetSeg);
                    $this->targetSeg .= ' ';
                }
            } else {
                $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_TAIL);
            }
        } elseif ((count($source_tags[0]) != count($target_tags[0])) && empty($source_tags[0])) {
            $this->targetSeg = rtrim($this->targetSeg);
        }

        // Handle CJ source with a non-CJ target
        if (CatUtils::isCJ($this->sourceSegLang) && false === CatUtils::isCJ($this->targetSegLang)) {
            $lastChar = CatUtils::getLastCharacter($this->sourceSeg);
            if (in_array($lastChar, CatUtils::CJKFullwidthPunctuationChars())) {
                $this->targetSeg = rtrim($this->targetSeg);
                $this->targetSeg .= ' ';
            }
        }

        $this->domHandler->setTrgDom($this->domHandler->loadDom($this->targetSeg, ErrorManager::ERR_TARGET));
        $this->domHandler->setNormalizedTrgDOM($this->domHandler->getTrgDom());
    }

    private function checkWhiteSpaces(array $source_tags, array $target_tags): void
    {
        $diffS = array_diff($target_tags, $source_tags);
        $diffT = array_diff($source_tags, $target_tags);

        $this->checkDiff($diffS);
        $this->checkDiff($diffT);
    }

    private function checkDiff(array $diff = []): void
    {
        foreach ($diff as $diffItem) {
            if ($diffItem !== rtrim($diffItem)) {
                $this->errorManager->addError(ErrorManager::ERR_SPACE_MISMATCH_AFTER_TAG);
            } elseif ($diffItem !== ltrim($diffItem)) {
                $this->errorManager->addError(ErrorManager::ERR_SPACE_MISMATCH_BEFORE_TAG);
            }
        }
    }
}

