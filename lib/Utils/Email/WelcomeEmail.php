<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/12/2016
 * Time: 15:23
 */

namespace Email;


use Users_UserStruct;

class WelcomeEmail extends AbstractEmail {

    /**
     * @var Users_UserStruct
     */
    protected $user;

    protected $title = 'Welcome to MateCat!';

    public function __construct( Users_UserStruct $user ) {
        $this->user = $user;

        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Signup/welcome_content.html' );
    }

    protected function _getTemplateVariables(): array {
        return [
                'user' => $this->user->toArray()
        ];
    }

    protected function _getLayoutVariables( $messageBody = null ): array {
        $vars                  = parent::_getLayoutVariables();
        $vars[ 'title' ]       = $this->title;
        $vars[ 'closingLine' ] = 'Join the Evolution!';

        return $vars;
    }

    public function send() {
        $recipient = [ $this->user->email, $this->user->fullName() ];

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );

    }

    protected function _getDefaultMailConf(): array {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf[ 'from' ]       = 'noreply@matecat.com';
        $mailConf[ 'sender' ]     = 'noreply@matecat.com';
        $mailConf[ 'returnPath' ] = 'noreply@matecat.com';

        return $mailConf;
    }

}