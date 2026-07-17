<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Closure;
use Model\FeaturesBase\FeatureSet;
use RuntimeException;
use Throwable;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionRepositoryInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\Constants\ProjectStatus;

class ProjectCompletionService implements ProjectCompletionServiceInterface
{
    private AnalysisRedisServiceInterface $redisService;
    private ProjectCompletionRepositoryInterface $repository;

    /**
     * Sink for completion log messages. Injected by the worker so these lines land in the
     * analysis-queue log (via the worker's _doLog observer) alongside the rest of the
     * analysis logs, instead of the global general_log.txt. Null → logging is skipped
     * (e.g. isolated unit tests that don't assert on log output).
     *
     * @var (Closure(string): void)|null
     */
    private ?Closure $logger;

    /**
     * Number of times the DB-authoritative completion gate is polled before giving up.
     * Injectable so tests can drive the exhaustion path without a real wait.
     */
    private int $maxGateRetries;

    /**
     * Microseconds slept between gate polls. Injectable so tests can pass 0 to avoid real sleeps.
     */
    private int $gateRetrySleepMicros;

    /**
     * @param AnalysisRedisServiceInterface $redisService
     * @param ProjectCompletionRepositoryInterface $repository
     * @param (Closure(string): void)|null $logger
     * @param int $maxGateRetries
     * @param int $gateRetrySleepMicros
     */
    public function __construct(
        AnalysisRedisServiceInterface $redisService,
        ProjectCompletionRepositoryInterface $repository,
        ?Closure $logger = null,
        int $maxGateRetries = 6,
        int $gateRetrySleepMicros = 1_000_000,
    ) {
        $this->redisService = $redisService;
        $this->repository = $repository;
        $this->logger = $logger;
        $this->maxGateRetries = $maxGateRetries;
        $this->gateRetrySleepMicros = $gateRetrySleepMicros;
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }

    public function tryCloseProject(int $pid, string $projectPassword, string $queueKey, FeatureSet $featureSet): void
    {
        $projectTotals = $this->redisService->getProjectWordCounts($pid);

        $this->log("--- trying to close project $pid: project_segments=" . ($projectTotals['project_segments'] ?? 'NULL'));
        $this->log("--- trying to close project $pid: num_analyzed=" . ($projectTotals['num_analyzed'] ?? 'NULL'));

        if (empty($projectTotals['project_segments'])) {
            $this->log("--- WARNING !!! error while counting segments in project $pid, skipping and continue");
            return;
        }

        if ((int)$projectTotals['project_segments'] - (int)$projectTotals['num_analyzed'] <= 0
            && $this->redisService->acquireCompletionLock($pid)
        ) {
            try {
                // DB-authoritative gate: Redis triggered the close attempt, but MySQL
                // is the source of truth. Verify all segments are actually DONE/SKIPPED
                // before proceeding. This handles Redis/MySQL drift: a sustained Redis
                // outage can lose an INCRBY, so the Redis counters can disagree with the
                // real per-segment state in MySQL.
                // Uses self-call to force master-read via transaction wrapper
                // (ProxySQL routes reads outside transactions to slave).
                //
                // Bounded gate-retry (last-segment race): with concurrent workers, the
                // one holding this lock can read the gate a moment before another worker
                // commits the truly-final segment, so it sees "segments remain". The
                // other worker — whose commit actually completed the project — is denied
                // this lock and drops its close attempt without retrying, and no segment
                // is set DONE afterwards, so no later trigger ever fires: the project
                // hangs at FAST_OK forever. Because we are the single lock holder, poll
                // the gate for a short bounded window so the concurrent final commit can
                // land, instead of releasing on a stale snapshot.
                // getProjectSegmentsTranslationSummary is a heavy GROUP BY ... WITH ROLLUP
                // over three joined tables, so poll sparingly: 6 checks 1s apart (~5s
                // window). That covers realistic inter-worker commit skew while keeping
                // master load to a few queries, and stays well under COMPLETION_LOCK_TTL
                // (300s) so this holder is never evicted mid-retry. The first check runs
                // immediately, so the common (no-drift) close path does a single query.
                $maxAttempts = $this->maxGateRetries;
                $sleepMicros = $this->gateRetrySleepMicros;

                $rollup       = null;
                $_full_report = [];

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    $_full_report = $this->getProjectSegmentsTranslationSummary($pid);
                    $rollup       = array_pop($_full_report);

                    if ($rollup === null) {
                        $this->log("--- WARNING: empty rollup for project $pid. Releasing lock.");
                        $this->redisService->releaseCompletionLock($pid);

                        return;
                    }

                    $dbRemaining = (int)$rollup['project_segments'] - (int)$rollup['num_analyzed'];
                    if ($dbRemaining <= 0) {
                        break; // MySQL confirms completion → finalize
                    }

                    if ($attempt === $maxAttempts) {
                        $this->log("--- Redis triggered close for project $pid but MySQL still reports $dbRemaining segments after $maxAttempts checks. Releasing lock.");
                        $this->redisService->releaseCompletionLock($pid);

                        return;
                    }

                    usleep($sleepMicros);
                }

                // Defensive: the loop only leaves $rollup null if it never ran (a
                // misconfigured maxGateRetries < 1). Bail without finalizing rather than
                // dereference a null rollup.
                if ($rollup === null) {
                    $this->redisService->releaseCompletionLock($pid);

                    return;
                }

                $_analyzed_report = $_full_report;

                $this->log("--- trying to initialize job total word count for project $pid.");

                $this->repository->beginTransaction();

                $this->log("--- analysis project $pid finished: change status to DONE");

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

                $this->redisService->clearProjectCounters($pid);

                $this->repository->destroyAllCaches($pid, $projectPassword);
            } catch (Throwable $e) {
                $this->log("**** Finalization failed for project $pid: " . $e->getMessage());

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
        // Called from initializeTMAnalysis (no outer transaction) and from the
        // DB-authoritative gate in tryCloseProject (before its transaction starts).
        $this->repository->beginTransaction();
        $result = $this->repository->getProjectSegmentsTranslationSummary($pid);
        $this->repository->commit();

        return $result;
    }
}
