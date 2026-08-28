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
     *
     * **What this hash holds.** Computed, cacheable state *about* one user, one field per datum —
     * currently only the rendered profile ({@see self::USER_PROFILE_FIELD}). It is a cache: every
     * field must be reconstructible from the database, because any field can vanish at any moment
     * (24h TTL, an explicit invalidation, or a `flushdb` from an unrelated test). Nothing may treat a
     * hit here as proof of anything.
     *
     * **What it must never hold, and why each is excluded.**
     *
     * - *Anything credential-bearing* — password hash, salt, `confirmation_token`. `UserStruct`
     *   declares all three, so serialising a whole user row into any long-lived store puts secrets at
     *   rest with a lifetime nobody purges on password change. That is the defect being walked back,
     *   not a shape to reproduce under a new key name.
     * - *The users row itself.* {@see \Model\Users\UserDao::getByUid()} is already a uid-keyed Redis
     *   cache with its own TTL and an explicit `destroyCacheByUid()`. A second copy here would be a
     *   cache of a cache with independent invalidation, i.e. two sources of truth that drift.
     * - *Authorization decisions.* Identity is resolved from the login-token ring on every request.
     *   If a field here could authorise, `DEL user_state:<uid>` would become a security operation and
     *   this class would need the ring's guarantees.
     * - *Login tokens.* They live in the ring's own map, for the reasons in the class docblock above.
     *
     * **Lifetime is per hash, not per field.** {@see DaoCacheTrait::_setInCacheMap()} re-`EXPIRE`s the
     * whole hash on every write, so writing any one field refreshes the clock of all the others — an
     * active user's fields never self-collect. The per-field age bound is `XFetchEnvelope.storedAt`
     * inside the stored value, so that is what to read if a hard staleness ceiling is ever needed.
     * Freshness comes from invalidation at the DAO write boundaries; this TTL only collects the state
     * of users who stop returning.
     */
    private const string USER_STATE_MAP = 'user_state:%d';

    /**
     * Field pattern for the cached user-profile payload.
     *
     * **What this field holds.** The fully rendered response body of `GET /api/app/user` — the array
     * built by {@see UserProfileBuilder::build()} and shaped by {@see \View\API\App\Json\UserProfile}:
     * the user, their connected services, their teams (each with its full member list) and their
     * metadata. It is the *rendered payload*, not the domain objects it was rendered from, so a reader
     * needs no DAOs to serve it.
     *
     * **Why it is worth caching at all**, given the underlying DAO reads are themselves cached: the
     * cost is round trips, not queries. The payload fans out per member — `Membership::renderItem()`
     * resolves a user for every member of every team — and each of those resolutions is a separate
     * sequential Redis read, because {@see \Model\Teams\MembershipStruct::getUser()} does set a 24h
     * TTL on the DAO it is handed. Add a Redis round trip per team for pending invitations, plus one
     * read that is uncached SQL ({@see \Model\ConnectedServices\ConnectedServiceDao::findServicesByUser()}),
     * and a manager in several large teams pays a latency linear in their total membership — of the
     * order of a hundred sequential round trips — at one to two calls per page load.
     *
     * An earlier revision of this docblock justified the store by claiming those per-member reads go
     * through a TTL-less DAO and are therefore live SQL. That was wrong, and it mattered: it named a
     * cost an order of magnitude larger than the real one. The store is still worth its keep on the
     * round-trip count above, which is the honest reason.
     *
     * **Storage shape.** Wrapped in a single-element list, as the trait's contract requires, inside an
     * XFetch envelope carrying the measured build cost — see {@see self::setProfile()} for why that
     * measurement has to be passed in rather than left to the trait's fallback.
     *
     * **Staleness is bounded by invalidation, not by the TTL.** Every DAO write that can change any
     * part of this payload drops the field: the users row, user metadata, connected services, team
     * membership, team renames and per-team project counts.
     *
     * A gap used to be recorded here rather than fixed: a renamed user stayed stale inside *other*
     * members' cached team lists for up to 24h, because those names came from a `getByUids()`
     * `IN (...)` query whose cache key was addressed by the whole uid list, and `destroyCacheByUid()`
     * — which knows one uid — could never name it. Both member-list reads now cache one entry per
     * uid, at the same address their single-uid accessors use, so the single-uid doors reach them.
     * See {@see \Model\DataAccess\AbstractDao::_fetchObjectMapPerId()}.
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
