<?php

namespace Controller\API\Commons;

use Controller\API\Commons\Exceptions\ConflictError;
use Exception;
use Predis\Client;
use Predis\PredisException;
use Psr\Log\InvalidArgumentException;
use Random\RandomException;
use Throwable;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Redis\RedisHandler;
use Utils\Redis\RedisLock;

/**
 * One split, merge or change-password request per job at a time.
 *
 * A split or a merge validates the job's chunks and then rewrites them; a password change renames one
 * of them. Two of these running on the same job acted on the state from before the other one
 * committed: a second split-job-apply re-split a job already split, and a merge swept the credentials
 * of chunk passwords read before a concurrent change-password renamed one of them.
 *
 * The lock is keyed by the job id alone, never by a chunk password, because a merge and a password
 * change rotate exactly that password. A second request is refused at once with a 409 instead of
 * queueing: nothing waits on a database lock, and no web process is held while the first one runs.
 *
 * A split or a merge takes it before reading any chunk of the job; a password change takes it once
 * the caller is authorized, before the rotation. Every holder releases it once its action has
 * committed or rolled back. Not through IDatabase::onCommit(): its queue is discarded on rollback, so
 * a failed split would keep the job locked until the key expires.
 *
 * The TTL only bounds what a holder that never released leaves behind: that job refuses every split,
 * merge and password change until the key expires. Accepted, as is a lock lost to a Redis failover.
 * Redis being down when the lock is taken fails the request: there is no running unlocked.
 *
 * The translator assignment and the outsource confirmation take it too, around the password
 * rotation TranslatorsModel::changeJobPassword() does. Not inside that method: a split calls it
 * while already holding the job, and would refuse itself.
 *
 * Each instance remembers the token of every job it acquired, and releases only those: a request
 * that ran past the TTL, whose job has since been taken by another one, leaves that lock alone.
 */
class JobRestructureLock
{
    public const string KEY_PREFIX = 'job_restructure:';

    public const int TTL_SECONDS = 3600;

    private readonly RedisLock $lock;

    private readonly MatecatLogger $logger;

    /** @var array<int, string> the release token of each job this instance holds */
    private array $tokens = [];

    public function __construct(Client $redis, ?MatecatLogger $logger = null)
    {
        $this->lock = new RedisLock($redis);
        $this->logger = $logger ?? LoggerFactory::getLogger('job_restructure_lock');
    }

    /**
     * @throws Exception when the Redis connection cannot be configured
     */
    public static function forDefaultConnection(): self
    {
        return new self((new RedisHandler())->getConnection());
    }

    /**
     * @throws ConflictError when another request holds the job
     * @throws PredisException when Redis cannot be reached: the request fails rather than run unlocked
     * @throws RandomException when no random source is available for the token
     */
    public function acquire(int $idJob): void
    {
        $token = $this->lock->acquire(self::KEY_PREFIX . $idJob, self::TTL_SECONDS);

        if ($token === null) {
            throw new ConflictError('This job is being changed by another request, try again in a few minutes');
        }

        $this->tokens[$idJob] = $token;
    }

    /**
     * Never lets a Redis error out. It runs in a finally: after a commit, a Redis error here would turn
     * work already done into a failed request, and after a failure it would replace the error the
     * caller has to see. A key that could not be deleted expires with its TTL.
     *
     * A job this instance does not hold is left alone.
     *
     * @throws InvalidArgumentException only from the logger, for a log level it does not know
     */
    public function release(int $idJob): void
    {
        $token = $this->tokens[$idJob] ?? null;

        if ($token === null) {
            return;
        }

        unset($this->tokens[$idJob]);

        try {
            if (!$this->lock->release(self::KEY_PREFIX . $idJob, $token)) {
                $this->logger->warning(
                    'The restructure lock expired before its holder released it, the request ran longer than its ttl',
                    ['id_job' => $idJob, 'ttl_seconds' => self::TTL_SECONDS]
                );
            }
        } catch (PredisException $e) {
            $this->logger->error(
                'Could not release the restructure lock, the job stays locked until the key expires',
                ['id_job' => $idJob, 'ttl_seconds' => self::TTL_SECONDS, 'exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Run $action holding the job, and release it however $action ends.
     *
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     *
     * @throws ConflictError when another request holds the job
     * @throws PredisException when Redis cannot be reached: the request fails rather than run unlocked
     * @throws RandomException when no random source is available for the token
     * @throws Throwable whatever $action throws
     */
    public function run(int $idJob, callable $action): mixed
    {
        $this->acquire($idJob);

        try {
            return $action();
        } finally {
            $this->release($idJob);
        }
    }
}
