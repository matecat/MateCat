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

    /** @var array{0: string, 1: string} */
    protected array $userMail;

    protected MemoryKeyStruct $keyStruct;

    protected UserStruct $sender;

    /**
     * @param UserStruct $sender
     * @param array{0: string, 1: string} $userMail
     * @param MemoryKeyStruct $keyStruct
     */
    public function __construct(UserStruct $sender, array $userMail, MemoryKeyStruct $keyStruct)
    {
        $this->userMail = $userMail;
        $this->keyStruct = $keyStruct;
        $this->sender = $sender;


        $this->_setLayout('skeleton.html');
        $this->_setTemplate('ShareKey/message_content.html');
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $mailConf = $this->_getDefaultMailConf();

        $mailConf['address'] = [$this->userMail[0], $this->userMail[1]];
        $mailConf['subject'] = $this->_getLayoutVariables()['title'];

        $mailConf['htmlBody'] = $this->_buildHTMLMessage();
        $mailConf['altBody'] = $this->_buildTxtMessage($this->_buildMessageContent());

        $this->_enqueueEmailDelivery($mailConf);
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getTemplateVariables(): array
    {
        $tmKey = $this->keyStruct->tm_key;
        $params = [];
        $params["senderFullName"] = $this->sender->fullName();
        $params["senderEmail"] = $this->sender->email;
        $params["tm_key_name"] = $tmKey !== null ? ($tmKey->name ?? '') : '';
        $params["tm_key_value"] = $tmKey !== null ? ($tmKey->key ?? '') : '';
        $params["addressMail"] = $this->userMail[0];

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getLayoutVariables($messageBody = null): array
    {
        $vars = parent::_getLayoutVariables();
        $vars['showTitle'] = true;
        $vars['title'] = "Matecat - Resource shared";

        return $vars;
    }

}