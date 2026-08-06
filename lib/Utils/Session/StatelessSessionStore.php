<?php

declare(strict_types=1);

namespace Utils\Session;


/**
 * The store injected into controllers declared stateless. Every method throws.
 *
 * This is the point of introducing the interface at all. Today "stateless" is only a convention:
 * `KleinController::$useSession` is false and `AuthenticationTrait::identifyUser()` hands the
 * controller an empty local array, but nothing stops that controller reaching straight for
 * `$_SESSION` anyway — and five `KleinController` subclasses did exactly that, calling
 * `sessionStart()` purely to invalidate a session-held cache, until that cache moved to a uid-keyed
 * store. The convention failed silently for as long as it existed.
 *
 * A throwing store converts that class of mistake from "works in production, quietly opens a session
 * on an API endpoint" into a `StatelessSessionViolation` on the first call, in the first test that exercises the
 * path. The failure mode is loud and local, which is the whole trade.
 */
class StatelessSessionStore implements SessionStore
{
    /**
     */
    public function get(string $key): mixed
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     */
    public function set(string $key, mixed $value): void
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     */
    public function has(string $key): bool
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     */
    public function remove(string $key): void
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     * The one method here that does not throw, and the reason is worth keeping.
     *
     * `keys()` answers "which keys are present", and for a stateless controller "none" is both the
     * truthful answer and a complete one — an empty list names nothing and so cannot be used to read
     * state the other methods refuse to hand over.
     *
     * Throwing would be actively harmful. The motivating caller is the `login_exceptions` logger in
     * `AuthenticationHelper`, which runs inside a `catch (Throwable)` that is itself wrapped in a
     * swallowing `try`/`catch`. A throw from here would take the entire log line down with it, on
     * precisely the api-key requests where a failed authentication is hardest to diagnose from
     * outside.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return [];
    }

    /**
     */
    public function regenerateId(): void
    {
        throw $this->refuse(__FUNCTION__);
    }

    /**
     */
    public function clear(): void
    {
        throw $this->refuse(__FUNCTION__);
    }

    public function destroy(): void
    {
        throw $this->refuse(__FUNCTION__);
    }

    /**
     * Names the key as well as the operation: the useful question when this fires is not "which
     * method" but "what was this endpoint trying to read", which points straight at the fix.
     */
    private function refuse(string $operation, ?string $key = null): StatelessSessionViolation
    {
        return new StatelessSessionViolation(sprintf(
            'This controller is declared stateless, so session %s(%s) is not available. Either read the '
            . 'value from the request or from a uid-keyed store, or declare the controller stateful.',
            $operation,
            $key === null ? '' : "'" . $key . "'"
        ));
    }
}
