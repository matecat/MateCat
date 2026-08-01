<?php

declare(strict_types=1);

namespace Utils\Session;

/**
 * In-memory store, for tests and for any caller that wants session-shaped storage without a session.
 *
 * This exists to remove global state from tests. A test that manipulates `$_SESSION` leaks into the
 * next one unless `tearDown()` resets it, and forgetting that reset produces a failure in an
 * unrelated test — which is the expensive kind to diagnose. An instance per test cannot leak.
 *
 * It also removes the `session_start()` problem: code reached through this store needs no active
 * session, so it does not require `#[RunInSeparateProcess]` to dodge "headers already sent".
 */
class ArraySessionStore implements SessionStore
{
    /**
     * @param array<string, mixed> $data seed state, so a test can arrange without a setter loop
     */
    public function __construct(private array $data = [], private int $regenerations = 0)
    {
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerateId(): void
    {
        // No id to rotate, so record that it was asked for: rotation is a security-relevant action
        // and a test asserting it happened needs something observable.
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
    }

    /**
     * How many times rotation was requested. Lets a test pin the fixation defence.
     */
    public function regenerationCount(): int
    {
        return $this->regenerations;
    }

    /**
     * The whole contents, for assertions about what a unit under test wrote.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
