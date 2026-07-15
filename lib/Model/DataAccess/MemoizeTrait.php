<?php

namespace Model\DataAccess;

use Throwable;

/**
 * In-memory memoization for instance methods.
 *
 * @example
 *   $model->foo();              // computes and caches
 *   $model->foo();              // returns cached
 *   $model->clearCache()->foo(); // recomputes
 */
trait MemoizeTrait
{
    /** @var array<string, mixed> */
    protected array $cached_results = [];

    /**
     * @param string $cache_key_name
     * @param callable $function
     * @return mixed
     * @throws Throwable
     */
    protected function memoize(string $cache_key_name, callable $function): mixed
    {
        return $this->cached_results[$cache_key_name] ??= $function();
    }

    /**
     * @return $this
     */
    public function forget(): static
    {
        $this->cached_results = [];

        return $this;
    }
}
