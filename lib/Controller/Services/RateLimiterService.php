<?php

namespace Controller\Services;

use DateTime;
use Exception;
use Klein\Response;
use Predis\Client;
use Utils\Redis\RedisHandler;

/**
 * Standalone rate-limiter service.
 *
 * Encapsulates Redis-based fixed-window rate limiting using an
 * atomic counter (INCR) with TTL-based expiration, decoupled from
 * any controller or trait dependency.
 *
 * The counter is bucketed by key and expires on a minute-aligned
 * boundary plus one additional minute; this is not a true
 * sliding-window algorithm.
 */
class RateLimiterService
{
    private ?Client $redis = null;

    /**
     * @param Client|null $redis
     * @throws Exception
     */
    public function __construct(?Client $redis = null)
    {
        $this->redis = $redis ?? (new RedisHandler())->getConnection();
    }

    /**
     * Atomically increment the rate limit counter and check whether the limit has been exceeded.
     * Uses Redis INCR (atomic) to avoid TOCTOU race conditions.
     *
     * @param Response $response
     * @param string   $identifier  Stable, attacker-invariant identifier (email, IP). NEVER a secret.
     * @param string   $route       Static route pattern. NEVER include passwords, tokens, or secrets.
     * @param int      $maxRetries
     * @return Response|null  429 response if rate-limited, null if under limit.
     * @throws Exception
     */
    public function checkAndIncrement(Response $response, string $identifier, string $route, int $maxRetries = 10): ?Response
    {
        $key   = $this->getKey($identifier, $route);
        $redis = $this->getRedis();

        $current = $redis->incr($key);

        if ($current === 1) {
            $redis->expire($key, $this->getTtl());
        }

        if ($current > $maxRetries) {
            $response->code(429);
            // PENALTY: reset ttl first, then report accurate Retry-After
            $redis->expire($key, $this->getTtl());
            $response->header("Retry-After", $redis->ttl($key));

            return $response;
        }

        return null;
    }

    /**
     * @return Client
     * @throws Exception
     */
    private function getRedis(): Client
    {
        if ($this->redis === null) {
            $this->redis = (new RedisHandler())->getConnection();
        }

        return $this->redis;
    }

    private function getKey(string $identifier, string $route): string
    {
        return md5($identifier . $route);
    }

    /**
     * Returns the end of the current minute + 1 minute (in seconds).
     *
     * Example: 12:30:46 → returns 14 + 60 = 74
     */
    private function getTtl(): int
    {
        $date = new DateTime();
        $ttl = 60 - (int)$date->format("s");

        return 60 + $ttl;
    }
}

