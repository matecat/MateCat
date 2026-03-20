<?php

namespace Model\ProjectCreation;

use Model\Xliff\DTO\XliffRuleInterface;

/**
 * Represents a pre-translated segment flowing through the creation pipeline.
 *
 * Created by {@see SegmentExtractor} with target, source (Layer 0), rawWordCount,
 * and optionally mrkPosition, rule, and state.
 * Completed by {@see SegmentStorageService::storeSegments()} with DB-assigned segment metadata.
 * Consumed by {@see SegmentStorageService::insertPreTranslations()} to build SQL inserts.
 */
class TranslationTuple
{
    // Set by SegmentStorageService::storeSegments()
    public int $segmentId;
    // This is not used, but the information is kept for completeness of information
    public string $internalId;
    public string $segmentHash;
    public int $fileId;

    public function __construct(
        public readonly string $target,
        public readonly string $source,
        public readonly float $rawWordCount,
        public readonly ?int $mrkPosition = null,
        public readonly ?XliffRuleInterface $rule = null,
        public readonly ?string $state = null,
    ) {
    }
}
