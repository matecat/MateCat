<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/12/2016
 * Time: 15:23
 */

namespace Utils\Email;


use Model\Users\UserStruct;
use Throwable;

class WelcomeEmail extends AbstractEmail
{

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    protected ?string $title = 'Welcome to Matecat! Get started with your first project today';

    public function __construct(UserStruct $user)
    {
        $this->user = $user;

        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Signup/welcome_content.html');
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getTemplateVariables(): array
    {
        return [
            'user' => $this->user->toArray()
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getLayoutVariables(): array
    {
        $vars = parent::_getLayoutVariables();
        $vars['closingLine'] = 'Happy translating!';

        return $vars;
    }

    /**
     * @throws Throwable
     */
    public function send(): void
    {
        $this->sendTo($this->user->email, $this->user->fullName());
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getDefaultMailConf(): array
    {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf['from'] = 'noreply@matecat.com';
        $mailConf['sender'] = 'noreply@matecat.com';
        $mailConf['returnPath'] = 'noreply@matecat.com';

        return $mailConf;
    }

}