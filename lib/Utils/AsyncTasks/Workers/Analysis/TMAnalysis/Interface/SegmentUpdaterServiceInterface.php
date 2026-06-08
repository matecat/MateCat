<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

interface SegmentUpdaterServiceInterface
{
    /**
     * @param array<string, mixed> $tmData
     */
    public function setAnalysisValue(array $tmData): int;

    public function forceSetSegmentAnalyzed(int $idSegment, int $idJob): bool;
}
