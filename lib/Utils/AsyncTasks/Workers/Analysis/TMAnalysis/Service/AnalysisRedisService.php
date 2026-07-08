<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Predis\Client;
use ReflectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\RedisKeys;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\Logger\LoggerFactory;

class AnalysisRedisService implements AnalysisRedisServiceInterface
{
    /**
     * TTL of the per-project init semaphore. Must exceed the worst-case duration of
     * doInit() so a slow-but-alive winner is never overtaken by a re-init. A crashed
     * winner's lock self-heals after this window; a winner that fails cleanly releases
     * it eagerly via releaseInitLock().
     */
    private const INIT_LOCK_TTL_SECONDS = 30;

    /**
     * TTL of the per-project completion semaphore. The lock is held across the WHOLE
     * finalization critical section: the gate-retry loop, the DB transaction, the
     * per-job word-count loops, cache invalidation and removeProjectFromQueue. That is a
     * seconds-long burst whose duration scales with the JOB count — NOT the multi-minute
     * analysis itself, which does not hold this lock. 300s gives a wide margin over that
     * burst even for large multi-job projects, while still letting a worker crashed mid
     * critical-section self-heal in ~5min (vs the old 24h) so finalization is never
     * blocked for long.
     */
    private const COMPLETION_LOCK_TTL_SECONDS = 300;

    private Client $redis;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function __construct(AMQHandler $queueHandler)
    {
        $this->redis = $queueHandler->getRedisClient();
    }

    public function reconnect(): void
    {
        $this->redis->disconnect();
    }

    public function acquireInitLock(int $pid): bool
    {
        return (bool)$this->redis->set(
            RedisKeys::PROJECT_INIT_SEMAPHORE . $pid,
            1,
            'EX', self::INIT_LOCK_TTL_SECONDS,
            'NX'
        );
    }

    /**
     * Release the init semaphore eagerly. Called when doInit() fails or finds the
     * segment count not yet available, so the next worker for this PID can re-attempt
     * initialization immediately instead of waiting out the TTL.
     */
    public function releaseInitLock(int $pid): void
    {
        $this->redis->del(RedisKeys::PROJECT_INIT_SEMAPHORE . $pid);
    }

    /**
     * Atomically initialize all project counters in a single MULTI/EXEC transaction.
     *
     * ORDERING CONTRACT: PROJECT_TOT_SEGMENTS is written first, PROJECT_NUM_SEGMENTS_DONE last.
     * waitForInitialization() polls for PROJECT_NUM_SEGMENTS_DONE as the "init complete" signal,
     * so losers only proceed once all counters are written.
     */
    public function initializeProjectCounters(int $pid, int $projectSegments, int $numAnalyzed): void
    {
        $this->redis->transaction(function ($tx) use ($pid, $projectSegments, $numAnalyzed) {
            // Start each analysis run with an empty idempotency set, otherwise segment ids
            // left over from a previous run of the same project (within the 24h TTL) would
            // make incrementAnalyzedCount skip their increments and undercount this run.
            $tx->del(RedisKeys::PROJECT_ANALYZED_SEGMENTS_SET . $pid);
            $tx->setex(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, 86400, $projectSegments);
            // Absolute resets (not incrby): re-analysis of the same PID within the 24h TTL
            // must not accumulate onto stale counters, otherwise a stale-high num_done keeps
            // re-triggering the completion lock. On first init the key does not exist yet, so
            // setex is equivalent to the old incrby-from-zero.
            $tx->setex(RedisKeys::PROJ_EQ_WORD_COUNT . $pid, 86400, 0);
            $tx->setex(RedisKeys::PROJ_ST_WORD_COUNT . $pid, 86400, 0);
            $tx->setex(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, 86400, $numAnalyzed);
        });

        $this->setProjectAnalyzedCountTTL($pid);
    }

    public function setProjectTotalSegments(int $pid, int $total): void
    {
        $this->redis->setex(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, 60 * 60 * 24, $total);
    }

    public function getProjectTotalSegments(int $pid): ?int
    {
        $val = $this->redis->get(RedisKeys::PROJECT_TOT_SEGMENTS . $pid);

        return $val !== null ? (int)$val : null;
    }

    public function getProjectAnalyzedCount(int $pid): ?int
    {
        $val = $this->redis->get(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid);

        return $val !== null ? (int)$val : null;
    }

    public function waitForInitialization(int $pid, int $maxWaitMs = 5000): bool
    {
        $waited  = 0;
        $sleepMs = 50;

        while ($waited < $maxWaitMs) {
            $tot   = $this->redis->get(RedisKeys::PROJECT_TOT_SEGMENTS . $pid);
            $count = $this->redis->get(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid);
            if ($tot !== null && $count !== null) {
                return true;
            }

            // The init semaphore is gone but the counters were never written: the winner
            // abandoned initialization (failed doInit released the lock, or crashed and the
            // TTL expired). Stop polling now so the caller re-acquires and re-inits, instead
            // of blocking for the full maxWaitMs waiting on counters that will never arrive.
            if (!$this->redis->exists(RedisKeys::PROJECT_INIT_SEMAPHORE . $pid)) {
                return false;
            }

            usleep($sleepMs * 1000);
            $waited  += $sleepMs;
            $sleepMs  = min($sleepMs * 2, 500);
        }

        LoggerFactory::doJsonLog("WARNING — timed out waiting for init completion for PID $pid");

        return false;
    }

    /**
     * Idempotently record one analyzed segment: add $idSegment to the per-project set and,
     * ONLY if it was not already present, bump the analyzed counter and word-count totals.
     *
     * Runs as a single Lua script, so it executes atomically server-side: on a connection
     * error the whole script either ran or did not. The retry loop in
     * applyPostCommitSideEffects can therefore re-invoke it with the same segment id without
     * ever double-counting — the second SADD returns 0 and the INCRBYs are skipped. This
     * keeps PROJECT_NUM_SEGMENTS_DONE from overshooting PROJECT_TOT_SEGMENTS, which used to
     * trigger the completion close prematurely and strand projects at FAST_OK.
     *
     * @throws Exception on Predis connection error
     */
    public function incrementAnalyzedCount(int $pid, int $idSegment, float $eqWc, float $stWc): void
    {
        static $script = <<<'LUA'
            if redis.call('SADD', KEYS[1], ARGV[1]) == 1 then
                redis.call('INCRBY', KEYS[2], ARGV[2])
                redis.call('INCRBY', KEYS[3], ARGV[3])
                redis.call('INCRBY', KEYS[4], ARGV[4])
            end
            redis.call('EXPIRE', KEYS[1], ARGV[5])
            redis.call('EXPIRE', KEYS[2], ARGV[5])
            redis.call('EXPIRE', KEYS[3], ARGV[5])
            redis.call('EXPIRE', KEYS[4], ARGV[5])
            return redis.call('SCARD', KEYS[1])
            LUA;

        $this->redis->eval(
            $script,
            4,
            RedisKeys::PROJECT_ANALYZED_SEGMENTS_SET . $pid, // KEYS[1] idempotency set
            RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid,     // KEYS[2] analyzed counter
            RedisKeys::PROJ_EQ_WORD_COUNT . $pid,            // KEYS[3] equivalent word count
            RedisKeys::PROJ_ST_WORD_COUNT . $pid,            // KEYS[4] standard word count
            (string)$idSegment,                              // ARGV[1]
            '1',                                             // ARGV[2] analyzed delta
            (string)(int)($eqWc * RedisKeys::WORD_COUNT_SCALE), // ARGV[3]
            (string)(int)($stWc * RedisKeys::WORD_COUNT_SCALE), // ARGV[4]
            '86400',                                         // ARGV[5] set TTL (matches counters)
        );
    }

    public function setProjectAnalyzedCountTTL(int $pid, int $ttlSeconds = 86400): void
    {
        $this->redis->expire(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $ttlSeconds);
    }

    /**
     * @return string[]
     */
    public function getWorkingProjects(string $queueKey): array
    {
        return $this->redis->lrange($queueKey, 0, -1);
    }

    public function decrementWaitingSegments(string $qid): int
    {
        return $this->redis->decr(RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $qid);
    }

    public function removeProjectFromQueue(string $queueKey, int $pid): void
    {
        $this->redis->lrem($queueKey, 0, (string)$pid);
    }

    public function acquireCompletionLock(int $pid): bool
    {
        return (bool)$this->redis->set(
            RedisKeys::PROJECT_ENDING_SEMAPHORE . $pid,
            1,
            'EX', self::COMPLETION_LOCK_TTL_SECONDS,
            'NX'
        );
    }

    public function releaseCompletionLock(int $pid): void
    {
        $this->redis->del(RedisKeys::PROJECT_ENDING_SEMAPHORE . $pid);
    }

    /**
     * @return array{project_segments: mixed, num_analyzed: mixed, eq_wc: float, st_wc: float}
     */
    public function getProjectWordCounts(int $pid): array
    {
        return [
            'project_segments' => $this->redis->get(RedisKeys::PROJECT_TOT_SEGMENTS . $pid),
            'num_analyzed'     => $this->redis->get(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid),
            'eq_wc'            => (float)$this->redis->get(RedisKeys::PROJ_EQ_WORD_COUNT . $pid) / RedisKeys::WORD_COUNT_SCALE,
            'st_wc'            => (float)$this->redis->get(RedisKeys::PROJ_ST_WORD_COUNT . $pid) / RedisKeys::WORD_COUNT_SCALE,
        ];
    }
}
