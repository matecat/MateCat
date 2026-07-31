<?php

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\DataAccess\IDatabase;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Users\MetadataDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Throwable;
use TypeError;
use Utils\Logger\LoggerFactory;

/**
 * Resolves the identity behind a request: api key, or login cookie validated against the token ring.
 *
 *  - all collaborators are constructor-injected (UserDao, ApiKeyDao, UserProfileBuilder,
 *    AuthCookie) → fully unit-testable, no singleton;
 *  - the authentication work lives in authenticate() instead of the constructor;
 *  - fromRequest() is the single composition root that touches the database.
 */
class AuthenticationHelper
{
    private UserStruct $user;
    private bool $logged = false;
    private ?ApiKeyStruct $api_record = null;
    /** @var array<string, mixed> */
    private array $session;
    private UserDao $userDao;
    private ApiKeyDao $apiKeyDao;
    private UserProfileBuilder $profileBuilder;
    private AuthCookie $authCookie;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        array &$session,
        UserDao $userDao,
        ApiKeyDao $apiKeyDao,
        UserProfileBuilder $profileBuilder,
        AuthCookie $authCookie,
    ) {
        $this->session =& $session;
        $this->userDao = $userDao;
        $this->apiKeyDao = $apiKeyDao;
        $this->profileBuilder = $profileBuilder;
        $this->authCookie = $authCookie;
        $this->user = new UserStruct();
    }

    /**
     * Composition root: wires real collaborators from an injected database
     * handle and runs the authentication flow. The database is mandatory — no
     * singleton fallback. Mirrors the original `new AuthenticationHelper(...)`.
     *
     * @param array<string, mixed> $session
     */
    public static function fromRequest(
        array &$session,
        IDatabase $db,
        ?string $api_key = null,
        ?string $api_secret = null,
    ): self {
        $self = new self(
            $session,
            new UserDao($db),
            new ApiKeyDao($db),
            new UserProfileBuilder(
                new MembershipDao($db),
                new ConnectedServiceDao($db),
                new UserDao($db),
                new TeamDao($db),
                new MetadataDao($db)
            ),
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
                        'session_keys' => array_keys($this->session),
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
        } finally {
            $this->logged = $this->user->isLogged();
        }
    }

    /**
     * The session is a cache for the expensive profile build, never an authorization decision.
     * It is usable only when it belongs to the uid the login cookie just proved: a session
     * holding user A together with a cookie for user B must not serve A's profile to B.
     */
    private function cachedSessionUser(int $uid): ?UserStruct
    {
        $cachedUser = $this->session['user'] ?? null;

        if (!$cachedUser instanceof UserStruct || empty($this->session['user_profile'])) {
            return null;
        }

        return (int)$cachedUser->uid === $uid ? $cachedUser : null;
    }

    public function refreshSession(): void
    {
        unset($this->session['user']);
        unset($this->session['user_profile']);
        $this->user = new UserStruct();
        $this->logged = false;
        $this->api_record = null;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function destroyAuthentication(): void
    {
        unset($this->session['user']);
        unset($this->session['user_profile']);
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

            $this->session['cid'] = $this->user->getEmail();
            $this->session['uid'] = $this->user->getUid();
            $this->session['user'] = $this->user;
            $this->session['user_profile'] = $this->profileBuilder->build($this->user);
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
