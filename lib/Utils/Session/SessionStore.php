<?php

declare(strict_types=1);

namespace Utils\Session;

/**
 * Per-device storage for application state.
 *
 * The seam that makes migrating off PHP sessions possible. Every consumer depends on this interface
 * rather than on `$_SESSION`, so a key group can move to a different backing store by swapping the
 * implementation at the composition root instead of editing every call site twice.
 *
 * This is deliberately *not* where user identity or credentials live. Identity is resolved from the
 * login-token ring on every request, and user-scoped cached data belongs in the uid-keyed
 * `UserStateStore`. What stays here is state bound to a browser rather than to a user — the login
 * CSRF token, OAuth `state`, the wanted url, flash messages — plus post-login application state that
 * has no other home.
 *
 * @see \Controller\Abstracts\Authentication\UserStateStore for uid-keyed user state
 */
interface SessionStore
{
    /**
     * Read one key. Returns null when absent, so callers cannot tell a missing key from a stored
     * null — deliberate, because no consumer stores a meaningful null.
     */
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    /**
     * Remove one key. A no-op when the key is absent.
     */
    public function remove(string $key): void;

    /**
     * Rotate the storage id, keeping the contents. This is the session-fixation defence, so it
     * belongs on a true anonymous-to-authenticated transition and nowhere else.
     */
    public function regenerateId(): void;

    /**
     * Discard everything for this device.
     */
    public function destroy(): void;
}
