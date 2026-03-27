<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 08/08/24
 * Time: 14:35
 *
 */

namespace Model\DataAccess;

use Exception;
use Predis\Client;
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
    protected bool $xfetchEnabled = true;

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
     */
    protected function _cacheSetConnection(): void
    {
        if (!isset(self::$cache_con) || empty(self::$cache_con)) {
            try {
                self::$cache_con = (new RedisHandler())->getConnection();
                self::$cache_con->get(1);
            } catch (Exception $e) {
                self::$cache_con = null;
                throw $e;
            }
        }
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
     * @template T of IDaoStruct
     * @param string $keyMap
     * @param string $query A query
     *
     * @return ?T[]
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _getFromCacheMap(string $keyMap, string $query): ?array
    {
        if (AppConfig::$SKIP_SQL_CACHE || $this->cacheTTL == 0) {
            return null;
        }

        $this->_cacheSetConnection();

        $value = null;
        if (isset(self::$cache_con) && !empty(self::$cache_con)) {
            $key = md5($query);
            $value = unserialize(self::$cache_con->hget($keyMap, $key) ?? '');
            $this->_logCache("GETMAP: " . $keyMap, $key, $value, $query);
        }

        return !is_bool($value) ? $value : null;
    }

    /**
     *
     * This method uses a clean, human-readable key instead of a md5 hash.
     * It also allows grouping multiple queries under a single namespace (`$keyMap`).
     *
     * @template T of IDaoStruct
     * @param string $keyMap
     * @param        $query string
     * @param        $value T[]
     *
     * @return void|null
     * @throws Exception
     */
    protected function _setInCacheMap(string $keyMap, string $query, array $value)
    {
        if ($this->cacheTTL == 0) {
            return null;
        }

        if (isset(self::$cache_con) && !empty(self::$cache_con)) {
            $key = md5($query);
            self::$cache_con->hset($keyMap, $key, serialize($value));
            self::$cache_con->expire($keyMap, $this->cacheTTL);
            self::$cache_con->setex($key, $this->cacheTTL, $keyMap);
            $this->_logCache("SETMAP: " . $keyMap, $key, $value, $query);
        }
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
     * Serialize params, ensuring values are always treated as strings.
     *
     * @param array $params
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
     * @param string $keyMap
     * @param string $keyElementName
     *
     * @return bool
     * @throws ReflectionException
     */
    protected function _removeObjectCacheMapElement(string $keyMap, string $keyElementName): bool
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
     * @param string $key
     * @param ?bool $isReverseKeyMap
     *
     * @return bool
     * @throws ReflectionException
     *
     */
    protected function _deleteCacheByKey(string $key, ?bool $isReverseKeyMap = true): bool
    {
        $this->_cacheSetConnection();
        if (isset(self::$cache_con) && !empty(self::$cache_con)) {
            if ($isReverseKeyMap) {
                $keyMap = self::$cache_con->get($key);
                $res = self::$cache_con->del($keyMap);
                self::$cache_con->del($key);

                return $res;
            }

            return self::$cache_con->del($key);
        }

        return false;
    }

}