<?php

namespace Controller\Abstracts\Authentication;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\Teams\MembershipDao;
use Model\Teams\TeamModel;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use Throwable;
use TypeError;
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
    private bool $logged = false;
    private ?ApiKeyStruct $api_record = null;
    /** @var array<string, mixed> */
    private array $session;
    private static ?AuthenticationHelper $instance = null;

    /**
     * @param array<string, mixed> $session
     *
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
     * @param array<string, mixed> $session
     *
     * @throws Exception
     */
    protected function __construct(array &$session, ?string $api_key = null, ?string $api_secret = null)
    {
        $this->session =& $session;
        $this->user = new UserStruct();

        try {
            if ($this->validKeys($api_key, $api_secret) && $this->api_record !== null) {
                $this->user = $this->api_record->getUser();
            } elseif (!empty($this->session['user']) && !empty($this->session['user_profile'])) {
                $this->user = $this->session['user'];
                AuthCookie::setCredentials($this->user, new SessionTokenStoreHandler(), true);
            } else {
                $user_cookie_credentials = AuthCookie::getCredentials(new SessionTokenStoreHandler());
                if (!empty($user_cookie_credentials) && !empty($user_cookie_credentials['user'])) {
                    $userDao = new UserDao();
                    $userDao->setCacheTTL(60 * 60 * 24);
                    $user = $userDao->getByUid($user_cookie_credentials['user']['uid']);
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
                        'session' => $this->session,
                        'api_key' => $api_key,
                        'api_secret' => $api_secret,
                        'cookie' => AuthCookie::getCredentials()['user'] ?? null
                    ]
                );
            } catch (ReflectionException|TypeError) {
            }
        } finally {
            // Set the logged status based on the user's authentication state.
            $this->logged = $this->user->isLogged();
        }
    }

    /**
     * @param array<string, mixed> $session
     *
     * @throws Exception
     */
    public static function refreshSession(array &$session): void
    {
        unset($session['user']);
        unset($session['user_profile']);
        self::$instance = new AuthenticationHelper($session);
    }

    /**
     * @param array<string, mixed> $session
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
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
     * @throws RuntimeException
     * @throws Exception
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
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     * @throws RuntimeException
     * @throws Exception
     */
    protected static function getUserProfile(UserStruct $user): array
    {
        $metadata = $user->getMetadataAsKeyValue();
        $membersDao = new MembershipDao();
        $membersDao->setCacheTTL(60 * 5);
        $userTeams = array_map(
            function ($team) {
                $teamModel = new TeamModel($team);
                $teamModel->updateMembersProjectsCount();

                return $team;
            },
            $membersDao->findUserTeams($user) ?? []
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
     * @throws PDOException
     */
    protected function validKeys(?string $api_key = null, ?string $api_secret = null): bool
    {
        if ($api_key || $api_secret) {
            $apiKey = $api_key ?? '';
            $this->api_record = ApiKeyDao::findByKey($apiKey);
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
