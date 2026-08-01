<?php
/**
 * Created by PhpStorm.
 * Handles session token store operations for user authentication.
 * This class provides methods to manage login tokens in a persistent storage (e.g., Redis),
 * ensuring secure authentication and session management.
 *
 * @package Controller\Abstracts\Authentication
 * @author  Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * @date    08/08/25
 */

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\DataAccess\DaoCacheTrait;
use ReflectionException;
use Utils\Logger\LoggerFactory;

class SessionTokenStoreHandler
{

    use DaoCacheTrait;

    /**
     * Key pattern for storing active user login tokens in the cache.
     * The `%s` placeholder is replaced with the user UID.
     */
    private const string ACTIVE_USER_LOGIN_TOKENS_MAP = 'active_user_login_tokens:%s';

    /**
     * Constructor to initialize the cache TTL (time-to-live).
     * The default TTL is set to 7 days.
     */
    public function __construct()
    {
        $this->cacheTTL = 60 * 60 * 24 * 7; // 7 days
        // Session tokens are written explicitly (not computed from queries),
        // so probabilistic early expiration has no semantic meaning here.
        $this->xFetchEnabled = false;
    }

    /**
     * Log cache operations for debugging and monitoring purposes.
     *
     * @param string $type The type of cache operation (e.g., set, get, remove).
     * @param string $key The cache key being operated on.
     * @param mixed $value The value associated with the cache key.
     * @param string $sqlQuery The SQL query related to the cache operation.
     *
     * @return void
     * @throws Exception
     */
    protected function _logCache(string $type, string $key, mixed $value, string $sqlQuery): void
    {
        LoggerFactory::getLogger("login_cookie_cache")->debug([
            "type" => $type,
            "key" => $key,
            "value" => preg_replace("/ +/", " ", str_replace("\n", " ", $sqlQuery)),
            //"result_set" => $value,
        ]);
    }

    /**
     * Activates a login token for a user in the session token store.
     *
     * This method sets a valid login token in the persistent storage (e.g., Redis)
     * and ensures it is active for the user's session. It should be called only
     * once when the user is authenticated and the session is established
     * or when the cookie expires during the current session.
     *
     * @param int $userId The unique identifier of the user.
     * @param string $loginCookieValue The value of the login cookie to activate.
     *
     * @return void
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    public function setCookieLoginTokenActive(int $userId, string $loginCookieValue): void
    {
        $key = sprintf(self::ACTIVE_USER_LOGIN_TOKENS_MAP, $userId);
        $this->_cacheSetConnection();
        $this->_setInCacheMap($key, $loginCookieValue, [$loginCookieValue]);
    }

    /**
     * Checks if a login token is still active in the session token store.
     *
     * This method validates whether the provided login token is still active
     * in the persistent storage. It should be called when a session does not exist (browser closed or user logged out)
     * but the Cookie is sent by the browser
     * to check that the user has a valid login token. This helps to determine if
     * the user's session is still valid and can be prolonged.
     *
     * @param int $userId The unique identifier of the user.
     * @param string $loginCookieValue The value of the login cookie to validate.
     *
     * @return bool Returns true if the token is active, false otherwise.
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    public function isLoginCookieStillActive(int $userId, string $loginCookieValue): bool
    {
        return $this->_getFromCacheMap(sprintf(self::ACTIVE_USER_LOGIN_TOKENS_MAP, $userId), $loginCookieValue) !== null;
    }

    /**
     * Removes a login token from the session token store.
     *
     * This method removes the specified login token from the persistent storage,
     * effectively invalidating it. It should be called when the user logs out
     * or when the token is no longer valid.
     *
     * @param int $userId The unique identifier of the user.
     * @param string $loginCookieValue The value of the login cookie to remove.
     *
     * @return void
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    public function removeLoginCookieFromStore(int $userId, string $loginCookieValue): void
    {
        if (empty($loginCookieValue)) {
            return;
        }

        $key = sprintf(self::ACTIVE_USER_LOGIN_TOKENS_MAP, $userId);
        $this->_removeObjectCacheMapElement($key, $loginCookieValue);
    }

    /**
     * Revokes every login token issued to a user.
     *
     * Drops the whole per-user token map, so no authentication cookie minted before this call can
     * pass {@see isLoginCookieStillActive()} again. Called when the account's credentials change
     * and every device has to authenticate afresh.
     *
     * This does not end sessions that are already running: the session branch of
     * AuthenticationHelper::authenticate() never consults this map.
     *
     * @param int $userId The unique identifier of the user.
     *
     * @return void
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    public function revokeAllLoginTokens(int $userId): void
    {
        $this->_cacheSetConnection();

        // _cacheSetConnection() either sets a connection or rethrows, so this is unreachable at
        // runtime. It stays because the property is nullable and the calls below are not.
        if (self::$cache_con === null) {
            return;
        }

        $key = sprintf(self::ACTIVE_USER_LOGIN_TOKENS_MAP, $userId);

        // _setInCacheMap() writes each token twice: as a field of the map, and as a standalone
        // reverse-pointer key under the same md5 name. Field names are therefore also reverse-key
        // names, so one HKEYS yields both sets. The reverse keys do expire on their own, but a
        // stale one still holds the map's name — and that name is reused on the next login, so
        // leaving them behind aims a dangling pointer at a live map.
        /** @var list<string> $tokens */
        $tokens = self::$cache_con->hkeys($key);

        if (!empty($tokens)) {
            self::$cache_con->del($tokens);
        }

        self::$cache_con->del($key);
    }

}