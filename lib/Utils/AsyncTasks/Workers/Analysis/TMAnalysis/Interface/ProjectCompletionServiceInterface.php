<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;

interface ProjectCompletionServiceInterface
{
    public function tryCloseProject(int $pid, string $projectPassword, string $queueKey, FeatureSet $featureSet): void;

    /**
     * Returns per-job word count summary with a ROLLUP totals row (last element).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjectSegmentsTranslationSummary(int $pid): array;
}
