<?php

namespace Controller\Abstracts\Authentication;

use Utils\Constants\Constants;
use Utils\Registry\AppConfig;

/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 19/04/22
 * Time: 18:41
 *
 * Instance-based cookie writer: callers use `set()` / `delete()` (or the
 * `setEmptyCookieValueIfMissing()` helper) over the `writeCookie()` seam.
 */
class CookieManager
{

    /**
     * Fully-configurable cookie setter. Defaults are the secure baseline
     * (Secure + HttpOnly + SameSite=Strict, path `/`, domain COOKIE_DOMAIN);
     * override only what a specific cookie needs.
     *
     * @param int $expires absolute unix timestamp (0 = session cookie)
     * @param string|null $domain null falls back to AppConfig::$COOKIE_DOMAIN
     */
    public function set(
        string $name,
        string $value = '',
        int $expires = 0,
        bool $secure = true,
        bool $httpOnly = true,
        string $sameSite = 'Strict',
        string $path = '/',
        ?string $domain = null
    ): bool {
        return $this->writeCookie($name, $value, [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain ?? AppConfig::$COOKIE_DOMAIN,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);
    }

    /**
     * Removes a cookie: drops it from the current request array and emits a
     * past-dated, empty overwrite so the browser discards it.
     */
    public function delete(string $name, string $path = '/', ?string $domain = null): bool
    {
        unset($_COOKIE[$name]);

        return $this->set($name, '', time() - 3600, true, false, 'None', $path, $domain);
    }

    /**
     * Seeds a same-site preference cookie with an empty placeholder value, but only when the
     * client is not already sending it (used to initialise the create-project language pickers).
     */
    public function setEmptyCookieValueIfMissing(string $cookieName): bool
    {
        if (isset($_COOKIE[$cookieName])) {
            return false;
        }

        return $this->set($cookieName, Constants::EMPTY_VAL, time() + (86400 * 365));
    }

    /**
     * Low-level write seam (overridable in tests to capture the emitted cookie).
     *
     * @param array<string, mixed> $options
     */
    protected function writeCookie(string $name, string $value, array $options): bool
    {
        if (headers_sent()) {
            return false;
        }

        return setcookie($name, $value, $options);
    }

}
