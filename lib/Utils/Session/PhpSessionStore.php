<?php

declare(strict_types=1);

namespace Utils\Session;

/**
 * The `$_SESSION` adapter, and the only file in the codebase permitted to name that superglobal.
 *
 * Held closed by a custom PHPStan rule (`NoDirectSessionSuperglobalRule`) rather than by convention,
 * modelled on the existing `Bootstrap::getDatabase()` allowlist rule. The allowlist starts wide and
 * shrinks as call sites convert; when it names only this file, the superglobal has exactly one reader
 * and one writer in the tree.
 *
 * Starting the session is not this class's job. Controllers already decide that through
 * `$useSession`, and starting one here would silently give a stateless controller session state —
 * which is precisely the boundary `StatelessSessionStore` exists to enforce.
 */
class PhpSessionStore implements SessionStore
{
    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        /** @var array<string, mixed> $session */
        $session = $_SESSION ?? [];

        return array_map('strval', array_keys($session));
    }

    /**
     * `true` deletes the old id's storage rather than orphaning it, so a stolen pre-rotation id
     * cannot be resumed.
     *
     * Both guards are required, and the second is the less obvious one: rotating emits a Set-Cookie
     * header, so once the response has begun `session_regenerate_id()` raises a warning and does
     * nothing. Returning quietly keeps a caller that rotates late from turning a missed rotation into
     * a visible error, and there is nothing it could do about it at that point anyway.
     */
    public function regenerateId(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        session_regenerate_id(true);
    }

    /**
     * Empties the superglobal and leaves the session running, so the caller can write into it.
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Clears the array as well as destroying the storage: `session_destroy()` alone leaves the live
     * `$_SESSION` array populated for the remainder of the request.
     */
    public function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
