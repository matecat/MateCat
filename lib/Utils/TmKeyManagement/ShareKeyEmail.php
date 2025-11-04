<?php

namespace Utils\TmKeyManagement;

use Exception;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use Utils\Email\AbstractEmail;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/10/16
 * Time: 18.03
 *
 */
class ShareKeyEmail extends AbstractEmail
{

    protected array $userMail = [];

    protected MemoryKeyStruct $keyStruct;

    protected UserStruct $sender;

    public function __construct(UserStruct $sender, array $userMail, MemoryKeyStruct $keyStruct)
    {
        $this->userMail  = $userMail;
        $this->keyStruct = $keyStruct;
        $this->sender    = $sender;


        $this->_setLayout('skeleton.html');
        $this->_setTemplate('ShareKey/message_content.html');
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $mailConf = $this->_getDefaultMailConf();

        $mailConf[ 'address' ] = [$this->userMail[ 0 ], $this->userMail[ 1 ]];
        $mailConf[ 'subject' ] = $this->_getLayoutVariables()[ 'title' ];

        $mailConf[ 'htmlBody' ] = $this->_buildHTMLMessage();
        $mailConf[ 'altBody' ]  = $this->_buildTxtMessage($this->_buildMessageContent());

        $this->_enqueueEmailDelivery($mailConf);
    }

    protected function _getTemplateVariables(): array
    {
        $params                     = [];
        $params[ "senderFullName" ] = $this->sender->fullName();
        $params[ "senderEmail" ]    = $this->sender->email;
        $params[ "tm_key_name" ]    = $this->keyStruct->tm_key->name;
        $params[ "tm_key_value" ]   = $this->keyStruct->tm_key->key;
        $params[ "addressMail" ]    = $this->userMail[ 0 ];

        return $params;
    }

    protected function _getLayoutVariables($messageBody = null): array
    {
        $vars                = parent::_getLayoutVariables();
        $vars[ 'showTitle' ] = true;
        $vars[ 'title' ]     = "Matecat - Resource shared";

        return $vars;
    }

}