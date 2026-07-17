<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/09/23
 * Time: 17:11
 *
 */

namespace Model\Users\Authentication;

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

    public function __construct(UserStruct $user, UserDao $userDao)
    {
        $this->user = $user;
        $this->userDao = $userDao;
    }

    /**
     * @throws ValidationError
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function changePassword(string $old_password, string $new_password): void
    {
        $salt = $this->user->salt ?? throw new RuntimeException('User salt must be set');
        $pass = $this->user->pass ?? throw new RuntimeException('User password must be set');

        if (!Utils::verifyPass($old_password, $salt, $pass)) {
            throw new ValidationError("Invalid password");
        }

        if ($old_password === $new_password) {
            throw new ValidationError("New password cannot be the same as your old password");
        }

        $this->user->pass = Utils::encryptPass($new_password, $salt);

        $fieldsToUpdate = [
            'fields' => ['pass']
        ];

        // update email_confirmed_at only if it's null
        if (null === $this->user->email_confirmed_at) {
            $this->user->email_confirmed_at = date('Y-m-d H:i:s');
            $fieldsToUpdate['fields'][] = 'email_confirmed_at';
        }

        $this->userDao->updateStruct($this->user, $fieldsToUpdate);
        $this->userDao->destroyCacheByEmail($this->user->email ?? throw new RuntimeException('User email must be set before cache invalidation'));
        $this->userDao->destroyCacheByUid($this->user->uid ?? throw new RuntimeException('User uid must be set before cache invalidation'));
    }

}
