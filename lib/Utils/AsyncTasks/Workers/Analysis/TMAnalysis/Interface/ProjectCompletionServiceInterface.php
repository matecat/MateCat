<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Model\FeaturesBase\FeatureSet;

interface ProjectCompletionServiceInterface
{
    public function tryCloseProject(int $pid, string $projectPassword, string $queueKey, FeatureSet $featureSet): void;
}
