<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:07
 */

namespace Email;


use Users\Signup;

class SignupEmail extends AbstractEmail
{

    /**
     * @var \Users_UserStruct
     */
    private $user ;

    public function __construct( \Users_UserStruct $user ) {

        $this->user = $user ;
        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Signup/signup_content.html');
    }

    public function send() {
        $recipient  = array( $this->user->email, $this->user->fullName() );

        $this->doSend( $recipient, 'Welcome to MateCat!',
            $this->_buildHTMLMessage(),
            $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    protected function _getTemplateVariables() {
        return array(
            'user'           => $this->user->toArray(),
            'activation_url' => \Routes::signupConfirmation( $this->user->confirmation_token ),
            'signup_url'     => \Routes::appRoot()
        );
    }

    protected function _getLayoutVariables() {
        return array(
            'title' => 'Welcome to Matecat',
            'messageBody' => $this->_buildMessageContent()
        );
    }
}