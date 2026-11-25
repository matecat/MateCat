<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 08/08/24
 * Time: 14:35
 */

namespace Model\DataAccess;

use Exception;
use PDOException;
use Predis\Client;
use Random\RandomException;
use ReflectionException;
use Utils\Logger\LoggerFactory;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

trait DaoCacheTrait
{

    /**
     * The cache connection object
     * @var ?Client
     */
    protected static ?Client $cache_con;

    /**
     * @var int Cache expiry time, expressed in seconds
     */
    protected int $cacheTTL = 0;

    /**
     * XFetch β (beta) tuning parameter.
     * Controls how aggressively early recomputation triggers.
     * 1.0 is the theoretically optimal value per Vattani et al. (2015).
     */
    protected const float XFETCH_BETA = 1.0;

    /**
     * Minimum TTL (seconds) for XFetch to activate.
     * Below this threshold, XFetch auto-disables because the early
     * recomputation window could exceed the remaining TTL.
     */
    protected const int XFETCH_MIN_TTL_THRESHOLD = 10;

    /**
     * Fallback δ (seconds) when no measured recomputation time is available.
     * Used by callers that bypass _fetchObjectMap (e.g., Pager).
     */
    protected const float XFETCH_FALLBACK_DELTA = 0.05;

    /**
     * Whether XFetch probabilistic early expiration is active for this class.
     * Override to false in classes that use DaoCacheTrait for non-query storage
     * (e.g., SessionTokenStoreHandler).
     */
    protected bool $xFetchEnabled = true;

    /**
     * Most fields one hash (one `$keyMap`) may hold before it is dropped.
     *
     * A healthy hash holds the few reads of one entity: on production the largest one had 15
     * fields. A hash whose name does not carry the values its reads vary by gains one field per
     * call and never expires, because every write renews its TTL. That is how
     * `SegmentNoteDao::getBySegmentIds-` reached 18.9 GB and Redis evicted the login tokens.
     * At about 50 KB a field, this cap keeps such a hash under about 500 MB.
     *
     * Final, and with no switch to turn it off: every hash written through this trait is bounded.
     * The two write methods are final too, so no subclass can skip the check. PHP still lets a
     * class that uses this trait directly declare its own write method; the PHPStan rule
     * NoCacheMapWriteOverrideRule reports that.
     */
    final protected const int CACHE_MAP_FIELD_CAP = 10_000;

    /**
     * Last measured recomputation time (δ) in seconds.
     * Set via _setLastComputeDelta() from AbstractDao::_fetchObjectMap().
     * Consumed-and-reset internally by _setInCacheMap() when building the envelope.
     */
    private float $lastComputeDelta = 0.0;

    /**
     * Set the last measured recomputation time (δ).
     *
     * @param float $delta Recomputation time in seconds
     */
    protected function _setLastComputeDelta(float $delta): void
    {
        $this->lastComputeDelta = $delta;
    }

    /**
     * Cache Initialization
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _cacheSetConnection(): void
    {
        if (!isset(self::$cache_con) || empty(self::$cache_con)) {
            try {
                self::$cache_con = (new RedisHandler())->getConnection();
                self::$cache_con->get('1');
            } catch (Exception $e) {
                self::$cache_con = null;
                throw $e;
            }
        }
    }

    /**
     * Sets the cache connection instance.
     *
     * @param Client|null $connection The cache connection instance to set, or null to unset.
     * @return void
     */
    public static function setCacheConnection(?Client $connection): void
    {
        self::$cache_con = $connection;
    }


    /** @noinspection PhpUnusedParameterInspection */
    protected function _logCache(string $type, string $key, mixed $value, string $sqlQuery): void
    {
        LoggerFactory::getLogger('query_cache')->debug(
            [
                "type" => $type,
                "key" => $key,
                "sql" => preg_replace("/ +/", " ", str_replace("\n", " ", $sqlQuery)),
                //"result_set" => $value,
            ]
        );
    }

    /**
     * XFetch probabilistic early expiration check.
     *
     * Returns true if the cache entry should be recomputed early to prevent stampede.
     * Formula: now - δ · β · log(rand) ≥ storedAt + TTL
     *
     * @param float $storedAt Timestamp when the entry was cached
     * @param float $delta Recomputation time (δ) in seconds
     * @param int $ttl Cache TTL in seconds
     *
     * @return bool True if early recomputation should happen
     *
     * @throws RandomException
     * @see https://en.wikipedia.org/wiki/Cache_stampede#Optimal_probabilistic_early_expiration
     */
    protected function _shouldRecompute(float $storedAt, float $delta, int $ttl): bool
    {
        if ($delta <= 0.0) {
            return false;
        }

        // XFetch formula: recompute when now - δ · β · log(rand()) ≥ expiry
        // log(rand()) is always ≤ 0 for rand() in (0, 1], so subtracting it adds a positive jitter window.
        return (microtime(true) - $delta * static::XFETCH_BETA * log(random_int(1, PHP_INT_MAX) / PHP_INT_MAX)) >= ($storedAt + $ttl);
    }

    /**
     * Strips the {@see XFetchEnvelope} wrapper from a stored cache-map value, if it has one.
     *
     * A value is written either bare or wrapped, decided by $xFetchEnabled at *write* time
     * ({@see _setInCacheMap()}). Readers therefore cannot assume either shape — not even readers on
     * a class that disables xFetch, because the flag can be turned on while values written under the
     * old setting are still in Redis. Every reader of a stored value must go through here.
     */
    protected function _unwrapCacheMapValue(mixed $unserialized): mixed
    {
        return $unserialized instanceof XFetchEnvelope ? $unserialized->value : $unserialized;
    }

    /**
     * @param string $keyMap
     * @param string $query A query
     *
     * @return ?list<mixed>
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _getFromCacheMap(string $keyMap, string $query): ?array
    {
        if (AppConfig::$SKIP_SQL_CACHE || $this->cacheTTL == 0) {
            return null;
        }

        $this->_cacheSetConnection();

        if (self::$cache_con === null) {
            return null;
        }

        $key = md5($query);

        return $this->_decodeCacheMapValue($keyMap, $key, $query, self::$cache_con->hget($keyMap, $key));
    }

    /**
     * Turn one stored entry into the value a reader gets back, or null when there is nothing
     * usable to return: an absent entry, an XFetch envelope this reader should recompute early,
     * or a payload that did not survive as an array.
     *
     * Shared by the single and the batched reader so the two cannot drift apart. They address the
     * same entries, so a difference in how they decode one would be a difference in what the same
     * cached row means depending on which method asked for it.
     *
     * @return ?list<mixed>
     * @throws ReflectionException
     * @throws Exception
     */
    private function _decodeCacheMapValue(
        string $keyMap,
        string $key,
        string $query,
        mixed $raw,
        bool $logEntry = true
    ): ?array {
        if (!is_string($raw)) {
            if ($logEntry) {
                $this->_logCache("GETMAP_MISS: " . $keyMap, $key, null, $query);
            }

            return null;
        }

        $unserialized = unserialize($raw);

        if ($unserialized instanceof XFetchEnvelope) {
            if (
                $this->xFetchEnabled
                && $this->cacheTTL >= static::XFETCH_MIN_TTL_THRESHOLD
                && $this->_shouldRecompute($unserialized->storedAt, $unserialized->delta, $this->cacheTTL)
            ) {
                if ($logEntry) {
                    $this->_logCache("GETMAP_XFETCH_RECOMPUTE: " . $keyMap, $key, null, $query);
                }

                return null;
            }
        }

        $unserialized = $this->_unwrapCacheMapValue($unserialized);

        if ($logEntry) {
            $this->_logCache("GETMAP: " . $keyMap, $key, $unserialized, $query);
        }

        if (!is_array($unserialized)) {
            return null;
        }

        /** @var list<mixed> $unserialized */
        return $unserialized;
    }

    /**
     * Read many entries in one round trip.
     *
     * Every entry lives in its own single-field hash, so no single Redis command fetches them
     * together; a pipeline sends the whole batch and reads the whole batch back. That is what lets
     * a set of entities be cached one entity per entry — evictable through the per-entity door —
     * without paying one round trip per entity for the privilege.
     *
     * @param array<array-key, array{keyMap: string, query: string}> $specs
     *
     * @return array<array-key, list<mixed>> Hits only, under the caller's own keys. A miss is
     *                                       absent rather than null, so a cached empty result
     *                                       stays distinguishable from no result at all.
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _getManyFromCacheMap(array $specs): array
    {
        if (AppConfig::$SKIP_SQL_CACHE || $this->cacheTTL == 0 || $specs === []) {
            return [];
        }

        $this->_cacheSetConnection();

        if (self::$cache_con === null) {
            return [];
        }

        /** @var list<mixed> $responses */
        $responses = self::$cache_con->pipeline(static function ($pipe) use ($specs): void {
            foreach ($specs as $spec) {
                $pipe->hget($spec['keyMap'], md5($spec['query']));
            }
        });

        $hits = [];
        $position = 0;

        foreach ($specs as $id => $spec) {
            $value = $this->_decodeCacheMapValue(
                $spec['keyMap'],
                md5($spec['query']),
                $spec['query'],
                $responses[$position++] ?? null,
                false
            );

            if ($value !== null) {
                $hits[$id] = $value;
            }
        }

        // One line for the batch, not one per entry. The single-key reader logs per key because a
        // key is what it did; this method does one round trip for the whole set, so a line per entry
        // describes nothing extra and costs a log write per member. Measured against a 327-member
        // team on production data, the per-entry lines were 95% of this method's wall clock —
        // 29.96 ms with them against 1.49 ms without — which made the cached path several times
        // slower than the uncached query it exists to avoid.
        $this->_logCache(
            "GETMAP_BATCH: " . count($hits) . "/" . count($specs) . " hit",
            (string)array_key_first($specs),
            null,
            reset($specs)['query']
        );

        return $hits;
    }

    /**
     *
     * This method uses a clean, human-readable key instead of a md5 hash.
     * It also allows grouping multiple queries under a single namespace (`$keyMap`).
     *
     * @param string $keyMap
     * @param string $query
     * @param list<mixed> $value
     *
     * @return void|null
     * @throws Exception
     */
    final protected function _setInCacheMap(string $keyMap, string $query, array $value)
    {
        if ($this->cacheTTL == 0) {
            return null;
        }

        // A row read inside an open transaction is this connection's private view of it: no other
        // connection can see it, and a rollback un-makes it. Publishing it to a cache every request
        // shares would hand them all a row that may never exist, for the whole TTL.
        if ($this->_isInsideTransaction()) {
            return null;
        }

        if (isset(self::$cache_con) && !empty(self::$cache_con)) {
            $key = md5($query);
            $storable = $this->_storableCacheMapValue($value, $this->_consumeComputeDelta());

            self::$cache_con->hset($keyMap, $key, $storable);
            self::$cache_con->expire($keyMap, $this->cacheTTL);
            self::$cache_con->setex($key, $this->cacheTTL, $keyMap);
            $this->_capCacheMap($keyMap, (int)self::$cache_con->hlen($keyMap));
            $this->_logCache("SETMAP: " . $keyMap, $key, $value, $query);
        }
    }

    /**
     * Drop a hash that holds more than CACHE_MAP_FIELD_CAP fields, and log it as an error.
     *
     * Only a hash that grows without limit gets that large, such as a cached read whose key map is
     * not scoped by its values. Dropping it costs one cache miss per field; keeping it lets it grow
     * until Redis evicts other keys. The error names the hash, so its writer can be found and fixed.
     * See CACHE_MAP_FIELD_CAP for the case that led to it.
     */
    private function _capCacheMap(string $keyMap, int $fields): void
    {
        if ($fields <= self::CACHE_MAP_FIELD_CAP) {
            return;
        }

        self::$cache_con?->unlink($keyMap);

        // A derived key map ends with the bound values, which can be a job password or an email.
        // Log the part before them, which names the writer, and a hash of the whole name.
        LoggerFactory::getLogger('query_cache')->error(
            "Redis hash dropped: it held more fields than one hash may hold.",
            [
                'writer' => strstr($keyMap, '-', true) ?: $keyMap,
                'keyMapMd5' => md5($keyMap),
                'fields' => $fields,
                'cap' => self::CACHE_MAP_FIELD_CAP,
            ]
        );
    }

    /**
     * The measured cost of the read that produced the value about to be stored, taken once and
     * cleared. XFetch uses it to decide how early a reader should recompute, so a stale delta
     * carried into the next unrelated write would misprice that decision.
     */
    private function _consumeComputeDelta(): float
    {
        $delta = $this->lastComputeDelta > 0.0 ? $this->lastComputeDelta : static::XFETCH_FALLBACK_DELTA;
        $this->lastComputeDelta = 0.0;

        return $delta;
    }

    /**
     * @param list<mixed> $value
     */
    private function _storableCacheMapValue(array $value, float $delta): string
    {
        if ($this->xFetchEnabled && $this->cacheTTL >= static::XFETCH_MIN_TTL_THRESHOLD) {
            return serialize(new XFetchEnvelope($value, microtime(true), $delta));
        }

        return serialize($value);
    }

    /**
     * Write many entries in one round trip, under the same addresses `_setInCacheMap()` uses.
     *
     * The batch shares one compute delta because it comes from one query: the cost of that read
     * is the cost of producing every entry in it, and splitting it per entry would tell XFetch
     * each row was cheaper to compute than it was.
     *
     * @param array<array-key, array{keyMap: string, query: string, value: list<mixed>}> $entries
     *
     * @throws PDOException
     * @throws Exception
     */
    final protected function _setManyInCacheMap(array $entries): void
    {
        if ($this->cacheTTL == 0 || $entries === []) {
            return;
        }

        // Same reason as the single write: a row read inside an open transaction is private to
        // this connection, and publishing it would share a row that a rollback un-makes.
        if ($this->_isInsideTransaction()) {
            return;
        }

        if (!isset(self::$cache_con) || empty(self::$cache_con)) {
            return;
        }

        $delta = $this->_consumeComputeDelta();
        $storables = [];

        foreach ($entries as $entry) {
            $storables[] = [
                'keyMap'   => $entry['keyMap'],
                'key'      => md5($entry['query']),
                'storable' => $this->_storableCacheMapValue($entry['value'], $delta),
            ];
        }

        $ttl = $this->cacheTTL;

        // Check every hash this batch writes to, and drop the ones past CACHE_MAP_FIELD_CAP.
        //
        // A pipeline sends many commands to Redis in one round trip. Redis answers with one list:
        // one reply per command, in the order the code queued them. The code below queues the
        // writes first, then one HLEN (the command that counts the fields of a hash) for each
        // distinct hash. The list therefore ends with the field counts:
        //
        //     [ hset, expire, setex,  hset, expire, setex,  ...,  hlen(map A), hlen(map B) ]
        //       |---- entry 0 ----|   |---- entry 1 ----|        |-- one per hash, last --|
        //
        // The code reads the last count($keyMaps) replies, in the order of $keyMaps. Each HLEN runs
        // after every write of the batch, so its count includes the fields the batch just added.
        // The counts stay at the end of the list, so a write command added to the first loop does
        // not move them.
        //
        // Example: a batch writes three entries, two to "Dao::read-1" and one to "Dao::read-2". It
        // queues 9 writes and 2 HLENs. Replies 9 and 10 are the field counts of "Dao::read-1" and
        // "Dao::read-2". If "Dao::read-1" now holds 10,001 fields, _capCacheMap() drops it.
        //
        // pipeline() returns an array whenever it gets a callback, as here. The is_array() check
        // is there for static analysis.
        $keyMaps = array_values(array_unique(array_column($storables, 'keyMap')));

        $replies = self::$cache_con->pipeline(static function ($pipe) use ($storables, $keyMaps, $ttl): void {
            foreach ($storables as $storable) {
                $pipe->hset($storable['keyMap'], $storable['key'], $storable['storable']);
                $pipe->expire($storable['keyMap'], $ttl);
                $pipe->setex($storable['key'], $ttl, $storable['keyMap']);
            }

            foreach ($keyMaps as $keyMap) {
                $pipe->hlen($keyMap);
            }
        });

        $fieldCounts = is_array($replies) ? array_slice($replies, -count($keyMaps)) : [];

        foreach ($keyMaps as $i => $keyMap) {
            $this->_capCacheMap($keyMap, (int)($fieldCounts[$i] ?? 0));
        }

        // One line for the batch, for the same reason the batched reader logs once: see the comment
        // there.
        $firstEntry = reset($entries);

        $this->_logCache(
            "SETMAP_BATCH: " . count($entries) . " entries",
            (string)array_key_first($entries),
            null,
            $firstEntry['query']
        );
    }

    /**
     * @param ?int $cacheSecondsTTL
     *
     * @return static
     */
    public function setCacheTTL(?int $cacheSecondsTTL): static
    {
        if (!AppConfig::$SKIP_SQL_CACHE) {
            $this->cacheTTL = $cacheSecondsTTL ?? 0;
        }

        return $this;
    }

    /**
     * The transaction whose visibility this object's cache writes have to follow, or null when
     * there is none.
     *
     * Cached data is shared by every connection; data written inside a transaction is not. Where
     * both are true at once the cache can publish, or keep, a value no other connection can see.
     * The trait cannot answer that on its own — it holds a Redis connection, not a database one —
     * so the consumer declares it.
     *
     * Null is the safe default and the honest answer for the three non-DAO consumers: Pager only
     * reads, and UserStateStore and SessionTokenStoreHandler write to Redis alone. A token
     * revocation in particular has to take effect at once and must never wait behind a commit.
     */
    protected function _cacheTransactionScope(): ?IDatabase
    {
        return null;
    }

    /**
     * @throws PDOException
     */
    private function _isInsideTransaction(): bool
    {
        $database = $this->_cacheTransactionScope();

        return $database !== null && $database->getConnection()->inTransaction();
    }

    /**
     * Serialize params, ensuring values are always treated as strings.
     *
     * @param array<int|string, scalar|null> $params
     *
     * @return string
     */
    protected function _serializeForCacheKey(array $params): string
    {
        foreach ($params as $key => $value) {
            $params[$key] = (string)$value;
        }

        return serialize($params);
    }

    /**
     * Destroy a single element in the hash set
     *
     * Inside an open transaction the eviction is queued for the commit instead of running.
     * Running it now would be worse than not running it at all: another connection cannot see the
     * uncommitted write, so it misses the cache, reads the old row and caches it again for the full
     * TTL — behind the eviction that has just happened, and outliving the commit.
     *
     * @param string $keyMap
     * @param string $keyElementName
     *
     * @return bool True when the entry was removed, or when the eviction was queued for the commit.
     *              A queued eviction cannot report what it will find, so the two are not
     *              distinguishable through the return value. Callers that assert on it have to run
     *              outside a transaction.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _removeObjectCacheMapElement(string $keyMap, string $keyElementName): bool
    {
        if ($this->_isInsideTransaction()) {
            $this->_cacheTransactionScope()?->onCommit(
                function () use ($keyMap, $keyElementName): void {
                    $this->_removeObjectCacheMapElementNow($keyMap, $keyElementName);
                }
            );

            return true;
        }

        return $this->_removeObjectCacheMapElementNow($keyMap, $keyElementName);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function _removeObjectCacheMapElementNow(string $keyMap, string $keyElementName): bool
    {
        $this->_cacheSetConnection();
        if (isset(self::$cache_con) && !empty(self::$cache_con)) {
            self::$cache_con->del(md5($keyElementName));

            return (bool)self::$cache_con->hdel($keyMap, [md5($keyElementName)]); // let the hashset expire by himself instead of calling HLEN and DEL
        }

        return false;
    }

    /**
     * Destroy a key directly when it is known
     *
     * Inside an open transaction the eviction is queued for the commit instead of running.
     * Running it now would be worse than not running it at all: another connection cannot see the
     * uncommitted write, so it misses the cache, reads the old row and caches it again for the full
     * TTL — behind the eviction that has just happened, and outliving the commit.
     *
     * @param string $key
     * @param ?bool $isReverseKeyMap
     *
     * @return bool True when the entry was removed, or when the eviction was queued for the commit.
     *              A queued eviction cannot report what it will find, so the two are not
     *              distinguishable through the return value. Callers that assert on it have to run
     *              outside a transaction.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _deleteCacheByKey(string $key, ?bool $isReverseKeyMap = true): bool
    {
        if ($this->_isInsideTransaction()) {
            $this->_cacheTransactionScope()?->onCommit(
                function () use ($key, $isReverseKeyMap): void {
                    $this->_deleteCacheByKeyNow($key, $isReverseKeyMap);
                }
            );

            return true;
        }

        return $this->_deleteCacheByKeyNow($key, $isReverseKeyMap);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function _deleteCacheByKeyNow(string $key, ?bool $isReverseKeyMap): bool
    {
        $this->_cacheSetConnection();
        if (isset(self::$cache_con) && !empty(self::$cache_con)) {
            if ($isReverseKeyMap) {
                $keyMap = self::$cache_con->get($key);
                if ($keyMap === null) {
                    self::$cache_con->del($key);

                    return false;
                }
                $res = (bool)self::$cache_con->del($keyMap);
                self::$cache_con->del($key);

                return $res;
            }

            return (bool)self::$cache_con->del($key);
        }

        return false;
    }

}