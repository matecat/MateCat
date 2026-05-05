<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 15:35
 */

namespace Utils\Email;


use Exception;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;

class MembershipDeletedEmail extends AbstractEmail
{

    protected ?string $title;

    /**
     * @var UserStruct
     */
    protected UserStruct $user;
    /**
     * @var UserStruct
     */
    protected UserStruct $sender;

    /**
     * @var TeamStruct
     */
    protected TeamStruct $team;

    /**
     * MembershipDeletedEmail constructor.
     *
     * @param UserStruct $sender
     * @param UserStruct $removed_user
     * @param TeamStruct $team
     */
    public function __construct(UserStruct $sender, UserStruct $removed_user, TeamStruct $team)
    {
        $this->user = $removed_user;
        $this->sender = $sender;
        $this->title = "You've been removed from team " . $team->name;
        $this->team = $team;

        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Team/membership_deleted_content.html');
    }

    protected function _getTemplateVariables(): array
    {
        return [
            'user' => $this->user->toArray(),
            'sender' => $this->sender->toArray(),
            'team' => $this->team->toArray()
        ];
    }

    protected function _getLayoutVariables($messageBody = null): array
    {
        $vars = parent::_getLayoutVariables();
        $vars['title'] = $this->title;

        return $vars;
    }

    /**
     * @throws Exception
     */
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

}
