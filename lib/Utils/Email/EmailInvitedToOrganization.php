<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 17:46
 */

namespace Email;


use Organizations\OrganizationStruct;

class EmailInvitedToOrganization extends AbstractEmail {

    protected $title;
    protected $user;
    protected $invited_email;
    protected $organization;

    public function __construct( \Users_UserStruct $user, $invited_email, OrganizationStruct $organization ) {
        $this->user          = $user;
        $this->invited_email = $invited_email;
        $this->organization  = $organization;
        $this->title         = "You've been invited to MateCat";

        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Organization/email_invited_to_organization.html' );
    }

    protected function _getTemplateVariables() {
        return array(
                'sender'       => $this->user->toArray(),
                'email'        => $this->invited_email,
                'organization' => $this->organization->toArray(),
                'signup_url'   => \Routes::inviteToOrganizationConfirm( [
                        'invited_by_uid'  => $this->user->uid,
                        'email'           => $this->invited_email,
                        'organization_id' => $this->organization->id
                ] )
        );
    }

    public function send() {
        $recipient = array( $this->invited_email );

        //we need to get the bodyHtmlMessage only once because JWT changes if called more than once
        // otherwise html message will differ from the alternative text message
        $bodyHtmlMessage = $this->_buildMessageContent();

        $this->doSend( $recipient, $this->title,
            $this->_buildHTMLMessage( $bodyHtmlMessage ),
            $this->_buildTxtMessage( $bodyHtmlMessage )
        );
    }
}