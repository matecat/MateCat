<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:07
 */

namespace Utils\Email;

use Exception;
use Model\Users\UserStruct;
use Utils\Registry\AppConfig;
use Utils\Url\CanonicalRoutes;

class  ForgotPasswordEmail extends AbstractEmail {

    protected ?string $title = 'Password reset';

    /**
     * @var UserStruct
     */
    private UserStruct $user;

    public function __construct( UserStruct $user ) {

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
                'password_reset_url' => CanonicalRoutes::passwordReset( $this->user->confirmation_token )
        ];
    }

    protected function _getLayoutVariables( $messageBody = null ): array {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
    }


    protected function _getDefaultMailConf(): array {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf[ 'from' ]       = AppConfig::$MAILER_RETURN_PATH;
        $mailConf[ 'sender' ]     = AppConfig::$MAILER_RETURN_PATH;
        $mailConf[ 'returnPath' ] = AppConfig::$MAILER_RETURN_PATH;

        return $mailConf;
    }
}