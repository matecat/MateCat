<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;

interface EngineServiceInterface
{
    public function getTMMatches(array $config, FeatureSet $featureSet, ?int $mtPenalty): array;

    public function getMTTranslation(array $config, FeatureSet $featureSet, ?int $mtPenalty, bool $skipAnalysis): array;
}
