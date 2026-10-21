<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use Random\RandomException;

interface AnalysisRedisServiceInterface
{
    /**
     * Force-close the underlying Redis connection so the next command
     * opens a fresh TCP socket. Used between retries after connection
     * failures to avoid reusing a corrupt/dead socket.
     */
    public function reconnect(): void;

    /**
     * Take the project's init lock, so only one worker initializes its counters.
     *
     * @return string|null the token to pass to releaseInitLock(), or null while another worker holds the lock
     * @throws RandomException when no random source is available for the token
     * @phpstan-impure
     */
    public function acquireInitLock(int $pid): ?string;

    /**
     * Release the init semaphore eagerly so the next worker can re-attempt init
     * without waiting out the lock TTL. Called on failed/incomplete doInit().
     * Does nothing when the lock expired and another worker took it: that lock is not ours.
     */
    public function releaseInitLock(int $pid, string $token): void;

    /**
     * Atomically initialize all project counters in a single MULTI/EXEC transaction.
     * Writes PROJECT_TOT_SEGMENTS first, PROJECT_NUM_SEGMENTS_DONE last.
     */
    public function initializeProjectCounters(int $pid, int $projectSegments, int $numAnalyzed): void;

    public function clearProjectCounters(int $pid): void;

    public function setProjectTotalSegments(int $pid, int $total): void;

    public function getProjectTotalSegments(int $pid): ?int;

    public function getProjectAnalyzedCount(int $pid): ?int;

    /**
     * Polls for both PROJECT_TOT_SEGMENTS and PROJECT_NUM_SEGMENTS_DONE.
     * Returns true if init is complete, false on timeout (winner likely crashed).
     */
    public function waitForInitialization(int $pid, int $maxWaitMs = 5000): bool;

    public function incrementAnalyzedCount(int $pid, int $idSegment, int $idJob, float $eqWc, float $stWc): void;

    public function setProjectAnalyzedCountTTL(int $pid, int $ttlSeconds = 86400): void;

    /**
     * @return string[]
     */
    public function getWorkingProjects(string $queueKey): array;

    public function decrementWaitingSegments(string $qid): int;

    public function removeProjectFromQueue(string $queueKey, int $pid): void;

    /**
     * Take the project's completion lock, so only one worker finalizes it.
     *
     * @return string|null the token to pass to releaseCompletionLock(), or null while another worker holds the lock
     * @throws RandomException when no random source is available for the token
     * @phpstan-impure
     */
    public function acquireCompletionLock(int $pid): ?string;

    /**
     * Release the completion lock so the next worker can retry finalizing without waiting out the TTL.
     * Does nothing when the lock expired and another worker took it: that lock is not ours.
     */
    public function releaseCompletionLock(int $pid, string $token): void;

    /**
     * @return array{project_segments: mixed, num_analyzed: mixed, eq_wc: float, st_wc: float}
     */
    public function getProjectWordCounts(int $pid): array;
}
