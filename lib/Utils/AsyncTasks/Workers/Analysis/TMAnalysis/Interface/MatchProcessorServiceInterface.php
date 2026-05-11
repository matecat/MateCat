<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;

interface MatchProcessorServiceInterface
{
    public function isMtMatch(array $match): bool;

    public function sortMatches(array $mtResult, array $tmMatches): array;

    public function detectIcuErrors(string $source, string $target, array $match): ?array;

    public function postProcessMatch(string $segment, string $source, string $target, array $match, FeatureSet $featureSet): array;

    public function calculateWordDiscount(string $matchType, float $rawWordCount, array $payableRates): array;

    public function determinePreTranslateStatus(array $tmData, object $params): array;

    public function getProjectSegmentsTranslationSummary(int $pid): array;
}
