<?php
/**
 * Uid-keyed store for computed, cacheable user state.
 *
 * A sibling of {@see SessionTokenStoreHandler}: same DaoCacheTrait machinery, its own key
 * namespace. Deliberately *not* the token ring's map — `DEL active_user_login_tokens:<uid>` is
 * complete revocation, and storing application state there would make revoking a login delete
 * data, while every token write would refresh that data's TTL.
 *
 * @package Controller\Abstracts\Authentication
 * @author  Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * @date    31/07/26
 */

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\DataAccess\DaoCacheTrait;
use ReflectionException;

class UserStateStore
{

    use DaoCacheTrait;

    /**
     * Key pattern for the per-user state hash. The `%d` placeholder is replaced with the user UID,
     * mirroring {@see SessionTokenStoreHandler::ACTIVE_USER_LOGIN_TOKENS_MAP}: one hash per user,
     * never a single shared hash holding every user's state.
     */
    private const string USER_STATE_MAP = 'user_state:%d';

    /**
     * Field pattern for the cached user-profile payload.
     */
    private const string USER_PROFILE_FIELD = 'user_profile:%d';

    public function __construct()
    {
        // Backstop only. Freshness comes from invalidation at the DAO write boundaries; this TTL
        // exists to collect the state of users who stop coming back.
        //
        // Set through setCacheTTL() rather than by assigning $this->cacheTTL directly:
        // SessionTokenStoreHandler assigns directly to bypass AppConfig::$SKIP_SQL_CACHE, because a
        // login token has to be stored whatever the caching configuration says. This is a cache, so
        // it must honour that kill switch instead.
        $this->setCacheTTL(60 * 60 * 24); // 24 hours

        // $xFetchEnabled is left at the trait's default of true, again unlike the token handler,
        // which disables it because a token is stored state rather than a computed value. The
        // profile is expensive to compute, so probabilistic early recomputation ahead of expiry is
        // exactly what it wants: without it, keys that lapse together cause a rebuild stampede.
    }

    /**
     * The Redis key of the per-user state hash.
     */
    private function mapKey(int $userId): string
    {
        return sprintf(self::USER_STATE_MAP, $userId);
    }

    /**
     * The profile field name, which carries the uid just as the map key does.
     *
     * This is a correctness requirement, not a naming preference. {@see DaoCacheTrait::_setInCacheMap()}
     * stores the field as `md5($query)` and *also* writes a global reverse key,
     * `setex(md5($query), ttl, $keyMap)` (`:249-251`). A uid-less field string would hash to the
     * same value for every user, so that one shared reverse key would point at whichever user's map
     * was written last — and both {@see DaoCacheTrait::_removeObjectCacheMapElement()} (which
     * `del`s it) and {@see DaoCacheTrait::_deleteCacheByKey()} (which follows it to a map and `del`s
     * that) act on it. One user's invalidation would then reach into another user's state. Keying
     * the field by uid keeps every entry, forward and reverse, scoped to a single user — the same
     * property the ring gets for free from `md5($cookieValue)`.
     */
    private function profileField(int $userId): string
    {
        return sprintf(self::USER_PROFILE_FIELD, $userId);
    }

    /**
     * The cached profile payload, or null on a miss.
     *
     * @return array<string, mixed>|null
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function getProfile(int $userId): ?array
    {
        $cached = $this->_getFromCacheMap($this->mapKey($userId), $this->profileField($userId));

        // The payload is stored as the single element of a list, as the trait's contract requires.
        // Anything else is treated as a miss and rebuilt, rather than served half-formed.
        $profile = $cached[0] ?? null;

        return is_array($profile) ? $profile : null;
    }

    /**
     * @param array<string, mixed> $profile
     * @param float $computeDelta How long the build took, in seconds.
     *
     *        Passed on to the trait so the XFetch envelope carries a measured cost. A consumer that
     *        omits this inherits the fallback delta of 0.05s, which understates a build of this size
     *        by orders of magnitude and collapses the early-recomputation window to nothing.
     *        AbstractDao::_fetchObjectMap() measures it for query results; a non-DAO consumer has to
     *        measure it itself.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function setProfile(int $userId, array $profile, float $computeDelta): void
    {
        $this->_setLastComputeDelta($computeDelta);

        // _setInCacheMap() does not open the connection itself: it silently does nothing unless one
        // is already set, so it has to be established here.
        $this->_cacheSetConnection();

        $this->_setInCacheMap($this->mapKey($userId), $this->profileField($userId), [$profile]);
    }

    /**
     * Drop the cached profile, leaving every other field of this user's state intact.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function invalidateProfile(int $userId): bool
    {
        return $this->_removeObjectCacheMapElement($this->mapKey($userId), $this->profileField($userId));
    }

    /**
     * Drop this user's whole state hash.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function invalidate(int $userId): bool
    {
        // isReverseKeyMap = false: the argument is the hash itself, not a reverse key holding the
        // name of one.
        return $this->_deleteCacheByKey($this->mapKey($userId), false);
    }

}
