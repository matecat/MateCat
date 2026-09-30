<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use RuntimeException;
use Throwable;

interface ProjectCompletionRepositoryInterface
{
    /**
     * Run a callback inside a database transaction.
     *
     * The repository keeps owning its connection; what it does not own is the decision about when a
     * transaction opens and closes. Handing out begin/commit/rollback instead made this class a
     * second place transaction control lives, invisible to every reader and to static analysis.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T The value returned by the callback
     *
     * @throws Throwable Re-throws the original exception after the transaction is aborted
     */
    public function transaction(callable $work): mixed;

    /**
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function getProjectSegmentsTranslationSummary(int $pid): array;

    public function updateProjectAnalysisStatus(int $pid, string $status, float $eqWc, float $stWc): void;

    /**
     * @return array<int, array{id: int, password: string}>
     */
    public function getProjectJobIds(int $pid): array;

    public function updateJobStandardWordCount(int $jobId, float $stWc): void;

    public function initializeJobWordCount(int $jobId, string $password): void;

    public function destroyProjectAndJobCaches(int $pid): void;

    public function destroyAllCaches(int $pid, string $projectPassword): void;
}
