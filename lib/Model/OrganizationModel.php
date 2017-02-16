<?php

use Organizations\MembershipDao ;

class OrganizationModel {

    protected $member_emails = array();

    /**
     * @var \Organizations\OrganizationStruct
     */
    protected $struct ;

    /**
     * @var Users_UserStruct
     */
    protected $user ;

    /**
     * @var \Organizations\MembershipStruct[]
     */
    protected $new_memberships;

    protected $emails_to_invite ;

    public function __construct( \Organizations\OrganizationStruct $struct ) {
        $this->struct = $struct ;
    }

    public function addMemberEmail($email) {
        $this->member_emails[] = $email ;
    }

    public function setUser( Users_UserStruct $user ) {
        $this->user = $user ;
    }

    public function create() {

        if ( !$this->user ) {
            throw new Exception('User is not set' ) ;
        }

        $this->struct->type = strtolower($this->struct->type ) ;

        $this->_checkType();
        $this->_checkPersonalUnique();

        $organization = $this->_createOrganizationWithMatecatUsers();

        $this->new_memberships = $organization->getMembers();

        $this->_sendEmailsToNewMemberships();

        return $organization ;
    }

    protected function _checkType() {
        if ( !Constants_Organizations::isAllowedType( $this->struct->type ) ) {
            throw new InvalidArgumentException( "User already has the personal organization" );
        }
    }

    protected function _checkPersonalUnique() {
        $dao = new \Organizations\OrganizationDao() ;
        if ( $this->struct->type == Constants_Organizations::PERSONAL && $dao->getPersonalByUid( $this->struct->created_by ) ) {
            throw new InvalidArgumentException( "User already has the personal organization" );
        }
    }

    protected function _createOrganizationWithMatecatUsers() {
        $dao = new \Organizations\OrganizationDao() ;

        \Database::obtain()->begin();
        $organization = $dao->createUserOrganization( $this->user, [
            'type'    => $this->struct->type,
            'name'    => $this->struct->name,
            'members' => $this->member_emails
        ] );

        \Database::obtain()->commit();

        ( new MembershipDao() )->destroyCacheUserOrganizations( $this->user );

        return $organization ;
    }

    protected function _sendEmailsToNewMemberships() {
        foreach( $this->getNewMembershipEmailList() as $membership ) {
            $email = new \Email\MembershipCreatedEmail($this->user, $membership ) ;
            $email->send() ;
        }
    }

    /**
     * @return \Organizations\MembershipStruct[]
     */
    protected function getNewMembershipEmailList() {
        $notify_list = [] ;
        foreach( $this->new_memberships as $membership ) {
            if ( $membership->getUser()->uid != $this->user->uid ) {
                $notify_list[] = $membership ;
            }
        }
        return $notify_list ;
    }


}