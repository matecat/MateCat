<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Model\FeaturesBase\FeatureSet;
use RuntimeException;
use Throwable;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionRepositoryInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\Constants\ProjectStatus;
use Utils\Logger\LoggerFactory;

class ProjectCompletionService implements ProjectCompletionServiceInterface
{
    private AnalysisRedisServiceInterface $redisService;
    private ProjectCompletionRepositoryInterface $repository;

    public function __construct(
        AnalysisRedisServiceInterface $redisService,
        ProjectCompletionRepositoryInterface $repository,
    ) {
        $this->redisService = $redisService;
        $this->repository = $repository;
    }

    public function tryCloseProject(int $pid, string $projectPassword, string $queueKey, FeatureSet $featureSet): void
    {
        $projectTotals = $this->redisService->getProjectWordCounts($pid);

        LoggerFactory::doJsonLog("--- trying to close project $pid: project_segments=" . ($projectTotals['project_segments'] ?? 'NULL'));
        LoggerFactory::doJsonLog("--- trying to close project $pid: num_analyzed=" . ($projectTotals['num_analyzed'] ?? 'NULL'));

        if (empty($projectTotals['project_segments'])) {
            LoggerFactory::doJsonLog("--- WARNING !!! error while counting segments in project $pid, skipping and continue");
            return;
        }

        if ((int)$projectTotals['project_segments'] - (int)$projectTotals['num_analyzed'] <= 0
            && $this->redisService->acquireCompletionLock($pid)
        ) {
            try {
                LoggerFactory::doJsonLog("--- trying to initialize job total word count for project $pid.");

                $this->repository->beginTransaction();

                $_full_report = $this->repository->getProjectSegmentsTranslationSummary($pid);
                $rollup       = array_pop($_full_report);
                assert($rollup !== null);
                $_analyzed_report = $_full_report;

                LoggerFactory::doJsonLog("--- analysis project $pid finished: change status to DONE");

                $this->repository->updateProjectAnalysisStatus(
                    $pid,
                    ProjectStatus::STATUS_DONE,
                    (float)$rollup['eq_wc'],
                    (float)$rollup['st_wc']
                );

                $jobs         = $this->repository->getProjectJobIds($pid);
                $numberOfJobs = count($jobs);

                foreach ($jobs as $job) {
                    $this->repository->updateJobStandardWordCount(
                        $job['id'],
                        (float)$rollup['st_wc'] / $numberOfJobs
                    );
                }

                $this->repository->destroyProjectAndJobCaches($pid);

                foreach ($_analyzed_report as $job_info) {
                    $this->repository->initializeJobWordCount((int)$job_info['id_job'], (string)$job_info['password']);
                }

                $this->repository->commit();

                // Remove from queue AFTER commit — if the worker crashes before commit,
                // the project stays in the queue and another worker can retry.
                $this->redisService->removeProjectFromQueue($queueKey, $pid);

                try {
                    $featureSet->run('afterTMAnalysisCloseProject', $pid, $_analyzed_report);
                } catch (Exception $e) {
                    LoggerFactory::doJsonLog("Ending project_id $pid with error {$e->getMessage()} . COMPLETED.");
                }

                $this->repository->destroyAllCaches($pid, $projectPassword);
            } catch (Throwable $e) {
                LoggerFactory::doJsonLog("**** Finalization failed for project $pid: " . $e->getMessage());

                try {
                    $this->repository->rollback();
                } catch (Throwable) {
                    // Already rolled back or no active transaction
                }

                $this->redisService->releaseCompletionLock($pid);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function getProjectSegmentsTranslationSummary(int $pid): array
    {
        // Wrap in transaction to force master-read in read-replica environments.
        // Note: tryCloseProject() has its own outer transaction, so this method
        // is only called directly from initializeTMAnalysis (which has no transaction).
        $this->repository->beginTransaction();
        $result = $this->repository->getProjectSegmentsTranslationSummary($pid);
        $this->repository->commit();

        return $result;
    }
}
