<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;
use Utils\AsyncTasks\Workers\Interface\MatchSorterInterface;

interface MatchProcessorServiceInterface extends MatchSorterInterface
{
    /**
     * @param string $segment source segment text (Layer 0)
     * @param string $source source language code
     * @param string $target target language code
     * @param array<string, mixed> $match best TM match (contains 'translation', 'segment', 'created_by')
     * @param FeatureSet $featureSet project feature set
     * @param string $matchType resolved fuzzy band from scoring (e.g. InternalMatchesConstants::MT, TM_100, REPETITIONS)
     * @param bool $icuEnabled whether ICU support is enabled for this project
     * @param int $pid project ID (for subfiltering custom handlers)
     *
     * @return array<string, mixed> processed match data with keys: suggestion, warning, serialized_errors_list
     */
    public function postProcessMatch(
        string $segment,
        string $source,
        string $target,
        array $match,
        FeatureSet $featureSet,
        string $matchType,
        bool $icuEnabled,
        int $pid,
    ): array;

    /**
     * @param array<string, float|int> $payableRates
     *
     * @return array{0: string, 1: float|int, 2: float|int}
     */
    public function calculateWordDiscount(string $matchType, float $rawWordCount, array $payableRates): array;

    /**
     * @param array<string, mixed> $tmData
     *
     * @return array<string, mixed>
     */
    public function determinePreTranslateStatus(array $tmData, object $params): array;
}
