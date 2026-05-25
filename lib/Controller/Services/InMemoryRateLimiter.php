<?php

namespace Controller\Services;

use Klein\Response;

/**
 * In-memory rate limiter for testing purposes.
 * Mimics RateLimiterService behavior without Redis.
 */
final class InMemoryRateLimiter implements RateLimiterInterface
{
    /** @var array<string, int> */
    private array $counters = [];

    public function checkAndIncrement(Response $response, string $identifier, string $route, int $maxRetries = 10): ?Response
    {
        $key = md5($identifier . $route);

        if (!isset($this->counters[$key])) {
            $this->counters[$key] = 0;
        }

        $this->counters[$key]++;

        if ($this->counters[$key] > $maxRetries) {
            $response->code(429);
            $response->header('Retry-After', '60');

            return $response;
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    public function getCounters(): array
    {
        return $this->counters;
    }

    public function reset(): void
    {
        $this->counters = [];
    }
}

