<?php

namespace Controller\Traits;

use Controller\Services\RateLimiterService;
use Exception;
use Klein\Response;

trait RateLimiterTrait
{
    private RateLimiterService $limiterService;

    /**
     * Provides an instance of the RateLimiterService.
     *
     * This method is responsible for providing access to the rate limiter service,
     * which is used to enforce and manage rate-limiting rules across the application.
     * The returned service instance can be utilized to check and increment request
     * limits based on predefined keys, ensuring that API or system usage adheres to
     * the configured restrictions.
     *
     * @return RateLimiterService The rate limiter service instance.
     * @throws Exception
     */
    protected function getRateLimiterService(): RateLimiterService
    {
        if (!isset($this->limiterService)) {
            $this->limiterService = new RateLimiterService();
        }

        return $this->limiterService;
    }

    protected function setRateLimiterService(RateLimiterService $limiterService): void
    {
        $this->limiterService = $limiterService;
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
    public function checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10): ?Response
    {
        return $this->getRateLimiterService()->checkAndIncrement($response, $identifier, $route, $maxRetries);
    }
}
