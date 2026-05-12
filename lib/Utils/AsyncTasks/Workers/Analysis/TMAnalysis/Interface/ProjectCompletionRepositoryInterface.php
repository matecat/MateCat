<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface;

use RuntimeException;

interface ProjectCompletionRepositoryInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

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

    public function destroyAllCaches(int $pid, string $projectPassword): void;
}
