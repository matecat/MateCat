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
use Utils\Tools\Utils;

class ChangePasswordModel
{

    /**
     * @var UserStruct
     */
    private $user;

    public function __construct(UserStruct $user)
    {
        $this->user = $user;
    }

    /**
     * @param $old_password
     * @param $new_password
     *
     * @throws ValidationError
     * @throws ReflectionException
     * @throws Exception
     */
    public function changePassword($old_password, $new_password): void
    {
        if (!Utils::verifyPass($old_password, $this->user->salt, $this->user->pass)) {
            throw new ValidationError("Invalid password");
        }

        if ($old_password === $new_password) {
            throw new ValidationError("New password cannot be the same as your old password");
        }

        $this->user->pass = Utils::encryptPass($new_password, $this->user->salt);

        $fieldsToUpdate = [
            'fields' => ['pass']
        ];

        // update email_confirmed_at only if it's null
        if (null === $this->user->email_confirmed_at) {
            $this->user->email_confirmed_at = date('Y-m-d H:i:s');
            $fieldsToUpdate['fields'][] = 'email_confirmed_at';
        }

        UserDao::updateStruct($this->user, $fieldsToUpdate);
        (new UserDao)->destroyCacheByEmail($this->user->email);
        (new UserDao)->destroyCacheByUid($this->user->uid);
    }

}