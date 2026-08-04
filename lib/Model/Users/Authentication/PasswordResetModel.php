<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 13:19
 */

namespace Model\Users\Authentication;

use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\Users\AuthTokenScope;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use RuntimeException;
use TypeError;
use Utils\Tools\Utils;
use Utils\Session\SessionStore;
use Utils\Url\CanonicalRoutes;


class PasswordResetModel
{

    protected ?string $token;
    /**
     * @var ?UserStruct
     */
    protected ?UserStruct $user = null;
    protected SessionStore $session;
    protected UserDao $userDao;
    protected SessionTokenStoreHandler $tokenStore;

    /**
     * @param UserDao $userDao
     * @param SessionTokenStoreHandler $tokenStore
     * @param string|null $token
     *
     * @throws TypeError
     */
    public function __construct(SessionStore $session, UserDao $userDao, SessionTokenStoreHandler $tokenStore, ?string $token = null)
    {
        $this->token = $token;
        $this->session = $session;
        $this->userDao = $userDao;
        $this->tokenStore = $tokenStore;
        if (empty($token)) {
            $this->token = $session->get('password_reset_token');
        }
    }

    /**
     * @return UserStruct|null
     */
    public function getUser(): ?UserStruct
    {
        return $this->user;
    }

    /**
     * Retrieves the user associated with the reset token.
     *
     * @return ?UserStruct The user associated with the reset token, or null if not found.
     * @throws Exception If an error occurs while retrieving the user.
     *
     */
     protected function getUserFromResetToken(): ?UserStruct
     {
         if (!isset($this->user)) {
             $this->user = $this->userDao->getByScopedConfirmationToken(
                 $this->token ?? throw new RuntimeException('Missing reset token'),
                 AuthTokenScope::PasswordReset
             );
         }

         return $this->user;
     }

    /**
     * Validates the user based on the reset token
     *
     * @throws ValidationError if confirmation token not found or auth token expired
     * @throws Exception if an error occurs
     */
    public function validateUser(): void
    {
        $this->getUserFromResetToken();

        $user = $this->user ?? throw new ValidationError('Invalid authentication token');

        $this->discardExpiredToken($user);

        // The unmarked value, matching what the link carried: the form submission that follows reads
        // this back and hands it to the same scoped lookup.
        $this->session->set('password_reset_token', $user->authTokenForUrl());
    }

    /**
     * Clears the token and refuses to go on once it is older than its lifetime.
     *
     * @throws ValidationError if the token has expired
     * @throws Exception if an error occurs
     */
    private function discardExpiredToken(UserStruct $user): void
    {
        if (strtotime($user->confirmation_token_created_at ?? '') >= time() - AuthTokenScope::PasswordReset->ttlSeconds()) {
            return;
        }

        $user->clearAuthToken();
        $this->userDao->updateStruct($user, ['fields' => ['confirmation_token']]);

        throw new ValidationError('Auth token expired, repeat the operation.');
    }

    /**
     * @param string $new_password
     *
     * @return void
     * @throws ValidationError
     * @throws Exception
     * @throws TypeError
     */
    public function resetPassword(string $new_password): void
    {
        $this->getUserFromResetToken();

        $user = $this->user ?? throw new ValidationError('Invalid authentication token');

        // validateUser() checks the age when the link is opened, but the form submission that follows
        // reads the token back out of the session and arrives here instead. Age has to be checked
        // again, or a token stays usable for as long as the session lives.
        $this->discardExpiredToken($user);

        $this->session->remove('password_reset_token');

        // Accounts created through an external provider never got a salt — the OAuth insert leaves the
        // column unset — and a NULL there used to abort this method, locking the owner out of the one
        // flow that could give them a password at all. An empty string is no better: it hashes, but
        // unsalted. Mint one now and persist it with the new password.
        if (empty($user->salt)) {
            $user->salt = Utils::randomString(32);
        }

        $user->pass = Utils::encryptPass($new_password, $user->salt);

        // reset token
        $user->clearAuthToken();

        $fieldsToUpdate = [
            'fields' => [
                'salt',
                'pass',
                'confirmation_token',
                'confirmation_token_created_at'
            ]
        ];

        // update email_confirmed_at only if it's null
        if (null === $user->email_confirmed_at) {
            $user->email_confirmed_at = date('Y-m-d H:i:s');
            $fieldsToUpdate['fields'][] = 'email_confirmed_at';
        }

        $this->userDao->updateStruct($user, $fieldsToUpdate);
        $this->userDao->destroyCacheByEmail($user->email ?? throw new RuntimeException('User email must be set before cache invalidation'));

        $uid = $user->uid ?? throw new RuntimeException('User uid must be set before cache invalidation');
        $this->userDao->destroyCacheByUid($uid);

        // Until now this flow revoked nothing at all: the user arrives without an authentication
        // cookie, so removeLoginCookieFromStore() was handed an empty value and returned early.
        // Anyone holding a stolen cookie kept working straight through the reset.
        $this->tokenStore->revokeAllLoginTokens($uid);
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

}
