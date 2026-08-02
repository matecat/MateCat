<?php

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\IDatabase;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Throwable;
use TypeError;
use Utils\Logger\LoggerFactory;
use Utils\Session\SessionStore;
use Utils\Session\StatelessSessionViolation;

/**
 * Resolves the identity behind a request: api key, or login cookie validated against the token ring.
 *
 *  - all collaborators are constructor-injected (UserDao, ApiKeyDao, AuthCookie) → fully
 *    unit-testable, no singleton;
 *  - the authentication work lives in authenticate() instead of the constructor;
 *  - fromRequest() is the single composition root that touches the database.
 */
class AuthenticationHelper
{
    private UserStruct $user;
    private bool $logged = false;
    private ?ApiKeyStruct $api_record = null;
    private SessionStore $session;
    private UserDao $userDao;
    private ApiKeyDao $apiKeyDao;
    private AuthCookie $authCookie;

    public function __construct(
        SessionStore $session,
        UserDao $userDao,
        ApiKeyDao $apiKeyDao,
        AuthCookie $authCookie,
    ) {
        $this->session = $session;
        $this->userDao = $userDao;
        $this->apiKeyDao = $apiKeyDao;
        $this->authCookie = $authCookie;
        $this->user = new UserStruct();
    }

    /**
     * Composition root: wires real collaborators from an injected database
     * handle and runs the authentication flow. The database is mandatory — no
     * singleton fallback. Mirrors the original `new AuthenticationHelper(...)`.
     */
    public static function fromRequest(
        SessionStore $session,
        IDatabase $db,
        ?string $api_key = null,
        ?string $api_secret = null,
    ): self {
        $self = new self(
            $session,
            new UserDao($db),
            new ApiKeyDao($db),
            new AuthCookie(new SessionTokenStoreHandler()),
        );
        $self->authenticate($api_key, $api_secret);

        return $self;
    }

    /**
     * Resolve the user from api-key, session, or login cookie. Never throws:
     * any failure is logged and leaves the helper in a logged-out state.
     */
    public function authenticate(?string $api_key, ?string $api_secret): void
    {
        try {
            if ($this->validKeys($api_key, $api_secret) && $this->api_record !== null) {
                $user = $this->api_record->getUser($this->userDao);
                if ($user !== null) {
                    $this->user = $user;
                }
            } else {
                // The token ring is the only authority: every request revalidates the login
                // cookie against it, so DEL active_user_login_tokens:<uid> logs the user out
                // everywhere at once. The PHP session used to be consulted first and never
                // consulted the ring, which is what let a revoked user keep working until their
                // session died of idleness.
                $credentials = $this->authCookie->getCredentials();
                if (!empty($credentials) && !empty($credentials['user'])) {
                    $uid = (int)$credentials['user']['uid'];

                    // Resolved from the uid the ring just proved, on every request. There used to be a
                    // session-cached copy of this UserStruct consulted first, which is what put the
                    // password hash, salt and confirmation token into the session's Redis database for
                    // the session's idle lifetime. Dropping it costs nothing: getByUid() is itself a
                    // uid-keyed Redis cache with the TTL set right below, so this is the same single
                    // Redis read the session lookup was, against a store that is invalidated when the
                    // users row changes.
                    //
                    // The uid-equality guard that used to sit here went with it. It existed because a
                    // session holding user A alongside a cookie for user B would have served A's cached
                    // struct to B; with no cached struct there is nothing to mismatch.
                    $this->userDao->setCacheTTL(60 * 60 * 24);
                    $user = $this->userDao->getByUid($uid);
                    if ($user !== null) {
                        $this->user = $user;
                        $this->setUserSession();
                    }

                    // Slide the cookie forward while the user is active. This replaces the
                    // post-expiry revamp the session branch used to perform: with the ring
                    // authoritative an expired JWT resolves to no uid at all, so renewal has to
                    // happen before expiry or everyone is signed out hard at the cookie duration.
                    if ($this->user->uid !== null) {
                        $this->authCookie->renewIfStale($this->user);
                    }
                }
            }
        } catch (Throwable $ignore) {
            // Log any exceptions encountered during the authentication process.
            try {
                LoggerFactory::getLogger('login_exceptions')->debug(
                    [
                        $ignore,
                        $ignore->getTraceAsString(),
                        // Keys only. The session holds the UserStruct, whose `pass` is the password
                        // hash, so dumping the whole array wrote credentials into login_exceptions.
                        // Which keys were present is what these logs are actually read for.
                        'session_keys' => $this->session->keys(),
                        // The key is the public identifier and stays. The secret is the shared
                        // secret and never belongs in a log; whether one was sent is the useful bit.
                        'api_key' => $api_key,
                        'api_secret_present' => !empty($api_secret),
                        // Reuse what the try block already read. Calling getCredentials() again here
                        // would re-verify the JWT and hit the token ring a second time just to log,
                        // and it may not have been reached before the throw — hence the ?? null.
                        'cookie' => $credentials['user'] ?? null,
                    ]
                );
            } catch (Throwable) {
            }

            // A session violation is a bug, not a rejected credential, so it is the one thing here
            // that must not be swallowed.
            //
            // StatelessSessionViolation is raised when a controller declared stateless reaches for
            // session state. It is deliberately an unchecked \Error so that it surfaces — phpstan.neon
            // says exactly that: "Enforcement works by surfacing." This catch absorbed it into "not
            // authenticated" instead, so every cookie-authenticated /api/v2 and /api/v3 request
            // answered 401 Invalid Login while holding a perfectly valid token. A programming mistake
            // must not be able to impersonate a failed login.
            //
            // Everything else still degrades to logged-out, deliberately. An \Exception here is a
            // runtime condition, and the ring check reaches Redis: re-throwing those would turn one
            // unavailable dependency into a site-wide 500 rather than a signed-out user.
            //
            // Narrowed to this class rather than to \Error in general because \Error is checked by
            // PHPStan, so re-throwing it demands a @throws on authenticate() and then on fromRequest(),
            // identifyUser() and every controller beneath them. Widening this would mean adding \Error
            // to the uncheckedExceptionClasses list, which is a repo-wide policy change and not one to
            // make in passing. The consequence is honest and worth knowing: a TypeError on this path
            // still degrades to logged-out.
            //
            // Logged before re-throwing either way, because that log line is the diagnostic.
            if ($ignore instanceof StatelessSessionViolation) {
                throw $ignore;
            }
        } finally {
            $this->logged = $this->user->isLogged();
        }
    }

    /**
     * Log out: retire the tokens and drop the cookie first, end the session last.
     *
     * The order is half the content of this method. Revocation is what actually ends the login —
     * identity is decided by the ring, never by the session — and it is also the only step that can
     * fail, because it reaches Redis. Ending the session first meant a throw there left the worst
     * possible pair of states: no `uid`, so the app treats the request as signed out, while the
     * cookie is still in the browser and its token still in the ring, so the very next request
     * authenticates normally. Doing the authoritative work first makes a failed logout leave the
     * user plainly still logged in, which is a state the app can represent and the user can retry.
     *
     * The other half is that the whole session goes, not just the `uid` marker. Logging out should
     * not leave a signed-out browser holding the previous user's flash messages, GDrive tokens or
     * cart, and this is the one place that knows a logout is happening. {@see AuthCookie} used to
     * call session_destroy() itself from inside its cookie-clearing helper, which both put the
     * session's lifecycle in the hands of a cookie class and tied it to a second path — a cookie that
     * merely failed to parse. That path no longer ends the session.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function destroyAuthentication(): void
    {
        $this->authCookie->destroyAuthentication();
        $this->session->destroy();
    }

    protected function sessionIsActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Record which user this session belongs to, and rotate the id while doing it.
     *
     * The uid is all that is written. Two things used to be written alongside it and are not:
     *
     *  - `user`, the whole {@see UserStruct}. That struct declares `pass`, `salt` and
     *    `confirmation_token`, so storing it serialised the password hash, the salt and the
     *    confirmation token into the session's Redis database, where they sat for the session's idle
     *    lifetime and were not purged when the password changed. Readers now take the user from the
     *    request's own authenticated identity, or from {@see UserDao::getByUid()} — already a
     *    uid-keyed Redis cache, and one that credentials-at-rest aside is invalidated on write.
     *  - `cid`, the email. It had no reader left anywhere in the tree once the translated plugin
     *    started taking the acting user from the event it handles.
     *
     * The uid stays, and is not a candidate for moving to {@see UserStateStore}: that store is keyed
     * `user_state:<uid>`, so the uid has to be readable *before* it can be used to find anything
     * there. It is not a credential — an integer the token ring re-proves on every request — and it
     * is what tells this session whose it is.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    protected function setUserSession(): void
    {
        if ($this->sessionIsActive()) {
            // Rotate only on the actual anonymous → authenticated transition: every login path
            // (password, signup confirmation, OAuth) reaches it through fromRequest(), and an id
            // known before the login must not still be valid after it.
            //
            // The explicit comparison matters now that this method runs on *every* authenticated
            // request. It used to be reached only on a session-cache miss, so the transition test was
            // implicit in getting here at all; with the cache gone, rotating unconditionally would
            // churn the id on every request and race parallel ones — for nothing, since the
            // privilege transition already happened.
            // Rotation goes through the store, which is what owns the session lifecycle and therefore
            // the two conditions that make rotating safe — an active session, and a response that has
            // not started. This class used to carry a copy of those guards, leaving
            // PhpSessionStore::regenerateId() with no callers at all.
            if ((int)$this->session->get('uid') !== (int)$this->user->getUid()) {
                $this->session->regenerateId();
            }

            $this->session->set('uid', $this->user->getUid());
        }
    }

    /**
     * @throws Exception
     */
    protected function validKeys(?string $api_key = null, ?string $api_secret = null): bool
    {
        if ($api_key || $api_secret) {
            $apiKey = $api_key ?? '';
            $this->api_record = $this->apiKeyDao->findByKey($apiKey);
            if ($this->api_record) {
                return $this->api_record->validSecret($api_secret ?? '');
            }
        }

        return false;
    }

    public function getUser(): UserStruct
    {
        return $this->user;
    }

    public function isLogged(): bool
    {
        return $this->logged;
    }

    public function getApiRecord(): ?ApiKeyStruct
    {
        return $this->api_record;
    }
}
