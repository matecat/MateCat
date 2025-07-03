<?php

use Email\AbstractEmail;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/10/16
 * Time: 18.03
 *
 */


class TmKeyManagement_ShareKeyEmail extends AbstractEmail {

    protected $userMail = [];

    protected $keyStruct;

    protected $sender;

    protected $alreadyRegistered = false;

    public function __construct( UserStruct $sender, Array $userMail, MemoryKeyStruct $keyStruct ) {

        $this->userMail  = $userMail;
        $this->keyStruct = $keyStruct;
        $this->sender = $sender;


        $this->_setLayout('skeleton.html') ;
        $this->_setTemplate('ShareKey/message_content.html') ;

    }

    /**
     * @throws Exception
     */
    public function send(): bool {

        $mailConf = $this->_getDefaultMailConf();

        $mailConf[ 'address' ] = array( $this->userMail[ 0 ], $this->userMail[ 1 ] );
        $mailConf[ 'subject' ] = $this->_getLayoutVariables()['title'] ;

        $mailConf[ 'htmlBody' ] = $this->_buildHTMLMessage();
        $mailConf[ 'altBody' ]  = $this->_buildTxtMessage( $this->_buildMessageContent() );

        $this->_enqueueEmailDelivery( $mailConf );

        return true;

    }

    protected function _getTemplateVariables(): array {
        $params                     = [];
        $params[ "senderFullName" ] = $this->sender->fullName();
        $params[ "senderEmail" ]    = $this->sender->email;
        $params[ "tm_key_name" ]    = $this->keyStruct->tm_key->name;
        $params[ "tm_key_value" ]   = $this->keyStruct->tm_key->key;
        $params[ "addressMail" ]    = $this->userMail[ 0 ];

        return $params ;
    }

    protected function _getLayoutVariables($messageBody = null): array {
        $vars = parent::_getLayoutVariables();
        $vars['showTitle'] = TRUE ;
        $vars['title'] = "Matecat - Resource shared" ;

        return $vars ;
    }

}