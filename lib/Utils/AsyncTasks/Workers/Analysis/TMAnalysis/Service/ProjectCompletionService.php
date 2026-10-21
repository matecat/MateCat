<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Model\FeaturesBase\FeatureSet;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Random\RandomException;
use RuntimeException;
use Throwable;
use Utils\Constants\ProjectStatus;

class ProjectCompletionService
{
    private AnalysisRedisService $redisService;
    private ProjectCompletionRepository $repository;

    /**
     * Where the service logs. The worker passes its queue logger, so these lines land in the
     * analysis-queue log. The default NullLogger drops them (unit tests).
     */
    private LoggerInterface $logger;

    /**
     * Number of times the DB-authoritative completion gate is polled before giving up.
     * Injectable so tests can drive the exhaustion path without a real wait.
     */
    private int $maxGateRetries;

    /**
     * Microseconds slept between gate polls. Injectable so tests can pass 0 to avoid real sleeps.
     */
    private int $gateRetrySleepMicros;

    public function __construct(
        AnalysisRedisService $redisService,
        ProjectCompletionRepository $repository,
        LoggerInterface $logger = new NullLogger(),
        int $maxGateRetries = 6,
        int $gateRetrySleepMicros = 1_000_000,
    ) {
        $this->redisService = $redisService;
        $this->repository = $repository;
        $this->logger = $logger;
        $this->maxGateRetries = $maxGateRetries;
        $this->gateRetrySleepMicros = $gateRetrySleepMicros;
    }

    /**
     * Finalize the project when its analysis is complete. Failures after the completion lock is taken are logged, not thrown.
     *
     * @throws RandomException when no random source is available for the completion lock token
     */
    public function tryCloseProject(int $pid, string $projectPassword, string $queueKey, FeatureSet $featureSet): void
    {
        $projectTotals = $this->redisService->getProjectWordCounts($pid);

        $this->logger->debug("--- trying to close project $pid: project_segments=" . ($projectTotals['project_segments'] ?? 'NULL'));
        $this->logger->debug("--- trying to close project $pid: num_analyzed=" . ($projectTotals['num_analyzed'] ?? 'NULL'));

        if (empty($projectTotals['project_segments'])) {
            $this->logger->warning("--- error while counting segments in project $pid, skipping and continue");
            return;
        }

        if ((int)$projectTotals['project_segments'] - (int)$projectTotals['num_analyzed'] > 0) {
            return;
        }

        $lockToken = $this->redisService->acquireCompletionLock($pid);
        if ($lockToken === null) {
            return;
        }

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
                    $this->logger->warning("--- empty rollup for project $pid. Releasing lock.");
                    $this->redisService->releaseCompletionLock($pid, $lockToken);

                    return;
                }

                $dbRemaining = (int)$rollup['project_segments'] - (int)$rollup['num_analyzed'];
                if ($dbRemaining <= 0) {
                    break; // MySQL confirms completion → finalize
                }

                if ($attempt === $maxAttempts) {
                    $this->logger->warning("--- Redis triggered close for project $pid but MySQL still reports $dbRemaining segments after $maxAttempts checks. Releasing lock.");
                    $this->redisService->releaseCompletionLock($pid, $lockToken);

                    return;
                }

                usleep($sleepMicros);
            }

            // Defensive: the loop only leaves $rollup null if it never ran (a
            // misconfigured maxGateRetries < 1). Bail without finalizing rather than
            // dereference a null rollup.
            if ($rollup === null) {
                $this->redisService->releaseCompletionLock($pid, $lockToken);

                return;
            }

            $_analyzed_report = $_full_report;

            $this->logger->debug("--- trying to initialize job total word count for project $pid.");

            $this->repository->transaction(function () use ($pid, $rollup, $_analyzed_report): void {
                $this->repository->updateProjectAnalysisStatus(
                    $pid,
                    ProjectStatus::STATUS_DONE,
                    (float)$rollup['eq_wc'],
                    (float)$rollup['st_wc']
                );

                $jobs = $this->repository->getProjectJobIds($pid);
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
            });

            $this->logger->info("--- analysis project $pid finished: status changed to DONE");

            // Remove from queue AFTER commit — if the worker crashes before commit,
            // the project stays in the queue and another worker can retry.
            $this->redisService->removeProjectFromQueue($queueKey, $pid);

            $this->redisService->clearProjectCounters($pid);

            $this->repository->destroyAllCaches($pid, $projectPassword);
        } catch (Throwable $e) {
            $this->logger->error("**** Finalization failed for project $pid: " . $e->getMessage());

            // No rollback here: the scope has already rolled its transaction back by the time
            // this catch runs, and the guard that used to sit around it existed because the
            // rollback could not tell "already rolled back" from "never opened".
            $this->redisService->releaseCompletionLock($pid, $lockToken);
        }
    }

    /**
     * Returns per-job word count summary with a ROLLUP totals row (last element).
     *
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function getProjectSegmentsTranslationSummary(int $pid): array
    {
        // Wrap in transaction to force master-read in read-replica environments.
        // Called from initializeTMAnalysis (no outer transaction) and from the
        // DB-authoritative gate in tryCloseProject (before its transaction starts).
        return $this->repository->transaction(fn(): array => $this->repository->getProjectSegmentsTranslationSummary($pid));
    }
}
