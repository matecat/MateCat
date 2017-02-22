<?php

use Email\EmailInvitedToOrganization;
use Email\MembershipCreatedEmail;
use Email\MembershipDeletedEmail;
use Organizations\MembershipDao ;
use Organizations\MembershipStruct;
use Organizations\OrganizationDao;
use Organizations\OrganizationStruct;
use Organizations\PendingInvitations;

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

    /**
     * @var array
     */
    protected $uids_to_remove = array() ;

    /**
     * @var Users_UserStruct[]
     */
    protected $removed_users = array();

    protected $emails_to_invite ;

    /**
     * @var \Organizations\MembershipStruct[]
     */
    protected $all_memberships;

    public function __construct( OrganizationStruct $struct ) {
        $this->struct = $struct ;
    }

    public function addMemberEmail($email) {
        $this->member_emails[] = $email ;
    }

    public function setUser( Users_UserStruct $user ) {
        $this->user = $user ;
    }

    public function addMemberEmails( $emails ) {
        foreach ($emails as $email ) {
            $this->addMemberEmail( $email ) ;
        }
    }

    public function removeMemberUids( $uids ) {
        $this->uids_to_remove = array_merge($this->uids_to_remove, $uids ) ;
    }

    /**
     * Updated member list.
     *
     * @return \Organizations\MembershipStruct[] the full list of members after the update.
     */
    public function updateMembers() {
        $this->removed_users = array();

        \Database::obtain()->begin();

        $membershipDao = new MembershipDao();

        if ( !empty( $this->member_emails ) ) {

            $this->_checkAddMembersToPersonalOrganization();

            $this->new_memberships = $membershipDao->createList( [
                'organization' => $this->struct,
                'members' => $this->member_emails
            ] );
        }

        if ( !empty( $this->uids_to_remove ) ) {
            $projectDao = new Projects_ProjectDao() ;

            foreach( $this->uids_to_remove as $uid ) {
                $user = $membershipDao->deleteUserFromOrganization( $uid, $this->struct->id );

                if ( $user ) {
                    $this->removed_users[] = $user ;
                    $projectDao->unassignProjects( $user );
                }
            }
        }

        $this->all_memberships = ( new MembershipDao )
            ->setCacheTTL(3600)
            ->getMemberListByOrganizationId( $this->struct->id ) ;

        \Database::obtain()->commit();

        $this->_sendEmailsToNewMemberships();
        $this->_sendEmailsToInvited();
        $this->_setPendingStatuses();
        $this->_sendEmailsForRemovedMemberships();

        return $this->all_memberships ;
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

    public function _sendEmailsToInvited() {
        foreach( $this->getInvitedEmails() as $email ) {
            $email = new EmailInvitedToOrganization($this->user, $email, $this->struct);
            $email->send();
        }
    }

    public function _setPendingStatuses(){
        $redis = ( new \RedisHandler() )->getConnection();
        foreach ( $this->getInvitedEmails() as $email ) {
            $pendingInvitation = new PendingInvitations( $redis, [
                    'organization_id' => $this->struct->id,
                    'email'           => $email
            ] );
            $pendingInvitation->set();
        }
    }

    public function getInvitedEmails(){

        $emails_of_existing_members = array_map(function( MembershipStruct $membership ) {
            return $membership->getUser()->email ;
        }, $this->all_memberships );

        return array_diff($this->member_emails, $emails_of_existing_members);

    }

    /**
     * @return \Organizations\MembershipStruct[]
     */
    public function getNewMembershipEmailList() {
        $notify_list = [] ;
        foreach( $this->new_memberships as $membership ) {
            if ( $membership->getUser()->uid != $this->user->uid ) {
                $notify_list[] = $membership ;
            }
        }
        return $notify_list ;
    }

    public function getRemovedMembersEmailList() {
        $notify_list = [] ;

        foreach( $this->removed_users as $user ) {
            if ( $user->uid != $this->user->uid ) {
                $notify_list[] = $user ;
            }
        }
        return $notify_list ;
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

    protected function _checkAddMembersToPersonalOrganization(){
        if( $this->struct->type == Constants_Organizations::PERSONAL ){
            throw new DomainException( "Can not invite members to a Personal organization." );
        }
    }

    protected function _createOrganizationWithMatecatUsers() {

        $this->_checkAddMembersToPersonalOrganization();

        $dao = new OrganizationDao() ;

        \Database::obtain()->begin();
        $organization = $dao->createUserOrganization( $this->user, [
            'type'    => $this->struct->type,
            'name'    => $this->struct->name,
            'members' => $this->member_emails
        ] );

        \Database::obtain()->commit();

        return $organization ;
    }

    protected function _sendEmailsToNewMemberships() {
        foreach( $this->getNewMembershipEmailList() as $membership ) {
            $email = new MembershipCreatedEmail($this->user, $membership ) ;
            $email->send() ;
        }
    }

    protected function _sendEmailsForRemovedMemberships() {
        foreach( $this->getRemovedMembersEmailList() as $user ) {
            $email = new MembershipDeletedEmail($this->user, $user, $this->struct ) ;
            $email->send() ;
        }
    }

}