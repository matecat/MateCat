<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 15:35
 */

namespace Email;


use Exception;
use Teams\TeamStruct;
use Users_UserStruct;

class MembershipDeletedEmail extends AbstractEmail {

    protected $title;

    /**
     * @var Users_UserStruct
     */
    protected $user;
    /**
     * @var Users_UserStruct
     */
    protected $sender;

    /**
     * @var TeamStruct
     */
    protected $team;

    /**
     * MembershipDeletedEmail constructor.
     *
     * @param Users_UserStruct $sender
     * @param Users_UserStruct $removed_user
     * @param TeamStruct       $team
     */
    public function __construct( Users_UserStruct $sender, Users_UserStruct $removed_user, TeamStruct $team ) {
        $this->user   = $removed_user;
        $this->sender = $sender;
        $this->title  = "You've been removed from team " . $team->name;
        $this->team   = $team;

        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Team/membership_deleted_content.html' );
    }

    protected function _getTemplateVariables(): array {
        return [
                'user'   => $this->user->toArray(),
                'sender' => $this->sender->toArray(),
                'team'   => $this->team->toArray()
        ];
    }

    protected function _getLayoutVariables( $messageBody = null ): array {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
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

}
