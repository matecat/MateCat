<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:07
 */

namespace Email;

use Users\Signup;

class  ForgotPasswordEmail extends AbstractEmail
{

    /**
     * @var \Users_UserStruct
     */
    private $user ;

    public function __construct( \Users_UserStruct $user ) {

        $this->user = $user ;
        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Signup/forgot_password_content.html');
    }

    public function send() {
        $recipient  = array( $this->user->email, $this->user->fullName() );

        $this->doSend( $recipient, 'Password Reset',
            $this->_buildHTMLMessage(),
            $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    protected function _getTemplateVariables() {
        return array(
            'user'           => $this->user->toArray(),
            'password_reset_url' => \Routes::passwordReset( $this->user->confirmation_token )
        );
    }

    protected function _getLayoutVariables() {
        return array(
            'title' => 'Password reset',
            'messageBody' => $this->_buildMessageContent()
        );
    }
}