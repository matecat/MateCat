<?php

namespace Utils\AsyncTasks\Workers\Analysis\MtQueue;

use Closure;
use Model\DataAccess\IDatabase;
use PDO;
use Predis\Client as PredisClient;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\RedisKeys;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Commons\Context;

/**
 * Reads how busy each MT analysis queue is, and keeps the list of projects each one is analysing.
 *
 * It assumes one fast-analysis daemon per installation: that daemon both records and prunes the projects, one
 * step after the other, so a prune never races with a new record.
 *
 * Example: P4 with 500 messages in the broker and one project with 60 segments left gives segmentLoad 500.
 */
final class MtQueueLoadReader
{
    /** Seconds a published project counts as live at most, so a project that never finishes stops counting. */
    private const int LIVE_PID_TTL_SECONDS = 86400;

    /** Seconds the load check waits for one Jolokia queue size read. */
    private const int READ_TIMEOUT_SECONDS = 3;

    /** Seconds between two identical warnings, so a long outage does not flood the log. */
    private const int WARNING_INTERVAL_SECONDS = 60;

    /**
     * When each rate-limited warning was last logged, by "<message>:<queue>:<part>".
     *
     * @var array<string, int>
     */
    private array $warnedAt = [];

    /**
     * @param Closure(): int $now current Unix time
     */
    public function __construct(
        private readonly IDatabase $db,
        private readonly MatecatLogger $logger,
        private readonly Closure $now
    ) {
    }

    /**
     * Redis hash of the projects published to a queue; each field is a pid that expires after a day.
     *
     * Example: the P4 context gives 'analysis_queue_P4_live_pids'.
     */
    public static function livePidsKey(Context $context): string
    {
        return $context->queue_name . '_live_pids';
    }

    /**
     * Records that a project was published to an MT queue; a failure is logged and never stops publishing.
     *
     * HSETEX needs Valkey 9 or Redis 8.0. On an older server every call fails: the hash stays empty, so the
     * project cap of the queue never triggers and a retried project is not kept on its queue. Throwing here
     * would instead release every Lara project after each publish attempt, so it would never be analyzed.
     * The `mt_queue_live_pid_unrecorded` warning is the only sign that the project cap is off.
     *
     * Example: register($amq, 'P4', $p4, 11) adds field 11 to 'analysis_queue_P4_live_pids' for 24 hours.
     */
    public function register(AMQHandler $amq, string $key, Context $context, int $pid): void
    {
        try {
            $amq->getRedisClient()->hsetex(self::livePidsKey($context), [$pid => '1'], '', 'ex', self::LIVE_PID_TTL_SECONDS);
        } catch (Throwable $e) {
            // Usually "ERR unknown command 'HSETEX'" on a server older than Valkey 9 or Redis 8.0.
            // Publishing goes on; only the project cap and the sticky retry of this queue stay off.
            $this->warnAtMostOncePerInterval('mt_queue_live_pid_unrecorded', $context, 'register', [
                'queue' => $key,
                'pid'   => $pid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * How busy each MT queue is now, in the order of $contexts.
     *
     * The broker size, the live projects and the workers are read one by one. A part that cannot be read is
     * logged (at most once a minute) and stays unknown, and an unknown value never makes a queue full; unknown
     * workers count as one, so the queue stays usable. Call it outside any open transaction: a failed DB read
     * inside one would mark the caller's transaction unable to commit, even though this method catches it.
     *
     * Example: ['P4' => <P4 context>] with 500 messages in the broker gives one MtQueueLoad with segmentLoad 500.
     *
     * @param array<string, Context> $contexts MT queues by context key
     *
     * @return list<MtQueueLoad>
     */
    public function read(AMQHandler $amq, array $contexts): array
    {
        $loads = [];
        foreach ($contexts as $key => $context) {
            $queueSize = $this->readPart($key, $context, 'queue_size', fn (): ?int => $amq->readQueueSize($context->queue_name, self::READ_TIMEOUT_SECONDS));
            $live      = $this->readPart($key, $context, 'projects', fn (): array => $this->readLiveProjects($amq->getRedisClient(), $context));
            $workers   = $this->readPart($key, $context, 'workers', fn (): int => (int)$amq->getRedisClient()->scard($context->pid_set_name));

            $livePids = $live['pids'] ?? null;

            // Remaining work also counts the requeued messages the broker holds back, which QueueSize leaves out
            $loadFigures = array_filter([$queueSize, $live['remaining'] ?? null], fn (?int $value): bool => $value !== null);

            $loads[] = new MtQueueLoad(
                key: $key,
                context: $context,
                segmentLoad: $loadFigures === [] ? null : max($loadFigures),
                liveProjects: $livePids === null ? null : count($livePids),
                workers: $workers ?? 1,
                livePids: $livePids ?? []
            );
        }

        return $loads;
    }

    /**
     * Runs one read of a queue load and turns a failure or a null answer into null plus a rate-limited warning.
     *
     * Example: Jolokia down for P4 gives null and logs `mt_queue_load_unreadable` with part 'queue_size'.
     *
     * @template T
     *
     * @param callable(): (T|null) $read
     *
     * @return T|null
     */
    private function readPart(string $key, Context $context, string $part, callable $read): mixed
    {
        $error = [];
        try {
            $value = $read();
        } catch (Throwable $e) {
            $value = null;
            $error = ['error' => $e->getMessage()];
        }

        if ($value === null) {
            $this->warnAtMostOncePerInterval('mt_queue_load_unreadable', $context, $part, ['queue' => $key, 'part' => $part] + $error);
        }

        return $value;
    }

    /**
     * Projects a queue is analysing now and the segments they still have to analyse; every other pid leaves
     * the queue hash.
     *
     * A pid is live when its hash field has not expired, its project is not finished yet (NEW, BUSY or
     * FAST_OK), and it still has work: its counters show segments left, or no worker has written them yet.
     * A published project is NEW again only when it was released for a retry, and it stays live so the retry
     * goes back to the same queue. A project that never reaches
     * DONE stops counting when its field expires, 24 hours after its last publish.
     *
     * Example: fields [11, 12], pid 11 with 60 segments left, pid 12 with none: pids [11], remaining 60, 12 removed.
     *
     * @return array{pids: list<int>, remaining: int}
     * @throws Throwable
     */
    private function readLiveProjects(PredisClient $redis, Context $context): array
    {
        $hashKey = self::livePidsKey($context);

        // Step 1: the projects published to this queue. Valkey has already dropped the fields older than 24 hours.
        $fields = $redis->hkeys($hashKey);

        // Step 2: keep the projects the DB does not show as finished (NEW, BUSY or FAST_OK).
        // NEW means published once and released for a retry: the retry must go back to this queue.
        // A field that is not a number cannot be a project id: it is skipped here and removed in step 4.
        $publishedPids = [];
        foreach ($fields as $field) {
            if (ctype_digit($field)) {
                $publishedPids[] = (int)$field;
            }
        }
        $unfinishedPids = $publishedPids === [] ? [] : $this->selectUnfinishedProjects($publishedPids);

        // Step 3: an unfinished project is live while it still has segments to analyse.
        // A project with no counters yet is waiting for its first worker, so it is live too.
        $livePids     = [];
        $segmentsLeft = 0;
        foreach ($this->segmentsLeft($redis, $unfinishedPids) as $pid => $left) {
            $waitingForFirstWorker = $left === null;
            $hasWorkLeft           = $left !== null && $left > 0;

            if ($waitingForFirstWorker || $hasWorkLeft) {
                $livePids[]   = $pid;
                $segmentsLeft += $left ?? 0;
            }
        }

        // Step 4: remove every other field from the hash, so the next read does not look at it again.
        // PHP turns a numeric array key such as "11" into the int 11, so isset() matches a field to its pid.
        $isLive      = array_fill_keys($livePids, true);
        $staleFields = [];
        foreach ($fields as $field) {
            if (!isset($isLive[$field])) {
                $staleFields[] = $field;
            }
        }
        if ($staleFields !== []) {
            $redis->hdel($hashKey, $staleFields);
        }

        return ['pids' => $livePids, 'remaining' => $segmentsLeft];
    }

    /**
     * Segments each project still has to analyze, read from the worker counters in one Redis call.
     *
     * Workers keep two counters per project: `p_tot_seg:<pid>`, the segments to analyse, and `p_num_done:<pid>`,
     * the segments done. The first worker that takes a message of the project creates them.
     *
     * Example: pid 11 with counters 100 and 40, pid 13 with no counters yet: [11 => 60, 13 => null].
     *
     * @param list<int> $pids
     *
     * @return array<int, int|null> segments left by pid; null when the project has no counters yet
     */
    private function segmentsLeft(PredisClient $redis, array $pids): array
    {
        if ($pids === []) {
            return [];
        }

        // Read both counters of every project with a single MGET
        $keys = [];
        foreach ($pids as $pid) {
            $keys[] = RedisKeys::PROJECT_TOT_SEGMENTS . $pid;
            $keys[] = RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid;
        }
        // MGET answers in the order of $keys; pair each key with its value
        $values   = $redis->mget($keys);
        $counters = [];
        foreach ($keys as $i => $counterKey) {
            $counters[$counterKey] = $values[$i] ?? null;
        }

        $left = [];
        foreach ($pids as $pid) {
            $total = $counters[RedisKeys::PROJECT_TOT_SEGMENTS . $pid];
            $done  = $counters[RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid];

            if ($total === null || $done === null) {
                $left[$pid] = null;
            } else {
                $left[$pid] = (int)$total - (int)$done;
            }
        }

        return $left;
    }

    /**
     * Ids, among the given ones, of the projects not finished yet (NEW, BUSY or FAST_OK), read on the master.
     *
     * The query never lists the ids. It asks for every unfinished project from the smallest given id up, and
     * PHP keeps the given ones. Two reasons:
     * - a list of ids grows with the hash. Past a size that depends on the server settings, MySQL stops
     *   planning a list that long through an index and reads the whole projects table instead, on the master,
     *   on every load read. The hash can hold a day of published projects, so the list has no safe bound.
     * - the index on `status_analysis` keeps its rows ordered by status, then by id. "status X and id from N
     *   up" is therefore one short slice of the index for each status, and the query text stays the same for
     *   three ids or for thousands.
     * The extra rows it reads are the projects newer than the oldest field that are unfinished but were not
     * published to this queue, such as projects on another queue or stuck ones. Old stuck projects are never
     * read: hash fields expire after a day, so the smallest id is at most a day old.
     *
     * Example: [11, 15] with project 15 DONE and project 13 BUSY on another queue reads ids 11 and 13, and
     * gives [11].
     *
     * @param non-empty-list<int> $pids
     *
     * @return list<int> the unfinished ones, in the order of $pids
     * @throws Throwable
     */
    private function selectUnfinishedProjects(array $pids): array
    {
        $query  = 'SELECT id FROM projects WHERE status_analysis IN (?, ?, ?) AND id >= ?';
        $params = [ProjectStatus::STATUS_NEW, ProjectStatus::STATUS_BUSY, ProjectStatus::STATUS_FAST_OK, min($pids)];

        // Inside a transaction the read goes to the master, as the pick query does
        $ids = $this->db->transaction(function () use ($query, $params): array {
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        });

        // Keep only the projects of this queue: PHP turns the numeric string ids into int keys
        $isUnfinished = array_fill_keys($ids, true);

        return array_values(array_filter($pids, fn (int $pid): bool => isset($isUnfinished[$pid])));
    }

    /**
     * Logs a warning unless the same one, for the same queue and part, was logged less than a minute ago.
     *
     * Example: Jolokia down for P4 at 1000, 1030 and 1061 logs at 1000 and 1061.
     *
     * @param array<string, mixed> $logContext
     */
    private function warnAtMostOncePerInterval(string $message, Context $context, string $part, array $logContext): void
    {
        $limitKey = "$message:$context->queue_name:$part";
        $now      = ($this->now)();
        $last     = $this->warnedAt[$limitKey] ?? null;
        if ($last !== null && $now - $last < self::WARNING_INTERVAL_SECONDS) {
            return;
        }

        $this->warnedAt[$limitKey] = $now;
        $this->logger->warning($message, $logContext);
    }
}
