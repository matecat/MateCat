<?php

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\DataAccess\IDatabase;
use Model\Teams\MembershipDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Throwable;
use TypeError;
use Utils\Logger\LoggerFactory;

/**
 * Split / dependency-injected re-implementation of {@see AuthenticationHelper}.
 *
 * Behaviorally identical to the original (verified by a parity test copy), but:
 *  - all collaborators are constructor-injected (UserDao, ApiKeyDao,
 *    UserProfileBuilder, AuthCookieStore) → fully unit-testable, no singleton;
 *  - the authentication work lives in authenticate() instead of the constructor;
 *  - fromRequest() is the single composition root that touches the database.
 *
 * The original class is intentionally kept in place; this lives beside it for
 * the verification phase.
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
    private AuthCookieStore $cookieStore;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        array &$session,
        UserDao $userDao,
        ApiKeyDao $apiKeyDao,
        UserProfileBuilder $profileBuilder,
        AuthCookieStore $cookieStore,
    ) {
        $this->session        =& $session;
        $this->userDao        = $userDao;
        $this->apiKeyDao      = $apiKeyDao;
        $this->profileBuilder = $profileBuilder;
        $this->cookieStore    = $cookieStore;
        $this->user           = new UserStruct();
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
            new UserProfileBuilder(new MembershipDao($db), new ConnectedServiceDao($db)),
            new AuthCookieStore(new SessionTokenStoreHandler()),
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
                $user = $this->api_record->getUser();
                if ($user !== null) {
                    $this->user = $user;
                }
            } elseif (!empty($this->session['user']) && !empty($this->session['user_profile'])) {
                $this->user = $this->session['user'];
                $this->cookieStore->setCredentials($this->user, true);
            } else {
                $credentials = $this->cookieStore->getCredentials();
                if (!empty($credentials) && !empty($credentials['user'])) {
                    $this->userDao->setCacheTTL(60 * 60 * 24);
                    $user = $this->userDao->getByUid($credentials['user']['uid']);
                    if ($user !== null) {
                        $this->user = $user;
                        $this->setUserSession();
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
                        'session'    => $this->session,
                        'api_key'    => $api_key,
                        'api_secret' => $api_secret,
                        'cookie'     => $this->cookieStore->getCredentials()['user'] ?? null,
                    ]
                );
            } catch (Throwable) {
            }
        } finally {
            $this->logged = $this->user->isLogged();
        }
    }

    public function refreshSession(): void
    {
        unset($this->session['user']);
        unset($this->session['user_profile']);
        $this->user       = new UserStruct();
        $this->logged     = false;
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
        $this->cookieStore->destroy();
    }

    protected function sessionIsActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    protected function setUserSession(): void
    {
        if ($this->sessionIsActive()) {
            $this->session['cid']          = $this->user->getEmail();
            $this->session['uid']          = $this->user->getUid();
            $this->session['user']         = $this->user;
            $this->session['user_profile'] = $this->profileBuilder->build($this->user);
        }
    }

    /**
     * @throws Exception
     */
    protected function validKeys(?string $api_key = null, ?string $api_secret = null): bool
    {
        if ($api_key || $api_secret) {
            $apiKey           = $api_key ?? '';
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
