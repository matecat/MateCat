<?php

namespace Utils\LQA\QA;

use Model\Segments\SegmentMetadataStruct;

/**
 * Handles size restriction validation for segment character limits.
 *
 * Some translation projects have character limits per segment (e.g., for
 * subtitles, UI strings, or SMS). This class validates that the target
 * segment does not exceed the configured character limit.
 *
 * Character limits are stored in segment metadata with the key "sizeRestriction".
 * A limit of 0 means no restriction.
 *
 * @package Utils\LQA\QA
 */
class SizeRestrictionChecker
{
    /** @var string Metadata key for size restriction */
    public const string SIZE_RESTRICTION = "sizeRestriction";

    /** @var ErrorManager Error manager for reporting size errors */
    private ErrorManager $errorManager;

    /** @var int|null Character count of the target segment */
    private ?int $charactersCount = null;
    private ?SegmentMetadataStruct $limit = null;

    /**
     * Creates a new SizeRestrictionChecker instance.
     *
     * @param ErrorManager $errorManager Error manager for reporting errors
     */
    public function __construct(ErrorManager $errorManager)
    {
        $this->errorManager = $errorManager;
    }

    /**
     * Sets the character count of the target segment.
     *
     * @param int $charactersCount The number of characters
     * @param SegmentMetadataStruct|null $limit
     * @return void
     */
    public function setCharactersCount(int $charactersCount, ?SegmentMetadataStruct $limit = null): void
    {
        $this->charactersCount = $charactersCount;
        $this->limit = $limit ?? new SegmentMetadataStruct(['meta_value' => 0, 'meta_key' => 'sizeRestriction']);
    }

    /**
     * Validates that the segment does not exceed its character limit.
     *
     * Looks up the size restriction from segment metadata and compares
     * it against the configured character count.
     *
     * @return void
     */
    public function checkSizeRestriction(): void
    {
        if (!$this->filterCheckSizeRestriction()) {
            $this->errorManager->addError(ErrorManager::ERR_SIZE_RESTRICTION);
        }
    }

    /**
     * Check if the segment meets the size restriction
     * @return bool
     */
    private function filterCheckSizeRestriction(): bool
    {
        if (!$this->charactersCount) {
            return true;
        }

        if (($this->limit ?? null) && ($this->limit->meta_key ?? null) == self::SIZE_RESTRICTION) {
            // Ignore sizeRestriction = 0
            if ($this->limit->meta_value == 0) {
                return true;
            }

            return $this->charactersCount <= $this->limit->meta_value;
        }

        return true;
    }
}

