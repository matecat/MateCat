<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:07
 */

namespace Email;

use Exception;
use INIT;
use Routes;

class  ForgotPasswordEmail extends AbstractEmail {

    protected $title = 'Password reset';

    /**
     * @var \Model\Users\UserStruct
     */
    private $user;

    public function __construct( \Model\Users\UserStruct $user ) {

        $this->user = $user;
        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Signup/forgot_password_content.html' );
    }

    /**
     * @throws Exception
     */
    public function send() {
        $recipient = [ $this->user->email, $this->user->fullName() ];

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    /**
     * @throws Exception
     */
    protected function _getTemplateVariables(): array {
        return [
                'user'               => $this->user->toArray(),
                'password_reset_url' => Routes::passwordReset( $this->user->confirmation_token )
        ];
    }

    protected function _getLayoutVariables( $messageBody = null ): array {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
    }


    protected function _getDefaultMailConf(): array {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf[ 'from' ]       = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'sender' ]     = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'returnPath' ] = INIT::$MAILER_RETURN_PATH;

        return $mailConf;
    }
}