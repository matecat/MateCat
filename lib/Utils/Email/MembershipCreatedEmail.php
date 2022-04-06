<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 15/02/2017
 * Time: 18:02
 */

namespace Email;

use Teams\MembershipStruct;
use Users_UserStruct;

class MembershipCreatedEmail extends AbstractEmail {

    /**
     * @var Users_UserStruct
     */
    protected $user;

    /**
     * @var MembershipStruct
     */
    protected $membership;

    protected $title;

    /**
     * @var  Users_UserStruct
     */
    protected $sender;

    /**
     * MembershipCreatedEmail constructor.
     *
     * @param Users_UserStruct $sender
     * @param MembershipStruct  $membership
     */
    public function __construct( Users_UserStruct $sender, MembershipStruct $membership ) {
        $this->user = $membership->getUser();
        $this->_setlayout( 'skeleton.html' );
        $this->_settemplate( 'Team/membership_created_content.html' );
        $this->membership = $membership;

        $this->sender = $sender;
        $this->title  = "You've been added to team " . $this->membership->getTeam()->name;
    }

    public function send() {
        $recipient = array( $this->user->email, $this->user->fullName() );

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    public function _getDefaultMailConf() {
        return parent::_getDefaultMailConf();
    }

    public function _getLayoutVariables($messageBody = null) {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
    }

    public function _getTemplateVariables() {

        return array(
                'user'      => $this->user->toArray(),
                'sender'    => $this->sender->toArray(),
                'team'      => $this->membership->getTeam()->toArray(),
                'manageUrl' => \Routes::manage()
        );
    }

}