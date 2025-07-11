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
use Routes;

class MembershipCreatedEmail extends AbstractEmail {

    /**
     * @var UserStruct
     */
    protected $user;

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
     * @param UserStruct       $sender
     * @param MembershipStruct $membership
     *
     * @throws ReflectionException
     */
    public function __construct( UserStruct $sender, MembershipStruct $membership ) {
        $this->user = $membership->getUser();
        $this->_setlayout( 'skeleton.html' );
        $this->_settemplate( 'Team/membership_created_content.html' );
        $this->membership = $membership;

        $this->sender = $sender;
        $this->title  = "You've been added to team " . $this->membership->getTeam()->name;
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

    public function _getDefaultMailConf(): array {
        return parent::_getDefaultMailConf();
    }

    public function _getLayoutVariables( $messageBody = null ): array {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
    }

    /**
     * @throws Exception
     */
    public function _getTemplateVariables(): array {

        return [
                'user'      => $this->user->toArray(),
                'sender'    => $this->sender->toArray(),
                'team'      => $this->membership->getTeam()->toArray(),
                'manageUrl' => Routes::manage()
        ];

    }

}