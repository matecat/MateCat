<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;
use Random\RandomException;

interface ProjectCompletionServiceInterface
{
    /**
     * Finalize the project when its analysis is complete. Failures after the completion lock is taken are logged, not thrown.
     *
     * @throws RandomException when no random source is available for the completion lock token
     */
    public function tryCloseProject(int $pid, string $projectPassword, string $queueKey, FeatureSet $featureSet): void;

    /**
     * Returns per-job word count summary with a ROLLUP totals row (last element).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjectSegmentsTranslationSummary(int $pid): array;
}
