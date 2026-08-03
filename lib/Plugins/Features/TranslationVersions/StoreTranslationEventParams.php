<?php

namespace Plugins\Features\TranslationVersions;

use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Propagation\PropagationResult;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;

/**
 * Everything `VersionHandlerInterface::storeTranslationEvent()` needs to record one translation
 * event.
 *
 * Replaces an eight-key array whose shape lived in a docblock. Two costs came with that: callers had
 * no way to be told they had forgotten a key, and the implementation compensated by re-checking at
 * runtime — `TranslationVersionsHandler` threw `storeTranslationEvent requires the acting user in
 * $params['user']` for a key its own docblock already declared mandatory. Eight required,
 * non-nullable constructor parameters move that check to the call site.
 *
 * `$propagation` is non-nullable and defaults to nothing: the two callers that never propagate
 * (`GetSearchController`'s replace-all, and any set-translation where propagation is off) pass
 * `PropagationResult::empty()`. An absent propagation and a propagation that found nothing are the
 * same fact, and collapsing them keeps `propagated_ids` a list everywhere — which
 * `plugins/translated/lib/Features/Translated.php:479` relies on, reading it unguarded into a Kafka
 * payload.
 */
final class StoreTranslationEventParams
{
    public function __construct(
        public readonly SegmentTranslationStruct $translation,
        public readonly SegmentTranslationStruct $oldTranslation,
        public readonly PropagationResult $propagation,
        public readonly JobStruct $chunk,
        public readonly UserStruct $user,
        public readonly int $sourcePageCode,
        public readonly FeatureSet $features,
        public readonly ProjectStruct $project,
    ) {
    }
}
