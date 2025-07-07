<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:07
 */

namespace Utils\Email;


use Exception;
use INIT;
use Model\Users\UserStruct;
use Routes;

class SignupEmail extends AbstractEmail {

    /**
     * @var UserStruct
     */
    private UserStruct $user;

    protected ?string $title = 'Confirm your registration with Matecat';

    public function __construct( UserStruct $user ) {

        $this->user = $user;
        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Signup/signup_content.html' );
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
                'user'           => $this->user->toArray(),
                'activation_url' => Routes::signupConfirmation( $this->user->confirmation_token ),
                'signup_url'     => Routes::appRoot()
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