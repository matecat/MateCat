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
     * Rejects control characters in a password.
     *
     * Passwords reach the rules unescaped: they are compared against a hash and never rendered, so
     * escaping them would only shrink the usable character set. That leaves control characters as the
     * one category worth refusing outright, since they cannot be typed back reliably and travel
     * invisibly through copy and paste.
     *
     * @param mixed $password the raw request value, which is not necessarily a string
     *
     * @throws ValidationError
     */
    public function rejectControlCharacters(mixed $password): void
    {
        if (!is_string($password)) {
            return;
        }

        // Byte-wise on purpose: without the u modifier every byte of a multibyte character is above
        // 0x7F, so a legitimate accented or non-Latin password cannot match this range by accident.
        // `!== 0` rather than `=== 1`: preg_match returns false when PCRE gives up, and a rule that
        // exists to reject must reject when it cannot decide. Only an explicit 0 is a pass.
        if (preg_match('/[\x00-\x1F\x7F]/', $password) !== 0) {
            throw new ValidationError(
                'The password cannot contain control characters, such as tabs, line breaks or null bytes'
            );
        }
    }

    /**
     * @throws ValidationError
     */
    public function validatePasswordRequirements(string $password, string $password_confirmation): void
    {
        if (mb_substr($password, 0, 50) != $password) {
            throw new ValidationError('The password must be a maximum of 50 characters long');
        }

        if (mb_strlen($password) < 12) {
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