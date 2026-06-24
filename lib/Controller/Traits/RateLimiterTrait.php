<?php

namespace Controller\Traits;

use Controller\Services\RateLimiterService;
use Exception;
use Klein\Response;

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
     * @param RateLimiterService|null $limiterService Optional RateLimiterService instance for dependency injection (useful for testing).
     * @return Response|null  429 response if rate-limited, null if under limit.
     * @throws Exception
     */
    public function checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10, ?RateLimiterService $limiterService = null): ?Response
    {
        return ($limiterService ?? new RateLimiterService())->checkAndIncrement($response, $identifier, $route, $maxRetries);
    }
}
