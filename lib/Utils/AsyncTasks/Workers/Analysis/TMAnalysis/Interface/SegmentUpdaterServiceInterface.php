<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

interface SegmentUpdaterServiceInterface
{
    public function setAnalysisValue(array $tmData): int;

    public function forceSetSegmentAnalyzed(int $idSegment, int $idJob, float $rawWordCount): bool;
}
