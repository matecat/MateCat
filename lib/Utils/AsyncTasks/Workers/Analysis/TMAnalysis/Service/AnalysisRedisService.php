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
    private Client $redis;

    /**
     * @throws ReflectionException
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
            'EX', 30,
            'NX'
        );
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
            $tx->setex(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, 86400, $projectSegments);
            $tx->incrby(RedisKeys::PROJ_EQ_WORD_COUNT . $pid, 0);
            $tx->incrby(RedisKeys::PROJ_ST_WORD_COUNT . $pid, 0);
            $tx->incrby(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $numAnalyzed);
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
            usleep($sleepMs * 1000);
            $waited  += $sleepMs;
            $sleepMs  = min($sleepMs * 2, 500);
        }

        LoggerFactory::doJsonLog("WARNING — timed out waiting for init completion for PID $pid");

        return false;
    }

    /**
     * @throws Exception on Predis connection error
     */
    public function incrementAnalyzedCount(int $pid, int $numSegments, float $eqWc, float $stWc): void
    {
        $this->redis->transaction(function ($tx) use ($pid, $numSegments, $eqWc, $stWc) {
            $tx->incrby(RedisKeys::PROJ_EQ_WORD_COUNT . $pid, (int)($eqWc * RedisKeys::WORD_COUNT_SCALE));
            $tx->incrby(RedisKeys::PROJ_ST_WORD_COUNT . $pid, (int)($stWc * RedisKeys::WORD_COUNT_SCALE));
            $tx->incrby(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $numSegments);
        });
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
            'EX', 86400,
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
