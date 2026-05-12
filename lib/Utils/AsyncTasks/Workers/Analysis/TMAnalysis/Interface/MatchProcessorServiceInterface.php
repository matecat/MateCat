<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;
use Utils\AsyncTasks\Workers\Interface\MatchSorterInterface;

interface MatchProcessorServiceInterface extends MatchSorterInterface
{
    /**
     * @param array<string, mixed> $match
     *
     * @return array<string, mixed>|null
     */
    public function detectIcuErrors(string $source, string $target, array $match): ?array;

    /**
     * @param array<string, mixed> $match
     *
     * @return array<string, mixed>
     */
    public function postProcessMatch(string $segment, string $source, string $target, array $match, FeatureSet $featureSet): array;

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
