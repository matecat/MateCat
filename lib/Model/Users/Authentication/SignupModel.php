<?php

namespace Model\Users\Authentication;

use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\Teams\TeamDao;
use Model\Users\AuthTokenScope;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use Throwable;
use TypeError;
use Utils\Email\ForgotPasswordEmail;
use Utils\Email\SetPasswordRequestEmail;
use Utils\Email\SignupEmail;
use Utils\Email\WelcomeEmail;
use Utils\Tools\Utils;
use Utils\Session\SessionStore;
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

    private SessionStore $session;

    protected UserDao $userDao;

    protected TeamDao $teamDao;

    /**
     * @param array<string, mixed> $params
     * @param UserDao $userDao
     * @param TeamDao $teamDao
     */
    public function __construct(array $params, SessionStore $session, UserDao $userDao, TeamDao $teamDao)
    {
        $this->params = $params;
        $this->session = $session;
        $this->user = new UserStruct($this->params);
        $this->userDao = $userDao;
        $this->teamDao = $teamDao;
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
     * @throws Throwable the write runs inside a transaction scope, which aborts the transaction
     *                   on any throw and re-throws the original, whatever its type
     */
    public function processSignup(): void
    {
        $this->__saveWantedUrl();

        if (!$this->__userAlreadyExists()) {
            $this->__prepareNewUser();
            $this->user->uid = $this->userDao->insertStruct($this->user) ?: throw new RuntimeException('User uid must be set after signup insert');

            $this->teamDao->getDatabaseHandler()->transaction(fn() => $this->teamDao->createPersonalTeam($this->user));

            // Outside the scope: the mail is the one effect signup cannot take back, so it waits
            // until the team it announces is committed.
            $this->__sendConfirmationRequestEmail();

            return;
        }

        // The address is taken. Credentials belong to whoever controls the mailbox, so nothing
        // submitted here is stored against an account that already exists: issue a token and let the
        // recipient choose the password on the reset form.
        //
        // Taken and free addresses are answered identically, so the endpoint reveals nothing about
        // which addresses are registered.
        $this->__sendPasswordSetupRequestEmail();
    }

    /**
     * Mails a set-password link to the address on file. Only the token columns are written; no
     * password supplied by the caller is persisted anywhere.
     *
     * @throws Exception
     */
    private function __sendPasswordSetupRequestEmail(): void
    {
        // Every request mints, because a stored digest cannot be turned back into a link. A request
        // repeated inside the window keeps the original expiry — see UserStruct::initAuthTokenIfStale().
        $this->user->initAuthTokenIfStale(AuthTokenScope::PasswordReset);

        $this->userDao->updateStruct($this->user, [
            'fields' => [
                'confirmation_token',
                'confirmation_token_created_at'
            ]
        ]);

        $this->createSetPasswordRequestEmail()->send();
    }

    protected function createSetPasswordRequestEmail(): SetPasswordRequestEmail
    {
        return new SetPasswordRequestEmail($this->user);
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
        $this->session->set('wanted_url', $this->params['wanted_url']);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function flushWantedURL(): string
    {
        $url = $this->session->get('wanted_url') ?? CanonicalRoutes::appRoot();
        $this->session->remove('wanted_url');

        return $url;
    }

    /**
     * @throws RuntimeException
     */
    private function __prepareNewUser(): void
    {
        $email = $this->user->email ?? throw new RuntimeException('User email must be set before signup');

        $this->user->create_date = Utils::mysqlTimestamp(time());
        $this->user->email = $email;
        $this->user->salt = Utils::randomString(32);
        $this->user->pass = Utils::encryptPass($this->params['password'], $this->user->salt);

        $this->user->initAuthToken(AuthTokenScope::SignupConfirmation);
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
     * @throws ValidationError
     * @throws Exception
     * @throws TypeError
     */
    public function confirm(): UserStruct
    {
        $user = $this->userDao->getByScopedConfirmationToken(
            $this->params['token'],
            AuthTokenScope::SignupConfirmation
        );

        if (!$user) {
            throw new ValidationError('Confirmation token not found');
        }

        if ($user->confirmation_token_created_at === null) {
            throw new ValidationError('Confirmation token is invalid, please contact support.');
        }

        if (strtotime($user->confirmation_token_created_at) < time() - AuthTokenScope::SignupConfirmation->ttlSeconds()) {
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
            // Anyone can ask for a reset by naming an address. The link in flight cannot survive the
            // request now that only a digest is stored, but the expiry it was issued with does — see
            // UserStruct::initAuthTokenIfStale().
            $user->initAuthTokenIfStale(AuthTokenScope::PasswordReset);
            $this->userDao->updateStruct($user, ['fields' => ['confirmation_token', 'confirmation_token_created_at']]);

            $delivery = new ForgotPasswordEmail($user);
            $delivery->send();

            return true;
        }

        return false;
    }

    /**
     * @param string $email
     * @param UserDao $dao
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function resendConfirmationEmail(string $email, UserDao $dao): void
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if ($email === false || $email === '') {
            return;
        }

        $user = $dao->getByEmail($email);

        if ($user) {
            // The row holds a digest, so the link sent at signup cannot be reproduced from it: this
            // mints one. A confirmation still inside its three days keeps its original deadline, so
            // asking repeatedly cannot extend it.
            $user->initAuthTokenIfStale(AuthTokenScope::SignupConfirmation);
            $dao->updateStruct($user, [
                'fields' => [
                    'confirmation_token',
                    'confirmation_token_created_at'
                ]
            ]);

            static::createSignupEmail($user)->send();
        }
    }

    /**
     * Seam: the only thing standing between this static entry point and a real message going out.
     */
    protected static function createSignupEmail(UserStruct $user): SignupEmail
    {
        return new SignupEmail($user);
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
