<?php

namespace Controller\Traits;

use DateTime;
use Exception;
use Klein\Response;
use Predis\Client;
use Utils\Redis\RedisHandler;

trait RateLimiterTrait
{
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
    public function checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10): ?Response
    {
        $key   = $this->getKey($identifier, $route);
        $redis = $this->getRedis();

        $current = $redis->incr($key);

        if ($current === 1) {
            $redis->expire($key, $this->getTtl());
        }

        if ($current > $maxRetries) {
            $response->code(429);
            $response->header("Retry-After", $redis->ttl($key));
            // PENALTY: reset ttl
            $redis->expire($key, $this->getTtl());
            return $response;
        }

        return null;
    }

    /**
     * @return Client
     *
     * @throws Exception
     */
    private function getRedis(): Client
    {
        $redisHandler = new RedisHandler();

        return $redisHandler->getConnection();
    }

    /**
     * @param string $identifier
     * @param string $route
     *
     * @return string
     */
    private function getKey(string $identifier, string $route): string
    {
        return md5($identifier . $route);
    }

    /**
     * This function returns the end of the current minute + 1 minute (in seconds).
     *
     * Example:
     *
     * 12:30:46 ---> returns 14 + 60
     *
     * @return int
     */
    private function getTtl(): int
    {
        $date = new DateTime();
        $ttl = 60 - $date->format("s");

        return 60 + (int)$ttl;
    }
}
