<?php

namespace Controller\Services;

use Exception;
use Klein\Response;

/**
 * Contract for rate-limiting services.
 */
interface RateLimiterInterface
{
    /**
     * Atomically increment the rate limit counter and check whether the limit has been exceeded.
     *
     * @param Response $response
     * @param string   $identifier  Stable, attacker-invariant identifier (email, IP).
     * @param string   $route       Static route pattern.
     * @param int      $maxRetries
     * @return Response|null  429 response if rate-limited, null if under limit.
     * @throws Exception
     */
    public function checkAndIncrement(Response $response, string $identifier, string $route, int $maxRetries = 10): ?Response;
}

