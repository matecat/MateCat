<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;
use Utils\AsyncTasks\Workers\Interface\MatchSorterInterface;

interface MatchProcessorServiceInterface extends MatchSorterInterface
{
    public function detectIcuErrors(string $source, string $target, array $match): ?array;

    public function postProcessMatch(string $segment, string $source, string $target, array $match, FeatureSet $featureSet): array;

    public function calculateWordDiscount(string $matchType, float $rawWordCount, array $payableRates): array;

    public function determinePreTranslateStatus(array $tmData, object $params): array;
}
