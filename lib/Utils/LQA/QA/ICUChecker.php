<?php

namespace Utils\LQA\QA;

use Exception;
use Matecat\ICU\MessagePatternComparator;

/**
 * Handles ICU MessageFormat pattern validation for internationalized strings.
 *
 * ICU (International Components for Unicode) MessageFormat is used for
 * complex message formatting with plurals, gender, and selections.
 * This class validates that ICU patterns in the source match the target.
 *
 * Example ICU pattern:
 * ```
 * {count, plural, one {# item} other {# items}}
 * ```
 *
 * Validation includes:
 * - Plural form consistency between source and target
 * - Argument name and type matching
 * - Proper syntax in both segments
 *
 * @package Utils\LQA\QA
 */
class ICUChecker
{
    /** @var ErrorManager Error manager for reporting ICU errors */
    protected ErrorManager $errorManager;

    /** @var MessagePatternComparator|null ICU pattern comparator instance */
    protected ?MessagePatternComparator $icuPatternComparator = null;

    /** @var bool Whether the source segment contains ICU patterns */
    protected bool $sourceContainsIcu = false;

    /**
     * Creates a new ICUChecker instance.
     *
     * @param ErrorManager $errorManager Error manager for reporting errors
     */
    public function __construct(ErrorManager $errorManager)
    {
        $this->errorManager = $errorManager;
    }

    /**
     * Sets the ICU pattern comparator for validation.
     *
     * @param MessagePatternComparator|null $comparator The comparator instance
     * @return void
     */
    public function setIcuPatternComparator(?MessagePatternComparator $comparator): void
    {
        $this->icuPatternComparator = $comparator;
    }

    /**
     * Sets whether the source segment contains ICU patterns.
     *
     * @param bool $contains True if source contains ICU patterns
     * @return void
     */
    public function setSourceContainsIcu(bool $contains): void
    {
        $this->sourceContainsIcu = $contains;
    }

    /**
     * Checks if ICU pattern validation should be performed.
     *
     * @return bool True if both a comparator exists and source contains ICU
     */
    public function hasIcuPatterns(): bool
    {
        return $this->icuPatternComparator !== null && $this->sourceContainsIcu;
    }

    /**
     * Validates ICU message pattern consistency between source and target.
     *
     * Uses the MessagePatternComparator to check that plural forms and
     * arguments match between the source and target segments.
     *
     * @return void
     */
    public function checkICUMessageConsistency(): void
    {
        if (!$this->icuPatternComparator) {
            return;
        }

        try {
            $errorMessage = [];
            $complaints = $this->icuPatternComparator->validate(validateTarget: true);
            foreach ($complaints->targetWarnings?->getArgumentWarnings() ?? [] as $complaint) {
                $errorMessage[] = implode('<br/><br/>', $complaint->getMessages());
            }

            if ($errorMessage) {
                $this->errorManager->setErrorMessage(
                    ErrorManager::ERR_ICU_VALIDATION,
                    implode('<br/><br/>', $errorMessage)
                );
                $this->errorManager->addError(ErrorManager::ERR_ICU_VALIDATION);
            }
        } catch (Exception $e) {
            $this->errorManager->setErrorMessage(ErrorManager::ERR_ICU_VALIDATION, $e->getMessage());
            $this->errorManager->addError(ErrorManager::ERR_ICU_VALIDATION);
        }
    }
}

