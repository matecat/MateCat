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
                    $uid        = (int)$credentials['user']['uid'];
                    $cachedUser = $this->cachedSessionUser($uid);

                    if ($cachedUser !== null) {
                        $this->user = $cachedUser;
                    } else {
                        $this->userDao->setCacheTTL(60 * 60 * 24);
                        $user = $this->userDao->getByUid($uid);
                        if ($user !== null) {
                            $this->user = $user;
                            $this->setUserSession();
                        }
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
     * The session is a cache for the user row, never an authorization decision. It is usable only
     * when it belongs to the uid the login cookie just proved: a session holding user A together
     * with a cookie for user B must not serve A's user to B.
     *
     * The profile payload used to be cached here too, and its presence was part of this check. It
     * now lives in {@see UserStateStore}, keyed by uid, so this guards the UserStruct alone.
     */
    private function cachedSessionUser(int $uid): ?UserStruct
    {
        // A stateless controller — every /api/v2 and /api/v3 route — holds a StatelessSessionStore,
        // whose get() throws by design. Here the session is only a cache, so no session is a miss and
        // not an error, and the catch (Throwable) around the caller turned that throw into a silent
        // 401 on every cookie-authenticated request to those routes. Guarded on the same condition
        // that already gates the write in setUserSession(), so the cache is read exactly where it can
        // also be written.
        if (!$this->sessionIsActive()) {
            return null;
        }

        $cachedUser = $this->session->get('user');

        if (!$cachedUser instanceof UserStruct) {
            return null;
        }

        return (int)$cachedUser->uid === $uid ? $cachedUser : null;
    }

    /**
     * Refresh the session's cached user from the uid the session already proved.
     *
     * This is not an authentication pass, and that is the point of it existing: the callers that
     * need it had been calling {@see fromRequest()}, which validates the cookie against the token
     * ring, re-reads the user and re-stamps the session, only to have the caller discard part of
     * what it just built. Nothing here re-decides identity — the uid comes from the session, and a
     * caller that has no session user gets a no-op.
     *
     * Callers that just wrote the users row must invalidate its cache first
     * ({@see UserDao::destroyCacheByUid()}), or this re-reads the copy they superseded.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function refreshSessionUser(SessionStore $session, UserDao $userDao): void
    {
        $uid = $session->get('uid');

        if (empty($uid)) {
            return;
        }

        $userDao->setCacheTTL(60 * 60 * 24);
        $user = $userDao->getByUid((int)$uid);

        if ($user === null) {
            $session->remove('user');

            return;
        }

        $session->set('user', $user);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function destroyAuthentication(): void
    {
        $this->session->remove('user');
        $this->authCookie->destroyAuthentication();
    }

    protected function sessionIsActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Rotate the session id, discarding the old server-side entry so the previous id cannot be
     * replayed. Called when the session becomes authenticated; see {@see setUserSession()}.
     *
     * The active-session check deliberately reads session_status() rather than going through
     * {@see sessionIsActive()}: subclasses override that seam to exercise the session writes
     * without a real session, and calling session_regenerate_id() without one raises a warning.
     */
    protected function regenerateSessionId(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        session_regenerate_id(true);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    protected function setUserSession(): void
    {
        if ($this->sessionIsActive()) {
            // This is the transition from anonymous to authenticated, and the only place it
            // happens: every login path (password, signup confirmation, OAuth) reaches it through
            // fromRequest(). The id must not survive the transition, or anyone who learned it
            // beforehand would be holding an authenticated session.
            $this->regenerateSessionId();

            $this->session->set('cid', $this->user->getEmail());
            $this->session->set('uid', $this->user->getUid());
            $this->session->set('user', $this->user);
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
