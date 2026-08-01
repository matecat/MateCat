<?php

declare(strict_types=1);

namespace Utils\Session;

use LogicException;

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
 * on an API endpoint" into a `LogicException` on the first call, in the first test that exercises the
 * path. The failure mode is loud and local, which is the whole trade.
 */
class StatelessSessionStore implements SessionStore
{
    /**
     * @throws LogicException always — this controller is declared stateless
     */
    public function get(string $key): mixed
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     * @throws LogicException always — this controller is declared stateless
     */
    public function set(string $key, mixed $value): void
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     * @throws LogicException always — this controller is declared stateless
     */
    public function has(string $key): bool
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     * @throws LogicException always — this controller is declared stateless
     */
    public function remove(string $key): void
    {
        throw $this->refuse(__FUNCTION__, $key);
    }

    /**
     * @throws LogicException always — this controller is declared stateless
     */
    public function regenerateId(): void
    {
        throw $this->refuse(__FUNCTION__);
    }

    /**
     * @throws LogicException always — this controller is declared stateless
     */
    public function destroy(): void
    {
        throw $this->refuse(__FUNCTION__);
    }

    /**
     * Names the key as well as the operation: the useful question when this fires is not "which
     * method" but "what was this endpoint trying to read", which points straight at the fix.
     */
    private function refuse(string $operation, ?string $key = null): LogicException
    {
        return new LogicException(sprintf(
            'This controller is declared stateless, so session %s(%s) is not available. Either read the '
            . 'value from the request or from a uid-keyed store, or declare the controller stateful.',
            $operation,
            $key === null ? '' : "'" . $key . "'"
        ));
    }
}
