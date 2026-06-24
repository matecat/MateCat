<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 15/02/2017
 * Time: 18:02
 */

namespace Utils\Email;

use Exception;
use Model\Teams\MembershipStruct;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use Utils\Url\CanonicalRoutes;

class MembershipCreatedEmail extends AbstractEmail
{

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    /**
     * @var MembershipStruct
     */
    protected MembershipStruct $membership;

    protected ?string $title;

    /**
     * @var  UserStruct
     */
    protected UserStruct $sender;

    /**
     * MembershipCreatedEmail constructor.
     *
     * @param UserStruct $sender
     * @param MembershipStruct $membership
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function __construct(UserStruct $sender, MembershipStruct $membership)
    {
        $this->user = $membership->getUser();
        $this->_setlayout('skeleton.html');
        $this->_settemplate('Team/membership_created_content.html');
        $this->membership = $membership;

        $this->sender = $sender;
        $this->title = "You've been added to team " . $this->membership->getTeam()->name;
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $recipient = [$this->user->email, $this->user->fullName()];

        $this->doSend(
            $recipient,
            $this->title ?? '',
            $this->_buildHTMLMessage(),
            $this->_buildTxtMessage($this->_buildMessageContent())
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function _getDefaultMailConf(): array
    {
        return parent::_getDefaultMailConf();
    }

    /**
     * @return array<string, mixed>
     */
    public function _getLayoutVariables($messageBody = null): array
    {
        $vars = parent::_getLayoutVariables();
        $vars['title'] = $this->title;

        return $vars;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function _getTemplateVariables(): array
    {
        return [
            'user' => $this->user->toArray(),
            'sender' => $this->sender->toArray(),
            'team' => $this->membership->getTeam()->toArray(),
            'manageUrl' => CanonicalRoutes::manage()
        ];
    }

}