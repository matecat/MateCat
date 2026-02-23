<?php

namespace Utils\LQA;

use DOMException;
use Exception;
use LogicException;
use Model\FeaturesBase\FeatureSet;
use Utils\LQA\QA\ErrObject;
use Utils\LQA\QA\ErrorManager;
use Utils\Tools\CatUtils;

/**
 * PostProcess provides space realignment functionality for Machine Translation outputs.
 *
 * When MT engines produce translations, they often introduce incorrect whitespace
 * around tags (extra spaces before/after tags, missing spaces, etc.). This class
 * analyzes the source segment's whitespace patterns and adjusts the target segment
 * to match, preserving the original formatting intent.
 *
 * This class uses composition with QA, reusing QA's internal components:
 * - {@see ErrorManager}: For tracking realignment errors (via QA::getErrorManager())
 * - {@see DomHandler}: For XML/DOM operations (via QA::getDomHandler())
 * - {@see TagChecker}: For tag structure validation (via QA::getTagChecker())
 * - {@see ContentPreprocessor}: For segment preprocessing (via QA::getPreprocessor())
 *
 * @package Utils\LQA
 */
class PostProcess
{
    /** @var QA Internal QA instance - provides components and final validation */
    private QA $qa;

    /** @var string Preprocessed source segment */
    private string $source_seg;

    /** @var string Preprocessed target segment (modified during realignment) */
    private string $target_seg;

    /** @var FeatureSet|null Feature set for plugin customizations */
    private ?FeatureSet $featureSet = null;

    /**
     * Creates a new PostProcess instance for MT space realignment.
     *
     * Creates a QA instance and reuses its internal components for processing.
     * The segments are preprocessed via QA's preprocessor and loaded into
     * DOM structures for analysis.
     *
     * @param string|null $source_seg The source segment (reference for whitespace patterns)
     * @param string|null $target_seg The target segment to be realigned
     */
    public function __construct(?string $source_seg = null, ?string $target_seg = null)
    {
        // Create QA instance - we'll reuse its internal components
        $this->qa = new QA($source_seg, $target_seg);

        // Store preprocessed segments for manipulation (reuse QA's preprocessor)
        $this->source_seg = $this->qa->getPreprocessor()->preprocess($source_seg);
        $this->target_seg = $this->qa->getPreprocessor()->preprocess($target_seg);
    }

    /**
     * Sets the feature set for plugin-based customizations.
     *
     * Propagates the feature set to the QA instance which manages all components.
     *
     * @param FeatureSet $featureSet The feature set instance
     * @return self For method chaining
     * @throws Exception If feature set initialization fails
     */
    public function setFeatureSet(FeatureSet $featureSet): self
    {
        $this->featureSet = $featureSet;
        $this->qa->setFeatureSet($featureSet);
        return $this;
    }

    /**
     * Sets the source segment language code.
     *
     * Delegates to the internal QA instance for language-specific processing.
     *
     * @param string|null $lang Language code (e.g., 'en-US', 'ja-JP')
     * @return void
     */
    public function setSourceSegLang(?string $lang): void
    {
        $this->qa->setSourceSegLang($lang);
    }

    /**
     * Sets the target segment language code.
     *
     * Delegates to the internal QA instance for language-specific processing.
     *
     * @param string|null $lang Language code (e.g., 'it-IT', 'zh-CN')
     * @return void
     */
    public function setTargetSegLang(?string $lang): void
    {
        $this->qa->setTargetSegLang($lang);
    }

    /**
     * Realigns whitespace in the target segment to match the source segment patterns.
     *
     * This method performs the following operations:
     * 1. Prepares DOM structures for both source and target
     * 2. Validates tag structure matches (fails early if tags don't match)
     * 3. Analyzes whitespace patterns around tags in a source
     * 4. Adjusts target whitespace to match source patterns
     * 5. Re-validates the adjusted target to ensure it's still valid
     * 6. Updates internal state with the realigned target
     *
     * If the realignment fails (e.g., produces invalid XML), an ERR_TAG_MISMATCH
     * error is added and the original target is preserved.
     *
     * @return void
     * @throws Exception If feature set operations fail
     */
    public function realignMTSpaces(): void
    {
        // Get references to QA's components
        $domHandler = $this->qa->getDomHandler();
        $tagChecker = $this->qa->getTagChecker();
        $errorManager = $this->qa->getErrorManager();

        // Step 1: Prepare DOM structures for analysis
        try {
            $domHandler->prepareDOMStructures();
        } catch (DOMException) {
            return; // Exit silently if DOM preparation fails
        }

        // Step 2: Validate tag structure before attempting realignment
        $tagChecker->setSegments($this->source_seg, $this->target_seg);
        $tagChecker->checkTagMismatch();

        if ($errorManager->thereAreErrors()) {
            // Tag structure doesn't match - cannot realign safely
            $domHandler->getTagDiff($this->source_seg, $this->target_seg);
            return;
        }

        // Step 3-4: Perform the actual whitespace realignment
        [, $target_seg] = $this->realignTMSpaces();

        // Step 5: Re-validate the realigned target by creating a temporary check instance
        $qaCheck = new self($this->source_seg, $target_seg);
        if ($this->featureSet) {
            $qaCheck->setFeatureSet($this->featureSet);
        }
        $qaCheck->qa->performTagCheckOnly();

        // Step 6: Update internal state if realignment was successful
        if (!$qaCheck->qa->thereAreErrors()) {
            // Realignment succeeded - update target segment
            $preprocessor = $this->qa->getPreprocessor();
            $this->target_seg = $preprocessor->preprocess($target_seg);

            // Update DOM with the realigned target
            $domHandler->setTrgDom($domHandler->loadDom($this->target_seg, ErrorManager::ERR_TARGET));
            $domHandler->resetDOMMaps();
            $domHandler->prepareDOMStructures();

            // Create a fresh QA instance with a realigned target for normalization
            $this->qa = new QA($this->source_seg, $target_seg);
            if ($this->featureSet) {
                $this->qa->setFeatureSet($this->featureSet);
            }
            $this->qa->prepareDOMStructures();
        } else {
            // Realignment produced an invalid result - record error
            $errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);
        }
    }

    /**
     * Performs all integrity checks and comparisons on source and target strings.
     *
     * This is the main entry point for full QA validation. It performs the following checks:
     * 1. Prepares DOM structures from both segments
     * 2. For non-ICU content:
     *    - Checks tag boundary whitespace issues
     *    - Validates tag count and structure match
     *    - Checks whitespace consistency inside tags
     *    - Validates bx/ex tag nesting in g tags
     *    - Checks tag position ordering
     *    - Validates newline consistency
     *    - Checks symbol consistency (€, @, &, etc.)
     *    - Validates size restrictions
     * 3. For ICU content:
     *    - Validates ICU message pattern consistency
     *
     * @return ErrObject[] Array of error objects found during validation
     * @throws Exception If feature set operations fail
     */
    public function performConsistencyCheck(): array
    {
        return $this->qa->performConsistencyCheck();
    }

    /**
     * Performs the actual whitespace realignment between source and target.
     *
     * This method works in two passes:
     * 1. First pass: Split by ">" and normalize Headspaces (spaces at the beginning of text after tags)
     * 2. Second pass: Split by "<" and normalize TAIL spaces (spaces at the end of text before tags)
     *
     * The algorithm processes each text segment between tags and adjusts the target
     * whitespace to match the source pattern, handling both regular spaces and
     * non-breaking spaces (NBSP).
     *
     * @return array{0: string, 1: string} Array containing [realigned_source, realigned_target]
     */
    private function realignTMSpaces(): array
    {
        // ========== First Pass: Normalize HEAD spaces ==========
        // Split by ">" to get text segments that START after a tag
        // Example: "<g>text</g>" becomes ["<g", "text</g", ""]
        $source_seg = explode(">", $this->source_seg);
        $target_seg = explode(">", $this->target_seg);

        foreach ($source_seg as $pos => $_str) {
            // Skip empty segments and segments without a corresponding target
            if ($_str == "" || !isset($target_seg[$pos])) {
                continue;
            }
            // Normalize the leading space of the target to match a source
            $target_seg[$pos] = $this->normalizeHeadSpaces($_str, $target_seg[$pos]);
        }

        // Reconstruct strings after the first pass
        $source_seg = implode(">", $source_seg);
        $target_seg = implode(">", $target_seg);

        // ========== Second Pass: Normalize TAIL spaces ==========
        // Split by "<" to get text segments that END before a tag
        // Example: "text<g>more" becomes ["text", "g>more"]
        $source_seg = explode("<", $source_seg);
        $target_seg = explode("<", $target_seg);

        foreach ($source_seg as $pos => $_str) {
            // Skip empty segments and segments without a corresponding target
            if ($_str == "" || !isset($target_seg[$pos])) {
                continue;
            }
            // Normalize the trailing space of the target to match the source
            $target_seg[$pos] = $this->normalizeTailSpaces($_str, $target_seg[$pos]);
        }

        // Reconstruct final strings
        $source_seg = implode("<", $source_seg);
        $target_seg = implode("<", $target_seg);

        return [$source_seg, $target_seg];
    }

    /**
     * Normalizes leading (head) whitespace in target to match a source pattern.
     *
     * This method handles three cases:
     * 1. NBSP type mismatch: If the source starts with NBSP and the target starts with regular space (or vice versa),
     *    convert the target's leading space to match the source's type
     * 2. Missing space: If the source starts with space but the target doesn't, add a leading space to the target
     * 3. Extra space: If the target starts with space but the source doesn't, remove the leading space from the target
     *
     * @param string $srcNodeContent The source text segment (reference)
     * @param string $trgNodeContent The target text segment (to be normalized)
     * @return string The normalized target text segment
     */
    private function normalizeHeadSpaces(string $srcNodeContent, string $trgNodeContent): string
    {
        // Keep the original target for modification
        $_trgNodeContent = $trgNodeContent;

        // Check if source/target start with NBSP specifically
        $srcHasHeadNBSP = $this->hasHeadNBSP($srcNodeContent);
        $trgHasHeadNBSP = $this->hasHeadNBSP($trgNodeContent);

        // Convert NBSP to regular space for position checking
        // This normalizes both types of spaces for comparison
        $srcNodeContent = $this->nbspToSpace($srcNodeContent);
        $trgNodeContent = $this->nbspToSpace($trgNodeContent);

        // Check if the first character is a space (position 0 means starts with space)
        $headSrcWhiteSpaces = mb_stripos($srcNodeContent, " ", 0, 'utf-8');
        $headTrgWhiteSpaces = mb_stripos($trgNodeContent, " ", 0, 'utf-8');

        // ========== NBSP Type Normalization ==========
        // If space types don't match, convert the target to match the source
        if ($srcHasHeadNBSP != $trgHasHeadNBSP && $srcHasHeadNBSP) {
            // Source has NBSP, target has regular space -> convert target to NBSP
            $_trgNodeContent = preg_replace('/^\x{20}/u', CatUtils::unicode2chr(0Xa0), $_trgNodeContent);
        } elseif ($srcHasHeadNBSP != $trgHasHeadNBSP && $trgHasHeadNBSP) {
            // Target has NBSP, the source has regular space -> convert target to regular space
            $_trgNodeContent = preg_replace('/^\x{a0}/u', CatUtils::unicode2chr(0X20), $_trgNodeContent);
        }

        // ========== Space Presence Normalization ==========
        if (($headSrcWhiteSpaces === 0) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces) {
            // Source starts with space, target doesn't -> add space to target
            $_trgNodeContent = " " . $_trgNodeContent;
        } elseif (($headSrcWhiteSpaces !== 0 && $headTrgWhiteSpaces === 0) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces) {
            // Target starts with space, the source doesn't -> remove space from target
            $_trgNodeContent = mb_substr($_trgNodeContent, 1, mb_strlen($_trgNodeContent));
        }

        return $_trgNodeContent;
    }

    /**
     * Normalizes trailing (tail) whitespace in target to match the source pattern.
     *
     * This method handles three cases:
     * 1. NBSP type mismatch: If the source ends with NBSP and the target ends with regular space (or vice versa),
     *    convert the target's trailing space to match the source's type
     * 2. Missing space: If the source ends with space but the target doesn't, add a trailing space to the target
     * 3. Extra space: If the target ends with space but the source doesn't, remove the trailing space from the target
     *
     * @param string $srcNodeContent The source text segment (reference)
     * @param string $trgNodeContent The target text segment (to be normalized)
     * @return string The normalized target text segment
     */
    private function normalizeTailSpaces(string $srcNodeContent, string $trgNodeContent): string
    {
        // Keep the original target for modification
        $_trgNodeContent = $trgNodeContent;

        // Check if source/target end with NBSP specifically
        $srcHasTailNBSP = $this->hasTailNBSP($srcNodeContent);
        $trgHasTailNBSP = $this->hasTailNBSP($trgNodeContent);

        // Convert NBSP to regular space for comparison
        $srcNodeContent = $this->nbspToSpace($srcNodeContent);
        $trgNodeContent = $this->nbspToSpace($trgNodeContent);

        // Get string lengths for last character extraction
        $srcLen = mb_strlen($srcNodeContent);
        $trgLen = mb_strlen($trgNodeContent);

        // Extract the last character from each string
        $trailingSrcChar = mb_substr($srcNodeContent, $srcLen - 1, 1, 'utf-8');
        $trailingTrgChar = mb_substr($trgNodeContent, $trgLen - 1, 1, 'utf-8');

        // ========== NBSP Type Normalization ==========
        // If space types don't match, convert the target to match the source
        if ($srcHasTailNBSP != $trgHasTailNBSP && $srcHasTailNBSP) {
            // Source ends with NBSP, target ends with regular space -> convert target to NBSP
            $_trgNodeContent = preg_replace('/\x{20}$/u', CatUtils::unicode2chr(0Xa0), $_trgNodeContent);
        } elseif ($srcHasTailNBSP != $trgHasTailNBSP && $trgHasTailNBSP) {
            // Target ends with NBSP, the source ends with regular space -> convert target to regular space
            $_trgNodeContent = preg_replace('/\x{a0}$/u', CatUtils::unicode2chr(0X20), $_trgNodeContent);
        }

        // ========== Space Presence Normalization ==========
        if ($trailingSrcChar == " " && $trailingSrcChar != $trailingTrgChar) {
            // Source ends with space, target doesn't -> add space to target
            $_trgNodeContent = $_trgNodeContent . " ";
        } elseif (($trailingSrcChar != " " && $trailingTrgChar == " ") && $trailingSrcChar != $trailingTrgChar) {
            // Target ends with space, the source doesn't -> remove space from target
            $_trgNodeContent = mb_substr($_trgNodeContent, 0, $trgLen - 1);
        }

        return $_trgNodeContent;
    }

    /**
     * Replace non-breaking spaces with regular spaces
     */
    private function nbspToSpace(string $s): string
    {
        return preg_replace("/\x{a0}/u", chr(0x20), $s);
    }

    /**
     * Check if the string starts with a non-breaking space
     */
    private function hasHeadNBSP(string $s): bool
    {
        return (bool)preg_match("/^\x{a0}/u", $s);
    }

    /**
     * Check if the string ends with a non-breaking space
     */
    private function hasTailNBSP(string $s): bool
    {
        return (bool)preg_match("/\x{a0}$/u", $s);
    }

    // ========== Delegate to QA instance ==========

    /**
     * Checks if there are any errors from validation.
     *
     * @return bool True if errors exist
     */
    public function thereAreErrors(): bool
    {
        return $this->qa->thereAreErrors();
    }

    /**
     * Checks if there are any warnings from validation.
     *
     * @return bool True if warnings exist
     */
    public function thereAreWarnings(): bool
    {
        return $this->qa->thereAreWarnings();
    }

    /**
     * Gets the array of error objects.
     *
     * @return array Array of ErrObject instances
     */
    public function getErrors(): array
    {
        return $this->qa->getErrors();
    }

    /**
     * Gets the array of warning objects.
     *
     * @return array Array of ErrObject instances
     */
    public function getWarnings(): array
    {
        return $this->qa->getWarnings();
    }

    /**
     * Gets errors as a JSON string.
     *
     * @return string JSON-encoded errors
     */
    public function getErrorsJSON(): string
    {
        return $this->qa->getErrorsJSON();
    }


    /**
     * Gets the target segment (preprocessed).
     *
     * Used when errors occur and getTrgNormalized() cannot be called.
     *
     * @return string The preprocessed target segment
     */
    public function getTargetSeg(): string
    {
        return $this->qa->getTargetSeg();
    }

    /**
     * Gets the normalized target segment with whitespace adjustments.
     *
     * @return string The normalized target segment
     * @throws LogicException If called when errors exist
     */
    public function getTrgNormalized(): string
    {
        if ($this->thereAreErrors()) {
            throw new LogicException(__METHOD__ . " call when errors found in Source/Target integrity check & comparison.");
        }
        return $this->qa->getTrgNormalized();
    }

    /**
     * Performs tag-only validation without full consistency checks.
     *
     * @return array Array of ErrObject instances
     * @throws Exception If feature set operations fail
     */
    public function performTagCheckOnly(): array
    {
        return $this->qa->performTagCheckOnly();
    }
}
