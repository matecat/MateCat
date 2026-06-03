<?php

namespace Model\Users\Authentication;

use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\DataAccess\Database;
use Model\Teams\TeamDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Email\ForgotPasswordEmail;
use Utils\Email\SignupEmail;
use Utils\Email\WelcomeEmail;
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;

class SignupModel
{

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    /** @var array<string, mixed> */
    protected array $params;

    protected ?string $error = null;

    /** @var array<string, mixed> */
    private array $session;

    protected UserDao $userDao;

    protected TeamDao $teamDao;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $session
     * @param ?UserDao $userDao
     * @param ?TeamDao $teamDao
     */
    public function __construct(array $params, array &$session, ?UserDao $userDao = null, ?TeamDao $teamDao = null)
    {
        $this->params = $params;
        $this->session =& $session;
        $this->user = new UserStruct($this->params);
        $this->userDao = $userDao ?? new UserDao();
        $this->teamDao = $teamDao ?? new TeamDao();
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return UserStruct
     */
    public function getUser(): UserStruct
    {
        return $this->user;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function processSignup(): void
    {
        if ($this->__userAlreadyExists() && !$this->__userAlreadyExistsAndIsActive()) {
            $this->__updatePersistedUser();
            $this->userDao->updateStruct($this->user, [
                'fields' => [
                    'salt',
                    'pass',
                    'confirmation_token',
                    'confirmation_token_created_at'
                ]
            ]);
        } else {
            $this->__prepareNewUser();
            $this->user->uid = $this->userDao->insertStruct($this->user) ?: throw new RuntimeException('User uid must be set after signup insert');

            Database::obtain()->begin();
            $this->teamDao->createPersonalTeam($this->user);
            Database::obtain()->commit();
        }

        $this->__saveWantedUrl();

        // send a confirmation email only if
        // the user is not active (with a user/password pair)
        // AND do not own an active Oauth login
        if (!$this->__userAlreadyExistsAndIsActive()) {
            $this->__sendConfirmationRequestEmail();
        }
    }

    /**
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @throws Exception
     */
    private function __sendConfirmationRequestEmail(): void
    {
        $email = new SignupEmail($this->getUser());
        $email->send();
    }

    private function __saveWantedUrl(): void
    {
        $this->session['wanted_url'] = $this->params['wanted_url'];
    }

    /**
     * @return string
     * @throws Exception
     */
    public function flushWantedURL(): string
    {
        $url = $this->session['wanted_url'] ?? CanonicalRoutes::appRoot();
        unset($this->session['wanted_url']);

        return $url;
    }

    private function __updatePersistedUser(): void
    {
        /*
         * salt is empty when a user exists, and it's first login happened through external service providers (OAuth)
         * Check the salt before join the two accounts.
         */
        if (empty($this->user->salt)) {
            $this->user->salt = Utils::randomString(15, true);
        }

        $this->user->pass = Utils::encryptPass($this->params['password'], $this->user->salt);

        $this->user->initAuthToken();
    }

    /**
     * @throws RuntimeException
     */
    private function __prepareNewUser(): void
    {
        $email = $this->user->email ?? throw new RuntimeException('User email must be set before signup');

        $this->user->create_date = Utils::mysqlTimestamp(time());
        $this->user->email = $email;
        $this->user->salt = Utils::randomString(15, true);
        $this->user->pass = Utils::encryptPass($this->params['password'], $this->user->salt);

        $this->user->initAuthToken();
    }

    /**
     * Check if a user already exists
     *
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    private function __userAlreadyExists(): bool
    {
        if ($this->user->email === null) {
            return false;
        }

        $persisted = $this->userDao->getByEmail($this->user->email);

        if ($persisted) {
            $this->user = $persisted;
        }

        return isset($this->user->uid);
    }

    /**
     * Check if a user already exists
     * AND
     * is active (with a user/password pair)
     * OR do not own an active Oauth login
     *
     *
     * @return bool
     */
    private function __userAlreadyExistsAndIsActive(): bool
    {
        return (isset($this->user->uid) && (!empty($this->user->email_confirmed_at) || !empty($this->user->oauth_access_token)));
    }

    /**
     * @throws ValidationError
     * @throws Exception
     * @throws TypeError
     */
    public function confirm(): UserStruct
    {
        $user = $this->userDao->getByConfirmationToken($this->params['token']);

        if (!$user) {
            throw new ValidationError('Confirmation token not found');
        }

        if ($user->confirmation_token_created_at === null) {
            throw new ValidationError('Confirmation token is invalid, please contact support.');
        }

        if (strtotime($user->confirmation_token_created_at) < strtotime('3 days ago')) {
            throw new ValidationError('Confirmation token is too old, please contact support.');
        }

        $ever_signed_in = $user->everSignedIn();

        $user = $this->__updateUserFields($user);

        if (!$ever_signed_in) {
            $email = new WelcomeEmail($user);
            $email->send();
        }

        return $user;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function forgotPassword(): bool
    {
        $this->__saveWantedUrl();

        $user = $this->userDao->getByEmail($this->params['email']);

        if ($user) {
            $user->initAuthToken();

            $this->userDao->updateStruct($user, ['fields' => ['confirmation_token', 'confirmation_token_created_at']]);

            $delivery = new ForgotPasswordEmail($user);
            $delivery->send();

            return true;
        }

        return false;
    }

    /**
     * @param string $email
     * @param ?UserDao $dao
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function resendConfirmationEmail(string $email, ?UserDao $dao = null): void
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if ($email === false || $email === '') {
            return;
        }

        $dao ??= new UserDao();
        $user = $dao->getByEmail($email);

        if ($user) {
            $delivery = new SignupEmail($user);
            $delivery->send();
        }
    }

    /**
     * @param UserStruct $user
     *
     * @return UserStruct
     * @throws Exception
     * @throws TypeError
     */
    private function __updateUserFields(UserStruct $user): UserStruct
    {
        $user->email_confirmed_at = Utils::mysqlTimestamp(time());
        $user->clearAuthToken();

         $this->userDao->updateStruct($user, ['fields' => ['confirmation_token', 'email_confirmed_at']]);
         $this->userDao->destroyCacheByEmail($user->email ?? throw new RuntimeException('Missing user email'));
         $this->userDao->destroyCacheByUid($user->uid ?? throw new RuntimeException('Missing user uid'));

        return $user;
    }

}
