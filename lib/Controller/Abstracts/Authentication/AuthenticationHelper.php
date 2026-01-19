<?php

namespace Controller\Abstracts\Authentication;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\Teams\MembershipDao;
use Model\Teams\TeamModel;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Throwable;
use Utils\Logger\LoggerFactory;
use View\API\App\Json\UserProfile;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 19/09/24
 * Time: 13:36
 *
 */
class AuthenticationHelper
{

    private UserStruct $user;
    /**
     * @var true
     */
    private bool $logged;
    private ?ApiKeyStruct $api_record = null;
    private array $session;
    private static ?AuthenticationHelper $instance = null;

    /**
     * @param array $session
     * @param string|null $api_key
     * @param string|null $api_secret
     *
     * @return AuthenticationHelper
     * @throws Exception
     */
    public static function getInstance(array &$session, ?string $api_key = null, ?string $api_secret = null): AuthenticationHelper
    {
        if (!self::$instance) {
            self::$instance = new AuthenticationHelper($session, $api_key, $api_secret);
        }

        return self::$instance;
    }

    /**
     * Constructor for the AuthenticationHelper class.
     *
     * This constructor initializes the user session and attempts to authenticate the user
     * using one of the following methods:
     * 1. Valid API keys (if provided).
     * 2. Existing session credentials (if available and valid).
     * 3. Authentication cookie credentials (if present and valid).
     *
     * If authentication is successful, the user object is populated, and the session is updated.
     * If authentication fails, the user remains unauthenticated.
     *
     * @param array $session Reference to the session array, used to store user data.
     * @param string|null $api_key Optional API key for authentication.
     * @param string|null $api_secret Optional API secret for authentication.
     * @throws Exception
     */
    protected function __construct(array &$session, ?string $api_key = null, ?string $api_secret = null)
    {
        $this->session =& $session;
        $this->user = new UserStruct();

        try {
            if ($this->validKeys($api_key, $api_secret)) {
                // Authenticate using API keys and retrieve the associated user.
                $this->user = $this->api_record->getUser();
            } elseif (!empty($this->session['user']) && !empty($this->session['user_profile'])) {
                // Authenticate using session credentials if they are still active and valid.
                $this->user = $this->session['user']; // PHP deserializes this from the session string.
                AuthCookie::setCredentials($this->user, new SessionTokenStoreHandler(), true); // Possibly revamp the cookie.
            } else {
                // Authenticate using credentials from the authentication cookie.
                /**
                 * @var $user UserStruct
                 */
                $user_cookie_credentials = AuthCookie::getCredentials(new SessionTokenStoreHandler());
                if (!empty($user_cookie_credentials) && !empty($user_cookie_credentials['user'])) {
                    $userDao = new UserDao();
                    $userDao->setCacheTTL(60 * 60 * 24); // Set cache TTL to 24 hours.
                    $this->user = $userDao->getByUid($user_cookie_credentials['user']['uid']);
                    $this->setUserSession(); // Update the session with the authenticated user.
                }
            }
        } catch (Throwable $ignore) {
            // Log any exceptions encountered during the authentication process.
            try {
                LoggerFactory::getLogger('login_exceptions')->debug(
                    [
                        $ignore,
                        $ignore->getTraceAsString(),
                        'session' => $this->session,
                        'api_key' => $api_key,
                        'api_secret' => $api_secret,
                        'cookie' => AuthCookie::getCredentials()['user'] ?? null
                    ]
                );
            } catch (ReflectionException) {
            }
        } finally {
            // Set the logged status based on the user's authentication state.
            $this->logged = $this->user->isLogged();
        }
    }

    /**
     * @param array $session
     * @throws Exception
     */
    public static function refreshSession(array &$session): void
    {
        unset($session['user']);
        unset($session['user_profile']);
        self::$instance = new AuthenticationHelper($session);
    }

    /**
     * @throws ReflectionException
     */
    public static function destroyAuthentication(array &$session): void
    {
        unset($session['user']);
        unset($session['user_profile']);
        AuthCookie::destroyAuthentication(new SessionTokenStoreHandler());
    }

    /**
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     */
    protected function setUserSession(): void
    {
        $session_status = session_status();
        if ($session_status == PHP_SESSION_ACTIVE) {
            $this->session['cid'] = $this->user->getEmail();
            $this->session['uid'] = $this->user->getUid();
            $this->session['user'] = $this->user;
            $this->session['user_profile'] = static::getUserProfile($this->user);
        }
    }

    /**
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     */
    protected static function getUserProfile(UserStruct $user): array
    {
        $metadata = $user->getMetadataAsKeyValue();
        $membersDao = new MembershipDao();
        $membersDao->setCacheTTL(60 * 5);
        $userTeams = array_map(
            function ($team) use ($membersDao) {
                $teamModel = new TeamModel($team);
                $teamModel->updateMembersProjectsCount();

                /** @var $team TeamStruct */
                return $team;
            },
            $membersDao->findUserTeams($user)
        );

        $dao = new ConnectedServiceDao();
        $services = $dao->findServicesByUser($user);

        return (new UserProfile())->renderItem(
            $user,
            $userTeams,
            $services,
            $metadata
        );
    }

    /**
     * validKeys
     *
     * This was implemented to allow passing a pair of keys to identify the user, or to deny access.
     *
     * This function returns true if the keys are not provided.
     *
     * If keys are provided, it checks for them to be valid or return false.
     *
     */
    protected function validKeys(?string $api_key = null, ?string $api_secret = null): bool
    {
        if ($api_key || $api_secret) {
            $this->api_record = ApiKeyDao::findByKey($api_key);
            if ($this->api_record) {
                return $this->api_record->validSecret($api_secret);
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