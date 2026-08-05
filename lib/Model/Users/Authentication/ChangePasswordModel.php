<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/09/23
 * Time: 17:11
 *
 */

namespace Model\Users\Authentication;

use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Tools\Utils;

class ChangePasswordModel
{

    private UserStruct $user;
    private UserDao $userDao;
    private SessionTokenStoreHandler $tokenStore;

    public function __construct(UserStruct $user, UserDao $userDao, SessionTokenStoreHandler $tokenStore)
    {
        $this->user = $user;
        $this->userDao = $userDao;
        $this->tokenStore = $tokenStore;
    }

    /**
     * @throws ValidationError
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function changePassword(string $old_password, string $new_password): void
    {
        // An account created through an external provider has neither salt nor password, so there is
        // no old password for this call to check. That is a rejected attempt rather than a broken row,
        // and it has to be answered the same way a wrong password is.
        if ($this->user->salt === null || $this->user->pass === null) {
            throw new ValidationError("Invalid password");
        }

        if (!Utils::verifyPass($old_password, $this->user->salt, $this->user->pass)) {
            throw new ValidationError("Invalid password");
        }

        if ($old_password === $new_password) {
            throw new ValidationError("New password cannot be the same as your old password");
        }

        // Verification had to use the salt the stored hash was built with, but an empty one must not
        // survive a rewrite: it leaves the global pepper as the only per-account variation. The
        // password is being replaced anyway, so mint a real salt while we are here.
        if ($this->user->salt === '') {
            $this->user->salt = Utils::randomString(32);
        }

        $this->user->pass = Utils::encryptPass($new_password, $this->user->salt);

        // Retire any reset link already issued for this account. A token stays valid for 30 minutes
        // from the moment it was created, so without this a link sitting in the user's mailbox
        // outlives the password change and can be used to set a third password.
        $this->user->clearAuthToken();

        $fieldsToUpdate = [
            'fields' => ['salt', 'pass', 'confirmation_token', 'confirmation_token_created_at']
        ];

        // update email_confirmed_at only if it's null
        if (null === $this->user->email_confirmed_at) {
            $this->user->email_confirmed_at = date('Y-m-d H:i:s');
            $fieldsToUpdate['fields'][] = 'email_confirmed_at';
        }

        $this->userDao->updateStruct($this->user, $fieldsToUpdate);
        $this->userDao->destroyCacheByEmail($this->user->email ?? throw new RuntimeException('User email must be set before cache invalidation'));

        $uid = $this->user->uid ?? throw new RuntimeException('User uid must be set before cache invalidation');
        $this->userDao->destroyCacheByUid($uid);

        // Every other device is still holding a login cookie that the old password minted, so the
        // change has to retire them. The acting device is logged out separately by the controller's
        // broadcastLogout(), which only ever removes the cookie it was presented with.
        $this->tokenStore->revokeAllLoginTokens($uid);
    }

}
