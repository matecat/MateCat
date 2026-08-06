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
use Predis\Client;
use ReflectionException;
use RuntimeException;
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
     * The Redis key of the per-user token ring.
     */
    private function mapKey(int $userId): string
    {
        return sprintf(self::ACTIVE_USER_LOGIN_TOKENS_MAP, $userId);
    }

    /**
     * The Redis connection, guaranteed non-null.
     *
     * {@see DaoCacheTrait::_cacheSetConnection()} either sets a connection or rethrows, so the null
     * case is unreachable — but the property it fills is nullable, so something has to narrow it.
     * Doing that here, once, keeps every caller free of a dead null branch.
     *
     * This raises rather than returning null on purpose: a caller that silently did nothing would
     * be the worst outcome, since these operations revoke access. In practice the throw comes from
     * _cacheSetConnection() one line earlier, so this is not a new failure mode.
     *
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    private function connection(): Client
    {
        $this->_cacheSetConnection();

        return self::$cache_con ?? throw new RuntimeException('Redis connection unavailable');
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
        $key = $this->mapKey($userId);
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
        return $this->_getFromCacheMap($this->mapKey($userId), $loginCookieValue) !== null;
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

        // Ring field names are md5 of the cookie value, so this is retireLoginToken() with the
        // hashing done on the caller's behalf. Keeping one implementation of the two-key delete is
        // what stops the map field and its reverse key from drifting apart.
        $this->retireLoginToken($userId, md5($loginCookieValue));
    }

    /**
     * Revokes every login token issued to a user.
     *
     * Drops the whole per-user token map, so no authentication cookie minted before this call can
     * pass {@see isLoginCookieStillActive()} again. Called when the account's credentials change
     * and every device has to authenticate afresh.
     *
     * Since {@see AuthenticationHelper::authenticate()} revalidates against this map on every
     * request, dropping it ends running sessions too: the next request from any device finds no
     * live token and is anonymous, whatever its PHP session still holds.
     *
     * @param int $userId The unique identifier of the user.
     *
     * @return void
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    public function revokeAllLoginTokens(int $userId): void
    {
        $redis = $this->connection();

        $key = $this->mapKey($userId);

        // _setInCacheMap() writes each token twice: as a field of the map, and as a standalone
        // reverse-pointer key under the same md5 name. Field names are therefore also reverse-key
        // names, so one HKEYS yields both sets. The reverse keys do expire on their own, but a
        // stale one still holds the map's name — and that name is reused on the next login, so
        // leaving them behind aims a dangling pointer at a live map.
        /** @var list<string> $tokens */
        $tokens = $redis->hkeys($key);

        // One key per DEL, deliberately. A single multi-key DEL would be fewer round trips, but the
        // reverse keys are named by md5 while the map is named by uid, so they land in different
        // hash slots — and under REDIS_MODE=cluster Predis refuses a DEL whose keys span slots
        // ("Cannot use 'DEL' with redis-cluster.", RedisCluster::getConnection()). That exception
        // would surface from a password change *after* the new password was already stored, leaving
        // the old tokens live: the exact gap this method exists to close. Single-key DELs route to
        // one node each and behave identically in every REDIS_MODE. DaoCacheTrait does the same for
        // this same map/reverse-key pair.
        //
        // Reverse keys go first and the map last. A stale reverse key still holds the map's name,
        // and that name is reused on the next login, so dropping the map first would aim dangling
        // pointers at a live map. The reverse order leaves only absent pointers, which is harmless.
        //
        // Not a loop over retireLoginToken(): its HDEL would be wasted work, since the whole map is
        // deleted here anyway. That primitive is for single-token removal; this is the bulk path.
        foreach ($tokens as $token) {
            $redis->del($token);
        }

        $redis->del($key);
    }

    /**
     * Retires a single login token addressed by its ring field name.
     *
     * This is the one place that knows a token occupies two Redis keys — the map field and the
     * reverse key of the same name — so every single-token removal goes through here.
     *
     * It takes the hash rather than the cookie value because its callers already hold one: the
     * `prev` claim of a renewed cookie stores the superseded token's field name, which *is* that
     * md5. Hashing it again would address a field that does not exist. {@see
     * removeLoginCookieFromStore()} is the adapter for callers holding a raw cookie value instead.
     *
     * @param int $userId The unique identifier of the user.
     * @param string $fieldName The ring field name (an md5 of the superseded cookie value).
     *
     * @return void
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    public function retireLoginToken(int $userId, string $fieldName): void
    {
        if (empty($fieldName)) {
            return;
        }

        $redis = $this->connection();

        $key = $this->mapKey($userId);

        // Field name and reverse-key name are the same string, so both have to go.
        $redis->hdel($key, [$fieldName]);
        $redis->del($fieldName);
    }

    /**
     * Drops tokens the caller judges expired.
     *
     * Redis here is 7.1.0, so there is no HEXPIRE (7.4+) and hash fields carry no individual TTL.
     * Worse, {@see DaoCacheTrait::_setInCacheMap()} refreshes the *whole map's* TTL on every write,
     * so for a user who keeps logging in nothing in the map ever expires on its own. Renewal
     * retires the grandparent and keeps the steady state at roughly two fields per device, which
     * leaves exactly one leak this closes: fields belonging to devices that stopped coming back.
     * Those hold expired tokens, so they cannot authenticate — this is hygiene, not a hole.
     *
     * The expiry judgement is the caller's because JWT parsing needs the auth secret, which this
     * class has no business knowing. Anything the predicate cannot judge is kept: a slightly
     * larger hash beats logging someone out over an unreadable value.
     *
     * @param int $userId The unique identifier of the user.
     * @param callable(string): bool $isExpired Receives a stored cookie value, returns true to drop it.
     *
     * @return void
     * @throws ReflectionException If there is an issue with the cache operation.
     * @throws Exception
     */
    public function pruneExpiredLoginTokens(int $userId, callable $isExpired): void
    {
        $redis = $this->connection();

        /** @var array<string, string> $fields */
        $fields = $redis->hgetall($this->mapKey($userId));

        foreach ($fields as $fieldName => $storedValue) {
            // _setInCacheMap() wraps values in an XFetchEnvelope or not depending on $xFetchEnabled
            // at write time, so go through the trait's unwrap rather than assuming the bare shape.
            // This class disables xFetch, but values written under a different setting can outlive
            // that decision in Redis.
            $unserialized = $this->_unwrapCacheMapValue(unserialize($storedValue));
            $token = is_array($unserialized) ? ($unserialized[0] ?? null) : null;

            if (!is_string($token) || !$isExpired($token)) {
                continue;
            }

            $this->retireLoginToken($userId, $fieldName);
        }
    }

}