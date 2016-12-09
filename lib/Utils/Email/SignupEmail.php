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

    private $title = 'Confirm your registration with MateCat' ;

    public function __construct( \Users_UserStruct $user ) {

        $this->user = $user ;
        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Signup/signup_content.html');
    }

    public function send() {
        $recipient  = array( $this->user->email, $this->user->fullName() );

        $this->doSend( $recipient, $this->title,
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
        $vars = parent::_getLayoutVariables();
        $vars['title'] = $this->title ;

        return $vars ;
    }

    protected function _getDefaultMailConf() {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf[ 'from' ]       = 'noreply@matecat.com';
        $mailConf[ 'sender' ]     = 'noreply@matecat.com';
        $mailConf[ 'returnPath' ] = 'noreply@matecat.com';

        return $mailConf ;
    }
}