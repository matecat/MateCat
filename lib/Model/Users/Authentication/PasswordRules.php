<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 03/07/25
 * Time: 17:06
 *
 */

namespace Model\Users\Authentication;

use Controller\API\Commons\Exceptions\ValidationError;

trait PasswordRules
{

    /**
     * @throws ValidationError
     */
    public function validatePasswordRequirements(string $password, string $password_confirmation): void
    {
        if (mb_substr($password, 0, 50) != $password) {
            throw new ValidationError('The password must be a maximum of 50 characters long');
        }

        if (filter_var($password, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW) != $password) {
            throw new ValidationError('The password contains illegal characters');
        }

        if (strlen($password) < 12) {
            throw new ValidationError('Password must be at least 12 characters');
        }

        if ($password !== $password_confirmation) {
            throw new ValidationError('Passwords must match');
        }

        if (!preg_match('/[ !"#$%&\'()*+,-.\/:;<=>?@\[\]^_`{|}~]/', $password)) {
            throw new ValidationError('Passwords must contain at least one special character: !"#\$%&\'()\*\+,-./:;<=>?@[]^_`{|}~');
        }
    }

}