<?php

namespace Model\ProjectCreation;

use Model\Xliff\DTO\XliffRuleInterface;

/**
 * Represents a pre-translated segment flowing through the creation pipeline.
 *
 * Created by {@see SegmentExtractor} with target, source (Layer 0), rawWordCount,
 * rule, and optionally mrkPosition and state.
 * Completed by {@see SegmentStorageService::storeSegments()} with DB-assigned segment metadata.
 * Enriched by {@see QAProcessor::process()} with QA consistency-check results.
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

    // Set by QAProcessor::process()
    public string $translationLayer0;
    public string $suggestionLayer0;
    public string $serializedErrors = '';
    public int $warning = 0;

    public function __construct(
        public readonly string $target,
        public readonly string $source,
        public readonly int $rawWordCount,
        public readonly XliffRuleInterface $rule,
        public readonly ?int $mrkPosition = null,
        public readonly ?string $state = null,
    ) {
    }
}
