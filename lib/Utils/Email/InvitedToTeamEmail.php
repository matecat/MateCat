<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 17:46
 */

namespace Utils\Email;


use Exception;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use Utils\Url\CanonicalRoutes;

class InvitedToTeamEmail extends AbstractEmail {

    protected ?string    $title;
    protected UserStruct $user;
    protected string     $invited_email;
    protected TeamStruct $team;

    public function __construct( UserStruct $user, string $invited_email, TeamStruct $team ) {
        $this->user          = $user;
        $this->invited_email = $invited_email;
        $this->team          = $team;
        $this->title         = "You've been invited to Matecat";

        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Team/email_invited_to_team.html' );
    }

    /**
     * @throws Exception
     */
    protected function _getTemplateVariables(): array {
        return [
                'sender'     => $this->user->toArray(),
                'email'      => $this->invited_email,
                'team'       => $this->team->toArray(),
                'signup_url' => CanonicalRoutes::inviteToTeamConfirm( [
                        'invited_by_uid' => $this->user->uid,
                        'email'          => $this->invited_email,
                        'team_id'        => $this->team->id
                ] )
        ];
    }

    /**
     * @throws Exception
     */
    public function send() {
        $recipient = [ $this->invited_email ];

        //we need to get the bodyHtmlMessage only once because JWT changes if called more than once
        // otherwise html message will differ from the alternative text message
        $bodyHtmlMessage = $this->_buildMessageContent();

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage( $bodyHtmlMessage ),
                $this->_buildTxtMessage( $bodyHtmlMessage )
        );
    }
}