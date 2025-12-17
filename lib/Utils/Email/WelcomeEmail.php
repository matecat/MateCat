<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/12/2016
 * Time: 15:23
 */

namespace Utils\Email;


use Model\Users\UserStruct;

class WelcomeEmail extends AbstractEmail
{

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    protected ?string $title = 'Welcome to Matecat! Get Started with Your First Project Today';

    public function __construct(UserStruct $user)
    {
        $this->user = $user;

        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Signup/welcome_content.html');
    }

    protected function _getTemplateVariables(): array
    {
        return [
            'user' => $this->user->toArray()
        ];
    }

    protected function _getLayoutVariables($messageBody = null): array
    {
        $vars = parent::_getLayoutVariables();
        $vars['title'] = $this->title;
        $vars['closingLine'] = 'Happy translating!';

        return $vars;
    }

    public function send(): void
    {
        $recipient = [$this->user->email, $this->user->fullName()];

        $this->doSend(
            $recipient,
            $this->title,
            $this->_buildHTMLMessage(),
            $this->_buildTxtMessage($this->_buildMessageContent())
        );
    }

    protected function _getDefaultMailConf(): array
    {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf['from'] = 'noreply@matecat.com';
        $mailConf['sender'] = 'noreply@matecat.com';
        $mailConf['returnPath'] = 'noreply@matecat.com';

        return $mailConf;
    }

}