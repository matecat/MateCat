<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;
use Utils\TaskRunner\Commons\QueueElement;

interface EngineServiceInterface
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTMMatches(array $config, FeatureSet $featureSet, ?int $mtPenalty): array;

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function getMTTranslation(array $config, FeatureSet $featureSet, ?int $mtPenalty, QueueElement $queueElement): array;
}
