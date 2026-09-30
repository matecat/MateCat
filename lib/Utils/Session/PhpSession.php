<?php

declare(strict_types=1);

namespace Utils\Session;

use Exception;

/**
 * The PHP session runtime, and the only file in the codebase permitted to call a `session_*`
 * function or write a `session.*` ini key.
 *
 * The companion to {@see PhpSessionStore}, one layer below it. That class owns `$_SESSION` — the
 * data — while this one owns the session's existence: configuring it, starting it, rotating its id,
 * ending it. Both are thin adapters over process globals, and both are held closed by a PHPStan
 * allowlist rule rather than by convention, because a boundary nothing checks is one that gets
 * crossed invisibly. `NoDirectSessionFunctionRule` names this file and no other.
 *
 * The split matters in one direction in particular: Bootstrap configures and closes the session
 * before any controller exists, so the runtime cannot live behind the controller-side seam. It used
 * to live in the `SessionStarter` trait under `Controller\Abstracts\Authentication`, which made the
 * composition root depend on controller authentication code and left the runtime unreachable from
 * anything that is not a class using that trait.
 *
 * Static because the thing it adapts is a single process-global resource. There is no per-request
 * state here to inject; the injectable seam is {@see SessionStore} above it, which is what tests and
 * stateless controllers substitute.
 */
final class PhpSession
{
    /**
     * The session cookie's shape. Applied before the session starts, since ini keys read at start
     * time have no effect afterwards.
     */
    public static function configure(string $name, string $cookieDomain): void
    {
        ini_set('session.name', $name);
        ini_set('session.cookie_domain', $cookieDomain);
        ini_set('session.cookie_secure', true);
        ini_set('session.cookie_httponly', true);
    }

    /**
     * Idempotent: an already-active session is left alone rather than restarted.
     *
     * A disabled session module throws instead of degrading. MateCat cannot serve a logged-in
     * request without a session, and the bare function only raises a warning and returns false
     * there, which lets a page carry on with no session and no indication of why.
     *
     * @throws Exception when the session module is compiled out
     */
    public static function start(): void
    {
        $status = session_status();

        if ($status === PHP_SESSION_NONE) {
            session_start();
        } elseif ($status === PHP_SESSION_DISABLED) {
            throw new Exception("MateCat needs to have sessions. Sessions must be enabled.");
        }
    }

    public static function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Rotates the session id, deleting the old id's storage rather than orphaning it, so a stolen
     * pre-rotation id cannot be resumed.
     *
     * Both guards are required, and the second is the less obvious one: rotating emits a Set-Cookie
     * header, so once the response has begun `session_regenerate_id()` raises a warning and does
     * nothing. Returning quietly keeps a caller that rotates late from turning a missed rotation into
     * a visible error, and there is nothing it could do about it at that point anyway.
     */
    public static function regenerateId(): void
    {
        if (!self::isActive() || headers_sent()) {
            return;
        }

        session_regenerate_id(true);
    }

    /**
     * Destroys the stored session. The live `$_SESSION` array is not this class's to clear —
     * {@see PhpSessionStore::destroy()} empties it and then calls this.
     */
    public static function destroy(): void
    {
        if (self::isActive()) {
            session_destroy();
        }
    }

    /**
     * Writes the session out and releases its lock, ending the serialisation of concurrent requests
     * from the same browser.
     *
     * Registered as a shutdown function, which is why the warning is suppressed: at shutdown a
     * session that was never started, or already closed, has nowhere useful to report that to, and
     * the notice would land in output that has already been sent.
     */
    public static function close(): void
    {
        @session_write_close();
    }
}
