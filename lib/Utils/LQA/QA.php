<?php

namespace Utils\LQA;

use DOMException;
use Exception;
use LogicException;
use Matecat\ICU\MessagePatternComparator;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentMetadataStruct;
use Utils\LQA\BxExG\Validator;
use Utils\LQA\QA\ContentPreprocessor;
use Utils\LQA\QA\DomHandler;
use Utils\LQA\QA\ErrObject;
use Utils\LQA\QA\ErrorManager;
use Utils\LQA\QA\ICUChecker;
use Utils\LQA\QA\SizeRestrictionChecker;
use Utils\LQA\QA\SymbolChecker;
use Utils\LQA\QA\TagChecker;
use Utils\LQA\QA\WhitespaceChecker;

/**
 * Translation string quality assurance (QA) checker.
 *
 * This class performs integrity comparison of XML (XLIFF) strings between source and target segments.
 * It detects various types of mismatches including:
 * - Tag count and structure mismatches
 * - Whitespace inconsistencies (head/tail spaces, tabs, newlines)
 * - Symbol mismatches (€, @, &, £, %, etc.)
 * - ICU message pattern validation
 * - Size/character limit restrictions
 *
 * The class uses a facade pattern, delegating to specialized checker classes:
 * - {@see ErrorManager}: Handles error codes, messages, tips, and exception lists
 * - {@see ContentPreprocessor}: Handles ASCII character replacement and entity preprocessing
 * - {@see DomHandler}: Handles DOM loading, mapping, and XML structure analysis
 * - {@see TagChecker}: Handles tag mismatch, position, and boundary checks
 * - {@see WhitespaceChecker}: Handles whitespace consistency checks
 * - {@see SymbolChecker}: Handles symbol consistency checks
 * - {@see ICUChecker}: Handles ICU message pattern validation
 * - {@see SizeRestrictionChecker}: Handles character limit validation
 *
 * @package Utils\LQA
 */
class QA
{
    // ========== Error Constants (re-exported for backward compatibility) ==========

    /** @var int No error */
    public const int ERR_NONE = ErrorManager::ERR_NONE;
    public const int ERR_COUNT = ErrorManager::ERR_COUNT;
    public const int ERR_SOURCE = ErrorManager::ERR_SOURCE;
    public const int ERR_TARGET = ErrorManager::ERR_TARGET;
    public const int ERR_TAG_ID = ErrorManager::ERR_TAG_ID;
    public const int ERR_WS_HEAD = ErrorManager::ERR_WS_HEAD;
    public const int ERR_WS_TAIL = ErrorManager::ERR_WS_TAIL;
    public const int ERR_TAB_HEAD = ErrorManager::ERR_TAB_HEAD;
    public const int ERR_TAB_TAIL = ErrorManager::ERR_TAB_TAIL;
    public const int ERR_CR_HEAD = ErrorManager::ERR_CR_HEAD;
    public const int ERR_CR_TAIL = ErrorManager::ERR_CR_TAIL;
    public const int ERR_BOUNDARY_HEAD = ErrorManager::ERR_BOUNDARY_HEAD;
    public const int ERR_BOUNDARY_TAIL = ErrorManager::ERR_BOUNDARY_TAIL;
    public const int ERR_UNCLOSED_X_TAG = ErrorManager::ERR_UNCLOSED_X_TAG;
    public const int ERR_BOUNDARY_HEAD_TEXT = ErrorManager::ERR_BOUNDARY_HEAD_TEXT;
    public const int ERR_TAG_ORDER = ErrorManager::ERR_TAG_ORDER;
    public const int ERR_NEWLINE_MISMATCH = ErrorManager::ERR_NEWLINE_MISMATCH;
    public const int ERR_DOLLAR_MISMATCH = ErrorManager::ERR_DOLLAR_MISMATCH;
    public const int ERR_AMPERSAND_MISMATCH = ErrorManager::ERR_AMPERSAND_MISMATCH;
    public const int ERR_AT_MISMATCH = ErrorManager::ERR_AT_MISMATCH;
    public const int ERR_HASH_MISMATCH = ErrorManager::ERR_HASH_MISMATCH;
    public const int ERR_POUNDSIGN_MISMATCH = ErrorManager::ERR_POUNDSIGN_MISMATCH;
    public const int ERR_PERCENT_MISMATCH = ErrorManager::ERR_PERCENT_MISMATCH;
    public const int ERR_EQUALSIGN_MISMATCH = ErrorManager::ERR_EQUALSIGN_MISMATCH;
    public const int ERR_TAB_MISMATCH = ErrorManager::ERR_TAB_MISMATCH;
    public const int ERR_STARSIGN_MISMATCH = ErrorManager::ERR_STARSIGN_MISMATCH;
    public const int ERR_GLOSSARY_MISMATCH = ErrorManager::ERR_GLOSSARY_MISMATCH;
    public const int ERR_SPECIAL_ENTITY_MISMATCH = ErrorManager::ERR_SPECIAL_ENTITY_MISMATCH;
    public const int ERR_EUROSIGN_MISMATCH = ErrorManager::ERR_EUROSIGN_MISMATCH;
    public const int ERR_UNCLOSED_G_TAG = ErrorManager::ERR_UNCLOSED_G_TAG;
    public const int ERR_ICU_VALIDATION = ErrorManager::ERR_ICU_VALIDATION;
    public const int ERR_TAG_MISMATCH = ErrorManager::ERR_TAG_MISMATCH;
    public const int ERR_SPACE_MISMATCH = ErrorManager::ERR_SPACE_MISMATCH;
    public const int ERR_SPACE_MISMATCH_TEXT = ErrorManager::ERR_SPACE_MISMATCH_TEXT;
    public const int ERR_BOUNDARY_HEAD_SPACE_MISMATCH = ErrorManager::ERR_BOUNDARY_HEAD_SPACE_MISMATCH;
    public const int ERR_BOUNDARY_TAIL_SPACE_MISMATCH = ErrorManager::ERR_BOUNDARY_TAIL_SPACE_MISMATCH;
    public const int ERR_SPACE_MISMATCH_AFTER_TAG = ErrorManager::ERR_SPACE_MISMATCH_AFTER_TAG;
    public const int ERR_SPACE_MISMATCH_BEFORE_TAG = ErrorManager::ERR_SPACE_MISMATCH_BEFORE_TAG;
    public const int ERR_SYMBOL_MISMATCH = ErrorManager::ERR_SYMBOL_MISMATCH;
    public const int ERR_EX_BX_NESTED_IN_G = ErrorManager::ERR_EX_BX_NESTED_IN_G;
    public const int ERR_EX_BX_WRONG_POSITION = ErrorManager::ERR_EX_BX_WRONG_POSITION;
    public const int ERR_EX_BX_COUNT_MISMATCH = ErrorManager::ERR_EX_BX_COUNT_MISMATCH;
    public const int SMART_COUNT_PLURAL_MISMATCH = ErrorManager::SMART_COUNT_PLURAL_MISMATCH;
    public const int SMART_COUNT_MISMATCH = ErrorManager::SMART_COUNT_MISMATCH;
    public const int ERR_SIZE_RESTRICTION = ErrorManager::ERR_SIZE_RESTRICTION;

    public const string ERROR = ErrorManager::ERROR;
    public const string WARNING = ErrorManager::WARNING;
    public const string INFO = ErrorManager::INFO;

    public const string SIZE_RESTRICTION = SizeRestrictionChecker::SIZE_RESTRICTION;

    // ========== Component Instances ==========

    /** @var ErrorManager Manages error codes, messages, and exception lists */
    protected ErrorManager $errorManager;

    /** @var ContentPreprocessor Handles ASCII replacement and entity preprocessing */
    protected ContentPreprocessor $preprocessor;

    /** @var DomHandler Handles DOM loading, mapping, and structure analysis */
    protected DomHandler $domHandler;

    /** @var TagChecker Handles tag mismatch, position, and boundary checks */
    protected TagChecker $tagChecker;

    /** @var WhitespaceChecker Handles whitespace consistency checks */
    protected WhitespaceChecker $whitespaceChecker;

    /** @var SymbolChecker Handles symbol consistency checks */
    protected SymbolChecker $symbolChecker;

    /** @var ICUChecker Handles ICU message pattern validation */
    protected ICUChecker $icuChecker;

    /** @var SizeRestrictionChecker Handles character limit validation */
    protected SizeRestrictionChecker $sizeRestrictionChecker;

    // ========== Component Getters (for internal use by PostProcess) ==========

    /**
     * Gets the error manager component.
     *
     * @return ErrorManager The error manager instance
     * @internal This method is intended for use by PostProcess only.
     */
    public function getErrorManager(): ErrorManager
    {
        return $this->errorManager;
    }

    /**
     * Gets the content preprocessor component.
     *
     * @return ContentPreprocessor The preprocessor instance
     * @internal This method is intended for use by PostProcess only.
     */
    public function getPreprocessor(): ContentPreprocessor
    {
        return $this->preprocessor;
    }

    /**
     * Gets the DOM handler component.
     *
     * @return DomHandler The DOM handler instance
     * @internal This method is intended for use by PostProcess only.
     */
    public function getDomHandler(): DomHandler
    {
        return $this->domHandler;
    }

    /**
     * Gets the tag checker component.
     *
     * @return TagChecker The tag checker instance
     * @internal This method is intended for use by PostProcess only.
     */
    public function getTagChecker(): TagChecker
    {
        return $this->tagChecker;
    }

    // ========== Segment Data ==========

    /** @var string Preprocessed source segment */
    protected string $source_seg;

    /** @var string Preprocessed target segment */
    protected string $target_seg;

    /** @var string|null Source segment language code (e.g., 'en-US') */
    protected ?string $source_seg_lang = null;

    /** @var string|null Target segment language code (e.g., 'it-IT') */
    protected ?string $target_seg_lang = null;

    // ========== Context ==========

    /** @var JobStruct|null The job chunk associated with this QA check */
    protected ?JobStruct $chunk = null;

    /** @var FeatureSet|null Feature set for plugin-based customizations */
    protected ?FeatureSet $featureSet = null;

    /**
     * Creates a new QA checker instance.
     *
     * Initializes all checker components, preprocesses the source and target segments
     * (replacing non-printable ASCII characters with placeholders), and loads
     * the segments into DOM structures for analysis.
     *
     * @param string|null $source_seg The source segment to check (may contain XML/XLIFF tags)
     * @param string|null $target_seg The target segment to check (may contain XML/XLIFF tags)
     * @param MessagePatternComparator|null $icuPluralsValidator Optional ICU message pattern validator
     * @param bool $string_contains_icu Whether the source contains ICU message patterns
     */
    public function __construct(
        ?string $source_seg = null,
        ?string $target_seg = null,
        ?MessagePatternComparator $icuPluralsValidator = null,
        bool $string_contains_icu = false
    ) {
        // Set UTF-8 encoding for multibyte string functions
        mb_regex_encoding('UTF-8');
        mb_internal_encoding("UTF-8");

        // Initialize all checker components with dependency injection
        $this->errorManager = new ErrorManager();
        $this->preprocessor = new ContentPreprocessor();
        $this->domHandler = new DomHandler($this->errorManager);
        $this->tagChecker = new TagChecker($this->errorManager, $this->domHandler);
        $this->whitespaceChecker = new WhitespaceChecker($this->errorManager, $this->domHandler);
        $this->symbolChecker = new SymbolChecker($this->errorManager);
        $this->icuChecker = new ICUChecker($this->errorManager);
        $this->sizeRestrictionChecker = new SizeRestrictionChecker($this->errorManager);

        // Preprocess segments: replace non-printable chars with placeholders,
        // convert hex entities, and fill empty HTML tags
        $this->source_seg = $this->preprocessor->preprocess($source_seg);
        $this->target_seg = $this->preprocessor->preprocess($target_seg);

        // Load segments into DOM structures for tag analysis
        // This may add errors if XML is malformed
        $this->domHandler->loadDoms($this->source_seg, $this->target_seg);

        // Configure ICU checker for plural form validation
        $this->icuChecker->setIcuPatternComparator($icuPluralsValidator);
        $this->icuChecker->setSourceContainsIcu($string_contains_icu);
    }

    // ========== Configuration Methods ==========

    /**
     * Sets the character count for size restriction validation.
     *
     * @param int|null $characters_count The number of characters in the target segment
     * @return void
     */
    public function setCharactersCount(?int $characters_count, ?SegmentMetadataStruct $limit = null): void
    {
        $this->sizeRestrictionChecker->setCharactersCount((int)$characters_count, $limit);
    }

    /**
     * Gets the source segment language code.
     *
     * @return string|null The language code (e.g., 'en-US') or null if not set
     */
    public function getSourceSegLang(): ?string
    {
        return $this->source_seg_lang;
    }

    /**
     * Sets the source segment language code.
     *
     * This affects how certain checks behave, particularly for CJK languages
     * which have different whitespace handling rules.
     *
     * @param string|null $source_seg_lang The language code (e.g., 'en-US', 'ja-JP')
     * @return void
     */
    public function setSourceSegLang(?string $source_seg_lang): void
    {
        $this->source_seg_lang = $source_seg_lang;
        $this->errorManager->setSourceSegLang($source_seg_lang);
        $this->tagChecker->setSourceSegLang($source_seg_lang);
    }

    /**
     * Gets the target segment language code.
     *
     * @return string|null The language code (e.g., 'it-IT') or null if not set
     */
    public function getTargetSegLang(): ?string
    {
        return $this->target_seg_lang;
    }

    /**
     * Sets the target segment language code.
     *
     * This affects how certain checks behave, particularly for CJK languages
     * which have different whitespace handling rules.
     *
     * @param string|null $target_seg_lang The language code (e.g., 'it-IT', 'zh-CN')
     * @return void
     */
    public function setTargetSegLang(?string $target_seg_lang): void
    {
        $this->target_seg_lang = $target_seg_lang;
        $this->tagChecker->setTargetSegLang($target_seg_lang);
    }

    /**
     * Sets the job chunk associated with this QA check.
     *
     * @param JobStruct|null $chunk The job chunk
     * @return self For method chaining
     */
    public function setChunk(?JobStruct $chunk): self
    {
        $this->chunk = $chunk;
        return $this;
    }

    /**
     * Sets the feature set for plugin-based customizations.
     *
     * The feature set allows plugins to customize QA behavior,
     * such as adding custom error checks or modifying tag validation.
     *
     * @param FeatureSet $featureSet The feature set instance
     * @return self For method chaining
     * @throws Exception If feature set initialization fails
     */
    public function setFeatureSet(FeatureSet $featureSet): self
    {
        $this->featureSet = $featureSet;
        $this->domHandler->setFeatureSet($featureSet);
        $this->tagChecker->setFeatureSet($featureSet);
        $this->tagChecker->setQAInstance($this);
        return $this;
    }

    // ========== Segment Getters ==========

    /**
     * Gets the preprocessed source segment.
     *
     * The returned string has non-printable ASCII characters replaced with placeholders.
     *
     * @return string The preprocessed source segment
     */
    public function getSourceSeg(): string
    {
        return $this->source_seg;
    }

    /**
     * Gets the target segment with output content cleaned.
     *
     * The returned string has placeholders converted back to entities
     * and special characters properly encoded.
     *
     * @return string The cleaned target segment ready for output
     */
    public function getTargetSeg(): string
    {
        return $this->preprocessor->cleanOutputContent($this->target_seg);
    }


    /**
     * Gets the malformed XML structure differences.
     *
     * When XML parsing fails, this returns the tags that differ
     * between source and target segments.
     *
     * @return array{source: array, target: array} Tags present in source but not target and vice versa
     */
    public function getMalformedXmlStructs(): array
    {
        return $this->domHandler->getMalformedXmlStructs();
    }

    /**
     * Gets the tags that have position errors.
     *
     * @return array List of tag strings with position mismatches
     */
    public function getTargetTagPositionError(): array
    {
        return $this->tagChecker->getTagPositionError();
    }

    // ========== Error Methods ==========

    /**
     * Adds a custom error definition to the error map.
     *
     * @param array{code: int, debug?: string, tip?: string} $errorMap Error definition with code, optional debug message, and tip
     * @return void
     */
    public function addCustomError(array $errorMap): void
    {
        $this->errorManager->addCustomError($errorMap);
    }

    /**
     * Adds an error by its error code.
     *
     * The error will be categorized as ERROR, WARNING, or INFO based on the code.
     *
     * @param int $errCode One of the ERR_* constants
     * @return void
     */
    public function addError(int $errCode): void
    {
        $this->errorManager->addError($errCode);
    }

    /**
     * Gets the full exception list organized by severity level.
     *
     * @return array{ERROR: ErrObject[], WARNING: ErrObject[], INFO: ErrObject[]} Errors grouped by severity
     */
    public function getExceptionList(): array
    {
        return $this->errorManager->getExceptionList();
    }

    /**
     * Checks if there are any errors (highest severity).
     *
     * @return bool True if there are errors
     */
    public function thereAreErrors(): bool
    {
        return $this->errorManager->thereAreErrors();
    }

    /**
     * Gets all errors (highest severity level only).
     *
     * @return ErrObject[] Array of error objects
     */
    public function getErrors(): array
    {
        return $this->errorManager->getErrors();
    }

    /**
     * Gets all errors as a JSON string.
     *
     * @return string JSON-encoded array of errors with counts
     */
    public function getErrorsJSON(): string
    {
        return $this->errorManager->getErrorsJSON();
    }

    /**
     * Checks if there are any warnings (includes errors).
     *
     * @return bool True if there are warnings or errors
     */
    public function thereAreWarnings(): bool
    {
        return $this->errorManager->thereAreWarnings();
    }

    /**
     * Gets all warnings (includes errors).
     *
     * @return ErrObject[] Array of warning and error objects
     */
    public function getWarnings(): array
    {
        return $this->errorManager->getWarnings();
    }

    /**
     * Gets all warnings as a JSON string.
     *
     * @return string JSON-encoded array of warnings with counts
     */
    public function getWarningsJSON(): string
    {
        return $this->errorManager->getWarningsJSON();
    }

    /**
     * Checks if there are any notices (includes warnings and errors).
     *
     * @return bool True if there are any issues at any severity level
     */
    public function thereAreNotices(): bool
    {
        return $this->errorManager->thereAreNotices();
    }

    /**
     * Gets all notices (includes warnings and errors).
     *
     * @return ErrObject[] Array of all issue objects at any severity
     */
    public function getNotices(): array
    {
        return $this->errorManager->getNotices();
    }

    /**
     * Gets all notices as a JSON string.
     *
     * @return string JSON-encoded array of all issues
     */
    public function getNoticesJSON(): string
    {
        return $this->errorManager->getNoticesJSON();
    }

    /**
     * Converts a JSON error string back to an exception list.
     *
     * @param string $jsonString JSON-encoded array of error objects
     * @return array{ERROR: ErrObject[], WARNING: ErrObject[], INFO: ErrObject[]} Reconstructed exception list
     */
    public static function JSONtoExceptionList(string $jsonString): array
    {
        return ErrorManager::JSONtoExceptionList($jsonString);
    }

    // ========== Main Check Methods ==========

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
        // Prepare DOM structures; return early if DOM parsing fails
        try {
            [$srcNodeList, $trgNodeList] = $this->domHandler->prepareDOMStructures();
        } catch (DOMException $ex) {
            return $this->getErrors();
        }


        // ICU patterns require special handling - skip normal tag checks
        if (!$this->icuChecker->hasIcuPatterns()) {
            // Synchronize segments to all checker components
            $this->updateCheckerSegments();

            // Check whitespace around tags (between tags and text)
            $this->tagChecker->checkTagsBoundary();
            $this->updateTargetFromTagChecker();

            // Validate tag structure matches between source and target
            $this->tagChecker->checkTagMismatch();
            if ($this->thereAreErrors()) {
                // Get detailed diff for error reporting
                $this->domHandler->getTagDiff($this->source_seg, $this->target_seg);
            }

            // Check whitespace inside tag content
            $this->whitespaceChecker->checkContentConsistency($srcNodeList, $trgNodeList);

            // Validate bx/ex tags are not incorrectly nested in g tags
            $this->checkBxAndExInsideG();

            // Check tag ordering matches source
            $this->tagChecker->checkTagPositions();

            // Validate newline placeholder consistency
            $this->whitespaceChecker->checkNewLineConsistency();

            // Check special symbols match (€, @, &, £, %, etc.)
            $this->symbolChecker->checkSymbolConsistency();

            // Validate character count limits if configured
            $this->sizeRestrictionChecker->checkSizeRestriction();
        } else {
            // For ICU patterns, only check plural form consistency
            $this->icuChecker->checkICUMessageConsistency();
        }

        return $this->getErrors();
    }

    /**
     * Performs integrity check only for tag structure mismatch.
     *
     * This is a lightweight check that only validates:
     * - Tag count matches between source and target
     * - Tag IDs are consistent
     * - bx/ex tag nesting is correct
     * - Size restrictions are met
     *
     * Use this when you only need to verify tag structure without
     * full whitespace and symbol validation.
     *
     * @return ErrObject[] Array of error objects found
     * @throws Exception If feature set operations fail
     */
    public function performTagCheckOnly(): array
    {
        try {
            $this->domHandler->prepareDOMStructures();
        } catch (DOMException $ex) {
            return $this->getErrors();
        }

        $this->updateCheckerSegments();
        $this->tagChecker->checkTagMismatch();
        $this->checkBxAndExInsideG();
        $this->sizeRestrictionChecker->checkSizeRestriction();

        return $this->getErrors();
    }


    /**
     * Performs tag position check on arbitrary source/target strings.
     *
     * This allows checking tag positions without creating a new QA instance.
     * Useful for validating tag positions in transformed content.
     *
     * @param string $source The source string to check
     * @param string $target The target string to check
     * @param bool $performIdCheck Whether to validate tag ID consistency
     * @param bool $performTagPositionsCheck Whether to validate tag ordering
     * @return void
     */
    public function performTagPositionCheck(
        string $source,
        string $target,
        bool $performIdCheck = true,
        bool $performTagPositionsCheck = true
    ): void {
        $this->tagChecker->performTagPositionCheck($source, $target, $performIdCheck, $performTagPositionsCheck);
    }

    /**
     * Prepares DOM structures without running any checks.
     *
     * This populates the normalizedTrgDOM required for getTrgNormalized()
     * without adding any validation errors. Useful when you need the
     * normalized output without validation.
     *
     * @return void
     * @throws DOMException If DOM structure preparation fails
     */
    public function prepareDOMStructures(): void
    {
        $this->domHandler->prepareDOMStructures();
    }

    /**
     * Returns the target string normalized to match source whitespace.
     *
     * The returned string has head and tail spaces adjusted to match
     * the source segment, with all preprocessing placeholders converted
     * back to their original entities.
     *
     * @return string The normalized target segment
     * @throws LogicException If called when there are errors (normalization requires valid structure)
     */
    public function getTrgNormalized(): string
    {
        if (!$this->thereAreErrors()) {
            $normalizedTrgDOM = $this->domHandler->getNormalizedTrgDOM();
            // Extract content from root wrapper element
            preg_match('/<root>(.*)<\/root>/us', $normalizedTrgDOM->saveXML($normalizedTrgDOM->documentElement), $matches);
            return $this->preprocessor->cleanOutputContent($matches[1] ?? '');
        }

        throw new LogicException(__METHOD__ . " call when errors found in Source/Target integrity check & comparison.");
    }

    // ========== Private Helpers ==========

    /**
     * Checks for <bx> and/or <ex> tags incorrectly nested inside <g> tags.
     *
     * In XLIFF, bx/ex tags should not be nested inside g tags differently
     * between source and target. This validates the nesting is consistent.
     *
     * @return void
     */
    protected function checkBxAndExInsideG(): void
    {
        $bxExGValidator = new Validator($this);
        $errors = $bxExGValidator->validate();

        foreach ($errors as $error) {
            $this->errorManager->addError($error);
        }
    }

    /**
     * Updates all checker components with the current segment data.
     *
     * This ensures all checkers have synchronized copies of the
     * source and target segments for their validation logic.
     *
     * @return void
     */
    private function updateCheckerSegments(): void
    {
        $this->tagChecker->setSegments($this->source_seg, $this->target_seg);
        $this->whitespaceChecker->setSegments($this->source_seg, $this->target_seg);
        $this->symbolChecker->setSegments($this->source_seg, $this->target_seg);
    }

    /**
     * Updates the target segment from the tag checker.
     *
     * The tag checker may modify the target segment during boundary
     * checks (e.g., normalizing trailing whitespace). This syncs
     * those changes back to the main target_seg property.
     *
     * @return void
     */
    private function updateTargetFromTagChecker(): void
    {
        $this->target_seg = $this->tagChecker->getTargetSeg();
    }
}
