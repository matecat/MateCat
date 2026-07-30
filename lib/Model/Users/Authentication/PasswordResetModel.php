<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 13:19
 */

namespace Model\Users\Authentication;

use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use RuntimeException;
use TypeError;
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;


class PasswordResetModel
{

    protected ?string $token;
    /**
     * @var ?UserStruct
     */
    protected ?UserStruct $user = null;
    /** @var array<string, mixed> */
    protected array $session;
    protected UserDao $userDao;

    /**
     * @param array<string, mixed> $session reference to global $_SESSSION var
     * @param UserDao $userDao
     * @param string|null $token
     *
     * @throws TypeError
     */
    public function __construct(array &$session, UserDao $userDao, ?string $token = null)
    {
        $this->token = $token;
        $this->session =& $session;
        $this->userDao = $userDao;
        if (empty($token)) {
            $this->token = $session['password_reset_token'];
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
             $this->user = $this->userDao->getByConfirmationToken($this->token ?? throw new RuntimeException('Missing reset token'));
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

        $this->session['password_reset_token'] = $user->confirmation_token;
    }

    /**
     * Clears the token and refuses to go on once it is older than its lifetime.
     *
     * @throws ValidationError if the token has expired
     * @throws Exception if an error occurs
     */
    private function discardExpiredToken(UserStruct $user): void
    {
        if (strtotime($user->confirmation_token_created_at ?? '') >= strtotime('30 minutes ago')) {
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

        unset($this->session['password_reset_token']);

        $salt = $user->salt ?? throw new RuntimeException('User salt must be set');
        $user->pass = Utils::encryptPass($new_password, $salt);

        // reset token
        $user->clearAuthToken();

        $fieldsToUpdate = [
            'fields' => [
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
        $this->userDao->destroyCacheByUid($user->uid ?? throw new RuntimeException('User uid must be set before cache invalidation'));
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

}
