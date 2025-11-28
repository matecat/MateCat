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
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;


class PasswordResetModel
{

    protected ?string $token;
    /**
     * @var ?UserStruct
     */
    protected ?UserStruct $user = null;
    protected array       $session;

    /**
     * @param array       $session reference to global $_SESSSION var
     * @param string|null $token
     */
    public function __construct(array &$session, ?string $token = null)
    {
        $this->token   = $token;
        $this->session =& $session;
        if (empty($token)) {
            $this->token = $session[ 'password_reset_token' ];
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
            $dao        = new UserDao();
            $this->user = $dao->getByConfirmationToken($this->token);
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

        if (!$this->user) {
            throw new ValidationError('Invalid authentication token');
        }

        if (strtotime($this->user->confirmation_token_created_at) < strtotime('30 minutes ago')) {
            $this->user->clearAuthToken();
            UserDao::updateStruct($this->user, ['fields' => ['confirmation_token']]);

            throw new ValidationError('Auth token expired, repeat the operation.');
        }

        $this->session[ 'password_reset_token' ] = $this->user->confirmation_token;
    }

    /**
     * @param string $new_password
     *
     * @return void
     * @throws ValidationError
     * @throws Exception
     */
    public function resetPassword(string $new_password): void
    {
        $this->getUserFromResetToken();

        if (!$this->user) {
            throw new ValidationError('Invalid authentication token');
        }

        unset($this->session[ 'password_reset_token' ]);

        $this->user->pass = Utils::encryptPass($new_password, $this->user->salt);

        // reset token
        $this->user->clearAuthToken();

        $fieldsToUpdate = [
                'fields' => [
                        'pass',
                        'confirmation_token',
                        'confirmation_token_created_at'
                ]
        ];

        // update email_confirmed_at only if it's null
        if (null === $this->user->email_confirmed_at) {
            $this->user->email_confirmed_at = date('Y-m-d H:i:s');
            $fieldsToUpdate[ 'fields' ][]   = 'email_confirmed_at';
        }

        UserDao::updateStruct($this->user, $fieldsToUpdate);
        (new UserDao)->destroyCacheByEmail($this->user->email);
        (new UserDao)->destroyCacheByUid($this->user->uid);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function flushWantedURL(): string
    {
        $url = $this->session[ 'wanted_url' ] ?? CanonicalRoutes::appRoot();
        unset($this->session[ 'wanted_url' ]);

        return $url;
    }

}