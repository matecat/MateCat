<?php

namespace Utils\LQA;

use Exception;
use Utils\Logger\LoggerFactory;
use Utils\Redis\RedisHandler;

/**
 * Serializes access to a job's qa_chunk_reviews rows across the single-issue add/edit/delete
 * flow (Plugins\Features\ReviewExtended\TranslationIssueModel) and the split/merge
 * delete+recreate+recompute flow (Plugins\Features\AbstractRevisionFeature), which otherwise
 * race on non-locking reads/writes of the same rows for the same job.
 *
 * Locking is best-effort: if Redis is unavailable, or the lock can't be acquired within
 * $waitSeconds, the callback still runs unlocked rather than failing the request — losing this
 * protection for one call is far less harmful than making core review functionality hard-depend
 * on Redis availability.
 */
final class ChunkReviewJobLock
{
    private const string KEY_PREFIX = 'qa_chunk_review:job:';

    /**
     * @throws Exception
     */
    public static function run(int $idJob, callable $callback, int $waitSeconds = 5): mixed
    {
        $redisHandler = new RedisHandler();
        $lockKey = self::KEY_PREFIX . $idJob;

        try {
            $redisHandler->tryLock($lockKey, $waitSeconds);
        } catch (Exception $e) {
            LoggerFactory::doJsonLog('ChunkReviewJobLock: proceeding without lock for job ' . $idJob . ' - ' . $e->getMessage());
        }

        try {
            return $callback();
        } finally {
            try {
                $redisHandler->unlock($lockKey);
            } catch (Exception) {
                // best-effort: an unreleased lock simply expires via the TTL set in tryLock
            }
        }
    }
}
