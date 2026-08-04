<?php

namespace Utils\Email;

use Model\Users\UserStruct;

/**
 * Sent when a signup request names an address that already has an account.
 *
 * Delivery is identical to {@see ForgotPasswordEmail} — a tokened link to the reset form — but the
 * copy has to explain why the mail arrived unprompted. The recipient may sign in through an external
 * provider and have never wanted a password, so following the link has to be a decision rather than
 * a reflex.
 */
class SetPasswordRequestEmail extends ForgotPasswordEmail
{

    protected ?string $title = 'Set a password for your Matecat account';

    public function __construct(UserStruct $user)
    {
        parent::__construct($user);
        $this->_setTemplate('Signup/set_password_request_content.html');
    }

}
