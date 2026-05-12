<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

interface AnalysisRedisServiceInterface
{
    /**
     * Force-close the underlying Redis connection so the next command
     * opens a fresh TCP socket. Used between retries after connection
     * failures to avoid reusing a corrupt/dead socket.
     */
    public function reconnect(): void;

    public function acquireInitLock(int $pid): bool;

    public function setProjectTotalSegments(int $pid, int $total): void;

    public function getProjectTotalSegments(int $pid): ?int;

    public function getProjectAnalyzedCount(int $pid): ?int;

    public function waitForInitialization(int $pid, int $maxWaitMs = 5000): void;

    public function incrementAnalyzedCount(int $pid, int $numSegments, float $eqWc, float $stWc): void;

    public function setProjectAnalyzedCountTTL(int $pid, int $ttlSeconds = 86400): void;

    /**
     * @return string[]
     */
    public function getWorkingProjects(string $queueKey): array;

    public function decrementWaitingSegments(string $qid): int;

    public function removeProjectFromQueue(string $queueKey, int $pid): void;

    public function acquireCompletionLock(int $pid): bool;

    public function releaseCompletionLock(int $pid): void;

    /**
     * @return array{project_segments: mixed, num_analyzed: mixed, eq_wc: float, st_wc: float}
     */
    public function getProjectWordCounts(int $pid): array;
}
