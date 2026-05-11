<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

interface AnalysisRedisServiceInterface
{
    public function acquireInitLock(int $pid): bool;

    public function setProjectTotalSegments(int $pid, int $total): void;

    public function getProjectTotalSegments(int $pid): ?int;

    public function getProjectAnalyzedCount(int $pid): ?int;

    public function waitForInitialization(int $pid, int $maxWaitMs = 5000): void;

    public function incrementAnalyzedCount(int $pid, int $numSegments, int $eqWc, int $stWc): void;

    public function getWorkingProjects(string $queueKey): array;

    public function decrementWaitingSegments(string $qid): int;

    public function removeProjectFromQueue(string $queueKey, int $pid): void;

    public function reAddProjectToQueue(string $queueKey, int $pid): void;

    public function acquireCompletionLock(int $pid): bool;

    public function releaseCompletionLock(int $pid): void;

    public function getProjectWordCounts(int $pid): array;
}
