<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:07
 */

namespace Email;

use INIT;

class  ForgotPasswordEmail extends AbstractEmail
{

    protected $title = 'Password reset' ;

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

        $this->doSend( $recipient, $this->title ,
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

    protected function _getLayoutVariables($messageBody = null) {
        $vars  = parent::_getLayoutVariables();
        $vars['title'] = $this->title ;
        return $vars ;
    }


    protected function _getDefaultMailConf() {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf[ 'from' ]       = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'sender' ]     = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'returnPath' ] = INIT::$MAILER_RETURN_PATH;

        return $mailConf ;
    }
}